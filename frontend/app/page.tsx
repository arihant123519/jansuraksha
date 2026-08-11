"use client";
import Link from "next/link";

export default function Home() {
  return (
    <div style={{ minHeight: "100vh", background: "linear-gradient(160deg,#1e3a8a 0%,#1d4ed8 60%,#0ea5e9 100%)", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", padding: "32px 24px" }}>

      {/* Logo */}
      <div style={{ textAlign: "center", marginBottom: 48 }}>
        <div style={{ fontSize: 56, marginBottom: 12 }}>🚦</div>
        <h1 style={{ margin: 0, fontSize: 28, fontWeight: 700, color: "#fff", letterSpacing: -0.5 }}>JanSuraksha</h1>
        <p style={{ margin: "8px 0 0", color: "#bfdbfe", fontSize: 14 }}>Citizen Challan Portal</p>
      </div>

      {/* Citizen card */}
      <div style={{ width: "100%", maxWidth: 380, background: "rgba(255,255,255,0.12)", borderRadius: 20, padding: 24, border: "1px solid rgba(255,255,255,0.2)", marginBottom: 16 }}>
        <div style={{ fontSize: 28, marginBottom: 8 }}>📱</div>
        <h2 style={{ margin: "0 0 6px", color: "#fff", fontSize: 18, fontWeight: 600 }}>I am a Citizen</h2>
        <p style={{ margin: "0 0 20px", color: "#bfdbfe", fontSize: 13, lineHeight: 1.5 }}>
          Witnessed a violation? Upload evidence and get a complaint ID.
        </p>
        <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
          <Link href="/login" style={{ background: "#facc15", color: "#1e3a8a", fontWeight: 700, padding: "14px 0", borderRadius: 12, textAlign: "center", fontSize: 15, display: "block" }}>
            Report a Violation
          </Link>
          <Link href="/track" style={{ border: "1px solid rgba(255,255,255,0.4)", color: "#fff", padding: "12px 0", borderRadius: 12, textAlign: "center", fontSize: 14, display: "block" }}>
            Track My Complaint
          </Link>
        </div>
      </div>

      {/* Police card */}
      <div style={{ width: "100%", maxWidth: 380, background: "rgba(255,255,255,0.08)", borderRadius: 20, padding: 24, border: "1px solid rgba(255,255,255,0.15)" }}>
        <div style={{ fontSize: 28, marginBottom: 8 }}>👮</div>
        <h2 style={{ margin: "0 0 6px", color: "#fff", fontSize: 18, fontWeight: 600 }}>Traffic Police</h2>
        <p style={{ margin: "0 0 20px", color: "#bfdbfe", fontSize: 13, lineHeight: 1.5 }}>
          Filter and download citizen complaint reports.
        </p>
        <Link href="/police/login" style={{ background: "#22c55e", color: "#fff", fontWeight: 700, padding: "14px 0", borderRadius: 12, textAlign: "center", fontSize: 15, display: "block" }}>
          Police Portal
        </Link>
      </div>

      <p style={{ color: "#60a5fa", fontSize: 11, marginTop: 32, textAlign: "center", lineHeight: 1.6 }}>
        Free platform · False reports attract IPC 182 liability
      </p>
    </div>
  );
}