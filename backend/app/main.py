from __future__ import annotations

from datetime import datetime, date, time, timedelta
from typing import Any

from zoneinfo import ZoneInfo

from fastapi import FastAPI, Depends, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy import desc
from sqlalchemy.orm import Session

from .db import get_db
from .models import (
    Hospital, Route,
    RouteWeeklyHours,
    RouteException, RouteExceptionHours,
    News,
)

JST = ZoneInfo("Asia/Tokyo")

# 1) まず app を定義（ここより上に @app.*** を置かない）
app = FastAPI(title="Dux Call API", version="0.1.0")

# 2) CORS（React用。不要なら allow_origins を空にしてOK）
#    - localhost(開発PC) と ngrok(スマホ検証) を許可
#    - ngrok-free.app / ngrok-free.dev のサブドメインを全部許可（英単語URLでもOK）
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:5173"],
    allow_origin_regex=r"https://.*\.ngrok-free\.app|https://.*\.ngrok-free\.dev",
    allow_methods=["*"],
    allow_headers=["*"],
)


# ----------------------------
# Schedule helpers（today判定）
# ----------------------------
def _time_to_str(t: time | None) -> str | None:
    return t.strftime("%H:%M") if t else None


def _combine(d: date, t: time) -> datetime:
    return datetime(d.year, d.month, d.day, t.hour, t.minute, t.second, tzinfo=JST)


def _get_schedule_for_date(db: Session, route_id: int, target_date: date) -> dict[str, Any]:
    """
    その日における営業時間を返す。
    例外(期間)があれば「新しい方(created_at)」を優先。
    """
    dow = target_date.weekday()  # 0=Mon .. 6=Sun

    # 1) 例外（期間内）を新しい順で探す
    ex = (
        db.query(RouteException)
        .filter(
            RouteException.route_id == route_id,
            RouteException.start_date <= target_date,
            RouteException.end_date >= target_date,
        )
        .order_by(desc(RouteException.created_at))
        .first()
    )

    if ex:
        eh = (
            db.query(RouteExceptionHours)
            .filter(RouteExceptionHours.exception_id == ex.id, RouteExceptionHours.dow == dow)
            .first()
        )
        if eh:
            return {
                "source": "exception",
                "title": ex.title,
                "is_closed": bool(eh.is_closed),
                "open_time": eh.open_time,
                "close_time": eh.close_time,
            }

    # 2) 通常の曜日定義
    wh = (
        db.query(RouteWeeklyHours)
        .filter(RouteWeeklyHours.route_id == route_id, RouteWeeklyHours.dow == dow)
        .first()
    )
    if wh:
        return {
            "source": "weekly",
            "title": None,
            "is_closed": bool(wh.is_closed),
            "open_time": wh.open_time,
            "close_time": wh.close_time,
        }

    # 3) 未定義は休止扱い
    return {
        "source": "none",
        "title": None,
        "is_closed": True,
        "open_time": None,
        "close_time": None,
    }


def _find_next_open_at(db: Session, route_id: int, now: datetime) -> str | None:
    """
    今日以降7日間で「次に開く」日時を探す（例外も考慮）
    """
    for i in range(0, 8):
        d = (now + timedelta(days=i)).date()
        sch = _get_schedule_for_date(db, route_id, d)
        if sch["is_closed"] or not sch["open_time"] or not sch["close_time"]:
            continue

        open_dt = _combine(d, sch["open_time"])
        close_dt = _combine(d, sch["close_time"])

        # 今日なら「今より後」の開始のみ
        if i == 0:
            if now < open_dt:
                return open_dt.isoformat()
            continue

        if open_dt < close_dt:
            return open_dt.isoformat()

    return None


def compute_route_status(db: Session, route_id: int, now: datetime | None = None) -> dict[str, Any]:
    """
    今日の受付状態 + 次に開く時刻（分かる範囲で）を返す
    """
    now = now or datetime.now(JST)
    today = now.date()

    sch = _get_schedule_for_date(db, route_id, today)
    is_closed = sch["is_closed"]
    open_t = sch["open_time"]
    close_t = sch["close_time"]
    source = sch["source"]

    window = None
    if (open_t and close_t) and not is_closed:
        window = {"open": _time_to_str(open_t), "close": _time_to_str(close_t)}

    # 終日クローズ or 未設定
    if is_closed or not open_t or not close_t:
        return {
            "is_open": False,
            "reason": "closed",
            "source": source,
            "window": window,
            "next_open_at": _find_next_open_at(db, route_id, now),
        }

    start_dt = _combine(today, open_t)
    end_dt = _combine(today, close_t)

    # 営業中
    if start_dt <= now < end_dt:
        return {
            "is_open": True,
            "reason": "open",
            "source": source,
            "window": window,
            "next_open_at": None,
        }

    # 受付前
    if now < start_dt:
        return {
            "is_open": False,
            "reason": "before_open",
            "source": source,
            "window": window,
            "next_open_at": start_dt.isoformat(),
        }

    # 受付終了
    return {
        "is_open": False,
        "reason": "after_close",
        "source": source,
        "window": window,
        "next_open_at": _find_next_open_at(db, route_id, now),
    }


# ----------------------------
# Endpoints
# ----------------------------
@app.get("/health")
def health() -> dict[str, Any]:
    return {"ok": True, "ts": datetime.utcnow().isoformat()}

@app.post("/patient/hospitals/{hospital_code}/register", tags=["patient"])
def register_hospital(hospital_code: str, db: Session = Depends(get_db)) -> dict[str, Any]:
    hospital = (
        db.query(Hospital)
        .filter(Hospital.hospital_code == hospital_code, Hospital.is_active == True)  # noqa: E712
        .first()
    )
    if hospital is None:
        raise HTTPException(status_code=404, detail="Hospital not found")

    return {
        "hospital": {
            "code": hospital.hospital_code,
            "name": hospital.name,
            "timezone": hospital.timezone,
        }
    }


@app.post("/dev/seed", tags=["dev"])
def dev_seed(db: Session = Depends(get_db)) -> dict[str, Any]:
    """
    開発用 seed（何度叩いても同じ状態になるように、既存があれば再利用/更新する）
    - 病院: tokyo-clinic
    - routes: 予約 / 面会
    - weekly: 曜日別（予約・面会で別）
    - exception: 期間×曜日（例）※将来のテスト用
    - news: 重要なお知らせ（例）
    """

    # --- 1) 病院 ---
    hospital_code = "tokyo-clinic"
    hospital = db.query(Hospital).filter(Hospital.hospital_code == hospital_code).first()
    if hospital is None:
        hospital = Hospital(
            hospital_code=hospital_code,
            name="東京サンプル病院",
            timezone="Asia/Tokyo",
            is_active=True,
        )
        db.add(hospital)
        db.flush()
    else:
        hospital.name = "東京サンプル病院"
        hospital.is_active = True

    # --- 2) ルート（予約 / 面会） ---
    def upsert_route(key: str, label: str, phone: str, sort_order: int) -> Route:
        r = (
            db.query(Route)
            .filter(Route.hospital_id == hospital.id, Route.key == key)
            .first()
        )
        if r is None:
            r = Route(
                hospital_id=hospital.id,
                key=key,
                label=label,
                phone=phone,
                is_enabled=True,
                sort_order=sort_order,
            )
            db.add(r)
            db.flush()
        else:
            r.label = label
            r.phone = phone
            r.is_enabled = True
            r.sort_order = sort_order
        return r

    route_res = upsert_route("reservation", "予約", "0312345678", 10)
    route_vis = upsert_route("visit", "面会", "0399990000", 20)

    # --- 3) 通常スケジュール（曜日×用件）
    def upsert_weekly(route: Route, dow: int, open_hm: str | None, close_hm: str | None, is_closed: bool):
        w = (
            db.query(RouteWeeklyHours)
            .filter(RouteWeeklyHours.route_id == route.id, RouteWeeklyHours.dow == dow)
            .first()
        )
        if w is None:
            w = RouteWeeklyHours(route_id=route.id, dow=dow)
            db.add(w)

        w.is_closed = is_closed
        if is_closed:
            w.open_time = None
            w.close_time = None
        else:
            oh, om = open_hm.split(":")
            ch, cm = close_hm.split(":")
            from datetime import time as dtime
            w.open_time = dtime(int(oh), int(om))
            w.close_time = dtime(int(ch), int(cm))

    # 予約：月〜金 9-17 / 土 9-12 / 日 休
    for dow in range(0, 5):
        upsert_weekly(route_res, dow, "09:00", "17:00", False)
    upsert_weekly(route_res, 5, "09:00", "12:00", False)
    upsert_weekly(route_res, 6, None, None, True)

    # 面会：月〜金 13-16 / 土日 休
    for dow in range(0, 5):
        upsert_weekly(route_vis, dow, "13:00", "16:00", False)
    upsert_weekly(route_vis, 5, None, None, True)
    upsert_weekly(route_vis, 6, None, None, True)

    # --- 4) 例外（期間×曜日）テスト用：年度末で面会だけ変える例
    ex = (
        db.query(RouteException)
        .filter(
            RouteException.route_id == route_vis.id,
            RouteException.start_date == date(2026, 3, 20),
            RouteException.end_date == date(2026, 3, 31),
        )
        .first()
    )
    if ex is None:
        ex = RouteException(
            route_id=route_vis.id,
            start_date=date(2026, 3, 20),
            end_date=date(2026, 3, 31),
            title="年度末体制（テスト）",
        )
        db.add(ex)
        db.flush()

    def upsert_ex_hours(exception: RouteException, dow: int, open_hm: str | None, close_hm: str | None, is_closed: bool):
        eh = (
            db.query(RouteExceptionHours)
            .filter(RouteExceptionHours.exception_id == exception.id, RouteExceptionHours.dow == dow)
            .first()
        )
        if eh is None:
            eh = RouteExceptionHours(exception_id=exception.id, dow=dow)
            db.add(eh)

        eh.is_closed = is_closed
        if is_closed:
            eh.open_time = None
            eh.close_time = None
        else:
            oh, om = open_hm.split(":")
            ch, cm = close_hm.split(":")
            from datetime import time as dtime
            eh.open_time = dtime(int(oh), int(om))
            eh.close_time = dtime(int(ch), int(cm))

    for dow in range(0, 5):
        upsert_ex_hours(ex, dow, "14:00", "15:00", False)
    upsert_ex_hours(ex, 5, None, None, True)
    upsert_ex_hours(ex, 6, None, None, True)

    # --- 5) お知らせ（例）
    title = "面会のご案内（サンプル）"
    news = (
        db.query(News)
        .filter(News.hospital_id == hospital.id, News.title == title)
        .first()
    )
    if news is None:
        news = News(
            hospital_id=hospital.id,
            title=title,
            body="現在の面会受付時間はアプリ内「面会」からご確認ください。",
            priority="high",
            is_published=True,
        )
        db.add(news)
    else:
        news.body = "現在の面会受付時間はアプリ内「面会」からご確認ください。"
        news.priority = "high"
        news.is_published = True

    db.commit()
    return {"seeded": True, "hospital_code": hospital_code}


@app.get("/patient/hospitals/{hospital_code}", tags=["patient"])
def get_patient_hospital(hospital_code: str, db: Session = Depends(get_db)) -> dict[str, Any]:
    hospital = (
        db.query(Hospital)
        .filter(Hospital.hospital_code == hospital_code, Hospital.is_active == True)  # noqa: E712
        .first()
    )
    if hospital is None:
        raise HTTPException(status_code=404, detail="Hospital not found")

    routes = (
        db.query(Route)
        .filter(Route.hospital_id == hospital.id, Route.is_enabled == True)  # noqa: E712
        .order_by(Route.sort_order.asc())
        .all()
    )

    news_items = (
        db.query(News)
        .filter(News.hospital_id == hospital.id, News.is_published == True)  # noqa: E712
        .order_by(News.updated_at.desc())
        .limit(10)
        .all()
    )

    return {
        "hospital": {
            "code": hospital.hospital_code,
            "name": hospital.name,
            "timezone": hospital.timezone,
        },
        "news": [
            {
                "title": n.title,
                "body": n.body,
                "priority": n.priority,
                "updated_at": n.updated_at.isoformat(),
            }
            for n in news_items
        ],
        "routes": [
            {
                "key": r.key,
                "label": r.label,
                "phone": r.phone,
                "today": compute_route_status(db, r.id),
            }
            for r in routes
        ],
    }