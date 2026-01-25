import ReactDOM from "react-dom/client";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";

import Hospitals from "./Hospitals";
import HospitalDetail from "./HospitalDetail";
import Register from "./Register";
import QrScan from "./QrScan";
import QrTest from "./QrTest";

ReactDOM.createRoot(document.getElementById("root")!).render(
  // StrictMode は一旦外したままでOK（挙動確認優先）
  <BrowserRouter basename="/duxcall/app">
    <Routes>
      {/* トップアクセス時は病院一覧へ */}
      <Route path="/" element={<Navigate to="/hospitals" replace />} />

      <Route path="/hospitals" element={<Hospitals />} />
      <Route path="/hospital/:hospitalCode" element={<HospitalDetail />} />
      <Route path="/register/:hospitalCode" element={<Register />} />
      <Route path="/scan" element={<QrScan />} />
      <Route path="/qrtest" element={<QrTest />} />

      {/* 保険：存在しないURLは一覧へ */}
      <Route path="*" element={<Navigate to="/hospitals" replace />} />
    </Routes>
  </BrowserRouter>,
);
