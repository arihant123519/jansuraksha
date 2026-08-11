"use client";
import { useState, useEffect, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import toast from "react-hot-toast";
import { trackComplaint } from "@/lib/api";
import { Complaint, ComplaintStatus, VIOLATION_LABELS } from "@/types";
import Link from "next/link";

const STATUS_META: Record<ComplaintStatus, { label: string; icon: string; color: string; bg: string }> = {
  submitted:    { label: "Submitted",    icon: "📨", color: "#1d4ed8", bg: "#eff6ff" },
  under_review: { label: "Under Review", icon: "🔍", color: "#d97706", bg: "#fffbeb" },
  actioned:     { label: "Actioned",     icon: "✅", color: "#16a34a", bg: "#f0fdf4" },
  rejected:     { label: "Rejected",     icon: "❌", color: "#dc2626", bg: "#fef2f2" },
  duplicate:    { label: "Duplicate",    icon: "📋", color: "#6b7280", bg: "#f9fafb" },
  pending:      { label: "Pending",      icon: "⏳", color: "#d97706", bg: "#fffbeb" },
};

function TrackContent() {
  const params  = useSearchParams();
  const [id, setId]             = useState(params.get("id") || "");
  const [complaint, setComplaint] = useState<Complaint | null>(null);
  const [loading, setLoading]   = useState(false);

  useEffect(() => { if (params.get("id")) search(params.get("id")!); }, []);

  const search = async (searchId?: string) => {
    const target = searchId || id;
    if (!target.trim()) { toast.error("Enter complaint ID"); return; }
    setLoading(true);
    try {
      const res = await trackComplaint(target.trim());
      setComplaint(res.data);
    } catch (err: unknown) {
      const status = (err as { response?: { status?: number } })?.response?.status;
      toast.error(status === 404 ? "No complaint found with that ID" : "Could not reach the server. Try again.");
      setComplaint(null);
    }
    finally { setLoading(false); }
  };

  const meta = complaint ? STATUS_META[complaint.status] : null;

  return (
    <div style={{ minHeight: "100vh", background: "#f8fafc", paddingBottom: 40 }}>

      {/* Top bar */}
      <div style={{ background: "#1d4ed8", padding: "16px 20px", display: "flex", alignItems: "center", gap: 12 }}>
        <Link href="/" style={{ color: "#93c5fd", fontSize: 13 }}>← Back</Link>
        <h1 style={{ margin: 0, color: "#fff", fontSize: 16, fontWeight: 700 }}>Track Complaint</h1>
      </div>

      <div style={{ padding: 16 }}>

        {/* Search */}
        <div style={{ background: "#fff", borderRadius: 16, padding: 20, marginBottom: 16, boxShadow: "0 1px 4px rgba(0,0,0,0.06)" }}>
          <label style={{ display: "block", fontSize: 13, fontWeight: 600, color: "#475569", marginBottom: 8 }}>Complaint ID</label>
          <div style={{ display: "flex", gap: 10 }}>
            <input
              type="text" value={id} onChange={e => setId(e.target.value)}
              placeholder="JS-2024-XXXXXXXX"
              style={{ flex: 1, border: "1.5px solid #e2e8f0", borderRadius: 12, padding: "13px 14px", fontSize: 15, outline: "none", fontFamily: "monospace" }}
            />
            <button onClick={() => search()} disabled={loading} style={{ background: "#1d4ed8", color: "#fff", border: "none", borderRadius: 12, padding: "0 18px", fontWeight: 700, cursor: "pointer", fontSize: 20 }}>
              {loading ? "…" : "🔍"}
            </button>
          </div>
        </div>

        {/* Result */}
        {complaint && meta && (
          <>
            {/* Status banner */}
            <div style={{ background: meta.bg, border: `1.5px solid ${meta.color}40`, borderRadius: 16, padding: "20px", marginBottom: 12, display: "flex", alignItems: "center", gap: 14 }}>
              <span style={{ fontSize: 36 }}>{meta.icon}</span>
              <div>
                <div style={{ fontSize: 18, fontWeight: 700, color: meta.color }}>{meta.label}</div>
                <div style={{ fontSize: 12, color: "#64748b", fontFamily: "monospace", marginTop: 2 }}>{complaint.complaint_id}</div>
              </div>
            </div>

            {/* Details */}
            <div style={{ background: "#fff", borderRadius: 16, padding: 20, marginBottom: 12, boxShadow: "0 1px 4px rgba(0,0,0,0.06)" }}>
              <h3 style={{ margin: "0 0 16px", fontSize: 15, fontWeight: 700, color: "#1e293b" }}>Complaint Details</h3>
              {[
                ["Vehicle", <span style={{ fontFamily: "monospace", fontWeight: 700, color: "#1d4ed8" }}>{complaint.vehicle_number}</span>],
                ["Violation", VIOLATION_LABELS[complaint.violation_type]],
                ["Reported", new Date(complaint.reported_at).toLocaleString("en-IN")],
                ["District", complaint.area_district || "—"],
                ["State", complaint.area_state || "—"],
              ].map(([label, value]) => (
                <div key={String(label)} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", paddingBottom: 12, marginBottom: 12, borderBottom: "1px solid #f1f5f9" }}>
                  <span style={{ fontSize: 13, color: "#64748b" }}>{label}</span>
                  <span style={{ fontSize: 13, fontWeight: 600, color: "#1e293b" }}>{value}</span>
                </div>
              ))}

              {/* Map link */}
              {complaint.location_lat && (
                <a
                  href={`https://www.google.com/maps?q=${complaint.location_lat},${complaint.location_lng}`}
                  target="_blank" rel="noreferrer"
                  style={{ display: "block", textAlign: "center", background: "#eff6ff", color: "#1d4ed8", borderRadius: 10, padding: "12px", fontSize: 13, fontWeight: 600, marginTop: 4 }}
                >
                  📍 View Location on Map
                </a>
              )}
            </div>

            {/* Timeline */}
            {(complaint.status_logs?.length ?? 0) > 0 && (
              <div style={{ background: "#fff", borderRadius: 16, padding: 20, boxShadow: "0 1px 4px rgba(0,0,0,0.06)" }}>
                <h3 style={{ margin: "0 0 16px", fontSize: 15, fontWeight: 700, color: "#1e293b" }}>Status Timeline</h3>
                {complaint.status_logs!.map((log, i) => (
                  <div key={i} style={{ display: "flex", gap: 12, marginBottom: 16 }}>
                    <div style={{ display: "flex", flexDirection: "column", alignItems: "center" }}>
                      <div style={{ width: 10, height: 10, borderRadius: "50%", background: "#1d4ed8", marginTop: 2, flexShrink: 0 }} />
                      {i < complaint.status_logs!.length - 1 && <div style={{ width: 2, flex: 1, background: "#e2e8f0", marginTop: 4 }} />}
                    </div>
                    <div style={{ paddingBottom: 8 }}>
                      <div style={{ fontSize: 13, fontWeight: 600, color: "#1e293b", textTransform: "capitalize" }}>
                        {log.new_status.replace(/_/g, " ")}
                      </div>
                      {log.reason && <div style={{ fontSize: 12, color: "#dc2626", marginTop: 2 }}>Reason: {log.reason}</div>}
                      <div style={{ fontSize: 11, color: "#94a3b8", marginTop: 2 }}>{new Date(log.changed_at).toLocaleString("en-IN")}</div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

export default function TrackPage() {
  return <Suspense><TrackContent /></Suspense>;
}