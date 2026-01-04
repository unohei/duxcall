import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import Hospitals from "./Hospitals";
import HospitalDetail from "./HospitalDetail";
import Register from "./Register";
import QrScan from "./QrScan";
import QrTest from "./QrTest";

ReactDOM.createRoot(document.getElementById("root")!).render(
  // <React.StrictMode>
  <BrowserRouter>
    <Routes>
      <Route path="/" element={<Navigate to="/hospitals" replace />} />
      <Route path="/hospitals" element={<Hospitals />} />
      <Route path="/hospital/:hospitalCode" element={<HospitalDetail />} />
      <Route path="/register/:hospitalCode" element={<Register />} />
      <Route path="/scan" element={<QrScan />} />
      <Route path="/qrtest" element={<QrTest />} />
    </Routes>
  </BrowserRouter>
  // </React.StrictMode>
);
