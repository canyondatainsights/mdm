"use client";

import {
  ArrowUp, BookOpen, Check, ChevronDown, ChevronRight, Copy, Database,
  ExternalLink, FileText, SlidersHorizontal, Workflow, Globe, History, Link2,
  MoreHorizontal, Network, PanelLeft, PanelRight, Paperclip, Pin, Plus,
  RotateCw, Search, Send, Settings, Shield, Sparkles, Tag, ThumbsDown,
  ThumbsUp, X, LogOut, type LucideIcon,
} from "lucide-react";

// Design handoff icon name -> Lucide component.
const MAP: Record<string, LucideIcon> = {
  search: Search, plus: Plus, send: Send, paperclip: Paperclip, sparkle: Sparkles,
  book: BookOpen, doc: FileText, pin: Pin, panel: PanelRight, sidebar: PanelLeft,
  "chevron-down": ChevronDown, "chevron-right": ChevronRight, check: Check, copy: Copy,
  thumbsup: ThumbsUp, thumbsdown: ThumbsDown, refresh: RotateCw, filter: SlidersHorizontal,
  tag: Tag, shield: Shield, link: Link2, external: ExternalLink, database: Database,
  graph: Network, history: History, more: MoreHorizontal, "arrow-up": ArrowUp,
  flow: Workflow, globe: Globe, settings: Settings, close: X, logout: LogOut,
};

export function Icon({
  name, size = 16, stroke = 1.6, className, style,
}: {
  name: string; size?: number; stroke?: number; className?: string; style?: React.CSSProperties;
}) {
  const C = MAP[name] ?? Sparkles;
  return <C size={size} strokeWidth={stroke} className={className} style={style} />;
}
