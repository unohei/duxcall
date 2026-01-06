import { useEffect, useMemo, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";

type Hospital = { code: string; name: string; timezone?: string };

const LIST_KEY = "duxcall_hospitals";
const CURRENT_KEY = "duxcall_current_hospital";

function readHospitals(): Hospital[] {
  const raw = localStorage.getItem(LIST_KEY);
  if (!raw) return [];
  try {
    const arr = JSON.parse(raw) as Hospital[];
    return Array.isArray(arr) ? arr.filter((h) => h?.code) : [];
  } catch {
    return [];
  }
}

function writeHospitals(list: Hospital[]) {
  localStorage.setItem(LIST_KEY, JSON.stringify(list));
}

export default function Hospitals() {
  const [list, setList] = useState<Hospital[]>([]);
  const navigate = useNavigate();
  const location = useLocation();

  const currentCode = localStorage.getItem(CURRENT_KEY);

  // 画面に戻ってきた時も最新化（登録直後/削除後の反映漏れ防止）
  useEffect(() => {
    setList(readHospitals());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.key]);

  const sorted = useMemo(() => {
    // CURRENT_KEY を先頭に寄せる（存在する場合）
    const arr = [...list];
    if (!currentCode) return arr;

    arr.sort((a, b) => {
      if (a.code === currentCode) return -1;
      if (b.code === currentCode) return 1;
      // それ以外は名前→コードで安定ソート
      const an = (a.name || a.code).toLowerCase();
      const bn = (b.name || b.code).toLowerCase();
      return an.localeCompare(bn);
    });

    return arr;
  }, [list, currentCode]);

  const remove = (code: string) => {
    const target = list.find((h) => h.code === code);
    const label = target?.name ? `${target.name}（${code}）` : code;

    if (
      !window.confirm(`${label} を削除しますか？\n※端末内の登録だけが消えます`)
    ) {
      return;
    }

    const next = list.filter((h) => h.code !== code);
    setList(next);
    writeHospitals(next);

    const cur = localStorage.getItem(CURRENT_KEY);
    if (cur === code) localStorage.removeItem(CURRENT_KEY);
  };

  const openHospital = (code: string) => {
    localStorage.setItem(CURRENT_KEY, code);
    navigate(`/hospital/${encodeURIComponent(code)}`);
  };

  return (
    <div
      style={{
        padding: 24,
        fontFamily: "sans-serif",
        maxWidth: 560,
        margin: "0 auto",
      }}
    >
      <h2 style={{ marginTop: 0 }}>連絡先（病院一覧）</h2>

      <div
        style={{ display: "flex", gap: 10, marginBottom: 12, flexWrap: "wrap" }}
      >
        <Link
          to="/scan"
          style={{
            padding: "10px 12px",
            borderRadius: 10,
            background: "#111",
            color: "#fff",
            textDecoration: "none",
            fontWeight: 800,
          }}
        >
          QRで病院を追加
        </Link>

        {currentCode && (
          <button
            onClick={() => openHospital(currentCode)}
            style={{
              padding: "10px 12px",
              borderRadius: 10,
              border: "1px solid #ddd",
              background: "#fff",
              cursor: "pointer",
              fontWeight: 800,
            }}
          >
            最近開いた病院を開く
          </button>
        )}
      </div>

      {sorted.length === 0 ? (
        <div
          style={{
            border: "1px solid #ddd",
            borderRadius: 12,
            padding: 12,
            color: "#666",
          }}
        >
          まだ病院が登録されていません。QRで追加してください。
        </div>
      ) : (
        <div style={{ display: "grid", gap: 10 }}>
          {sorted.map((h) => {
            const isCurrent = currentCode === h.code;
            const title = h.name || h.code;

            return (
              <div
                key={h.code}
                style={{
                  border: "1px solid #ddd",
                  borderRadius: 12,
                  padding: 12,
                  background: isCurrent ? "#fafafa" : "#fff",
                }}
              >
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    gap: 10,
                  }}
                >
                  <div style={{ fontWeight: 900 }}>{title}</div>

                  {isCurrent && (
                    <span
                      style={{
                        fontSize: 12,
                        fontWeight: 900,
                        padding: "4px 8px",
                        borderRadius: 999,
                        border: "1px solid #ddd",
                        background: "#fff",
                        color: "#111",
                        height: "fit-content",
                      }}
                    >
                      最近
                    </span>
                  )}
                </div>

                <div style={{ color: "#666", fontSize: 13, marginTop: 4 }}>
                  code: {h.code}
                </div>

                <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                  <button
                    onClick={() => openHospital(h.code)}
                    style={{
                      padding: "10px 12px",
                      borderRadius: 10,
                      background: "#111",
                      color: "#fff",
                      border: "1px solid #111",
                      cursor: "pointer",
                      fontWeight: 800,
                      flex: 1,
                    }}
                  >
                    開く
                  </button>

                  <button
                    onClick={() => remove(h.code)}
                    style={{
                      padding: "10px 12px",
                      borderRadius: 10,
                      border: "1px solid #ddd",
                      background: "#fff",
                      cursor: "pointer",
                      fontWeight: 800,
                    }}
                  >
                    削除
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      <div
        style={{ marginTop: 14, color: "#888", fontSize: 13, lineHeight: 1.4 }}
      >
        ※ 「削除」は端末内の登録情報のみ。病院側データは消しません。
      </div>
    </div>
  );
}
