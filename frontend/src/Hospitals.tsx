import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";

type Hospital = { code: string; name: string; timezone?: string };

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

export default function Hospitals() {
  const [list, setList] = useState<Hospital[]>([]);
  const navigate = useNavigate();

  useEffect(() => {
    setList(readHospitals());
  }, []);

  const remove = (code: string) => {
    const next = list.filter((h) => h.code !== code);
    setList(next);
    localStorage.setItem(LIST_KEY, JSON.stringify(next));

    const cur = localStorage.getItem(CURRENT_KEY);
    if (cur === code) localStorage.removeItem(CURRENT_KEY);
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

      <div style={{ display: "flex", gap: 10, marginBottom: 12 }}>
        <a
          href="/scan"
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
        </a>
      </div>

      {list.length === 0 ? (
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
          {list.map((h) => (
            <div
              key={h.code}
              style={{
                border: "1px solid #ddd",
                borderRadius: 12,
                padding: 12,
              }}
            >
              <div style={{ fontWeight: 900 }}>{h.name || h.code}</div>
              <div style={{ color: "#666", fontSize: 13, marginTop: 4 }}>
                code: {h.code}
              </div>

              <div style={{ display: "flex", gap: 10, marginTop: 10 }}>
                <Link
                  to={`/hospital/${encodeURIComponent(h.code)}`}
                  style={{
                    padding: "10px 12px",
                    borderRadius: 10,
                    background: "#111",
                    color: "#fff",
                    textDecoration: "none",
                    fontWeight: 800,
                    flex: 1,
                    textAlign: "center",
                  }}
                >
                  開く
                </Link>

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
          ))}
        </div>
      )}

      <div style={{ marginTop: 14, color: "#888", fontSize: 13 }}>
        ※ 「削除」は端末内の登録情報のみ。病院側データは消しません。
      </div>
    </div>
  );
}
