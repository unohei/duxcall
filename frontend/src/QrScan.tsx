import { useEffect, useRef, useState } from "react";
import { BrowserMultiFormatReader } from "@zxing/browser";
import { useNavigate } from "react-router-dom";

export default function QrScan() {
  const videoRef = useRef<HTMLVideoElement | null>(null);

  // ZXing reader と stop制御
  const readerRef = useRef<BrowserMultiFormatReader | null>(null);
  const controlsRef = useRef<{ stop: () => void } | null>(null);

  // 念のため（端末/ブラウザ差異で stream が残るケース対策）
  const streamRef = useRef<MediaStream | null>(null);

  const navigate = useNavigate();

  const [status, setStatus] = useState<"starting" | "scanning" | "error">(
    "starting"
  );
  const [message, setMessage] = useState<string>(
    "カメラを起動しています…（許可が出たらOK）"
  );

  const stopCamera = () => {
    // 1) decode停止（ZXing側）
    try {
      controlsRef.current?.stop();
    } catch {
      // 互換差異があっても落とさない
    } finally {
      controlsRef.current = null;
    }

    // 2) reader参照（破棄してOK）
    readerRef.current = null;

    // 3) カメラ停止（ブラウザ側：残留対策）
    try {
      streamRef.current?.getTracks().forEach((t) => t.stop());
    } catch {
      // ignore
    } finally {
      streamRef.current = null;
    }

    // 4) video解放
    if (videoRef.current) videoRef.current.srcObject = null;
  };

  useEffect(() => {
    let mounted = true;

    const start = async () => {
      try {
        setStatus("starting");
        setMessage("カメラを起動しています…（許可が出たらOK）");

        const video = videoRef.current;
        if (!video) throw new Error("video element not found");

        const reader = new BrowserMultiFormatReader();
        readerRef.current = reader;

        // ✅ ここが重要：decodeFromVideoDevice を使う（推奨）
        // - undefined: 端末の既定カメラ（背面優先になりやすい）
        // - コールバックは result が来たときだけ処理
        const controls = await reader.decodeFromVideoDevice(
          undefined,
          video,
          (result, _err) => {
            if (!mounted) return;
            if (!result) return;

            const text = result.getText().trim();
            const code = parseHospitalCode(text);

            if (!code) {
              setStatus("error");
              setMessage("QRの形式が不正です（病院コードが見つかりません）");
              return;
            }

            stopCamera();
            navigate(`/register/${encodeURIComponent(code)}`);
          }
        );

        // decodeFromVideoDevice が返す controls で停止できる
        controlsRef.current = controls as unknown as { stop: () => void };

        // 念のため stream を保持（ZXingが内部で作るので video.srcObject から拾う）
        // ※ ブラウザによっては即時反映されないので try/catch
        try {
          const so = (video as any).srcObject as MediaStream | null;
          if (so) streamRef.current = so;
        } catch {
          // ignore
        }

        setStatus("scanning");
        setMessage("QRコードを枠内に写してください");
      } catch (e: unknown) {
        setStatus("error");
        const err = e as any;
        const msg =
          err?.name && err?.message
            ? `${err.name}: ${err.message}`
            : e instanceof Error
            ? e.message
            : "不明なエラー";
        setMessage(`カメラを起動できません：${msg}`);
      }
    };

    start();

    return () => {
      mounted = false;
      stopCamera();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [navigate]);

  return (
    <div
      style={{
        padding: 16,
        fontFamily: "sans-serif",
        maxWidth: 560,
        margin: "0 auto",
      }}
    >
      <h2 style={{ marginTop: 0 }}>QRコードを読み取る</h2>

      <div
        style={{
          border: "1px solid #ddd",
          borderRadius: 12,
          overflow: "hidden",
          background: "#000",
        }}
      >
        <video
          ref={videoRef}
          style={{ width: "100%", height: "auto", display: "block" }}
          muted
          playsInline
        />
      </div>

      <div
        style={{
          marginTop: 10,
          color: status === "error" ? "#b00" : "#666",
          fontWeight: 700,
        }}
      >
        {message}
      </div>

      <div style={{ marginTop: 14, display: "flex", gap: 10 }}>
        <button
          onClick={() => {
            stopCamera();
            navigate("/");
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
          戻る
        </button>

        {status === "error" && (
          <button
            onClick={() => window.location.reload()}
            style={{
              padding: "10px 12px",
              borderRadius: 10,
              border: "1px solid #111",
              background: "#111",
              color: "#fff",
              cursor: "pointer",
              fontWeight: 800,
            }}
          >
            もう一度試す
          </button>
        )}
      </div>

      <div
        style={{ marginTop: 14, color: "#888", fontSize: 13, lineHeight: 1.4 }}
      >
        QRの中身はどちらでもOK：
        <ul style={{ marginTop: 6 }}>
          <li>
            病院コードだけ：<code>tokyo-clinic</code>
          </li>
          <li>
            登録URL：<code>http://localhost:5173/register/tokyo-clinic</code>
          </li>
        </ul>
      </div>
    </div>
  );
}

function parseHospitalCode(text: string): string | null {
  // URL形式：.../register/{code}
  try {
    if (text.startsWith("http://") || text.startsWith("https://")) {
      const u = new URL(text);
      const parts = u.pathname.split("/").filter(Boolean);
      const idx = parts.findIndex((p) => p === "register");
      if (idx >= 0 && parts[idx + 1]) return parts[idx + 1];
      return null;
    }
  } catch {
    // not url
  }

  // コードだけ（英数とハイフン）
  if (/^[a-z0-9-]+$/i.test(text)) return text;

  return null;
}
