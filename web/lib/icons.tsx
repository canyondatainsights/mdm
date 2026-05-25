"use client";

import {
  ArrowUp, BookMarked, BookOpen, Check, ChevronDown, ChevronRight, Copy, Database, Download,
  ExternalLink, FileText, SlidersHorizontal, Workflow, Globe, History, Link2,
  MoreHorizontal, Network, PanelLeft, PanelRight, Paperclip, Pencil, Pin, PinOff, Plus,
  RotateCw, Search, Send, Settings, Share2, Shield, Sparkles, Square, Tag, ThumbsDown,
  ThumbsUp, X, LogOut, type LucideIcon,
} from "lucide-react";

// Design handoff icon name -> Lucide component.
const MAP: Record<string, LucideIcon> = {
  search: Search, plus: Plus, send: Send, paperclip: Paperclip, sparkle: Sparkles,
  book: BookOpen, wiki: BookMarked, doc: FileText, pin: Pin, "pin-off": PinOff,
  edit: Pencil, share: Share2, panel: PanelRight, sidebar: PanelLeft,
  "chevron-down": ChevronDown, "chevron-right": ChevronRight, check: Check, copy: Copy,
  thumbsup: ThumbsUp, thumbsdown: ThumbsDown, refresh: RotateCw, filter: SlidersHorizontal,
  tag: Tag, shield: Shield, link: Link2, external: ExternalLink, database: Database,
  graph: Network, history: History, more: MoreHorizontal, "arrow-up": ArrowUp,
  flow: Workflow, globe: Globe, settings: Settings, close: X, logout: LogOut,
  stop: Square, download: Download,
};

export function Icon({
  name, size = 16, stroke = 1.6, className, style,
}: {
  name: string; size?: number; stroke?: number; className?: string; style?: React.CSSProperties;
}) {
  const C = MAP[name] ?? Sparkles;
  return <C size={size} strokeWidth={stroke} className={className} style={style} />;
}
