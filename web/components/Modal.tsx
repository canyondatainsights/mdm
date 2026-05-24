"use client";

import { IconButton } from "./ui";
import type { ReactNode } from "react";

export function Modal({
  title, onClose, children, width = 460,
}: {
  title: string; onClose: () => void; children: ReactNode; width?: number;
}) {
  return (
    <div
      onClick={onClose}
      style={{
        position: "fixed", inset: 0, background: "oklch(0.2 0.02 50 / 0.35)",
        display: "flex", alignItems: "center", justifyContent: "center", zIndex: 50, padding: 20,
      }}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        style={{
          width, maxWidth: "100%", maxHeight: "90vh", overflow: "auto",
          background: "var(--panel)", border: "1px solid var(--border)",
          borderRadius: 14, boxShadow: "var(--shadow)",
        }}
      >
        <div style={{ display: "flex", alignItems: "center", padding: "14px 16px", borderBottom: "1px solid var(--border)" }}>
          <span style={{ fontSize: 14, fontWeight: 600 }}>{title}</span>
          <div style={{ marginLeft: "auto" }}>
            <IconButton icon="close" label="Close" onClick={onClose} />
          </div>
        </div>
        <div style={{ padding: 16 }}>{children}</div>
      </div>
    </div>
  );
}
