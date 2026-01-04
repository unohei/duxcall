import { useEffect, useMemo, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";

type Api = {
  hospital: { code: string; name: string; timezone: string };
  news: {
    title: string;
    body?: string | null;
    priority: "high" | "normal";
    updated_at: string;
  }[];
  routes: {
    key: string;
    label: string;
    phone: string;
    today: {
      is_open: boolean;
      reason: string; // open | before_open | after_close | closed
      source?: string;
      window: { open: string; close: string } | null;
      next_open_at: string | null;
    };
  }[];
};

type Hospital = { code: string; name: string; timezone?: string };

// const API_BASE = "http://127.0.0.1:8000";
// const API_BASE = import.meta.env.VITE_API_BASE ?? "http://127.0.0.1:8000";
const API_BASE = import.meta.env.VITE_API_BASE ?? "/api";
const FONT_MODE_KEY = "duxcall_font_mode"; // "normal" | "large"
const LIST_KEY = "duxcall_hospitals";
const CURRENT_KEY = "duxcall_current_hospital";

function readHospitals(): Hospital[] {
  const raw = localStorage.getItem(LIST_KEY);
  if (!raw) return [];
  try {
    return JSON.parse(raw) as Hospital[];
  } catch {
    return [];
  }
}

function writeHospitals(list: Hospital[]) {
  localStorage.setItem(LIST_KEY, JSON.stringify(list));
}

function formatNext(next: string | null) {
  if (!next) return "";
  const d = new Date(next);
  const mm = d.getMonth() + 1;
  const dd = d.getDate();
  const hh = String(d.getHours()).padStart(2, "0");
  const mi = String(d.getMinutes()).padStart(2, "0");
  return `${mm}/${dd} ${hh}:${mi}`;
}

function reasonLabel(reason: string, nextOpenAt: string | null) {
  switch (reason) {
    case "open":
      return { status: "今、電話できます", tone: "good" as const };
    case "before_open":
      return {
        status: nextOpenAt
          ? `受付前（開始：${formatNext(nextOpenAt)}）`
          : "受付前",
        tone: "warn" as const,
      };
    case "after_close":
      return {
        status: nextOpenAt
          ? `本日の受付は終了（次：${formatNext(nextOpenAt)}）`
          : "本日の受付は終了",
        tone: "warn" as const,
      };
    case "closed":
    default:
      return { status: "本日は受付していません", tone: "bad" as const };
  }
}

export default function HospitalDetail() {
  const { hospitalCode } = useParams<{ hospitalCode: string }>();
  const navigate = useNavigate();

  const [data, setData] = useState<Api | null>(null);
  const [error, setError] = useState<string>("");

  const [fontMode, setFontMode] = useState<"normal" | "large">(() => {
    const saved = localStorage.getItem(FONT_MODE_KEY);
    return saved === "large" ? "large" : "normal";
  });

  useEffect(() => {
    localStorage.setItem(FONT_MODE_KEY, fontMode);
  }, [fontMode]);

  // URLの hospitalCode がないなら一覧へ
  useEffect(() => {
    if (!hospitalCode) navigate("/hospitals", { replace: true });
  }, [hospitalCode, navigate]);

  // その病院が登録済みか確認（未登録なら一覧へ）
  useEffect(() => {
    if (!hospitalCode) return;
    const list = readHospitals();
    const exists = list.some((h) => h.code === hospitalCode);
    if (!exists) {
      navigate("/hospitals", { replace: true });
    } else {
      localStorage.setItem(CURRENT_KEY, hospitalCode);
    }
  }, [hospitalCode, navigate]);

  // データ取得
  useEffect(() => {
    if (!hospitalCode) return;

    setError("");
    setData(null);

    fetch(`${API_BASE}/patient/hospitals/${hospitalCode}`)
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then(setData)
      .catch((e: unknown) =>
        setError(e instanceof Error ? e.message : String(e))
      );
  }, [hospitalCode]);

  const ui = useMemo(() => {
    const scale = fontMode === "large" ? 1.2 : 1.0;
    return {
      scale,
      h1: 24 * scale,
      base: 16 * scale,
      small: 13 * scale,
      button: 16 * scale,
      pad: 12 * scale,
      radius: 14,
    };
  }, [fontMode]);

  if (error) {
    return (
      <div style={{ padding: 16, fontFamily: "sans-serif" }}>
        <div style={{ marginBottom: 10 }}>
          <Link
            to="/hospitals"
            style={{ textDecoration: "none", fontWeight: 800 }}
          >
            ← 連絡先一覧へ
          </Link>
        </div>
        Error: {error}
        <div style={{ marginTop: 12 }}>
          <button
            onClick={() => {
              if (!hospitalCode) return;
              const next = readHospitals().filter(
                (h) => h.code !== hospitalCode
              );
              writeHospitals(next);
              localStorage.removeItem(CURRENT_KEY);
              navigate("/hospitals");
            }}
            style={{
              padding: "10px 12px",
              borderRadius: 10,
              border: "1px solid #ddd",
              background: "#fff",
              cursor: "pointer",
              fontWeight: 800,
            }}
          >
            この病院を登録解除して戻る
          </button>
        </div>
      </div>
    );
  }

  if (!data) {
    return (
      <div style={{ padding: 16, fontFamily: "sans-serif" }}>Loading...</div>
    );
  }

  return (
    <div
      style={{
        maxWidth: 560,
        margin: "0 auto",
        padding: 16,
        fontFamily: "sans-serif",
        fontSize: ui.base,
        lineHeight: 1.45,
      }}
    >
      {/* back */}
      <div style={{ marginBottom: 10 }}>
        <Link
          to="/hospitals"
          style={{ textDecoration: "none", fontWeight: 800 }}
        >
          ← 連絡先一覧へ
        </Link>
      </div>

      {/* header */}
      <div
        style={{
          display: "flex",
          justifyContent: "space-between",
          gap: 12,
          alignItems: "flex-start",
        }}
      >
        <div>
          <h1 style={{ margin: "8px 0 4px", fontSize: ui.h1 }}>
            {data.hospital.name}
          </h1>
          <div style={{ color: "#666", fontSize: ui.small }}>
            code: {data.hospital.code}
          </div>

          <div style={{ marginTop: 8 }}>
            <button
              onClick={() => {
                const next = readHospitals().filter(
                  (h) => h.code !== data.hospital.code
                );
                writeHospitals(next);
                const cur = localStorage.getItem(CURRENT_KEY);
                if (cur === data.hospital.code)
                  localStorage.removeItem(CURRENT_KEY);
                navigate("/hospitals");
              }}
              style={{
                padding: "8px 10px",
                borderRadius: 10,
                border: "1px solid #ddd",
                background: "#fff",
                cursor: "pointer",
                fontSize: ui.small,
                fontWeight: 800,
              }}
            >
              この病院を登録解除
            </button>
          </div>
        </div>

        {/* Font toggle */}
        <div
          style={{
            border: "1px solid #ddd",
            borderRadius: 999,
            padding: 6,
            display: "flex",
            gap: 6,
            alignItems: "center",
            height: "fit-content",
            background: "#fff",
          }}
          aria-label="文字サイズ切替"
        >
          <button
            onClick={() => setFontMode("normal")}
            style={{
              padding: "6px 10px",
              borderRadius: 999,
              border: "1px solid #ddd",
              background: fontMode === "normal" ? "#111" : "#fff",
              color: fontMode === "normal" ? "#fff" : "#111",
              cursor: "pointer",
              fontSize: ui.small,
              fontWeight: 700,
            }}
          >
            標準
          </button>
          <button
            onClick={() => setFontMode("large")}
            style={{
              padding: "6px 10px",
              borderRadius: 999,
              border: "1px solid #ddd",
              background: fontMode === "large" ? "#111" : "#fff",
              color: fontMode === "large" ? "#fff" : "#111",
              cursor: "pointer",
              fontSize: ui.small,
              fontWeight: 700,
            }}
          >
            大
          </button>
        </div>
      </div>

      {/* news */}
      {data.news.length > 0 && (
        <div
          style={{
            border: "1px solid #ddd",
            borderRadius: ui.radius,
            padding: ui.pad,
            marginTop: 14,
          }}
        >
          <div style={{ fontWeight: 800, marginBottom: 8 }}>お知らせ</div>
          {data.news.slice(0, 2).map((n, idx) => (
            <div key={idx} style={{ marginBottom: 10 }}>
              <div style={{ fontWeight: 800 }}>
                {n.priority === "high" ? "【重要】" : ""}
                {n.title}
              </div>
              {n.body && (
                <div style={{ color: "#333", marginTop: 4 }}>{n.body}</div>
              )}
            </div>
          ))}
        </div>
      )}

      {/* routes */}
      <div style={{ display: "grid", gap: 12, marginTop: 16 }}>
        {data.routes.map((r) => {
          const disabled = !r.today.is_open;
          const windowText = r.today.window
            ? `${r.today.window.open}〜${r.today.window.close}`
            : "受付時間未設定";
          const rl = reasonLabel(r.today.reason, r.today.next_open_at);

          const statusColor =
            rl.tone === "good" ? "#0a7" : rl.tone === "warn" ? "#b60" : "#b00";

          return (
            <div
              key={r.key}
              style={{
                border: "1px solid #ddd",
                borderRadius: ui.radius,
                padding: ui.pad,
              }}
            >
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  gap: 10,
                  marginBottom: 6,
                }}
              >
                <div style={{ fontSize: ui.base * 1.1, fontWeight: 900 }}>
                  {r.label}
                </div>
                <div
                  style={{
                    color: statusColor,
                    fontWeight: 900,
                    fontSize: ui.small,
                  }}
                >
                  {rl.status}
                </div>
              </div>

              <div
                style={{ color: "#666", marginBottom: 10, fontSize: ui.small }}
              >
                受付時間：{windowText}
              </div>

              {disabled ? (
                <button
                  disabled
                  style={{
                    width: "100%",
                    padding: `${12 * ui.scale}px ${12 * ui.scale}px`,
                    borderRadius: 12,
                    border: "1px solid #ccc",
                    background: "#f3f3f3",
                    color: "#888",
                    fontSize: ui.button,
                    fontWeight: 800,
                    cursor: "not-allowed",
                  }}
                >
                  電話できません（時間外）
                </button>
              ) : (
                <a
                  href={`tel:${r.phone}`}
                  style={{
                    display: "block",
                    textAlign: "center",
                    padding: `${12 * ui.scale}px ${12 * ui.scale}px`,
                    borderRadius: 12,
                    border: "1px solid #111",
                    background: "#111",
                    color: "#fff",
                    textDecoration: "none",
                    fontSize: ui.button,
                    fontWeight: 900,
                  }}
                >
                  電話する（{r.phone}）
                </a>
              )}

              <div style={{ marginTop: 10, color: "#777", fontSize: ui.small }}>
                ※ 受付時間外は押せません。次の受付開始を待ってください。
              </div>
            </div>
          );
        })}
      </div>

      <div style={{ marginTop: 18, color: "#888", fontSize: ui.small }}>
        ※ 緊急時は迷わず救急（119）
      </div>
    </div>
  );
}
