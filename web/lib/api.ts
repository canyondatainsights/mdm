import type {
  Conversation,
  Dimensions,
  Message,
  SettingsInfo,
  SourceDetail,
  StreamEvent,
  User,
  WikiPageDetail,
  WikiPageSummary,
} from "./types";

const BASE = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api";
const TOKEN_KEY = "mdm_token";

/** API host root (BASE without the trailing /api) — used to resolve root-relative media URLs. */
export const apiOrigin = BASE.replace(/\/api\/?$/, "");

export const auth = {
  token: (): string | null => (typeof window === "undefined" ? null : localStorage.getItem(TOKEN_KEY)),
  set: (t: string) => localStorage.setItem(TOKEN_KEY, t),
  clear: () => localStorage.removeItem(TOKEN_KEY),
};

async function req<T>(path: string, opts: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    ...(opts.headers as Record<string, string>),
  };
  if (!(opts.body instanceof FormData)) headers["Content-Type"] = "application/json";
  const token = auth.token();
  if (token) headers["Authorization"] = `Bearer ${token}`;

  const res = await fetch(`${BASE}${path}`, { ...opts, headers });
  if (!res.ok) {
    let msg = `HTTP ${res.status}`;
    try {
      const j = await res.json();
      msg = j.message || JSON.stringify(j.errors ?? j);
    } catch {}
    throw new Error(msg);
  }
  return res.status === 204 ? (undefined as T) : ((await res.json()) as T);
}

export const api = {
  // auth
  login: (email: string, password: string) =>
    req<{ token: string; user: User }>("/auth/login", { method: "POST", body: JSON.stringify({ email, password }) }),
  register: (body: { name: string; email: string; password: string; title?: string }) =>
    req<{ token: string; user: User }>("/auth/register", { method: "POST", body: JSON.stringify(body) }),
  me: () => req<User>("/auth/me"),
  logout: () => req("/auth/logout", { method: "POST" }),

  // meta
  dimensions: () => req<Dimensions>("/meta/dimensions"),
  stats: () => req<Record<string, unknown>>("/meta/stats"),

  // conversations
  conversations: () => req<Conversation[]>("/conversations"),
  createConversation: (body: Partial<Conversation>) =>
    req<Conversation>("/conversations", { method: "POST", body: JSON.stringify(body) }),
  conversation: (id: number) => req<Conversation & { messages: Message[] }>(`/conversations/${id}`),
  suggestions: (id: number) => req<{ questions: string[] }>(`/conversations/${id}/suggestions`),
  updateConversation: (id: number, body: { title?: string; pinned?: boolean }) =>
    req<Conversation>(`/conversations/${id}`, { method: "PATCH", body: JSON.stringify(body) }),
  shareConversation: (id: number) => req<{ token: string }>(`/conversations/${id}/share`, { method: "POST" }),
  unshareConversation: (id: number) => req<{ ok: boolean }>(`/conversations/${id}/share`, { method: "DELETE" }),
  sharedConversation: (token: string) => req<import("./types").SharedConversation>(`/share/${token}`),
  deleteConversation: (id: number) => req(`/conversations/${id}`, { method: "DELETE" }),

  // sources
  sources: (params?: import("./types").SourcesQuery) => {
    const qs = params
      ? "?" + new URLSearchParams(
          Object.entries(params)
            .filter(([, v]) => v !== undefined && v !== null && v !== "")
            .map(([k, v]) => [k, String(v)]),
        ).toString()
      : "";
    return req<import("./types").SourcesResponse>(`/sources${qs}`);
  },
  source: (path: string) => req<SourceDetail>(`/sources/${path}`),

  // wiki (browse curated knowledge)
  wikiPages: () => req<{ count: number; pages: WikiPageSummary[] }>("/wiki"),
  wikiPage: (path: string) => req<WikiPageDetail>(`/wiki/${path}`),
  classifyUploads: (form: FormData) =>
    req<import("./types").ClassifyResult>("/uploads/classify", { method: "POST", body: form }),
  upload: (form: FormData) =>
    req<{
      ok: boolean;
      queued: number;
      skipped: number;
      files: { path?: string; url?: string; name?: string; status: string; by?: string | null; existing?: { id: number; title: string | null; path: string } | null }[];
    }>("/uploads", { method: "POST", body: form }),
  uploadStatus: (paths: string[]) =>
    req<{ statuses: Record<string, { status: string; needs_metadata: boolean; chunks: number }> }>(
      "/uploads/status",
      { method: "POST", body: JSON.stringify({ paths }) },
    ),

  // exports
  exportXlsx: async (messageId: number): Promise<Blob> => {
    const token = auth.token();
    const res = await fetch(`${BASE}/exports/xlsx`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: JSON.stringify({ message_id: messageId }),
    });
    if (!res.ok) throw new Error(`Export failed: HTTP ${res.status}`);
    return res.blob();
  },

  // settings (admin)
  settings: () => req<SettingsInfo>("/settings"),
  updateKey: (anthropic_api_key: string) =>
    req<{ ok: boolean; hint: string }>("/settings", { method: "PUT", body: JSON.stringify({ anthropic_api_key }) }),
  testKey: (anthropic_api_key?: string) =>
    req<{ ok: boolean; message: string }>("/settings/test-key", {
      method: "POST",
      body: JSON.stringify(anthropic_api_key ? { anthropic_api_key } : {}),
    }),

  // research topics
  researchTopics: () => req<import("./types").ResearchTopic[]>("/research-topics"),
  createResearchTopic: (body: Partial<import("./types").ResearchTopic>) =>
    req<import("./types").ResearchTopic>("/research-topics", { method: "POST", body: JSON.stringify(body) }),
  updateResearchTopic: (id: number, body: Partial<import("./types").ResearchTopic>) =>
    req<import("./types").ResearchTopic>(`/research-topics/${id}`, { method: "PATCH", body: JSON.stringify(body) }),
  deleteResearchTopic: (id: number) => req(`/research-topics/${id}`, { method: "DELETE" }),
  deepDiveTopic: (id: number) =>
    req<{ conversation_id: number; seed: string }>(`/research-topics/${id}/deep-dive`, { method: "POST" }),

  // stewardship
  stewardship: () => req<StewardshipTask[]>("/stewardship/tasks"),
  requestCrawl: (url: string, note?: string) =>
    req<{ ok: boolean; task_id: number }>("/stewardship/requests", { method: "POST", body: JSON.stringify({ url, note }) }),
  approveTask: (id: number) => req<{ ok: boolean }>(`/stewardship/tasks/${id}/approve`, { method: "POST" }),
  rejectTask: (id: number) => req<{ ok: boolean }>(`/stewardship/tasks/${id}/reject`, { method: "POST" }),
};

export interface StewardshipTask {
  id: number;
  type: string;
  summary: string;
  status: "pending" | "approved" | "rejected";
  target_path: string | null;
  created_at: string;
  proposer?: { id: number; name: string } | null;
}

/** Stream an assistant reply over SSE (POST + ReadableStream). */
export async function streamMessage(
  conversationId: number,
  message: string,
  onEvent: (e: StreamEvent) => void,
  signal?: AbortSignal,
): Promise<void> {
  const token = auth.token();
  const res = await fetch(`${BASE}/conversations/${conversationId}/messages`, {
    method: "POST",
    signal,
    headers: {
      "Content-Type": "application/json",
      Accept: "text/event-stream",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ message }),
  });

  if (!res.ok || !res.body) {
    throw new Error(`Stream failed: HTTP ${res.status}`);
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });

    const frames = buffer.split("\n\n");
    buffer = frames.pop() ?? "";
    for (const frame of frames) {
      const line = frame.split("\n").find((l) => l.startsWith("data:"));
      if (!line) continue;
      const data = line.slice(5).trim();
      if (data === "[DONE]") return;
      try {
        onEvent(JSON.parse(data) as StreamEvent);
      } catch {
        // ignore malformed frame
      }
    }
  }
}
