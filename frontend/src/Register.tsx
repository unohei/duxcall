import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";

const API_BASE = import.meta.env.VITE_API_BASE ?? "/api";

const LIST_KEY = "duxcall_hospitals";
const CURRENT_KEY = "duxcall_current_hospital";

type Hospital = {
  code: string;
  name: string;
  timezone?: string;
};

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

export default function Register() {
  const { hospitalCode } = useParams();
  const navigate = useNavigate();
  const [status, setStatus] = useState<"loading" | "error" | "done">("loading");
  const [message, setMessage] = useState<string>("");

  useEffect(() => {
    if (!hospitalCode) {
      setStatus("error");
      setMessage("病院コードが不正です");
      return;
    }

    setStatus("loading");
    setMessage("");

    const url = `${API_BASE}/register.php?code=${encodeURIComponent(
      hospitalCode
    )}`;

    fetch(url, { method: "POST" }) // register.phpがGET/POST両対応ならPOSTのままでOK
      .then(async (r) => {
        if (!r.ok) {
          let detail = "";
          try {
            const j = await r.json();
            detail = j?.detail ? ` detail=${String(j.detail)}` : "";
          } catch {}
          throw new Error(`HTTP ${r.status}${detail}`);
        }
        return r.json();
      })
      .then((data: { hospital: Hospital }) => {
        const hospital = data.hospital;

        const list = readHospitals();
        const exists = list.some((h) => h.code === hospital.code);
        const next = exists
          ? list.map((h) => (h.code === hospital.code ? hospital : h))
          : [...list, hospital];

        writeHospitals(next);
        localStorage.setItem(CURRENT_KEY, hospital.code);

        setStatus("done");
        setMessage(`${hospital.name} を登録しました`);
        setTimeout(() => navigate("/hospitals"), 900);
      })
      .catch((e: unknown) => {
        const msg = e instanceof Error ? e.message : String(e);
        setStatus("error");
        setMessage(`この病院は登録できません（${msg} / api=${API_BASE}）`);
      });
  }, [hospitalCode, navigate]);

  return (
    <div style={{ padding: 24, fontFamily: "sans-serif" }}>
      <h2>病院登録</h2>
      {status === "loading" && <p>登録しています…</p>}
      {status === "done" && <p>✅ {message}</p>}
      {status === "error" && <p style={{ color: "red" }}>❌ {message}</p>}
    </div>
  );
}
