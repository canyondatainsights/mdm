export type Role = "Admin" | "Steward" | "Contributor" | "Viewer";

export interface User {
  id: number;
  name: string;
  title?: string | null;
  email: string;
  roles: Role[];
}

export interface Dimensions {
  mdm_vendor: string[];
  data_platform: string[];
  financial_model: string[];
  domain: string[];
  extension?: string[];
  products?: Record<string, string[]>;
}

export interface ClassifySuggestion {
  mdm_vendor: string | null;
  data_platform: string | null;
  product: string | null;
  domain: string | null;
  extension: string | null;
  financial_model: string | null;
  proposed_subject: { value: string; label: string } | null;
  confidence: "high" | "medium" | "low";
  reasoning: string | null;
  error?: string;
}

export interface ClassifyResult {
  files: { filename: string; suggestion: ClassifySuggestion; is_url?: boolean }[];
}

export interface Conversation {
  id: number;
  title: string;
  pinned: boolean;
  mdm_vendor: string | null;
  data_platform: string | null;
  financial_model: string | null;
  domains: string[];
  extensions?: string[] | null;
  pii_redacted: boolean;
  updated_at: string;
}

export interface Citation {
  n: number;
  path: string;
  anchor: string | null;
  title?: string | null;
  origin?: string | null;
  doc_type?: string | null;
  product?: string | null;
  product_version?: string | null;
  date?: string | null;
}

export type Block =
  | { type: "markdown"; text: string }
  | { type: "p"; text: string }
  | { type: "ol"; items: string[] };

export interface Message {
  id: number;
  role: "user" | "assistant";
  content: { text?: string } | Block[];
  citations?: Citation[] | null;
  confidence?: "low" | "medium" | "high" | null;
  created_at?: string;
}

export interface SourceListItem {
  id: string;
  kind: "wiki" | "raw";
  title: string;
  path: string;
  section?: string;
  doc_type: string;
  mdm_vendor: string | null;
  data_platform: string | null;
  domain: string;
  scope: string;
  product?: string | null;
  product_version?: string | null;
  updated?: string | null;
  approved?: boolean;
  needs_metadata?: boolean;
  ingest_status?: string;
}

export interface SourceDetail {
  path: string;
  title: string;
  doc_type: string;
  mdm_vendor: string | null;
  data_platform: string | null;
  financial_model: string | null;
  domain: string | null;
  product: string | null;
  extension: string | null;
  scope: string | null;
  updated: string | null;
  origin: string | null;
  approved: boolean;
  tags: string[];
  excerpt: { anchor: string | null; text: string }[];
  related: { title: string; path: string }[];
  trust: { score: number; level: "low" | "medium" | "high"; factors: { label: string; ok: boolean }[] } | null;
}

export interface SettingsInfo {
  anthropic: { has_key: boolean; hint: string | null; source: string | null; model: string };
  embeddings: { driver: string; dim: number };
}

export type StreamEvent =
  | { type: "meta"; sources_found: number; enrichment: { task_id: number; status: string } | null }
  | { type: "delta"; text: string }
  | { type: "done"; message_id: number; citations: Citation[]; confidence: string }
  | { type: "suggestions"; questions: string[] }
  | { type: "error"; message: string };
