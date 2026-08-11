"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
import toast from "react-hot-toast";
import { verifyOtp } from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();
  const [mobile, setMobile]   = useState("");
  const [otp, setOtp]         = useState("");
  const [step, setStep]       = useState<"mobile" | "otp">("mobile");
  const [loading, setLoading] = useState(false);

  const handleSendOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!/^[6-9]\d{9}$/.test(mobile)) { toast.error("Enter valid 10-digit mobile"); return; }
    setLoading(true);
    try {
      // TESTING ONLY: skip OTP, log in directly with the master test code.
      const res = await verifyOtp(mobile, "000000");
      localStorage.setItem("token", res.data.token);
      localStorage.setItem("user", JSON.stringify(res.data.user));
      toast.success("Logged in!");
      router.push("/report");
    } catch { toast.error("Login failed"); }
    finally { setLoading(false); }
  };

  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (otp.length !== 6) { toast.error("Enter 6-digit OTP"); return; }
    setLoading(true);
    try {
      const res = await verifyOtp(mobile, otp);
      localStorage.setItem("token", res.data.token);
      localStorage.setItem("user", JSON.stringify(res.data.user));
      toast.success("Logged in!");
      router.push("/report");
    } catch { toast.error("Invalid OTP"); }
    finally { setLoading(false); }
  };

  return (
    <div style={{ minHeight: "100vh", background: "linear-gradient(160deg,#1e3a8a,#1d4ed8)", display: "flex", flexDirection: "column" }}>

      {/* Header */}
      <div style={{ padding: "56px 24px 32px", textAlign: "center" }}>
        <div style={{ fontSize: 40 }}>🚦</div>
        <h1 style={{ margin: "8px 0 4px", color: "#fff", fontSize: 22, fontWeight: 700 }}>JanSuraksha</h1>
        <p style={{ margin: 0, color: "#bfdbfe", fontSize: 13 }}>
          {step === "mobile" ? "Login with your mobile number" : `OTP sent to +91 ${mobile}`}
        </p>
      </div>

      {/* Card */}
      <div style={{ flex: 1, background: "#fff", borderRadius: "24px 24px 0 0", padding: "32px 24px" }}>
        <h2 style={{ margin: "0 0 24px", fontSize: 20, fontWeight: 700, color: "#1e293b" }}>
          {step === "mobile" ? "Enter Mobile Number" : "Enter OTP"}
        </h2>

        {step === "mobile" ? (
          <form onSubmit={handleSendOtp} style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <div>
              <label style={{ fontSize: 13, fontWeight: 600, color: "#475569", display: "block", marginBottom: 8 }}>Mobile Number</label>
              <div style={{ display: "flex", border: "1.5px solid #e2e8f0", borderRadius: 12, overflow: "hidden" }}>
                <span style={{ background: "#f8fafc", padding: "14px 12px", color: "#64748b", fontSize: 15, borderRight: "1.5px solid #e2e8f0" }}>+91</span>
                <input
                  type="tel" value={mobile} maxLength={10}
                  onChange={e => setMobile(e.target.value.replace(/\D/g, ""))}
                  placeholder="9876543210"
                  style={{ flex: 1, border: "none", outline: "none", padding: "14px 12px", fontSize: 16, background: "#fff" }}
                  required
                />
              </div>
            </div>
            <button type="submit" disabled={loading} style={btnStyle("#1d4ed8")}>
              {loading ? "Sending..." : "Send OTP"}
            </button>
          </form>
        ) : (
          <form onSubmit={handleVerifyOtp} style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <div>
              <label style={{ fontSize: 13, fontWeight: 600, color: "#475569", display: "block", marginBottom: 8 }}>6-Digit OTP</label>
              <input
                type="text" value={otp} maxLength={6}
                onChange={e => setOtp(e.target.value.replace(/\D/g, ""))}
                placeholder="123456"
                style={{ width: "100%", border: "1.5px solid #e2e8f0", borderRadius: 12, padding: "16px", fontSize: 24, textAlign: "center", letterSpacing: 12, outline: "none" }}
                required
              />
            </div>
            <button type="submit" disabled={loading} style={btnStyle("#1d4ed8")}>
              {loading ? "Verifying..." : "Verify & Continue"}
            </button>
            <button type="button" onClick={() => { setStep("mobile"); setOtp(""); }} style={btnStyle("#f1f5f9", "#64748b")}>
              Change Number
            </button>
          </form>
        )}

        <p style={{ marginTop: 32, fontSize: 11, color: "#94a3b8", textAlign: "center", lineHeight: 1.6 }}>
          By continuing you agree to our Terms of Service.{"\n"}Evidence shared under IT Act 2000. DPDP Act 2023 compliant.
        </p>
      </div>
    </div>
  );
}

const btnStyle = (bg: string, color = "#fff"): React.CSSProperties => ({
  background: bg, color, fontWeight: 700, fontSize: 16,
  border: "none", borderRadius: 12, padding: "16px", cursor: "pointer",
  opacity: 1, transition: "opacity 0.2s",
});