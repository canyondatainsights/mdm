"use client";

import { Modal } from "./Modal";
import type { ReactNode } from "react";

/**
 * Design-system confirmation dialog — replaces native window.confirm(). Built on Modal so it
 * inherits the panel chrome, backdrop, and tokens. `danger` tone gives a destructive coral-red
 * action; Cancel is auto-focused so a stray Enter never confirms a destructive action.
 */
export function ConfirmDialog({
  title,
  message,
  confirmLabel = "Delete",
  cancelLabel = "Cancel",
  tone = "danger",
  onConfirm,
  onCancel,
}: {
  title: string;
  message: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  tone?: "danger" | "accent";
  onConfirm: () => void;
  onCancel: () => void;
}) {
  const danger = tone === "danger";
  return (
    <Modal title={title} onClose={onCancel} width={400}>
      <div style={{ fontSize: 13, color: "var(--fg-2)", lineHeight: 1.5 }}>{message}</div>
      <div style={{ display: "flex", justifyContent: "flex-end", gap: 8, marginTop: 18 }}>
        <button
          onClick={onCancel}
          autoFocus
          style={{
            padding: "7px 14px", borderRadius: 8, fontSize: 13, fontWeight: 500,
            background: "var(--panel)", color: "var(--fg-2)",
            border: "1px solid var(--border)", boxShadow: "var(--shadow-sm)",
          }}
        >
          {cancelLabel}
        </button>
        <button
          onClick={onConfirm}
          style={{
            padding: "7px 14px", borderRadius: 8, fontSize: 13, fontWeight: 600, color: "white",
            border: danger ? "1px solid oklch(0.50 0.18 25)" : "1px solid oklch(0.48 0.18 33)",
            background: danger
              ? "linear-gradient(180deg, oklch(0.62 0.20 25), oklch(0.52 0.21 25))"
              : "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))",
            boxShadow: "0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 6px 16px -6px oklch(0.48 0.18 33 / 0.55)",
          }}
        >
          {confirmLabel}
        </button>
      </div>
    </Modal>
  );
}
