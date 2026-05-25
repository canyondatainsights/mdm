import { ImageResponse } from "next/og";

export const alt = "Shared Sidecar conversation";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

const API = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api";
const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");

export default async function Image({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  let title = "Shared conversation";
  let stack = "";
  try {
    const r = await fetch(`${API}/share/${token}`, { cache: "no-store" });
    if (r.ok) {
      const c = await r.json();
      title = (c.title as string) || title;
      stack = [c.mdm_vendor, c.data_platform, c.financial_model].filter(Boolean).map(cap).join("  ·  ");
    }
  } catch {
    /* fall back to defaults */
  }

  return new ImageResponse(
    (
      <div
        style={{
          width: "100%", height: "100%", display: "flex", flexDirection: "column", justifyContent: "space-between",
          background: "linear-gradient(135deg, #fbf6f0 0%, #f6e6db 100%)", padding: "72px 80px", fontFamily: "sans-serif",
        }}
      >
        {/* brand row */}
        <div style={{ display: "flex", alignItems: "center" }}>
          <div style={{ width: 56, height: 48, background: "#e2613e", borderRadius: 16, display: "flex" }} />
          <div style={{ marginLeft: 22, fontSize: 40, fontWeight: 800, color: "#2b2320", letterSpacing: -1 }}>Sidecar</div>
        </div>

        {/* title */}
        <div style={{ display: "flex", flexDirection: "column" }}>
          <div style={{ fontSize: 64, fontWeight: 700, color: "#2b2320", lineHeight: 1.1, maxWidth: 1040 }}>{title}</div>
          {stack ? <div style={{ marginTop: 24, fontSize: 30, color: "#9a5b3f", fontWeight: 600 }}>{stack}</div> : <div style={{ display: "flex" }} />}
        </div>

        {/* footer */}
        <div style={{ display: "flex", alignItems: "center", fontSize: 26, color: "#7c6f66" }}>
          Read-only shared conversation · vendor-isolated knowledge
        </div>
      </div>
    ),
    { ...size },
  );
}
