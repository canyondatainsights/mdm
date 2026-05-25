import type { Metadata } from "next";
import { SharedConversation } from "@/components/SharedConversation";

const API = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api";
const cap = (s?: string | null) => (s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ") : "");

async function fetchShared(token: string): Promise<Record<string, unknown> | null> {
  try {
    const res = await fetch(`${API}/share/${token}`, { cache: "no-store" });
    return res.ok ? await res.json() : null;
  } catch {
    return null;
  }
}

export async function generateMetadata({ params }: { params: Promise<{ token: string }> }): Promise<Metadata> {
  const { token } = await params;
  const c = await fetchShared(token);
  const title = c?.title ? `${c.title} · Sidecar` : "Shared conversation · Sidecar";
  const stack = c ? [c.mdm_vendor as string, c.data_platform as string].filter(Boolean).map(cap).join(" · ") : "";
  const description = c
    ? `A shared Sidecar knowledge conversation${stack ? ` — ${stack}` : ""}.`
    : "A shared, read-only Sidecar conversation.";

  return {
    title,
    description,
    openGraph: { title, description, siteName: "Sidecar", type: "article" },
    twitter: { card: "summary_large_image", title, description },
    robots: { index: false, follow: false }, // shared transcripts shouldn't be search-indexed
  };
}

export default async function Page({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  return <SharedConversation token={token} />;
}
