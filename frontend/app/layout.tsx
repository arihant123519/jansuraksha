import type { Metadata } from "next";
import "./globals.css";
import { Toaster } from "react-hot-toast";

export const metadata: Metadata = {
  title: "JanSuraksha",
  description: "Citizen Challan Portal",
  manifest: "/manifest.json",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body style={{ margin: 0, fontFamily: "system-ui, sans-serif", background: "#f8fafc" }}>
        <Toaster position="top-center" toastOptions={{ style: { fontSize: 14 } }} />
        {children}
      </body>
    </html>
  );
}