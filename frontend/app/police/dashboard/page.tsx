"use client";
import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import toast from "react-hot-toast";
import { getPoliceComplaints, updateStatus, exportCsv, exportPdf } from "@/lib/api";
import { Complaint, ComplaintStatus, VIOLATION_LABELS, ViolationType } from "@/types";

const REJECTION_REASONS = [
  "Insufficient evidence",
  "Vehicle number not visible",
  "Duplicate complaint",
  "Outside jurisdiction",
  "Invalid location",
];

const STATUS_COLORS: Record<string, string> = {
  submitted:    "#1d4ed8",
  under_review: "#d97706",
  actioned:     "#16a34a",
  rejected:     "#dc2626",
  duplicate:    "#6b7280",
  pending:      "#d97706",
};

export default function PoliceDashboard() {
  const router = useRouter();
  const [officer, setOfficer] = useState<{ name: string; badge_number: string; jurisdiction_district: string } | null>(null);
  const [complaints, setComplaints] = useState<Complaint[]>([]);
  const [total,      setTotal]      = useState(0);
  const [fetching,   setFetching]   = useState(false);
  const [exporting,  setExporting]  = useState<"csv" | "pdf" | null>(null);
  const [showFilters, setShowFilters] = useState(false);

  // Filters
  const [filters, setFilters] = useState({ state: "", district: "", date_from: "", date_to: "", violation_type: "", vehicle_number: "", status: "all" });

  // Status update modal
  const [selected,  setSelected]  = useState<Complaint | null>(null);
  const [newStatus, setNewStatus] = useState<ComplaintStatus>("pending");
  const [reason,    setReason]    = useState("");
  const [updating,  setUpdating]  = useState(false);

  useEffect(() => {
    const token = localStorage.getItem("token");
    const user  = localStorage.getItem("police_user");
    if (!token || !user) { router.push("/police/login"); return; }
    setOfficer(JSON.parse(user));
  }, []);

  const setFilter = (k: string, v: string) => setFilters(f => ({ ...f, [k]: v }));

  const fetch = async () => {
    setFetching(true);
    try {
      const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v && v !== "all"));
      const res = await getPoliceComplaints(params);
      setComplaints(res.data.data || []);
      setTotal(res.data.meta?.total || 0);
    } catch (err: unknown) {
      const status = (err as { response?: { status?: number } })?.response?.status;
      if (status === 401 || status === 403) {
        localStorage.removeItem("token"); localStorage.removeItem("police_user");
        toast.error("Session expired — please log in again");
        router.push("/police/login");
      } else {
        toast.error("Failed to load complaints");
      }
    }
    finally { setFetching(false); }
  };

  const doExport = async (type: "csv" | "pdf") => {
    setExporting(type);
    try {
      const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v && v !== "all"));
      const res = type === "csv" ? await exportCsv(params) : await exportPdf(params);
      const url  = URL.createObjectURL(new Blob([res.data]));
      const a    = document.createElement("a");
      a.href     = url;
      a.download = `jansuraksha.${type}`;
      a.click();
      URL.revokeObjectURL(url);
      toast.success(`${type.toUpperCase()} downloaded`);
    } catch { toast.error("Export failed"); }
    finally { setExporting(null); }
  };

  const doUpdate = async () => {
    if (!selected) return;
    if (newStatus === "rejected" && !reason) { toast.error("Select rejection reason"); return; }
    setUpdating(true);
    try {
      await updateStatus(selected.complaint_id, newStatus, reason || undefined);
      toast.success("Status updated");
      setSelected(null);
      fetch();
    } catch { toast.error("Update failed"); }
    finally { setUpdating(false); }
  };

  return (
    <div style={{ minHeight: "100vh", background: "#f8fafc", paddingBottom: 80 }}>

      {/* Top bar */}
      <div style={{ background: "#0f172a", padding: "16px 20px" }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
          <div>
            <div style={{ color: "#fff", fontWeight: 700, fontSize: 15 }}>👮 {officer?.name || "Police Portal"}</div>
            <div style={{ color: "#64748b", fontSize: 12 }}>{officer?.badge_number} · {officer?.jurisdiction_district}</div>
          </div>
          <button onClick={() => { localStorage.removeItem("token"); localStorage.removeItem("police_user"); router.push("/police/login"); }}
            style={{ background: "none", border: "1px solid #334155", color: "#94a3b8", borderRadius: 8, padding: "6px 12px", fontSize: 12, cursor: "pointer" }}>
            Logout
          </button>
        </div>
      </div>

      <div style={{ padding: 16 }}>

        {/* Filter toggle */}
        <div style={{ background: "#fff", borderRadius: 16, padding: 16, marginBottom: 12, boxShadow: "0 1px 4px rgba(0,0,0,0.06)" }}>
          <button onClick={() => setShowFilters(f => !f)}
            style={{ width: "100%", display: "flex", justifyContent: "space-between", alignItems: "center", background: "none", border: "none", cursor: "pointer", padding: 0 }}>
            <span style={{ fontWeight: 700, fontSize: 15, color: "#1e293b" }}>🔍 Filters</span>
            <span style={{ color: "#94a3b8", fontSize: 13 }}>{showFilters ? "▲ Hide" : "▼ Show"}</span>
          </button>

          {showFilters && (
            <div style={{ marginTop: 16, display: "flex", flexDirection: "column", gap: 12 }}>
              {[
                { label: "State",    key: "state",    placeholder: "e.g. Delhi" },
                { label: "District", key: "district", placeholder: "e.g. Central Delhi" },
                { label: "Vehicle",  key: "vehicle_number", placeholder: "DL01 (partial ok)" },
              ].map(({ label, key, placeholder }) => (
                <div key={key}>
                  <label style={lbl}>{label}</label>
                  <input value={filters[key as keyof typeof filters]} onChange={e => setFilter(key, e.target.value)}
                    placeholder={placeholder} style={inp()} />
                </div>
              ))}

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                <div>
                  <label style={lbl}>From Date</label>
                  <input type="date" value={filters.date_from} onChange={e => setFilter("date_from", e.target.value)} style={inp()} />
                </div>
                <div>
                  <label style={lbl}>To Date</label>
                  <input type="date" value={filters.date_to} onChange={e => setFilter("date_to", e.target.value)} style={inp()} />
                </div>
              </div>

              <div>
                <label style={lbl}>Violation Type</label>
                <select value={filters.violation_type} onChange={e => setFilter("violation_type", e.target.value)} style={inp()}>
                  <option value="">All Types</option>
                  {(Object.entries(VIOLATION_LABELS) as [ViolationType, string][]).map(([k, v]) => (
                    <option key={k} value={k}>{v}</option>
                  ))}
                </select>
              </div>

              <div>
                <label style={lbl}>Status</label>
                <select value={filters.status} onChange={e => setFilter("status", e.target.value)} style={inp()}>
                  {["all","submitted","pending","actioned","rejected","duplicate"].map(s => (
                    <option key={s} value={s} style={{ textTransform: "capitalize" }}>{s}</option>
                  ))}
                </select>
              </div>
            </div>
          )}

          <button onClick={fetch} disabled={fetching}
            style={{ width: "100%", background: "#1d4ed8", color: "#fff", fontWeight: 700, border: "none", borderRadius: 12, padding: "14px", fontSize: 15, cursor: "pointer", marginTop: 16, opacity: fetching ? 0.6 : 1 }}>
            {fetching ? "Loading..." : "Apply Filters"}
          </button>
        </div>

        {/* Results header */}
        {complaints.length > 0 && (
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
            <span style={{ fontSize: 14, fontWeight: 600, color: "#1e293b" }}>{total} complaints</span>
            <div style={{ display: "flex", gap: 8 }}>
              <button onClick={() => doExport("csv")} disabled={!!exporting}
                style={{ background: "#f0fdf4", color: "#16a34a", border: "1px solid #86efac", borderRadius: 8, padding: "8px 14px", fontSize: 13, fontWeight: 600, cursor: "pointer" }}>
                {exporting === "csv" ? "..." : "⬇ CSV"}
              </button>
              <button onClick={() => doExport("pdf")} disabled={!!exporting}
                style={{ background: "#fef2f2", color: "#dc2626", border: "1px solid #fca5a5", borderRadius: 8, padding: "8px 14px", fontSize: 13, fontWeight: 600, cursor: "pointer" }}>
                {exporting === "pdf" ? "..." : "⬇ PDF"}
              </button>
            </div>
          </div>
        )}

        {/* Complaint cards */}
        {complaints.length === 0 && !fetching && (
          <div style={{ textAlign: "center", padding: "48px 24px", color: "#94a3b8" }}>
            <div style={{ fontSize: 40, marginBottom: 12 }}>🔍</div>
            <p style={{ margin: 0, fontSize: 14 }}>Apply filters to load complaints</p>
          </div>
        )}

        {complaints.map(c => (
          <div key={c.id} style={{ background: "#fff", borderRadius: 16, padding: 16, marginBottom: 12, boxShadow: "0 1px 4px rgba(0,0,0,0.06)", border: c.is_flagged ? "1.5px solid #fca5a5" : "1px solid #f1f5f9" }}>
            {c.is_flagged && (
              <div style={{ background: "#fef2f2", borderRadius: 8, padding: "6px 10px", marginBottom: 10, fontSize: 12, color: "#dc2626", fontWeight: 600 }}>
                ⚠️ Flagged — 5+ reports from different users
              </div>
            )}

            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 10 }}>
              <div>
                <div style={{ fontFamily: "monospace", fontSize: 13, color: "#1d4ed8", fontWeight: 700 }}>{c.complaint_id}</div>
                <div style={{ fontFamily: "monospace", fontSize: 17, fontWeight: 700, color: "#1e293b", marginTop: 2 }}>{c.vehicle_number}</div>
              </div>
              <span style={{ background: STATUS_COLORS[c.status] + "20", color: STATUS_COLORS[c.status], fontSize: 12, fontWeight: 700, padding: "4px 10px", borderRadius: 20, textTransform: "capitalize" }}>
                {c.status}
              </span>
            </div>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8, marginBottom: 12 }}>
              {[
                ["Violation", VIOLATION_LABELS[c.violation_type]],
                ["Date",      new Date(c.reported_at).toLocaleDateString("en-IN")],
                ["District",  c.area_district || "—"],
                ["Evidence",  `${c.evidence_count || 0} file(s)`],
              ].map(([k, v]) => (
                <div key={String(k)}>
                  <div style={{ fontSize: 11, color: "#94a3b8", textTransform: "uppercase", letterSpacing: 0.5 }}>{k}</div>
                  <div style={{ fontSize: 13, fontWeight: 600, color: "#1e293b", marginTop: 2 }}>{v}</div>
                </div>
              ))}
            </div>

            <div style={{ display: "flex", gap: 8 }}>
              <a href={`https://www.google.com/maps?q=${c.location_lat},${c.location_lng}`} target="_blank" rel="noreferrer"
                style={{ flex: 1, background: "#eff6ff", color: "#1d4ed8", borderRadius: 10, padding: "10px", textAlign: "center", fontSize: 13, fontWeight: 600, display: "block" }}>
                📍 Map
              </a>
              <button onClick={() => { setSelected(c); setNewStatus(c.status); setReason(""); }}
                style={{ flex: 2, background: "#f8fafc", border: "1px solid #e2e8f0", color: "#334155", borderRadius: 10, padding: "10px", fontSize: 13, fontWeight: 600, cursor: "pointer" }}>
                Update Status
              </button>
            </div>
          </div>
        ))}
      </div>

      {/* Status update bottom sheet */}
      {selected && (
        <div style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.5)", zIndex: 50, display: "flex", alignItems: "flex-end" }}
          onClick={e => { if (e.target === e.currentTarget) setSelected(null); }}>
          <div style={{ width: "100%", maxWidth: 430, margin: "0 auto", background: "#fff", borderRadius: "20px 20px 0 0", padding: "24px 20px 40px" }}>
            <div style={{ width: 40, height: 4, background: "#e2e8f0", borderRadius: 2, margin: "0 auto 20px" }} />
            <h3 style={{ margin: "0 0 4px", fontSize: 17, fontWeight: 700, color: "#1e293b" }}>Update Status</h3>
            <p style={{ margin: "0 0 20px", fontSize: 12, color: "#94a3b8", fontFamily: "monospace" }}>{selected.complaint_id}</p>

            <div style={{ marginBottom: 16 }}>
              <label style={lbl}>New Status</label>
              <select value={newStatus} onChange={e => setNewStatus(e.target.value as ComplaintStatus)} style={inp()}>
                {(["pending","actioned","rejected","duplicate"] as ComplaintStatus[]).map(s => (
                  <option key={s} value={s} style={{ textTransform: "capitalize" }}>{s}</option>
                ))}
              </select>
            </div>

            {newStatus === "rejected" && (
              <div style={{ marginBottom: 16 }}>
                <label style={lbl}>Rejection Reason</label>
                <select value={reason} onChange={e => setReason(e.target.value)} style={inp()}>
                  <option value="">Select reason...</option>
                  {REJECTION_REASONS.map(r => <option key={r}>{r}</option>)}
                </select>
              </div>
            )}

            <div style={{ display: "flex", gap: 10 }}>
              <button onClick={() => setSelected(null)} style={{ flex: 1, background: "#f1f5f9", color: "#64748b", border: "none", borderRadius: 12, padding: 14, fontWeight: 600, cursor: "pointer", fontSize: 15 }}>
                Cancel
              </button>
              <button onClick={doUpdate} disabled={updating} style={{ flex: 2, background: "#1d4ed8", color: "#fff", border: "none", borderRadius: 12, padding: 14, fontWeight: 700, cursor: "pointer", fontSize: 15, opacity: updating ? 0.6 : 1 }}>
                {updating ? "Saving..." : "Save"}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

const lbl: React.CSSProperties = { display: "block", fontSize: 13, fontWeight: 600, color: "#475569", marginBottom: 8 };
const inp = (): React.CSSProperties => ({ width: "100%", border: "1.5px solid #e2e8f0", borderRadius: 12, padding: "13px 14px", fontSize: 15, outline: "none", background: "#fff", appearance: "none" as const });