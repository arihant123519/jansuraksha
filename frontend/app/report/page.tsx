"use client";
import { useState, useEffect, useRef } from "react";
import { useRouter } from "next/navigation";
import toast from "react-hot-toast";
import { submitComplaint, getQuota } from "@/lib/api";
import { VIOLATION_LABELS, ViolationType } from "@/types";
import Link from "next/link";

const PLATE_RE = /^[A-Z]{2}\d{2}[A-Z]{1,2}\d{4}$/;

export default function ReportPage() {
  const router  = useRouter();
  const fileRef = useRef<HTMLInputElement>(null);

  const [quota,   setQuota]   = useState({ used: 0, max: 10, remaining: 10 });
  const [files,   setFiles]   = useState<File[]>([]);
  const [vehicle, setVehicle] = useState("");
  const [vtype,   setVtype]   = useState<ViolationType | "">("");
  const [dt,      setDt]      = useState("");
  const [lat,     setLat]     = useState<number | null>(null);
  const [lng,     setLng]     = useState<number | null>(null);
  const [gpsLoad, setGpsLoad] = useState(false);
  const [loading, setLoading] = useState(false);
  const [done,    setDone]    = useState<string | null>(null);

  useEffect(() => {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    setDt(now.toISOString().slice(0, 16));

    getQuota().then(r => setQuota(r.data)).catch(() => {});
  }, []);

  const getGps = () => {
    if (!navigator.geolocation) { toast.error("GPS not supported"); return; }
    setGpsLoad(true);
    navigator.geolocation.getCurrentPosition(
      p => { setLat(p.coords.latitude); setLng(p.coords.longitude); setGpsLoad(false); toast.success("Location captured"); },
      () => { toast.error("GPS failed — enter manually"); setGpsLoad(false); }
    );
  };

  const onFiles = (e: React.ChangeEvent<HTMLInputElement>) => {
    const picked = Array.from(e.target.files || []).filter(f => {
      if (f.size > 50 * 1024 * 1024) { toast.error(`${f.name} > 50MB`); return false; }
      if (!["image/jpeg","image/png","video/mp4"].includes(f.type)) { toast.error(`${f.name}: JPG/PNG/MP4 only`); return false; }
      return true;
    });
    setFiles(prev => [...prev, ...picked].slice(0, 5));
    e.target.value = "";
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!files.length)                           { toast.error("Upload at least 1 file"); return; }
    if (!PLATE_RE.test(vehicle.toUpperCase()))   { toast.error("Invalid plate. Example: DL01AB1234"); return; }
    if (!vtype)                                  { toast.error("Select violation type"); return; }
    if (!lat || !lng)                            { toast.error("Capture GPS location"); return; }

    setLoading(true);
    const fd = new FormData();
    files.forEach(f => fd.append("evidence[]", f));
    fd.append("vehicle_number", vehicle.toUpperCase());
    fd.append("violation_type", vtype);
    // `dt` is a tz-less local wall-clock string from the datetime-local input.
    // Send an absolute UTC instant so the backend (UTC) doesn't read a local
    // time as a future UTC time and reject it via `before_or_equal:now`.
    fd.append("reported_at",    new Date(dt).toISOString());
    fd.append("lat",            String(lat));
    fd.append("lng",            String(lng));

    try {
      const res = await submitComplaint(fd);
      setDone(res.data.complaint_id);
      setQuota(res.data.quota);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      toast.error(e?.response?.data?.message || "Submission failed");
    } finally { setLoading(false); }
  };

  // ── Success screen ─────────────────────────────────────────────────
  if (done) return (
    <div style={{ minHeight: "100vh", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", padding: 24, background: "#f8fafc" }}>
      <div style={{ width: "100%", maxWidth: 380, background: "#fff", borderRadius: 24, padding: 32, textAlign: "center", boxShadow: "0 4px 24px rgba(0,0,0,0.08)" }}>
        <div style={{ fontSize: 56, marginBottom: 16 }}>✅</div>
        <h2 style={{ margin: "0 0 8px", color: "#1e293b", fontSize: 20, fontWeight: 700 }}>Report Submitted!</h2>
        <p style={{ margin: "0 0 20px", color: "#64748b", fontSize: 14 }}>Your complaint ID:</p>
        <div style={{ background: "#eff6ff", border: "1.5px solid #bfdbfe", borderRadius: 12, padding: "14px 20px", fontFamily: "monospace", fontSize: 18, fontWeight: 700, color: "#1d4ed8", marginBottom: 24, letterSpacing: 1 }}>
          {done}
        </div>
        <p style={{ margin: "0 0 24px", color: "#64748b", fontSize: 13 }}>SMS confirmation sent. Track anytime with this ID.</p>
        <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
          <button onClick={() => router.push(`/track?id=${done}`)} style={btnStyle("#1d4ed8")}>Track This Complaint</button>
          <button onClick={() => { setDone(null); setFiles([]); setVehicle(""); setVtype(""); setLat(null); setLng(null); }} style={btnStyle("#f1f5f9", "#64748b")}>
            Submit Another ({quota.remaining - 1} left today)
          </button>
        </div>
      </div>
    </div>
  );

  // ── Form ────────────────────────────────────────────────────────────
  return (
    <div style={{ minHeight: "100vh", background: "#f8fafc", paddingBottom: 40 }}>

      {/* Top bar */}
      <div style={{ background: "#1d4ed8", padding: "16px 20px", display: "flex", alignItems: "center", justifyContent: "space-between" }}>
        <Link href="/" style={{ color: "#93c5fd", fontSize: 13 }}>← Back</Link>
        <h1 style={{ margin: 0, color: "#fff", fontSize: 16, fontWeight: 700 }}>Report Violation</h1>
        <span style={{ background: "rgba(255,255,255,0.2)", color: "#fff", fontSize: 12, padding: "4px 10px", borderRadius: 20 }}>
          {quota.used}/{quota.max}
        </span>
      </div>

      {quota.remaining === 0 && (
        <div style={{ margin: 16, background: "#fef2f2", border: "1px solid #fecaca", borderRadius: 12, padding: "12px 16px", color: "#dc2626", fontSize: 13 }}>
          ⚠️ Daily limit reached. Resets at midnight IST.
        </div>
      )}

      <form onSubmit={handleSubmit} style={{ padding: "16px 16px 0" }}>

        {/* Evidence upload */}
        <Section title="📷 Evidence">
          <div
            onClick={() => fileRef.current?.click()}
            style={{ border: "2px dashed #93c5fd", borderRadius: 12, padding: "24px 16px", textAlign: "center", cursor: "pointer", background: "#eff6ff", marginBottom: files.length ? 12 : 0 }}
          >
            <div style={{ fontSize: 28, marginBottom: 6 }}>⬆️</div>
            <p style={{ margin: 0, color: "#3b82f6", fontWeight: 600, fontSize: 14 }}>Tap to upload photos / videos</p>
            <p style={{ margin: "4px 0 0", color: "#93c5fd", fontSize: 12 }}>JPG · PNG · MP4 — max 50 MB each</p>
          </div>
          <input ref={fileRef} type="file" multiple accept="image/jpeg,image/png,video/mp4" style={{ display: "none" }} onChange={onFiles} />
          {files.map((f, i) => (
            <div key={i} style={{ display: "flex", alignItems: "center", justifyContent: "space-between", background: "#f8fafc", border: "1px solid #e2e8f0", borderRadius: 10, padding: "10px 14px", marginTop: 8 }}>
              <span style={{ fontSize: 13, color: "#334155", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", flex: 1 }}>{f.name}</span>
              <button type="button" onClick={() => setFiles(p => p.filter((_, j) => j !== i))} style={{ background: "none", border: "none", color: "#94a3b8", fontSize: 18, cursor: "pointer", paddingLeft: 8 }}>×</button>
            </div>
          ))}
        </Section>

        {/* Vehicle & violation */}
        <Section title="🚗 Violation Details">
          <Field label="Vehicle Number">
            <input
              type="text" value={vehicle} maxLength={15}
              onChange={e => setVehicle(e.target.value.toUpperCase())}
              placeholder="DL01AB1234"
              style={inputStyle({ fontFamily: "monospace" })}
              required
            />
            <p style={{ margin: "4px 0 0", color: "#94a3b8", fontSize: 12 }}>Format: DL01AB1234</p>
          </Field>

          <Field label="Violation Type">
            <select value={vtype} onChange={e => setVtype(e.target.value as ViolationType)} style={inputStyle()} required>
              <option value="">Select violation...</option>
              {(Object.entries(VIOLATION_LABELS) as [ViolationType, string][]).map(([k, v]) => (
                <option key={k} value={k}>{v}</option>
              ))}
            </select>
          </Field>

          <Field label="Date & Time of Violation">
            <input type="datetime-local" value={dt} onChange={e => setDt(e.target.value)} style={inputStyle()} required />
          </Field>
        </Section>

        {/* Location */}
        <Section title="📍 Location">
          <button type="button" onClick={getGps} disabled={gpsLoad} style={{ width: "100%", background: lat ? "#f0fdf4" : "#eff6ff", border: `2px solid ${lat ? "#86efac" : "#93c5fd"}`, borderRadius: 12, padding: "16px", color: lat ? "#16a34a" : "#1d4ed8", fontWeight: 700, fontSize: 15, cursor: "pointer" }}>
            {gpsLoad ? "Getting GPS..." : lat ? `✅ ${lat.toFixed(5)}, ${lng?.toFixed(5)}` : "📍 Capture My Location"}
          </button>

          {!lat && (
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10, marginTop: 12 }}>
              <div>
                <label style={labelStyle}>Latitude</label>
                <input type="number" step="any" placeholder="28.6139" onChange={e => setLat(parseFloat(e.target.value))} style={inputStyle({ fontSize: 14 })} />
              </div>
              <div>
                <label style={labelStyle}>Longitude</label>
                <input type="number" step="any" placeholder="77.2090" onChange={e => setLng(parseFloat(e.target.value))} style={inputStyle({ fontSize: 14 })} />
              </div>
            </div>
          )}
        </Section>

        <button
          type="submit"
          disabled={loading || quota.remaining === 0}
          style={{ ...btnStyle("#1d4ed8"), width: "100%", fontSize: 17, padding: "18px", marginTop: 8, opacity: (loading || quota.remaining === 0) ? 0.5 : 1 }}
        >
          {loading ? "Submitting..." : "Submit Report"}
        </button>

        <p style={{ textAlign: "center", color: "#94a3b8", fontSize: 11, marginTop: 16, lineHeight: 1.6 }}>
          By submitting you confirm evidence is authentic. False reports attract IPC 182 liability.
        </p>
      </form>
    </div>
  );
}

// ── Helpers ────────────────────────────────────────────────────────────
const Section = ({ title, children }: { title: string; children: React.ReactNode }) => (
  <div style={{ background: "#fff", borderRadius: 16, padding: 20, marginBottom: 12, boxShadow: "0 1px 4px rgba(0,0,0,0.06)" }}>
    <h3 style={{ margin: "0 0 16px", fontSize: 15, fontWeight: 700, color: "#1e293b" }}>{title}</h3>
    {children}
  </div>
);

const Field = ({ label, children }: { label: string; children: React.ReactNode }) => (
  <div style={{ marginBottom: 16 }}>
    <label style={labelStyle}>{label}</label>
    {children}
  </div>
);

const labelStyle: React.CSSProperties = { display: "block", fontSize: 13, fontWeight: 600, color: "#475569", marginBottom: 8 };

const inputStyle = (extra?: React.CSSProperties): React.CSSProperties => ({
  width: "100%", border: "1.5px solid #e2e8f0", borderRadius: 12,
  padding: "14px 14px", fontSize: 16, outline: "none", background: "#fff",
  appearance: "none", ...extra,
});

const btnStyle = (bg: string, color = "#fff"): React.CSSProperties => ({
  background: bg, color, fontWeight: 700, fontSize: 16,
  border: "none", borderRadius: 12, padding: "16px", cursor: "pointer", display: "block",
});