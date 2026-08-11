"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
import toast from "react-hot-toast";
import { policeLogin } from "@/lib/api";
import Link from "next/link";

export default function PoliceLoginPage() {
  const router = useRouter();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [loading,  setLoading]  = useState(false);
  const [showPass, setShowPass] = useState(false);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      const res = await policeLogin(username, password);
      localStorage.setItem("token",        res.data.token);
      localStorage.setItem("police_user",  JSON.stringify(res.data.user));
      toast.success(`Welcome, ${res.data.user.name}`);
      router.push("/police/dashboard");
    } catch { toast.error("Invalid credentials"); }
    finally { setLoading(false); }
  };

  return (
    <div style={{ minHeight: "100vh", background: "linear-gradient(160deg,#0f172a 0%,#1e293b 100%)", display: "flex", flexDirection: "column" }}>

      <div style={{ padding: "64px 24px 32px", textAlign: "center" }}>
        <div style={{ fontSize: 48 }}>👮</div>
        <h1 style={{ margin: "12px 0 4px", color: "#fff", fontSize: 22, fontWeight: 700 }}>Police Portal</h1>
        <p style={{ margin: 0, color: "#94a3b8", fontSize: 13 }}>JanSuraksha — Traffic Division</p>
      </div>

      <div style={{ flex: 1, background: "#fff", borderRadius: "24px 24px 0 0", padding: "32px 24px" }}>
        <h2 style={{ margin: "0 0 24px", fontSize: 20, fontWeight: 700, color: "#1e293b" }}>Officer Login</h2>

        <form onSubmit={handleLogin} style={{ display: "flex", flexDirection: "column", gap: 16 }}>
          <div>
            <label style={lbl}>Badge / Username</label>
            <input type="text" value={username} onChange={e => setUsername(e.target.value)}
              placeholder="delhi_tp_001" style={inp()} required />
          </div>
          <div>
            <label style={lbl}>Password</label>
            <div style={{ position: "relative" }}>
              <input type={showPass ? "text" : "password"} value={password} onChange={e => setPassword(e.target.value)}
                placeholder="••••••••" style={{ ...inp(), paddingRight: 48 }} required />
              <button type="button" onClick={() => setShowPass(p => !p)}
                style={{ position: "absolute", right: 14, top: "50%", transform: "translateY(-50%)", background: "none", border: "none", cursor: "pointer", fontSize: 18 }}>
                {showPass ? "🙈" : "👁️"}
              </button>
            </div>
          </div>

          <button type="submit" disabled={loading}
            style={{ background: "#16a34a", color: "#fff", fontWeight: 700, fontSize: 16, border: "none", borderRadius: 12, padding: 16, cursor: "pointer", opacity: loading ? 0.6 : 1, marginTop: 8 }}>
            {loading ? "Logging in..." : "Login to Portal"}
          </button>
        </form>

        <div style={{ marginTop: 32, padding: "16px", background: "#f8fafc", borderRadius: 12, border: "1px solid #e2e8f0" }}>
          <p style={{ margin: "0 0 6px", fontSize: 12, fontWeight: 600, color: "#475569" }}>Test credentials:</p>
          <p style={{ margin: 0, fontSize: 12, color: "#64748b", fontFamily: "monospace" }}>
            delhi_tp_001 / Police@1234
          </p>
        </div>

        <div style={{ textAlign: "center", marginTop: 24 }}>
          <Link href="/" style={{ color: "#94a3b8", fontSize: 13 }}>← Back to home</Link>
        </div>
      </div>
    </div>
  );
}

const lbl: React.CSSProperties = { display: "block", fontSize: 13, fontWeight: 600, color: "#475569", marginBottom: 8 };
const inp = (): React.CSSProperties => ({ width: "100%", border: "1.5px solid #e2e8f0", borderRadius: 12, padding: "14px", fontSize: 16, outline: "none", background: "#fff" });