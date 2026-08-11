export type ViolationType =
  | "signal_jump" | "wrong_side" | "no_helmet" | "triple_riding"
  | "phone_while_driving" | "no_seatbelt" | "overloading" | "others";

export const VIOLATION_LABELS: Record<ViolationType, string> = {
  signal_jump:        "Signal Jump",
  wrong_side:         "Wrong Side Driving",
  no_helmet:          "No Helmet",
  triple_riding:      "Triple Riding",
  phone_while_driving:"Phone While Driving",
  no_seatbelt:        "No Seatbelt",
  overloading:        "Overloading",
  others:             "Others",
};

export type ComplaintStatus =
  | "submitted" | "under_review" | "actioned" | "rejected" | "duplicate" | "pending";

export interface Complaint {
  id: number;
  complaint_id: string;
  vehicle_number: string;
  violation_type: ViolationType;
  status: ComplaintStatus;
  reported_at: string;
  location_lat: number;
  location_lng: number;
  area_state: string;
  area_district: string;
  // Present on the police list endpoint (GET /police/complaints).
  is_flagged?: boolean;
  evidence_count?: number;
  // Present on the detail/track endpoints (GET /track/{id}, /complaints/{id}).
  evidence_files?: EvidenceFile[];
  status_logs?: StatusLog[];
}

export interface EvidenceFile {
  id: string;
  file_type: "photo" | "video";
  thumbnail_url: string | null;
  file_url: string;
  uploaded_at: string;
}

export interface StatusLog {
  old_status: ComplaintStatus;
  new_status: ComplaintStatus;
  reason?: string;
  changed_at: string;
}