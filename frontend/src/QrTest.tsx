import { useEffect, useState } from "react";
import QRCode from "qrcode";

export default function QrTest() {
  const [text, setText] = useState("tokyo-clinic");
  const [dataUrl, setDataUrl] = useState<string>("");
  const [error, setError] = useState<string>("");

  useEffect(() => {
    let cancelled = false;
    setError("");

    (async () => {
      try {
        const url = await QRCode.toDataURL(text, { margin: 1, scale: 6 });
        if (!cancelled) setDataUrl(url);
      } catch (e: any) {
        if (!cancelled) setError(e?.message ?? String(e));
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [text]);

  return (
    <div style={{ padding: 16, fontFamily: "sans-serif", maxWidth: 520 }}>
      <h2 style={{ marginTop: 0 }}>QRテスト</h2>

      <label style={{ fontWeight: 800 }}>QRにする文字</label>
      <input
        value={text}
        onChange={(e) => setText(e.target.value)}
        style={{
          width: "100%",
          padding: 12,
          borderRadius: 12,
          border: "1px solid #ddd",
          marginTop: 6,
          fontSize: 16,
        }}
      />

      {error && (
        <div style={{ marginTop: 12, color: "#b00", fontWeight: 800 }}>
          {error}
        </div>
      )}

      {dataUrl && (
        <div style={{ marginTop: 14 }}>
          <img
            src={dataUrl}
            alt="qr"
            style={{ width: 220, height: 220, border: "1px solid #ddd" }}
          />
        </div>
      )}
    </div>
  );
}
