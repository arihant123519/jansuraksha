import axios from "axios";

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api",
  headers: { "Content-Type": "application/json", Accept: "application/json" },
});

api.interceptors.request.use((config) => {
  const token = typeof window !== "undefined" ? localStorage.getItem("token") : null;
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// Normalize errors so callers get a predictable shape, and proactively clear a
// stale token on 401 so the app doesn't keep sending a dead credential.
api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (typeof window !== "undefined" && error?.response?.status === 401) {
      localStorage.removeItem("token");
    }
    if (!error?.response) {
      // No response = network/CORS/server-down. Tag it so the UI can tell the
      // difference between "server said no" and "couldn't reach the server".
      error.isNetworkError = true;
    }
    return Promise.reject(error);
  }
);

// Auth
export const sendOtp      = (mobile: string) => api.post("/auth/otp/send", { mobile });
export const verifyOtp    = (mobile: string, otp: string) => api.post("/auth/otp/verify", { mobile, otp });
export const policeLogin  = (username: string, password: string) => api.post("/auth/police/login", { username, password });
export const logout       = () => api.post("/auth/logout");

// Citizen
export const submitComplaint = (data: FormData) =>
  api.post("/complaints", data, { headers: { "Content-Type": "multipart/form-data" } });
// Authenticated lookup of a complaint (citizen viewing their own).
export const getComplaint = (id: string) => api.get(`/complaints/${id}`);
// Public complaint tracker — no auth required. The /track page uses this so
// that visitors who are not logged in can still look up a complaint by ID.
export const trackComplaint = (id: string) => api.get(`/track/${id}`);
export const getQuota     = () => api.get("/me/quota");

// Police
export const getPoliceComplaints  = (params: Record<string, string>) => api.get("/police/complaints", { params });
export const updateStatus         = (id: string, status: string, reason?: string) =>
  api.patch(`/police/complaints/${id}`, { status, reason });
export const exportCsv = (params: Record<string, string>) =>
  api.get("/police/export/csv", { params, responseType: "blob" });
export const exportPdf = (params: Record<string, string>) =>
  api.get("/police/export/pdf", { params, responseType: "blob" });

export default api;