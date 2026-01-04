import { useMemo, useState } from "react";
import QRCode from "qrcode";

export default function QrTest() {
  const [text, setText] = useState("tokyo-clinic");

  const dataUrl = useMemo(async () => {
    return await QRCode.toDataURL(text, { margin: 2, width: 260 });
  }, [text]);

  const [img, setImg] = useState<string>("");

  useMemo(() => {
    (async () =>
      setImg(await QRCode.toDataURL(text, { margin: 2, width: 260 })))();
  }, [text]);

  return (
    <div
      style={{
        padding: 24,
        fontFamily: "sans-serif",
        maxWidth: 560,
        margin: "0 auto",
      }}
    >
      <h2 style={{ marginTop: 0 }}>QRテスト</h2>

      <div style={{ display: "grid", gap: 8 }}>
        <label style={{ fontWeight: 800 }}>QRの中身</label>
        <input
          value={text}
          onChange={(e) => setText(e.target.value)}
          style={{
            padding: "10px 12px",
            borderRadius: 10,
            border: "1px solid #ddd",
            fontSize: 16,
          }}
        />

        <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
          <button
            onClick={() => setText("tokyo-clinic")}
            style={{
              padding: "10px 12px",
              borderRadius: 10,
              border: "1px solid #ddd",
              background: "#fff",
              fontWeight: 800,
            }}
          >
            コードだけ
          </button>
          <button
            onClick={() =>
              setText("http://localhost:5173/register/tokyo-clinic")
            }
            style={{
              padding: "10px 12px",
              borderRadius: 10,
              border: "1px solid #ddd",
              background: "#fff",
              fontWeight: 800,
            }}
          >
            URL形式
          </button>
        </div>
      </div>

      <div style={{ marginTop: 14 }}>
        {img ? (
          <img
            src={img}
            alt="qr"
            style={{
              width: 260,
              height: 260,
              border: "1px solid #ddd",
              borderRadius: 12,
            }}
          />
        ) : (
          "生成中..."
        )}
      </div>

      <div style={{ marginTop: 10, color: "#666" }}>
        これを別端末（スマホなど）で表示して、<code>/scan</code>{" "}
        で読み取ってください。
      </div>
    </div>
  );
}
