"use client";

import { api } from "@/lib/api";
import type { Conversation, Dimensions } from "@/lib/types";
import { useEffect, useState } from "react";
import { Modal } from "./Modal";

const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1).replace(/-/g, " ");

export function StackLockModal({
  onClose, onCreated,
}: {
  onClose: () => void; onCreated: (c: Conversation) => void;
}) {
  const [dims, setDims] = useState<Dimensions | null>(null);
  const [vendor, setVendor] = useState("informatica");
  const [platform, setPlatform] = useState("databricks");
  const [financial, setFinancial] = useState("");
  const [domains, setDomains] = useState<string[]>(["customer"]);
  const [extensions, setExtensions] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    api.dimensions().then(setDims).catch(() => {});
  }, []);

  const toggleDomain = (d: string) =>
    setDomains((prev) => (prev.includes(d) ? prev.filter((x) => x !== d) : [...prev, d]));
  const toggleExtension = (e: string) =>
    setExtensions((prev) => (prev.includes(e) ? prev.filter((x) => x !== e) : [...prev, e]));

  const create = async () => {
    setBusy(true);
    setErr(null);
    try {
      const c = await api.createConversation({
        mdm_vendor: vendor,
        data_platform: platform,
        financial_model: financial || null,
        domains: domains.length ? domains : ["general"],
        extensions: extensions.length ? extensions : null,
      } as Partial<Conversation>);
      onCreated(c);
    } catch (e) {
      setErr((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const Group = ({ label, children }: { label: string; children: React.ReactNode }) => (
    <div style={{ marginBottom: 14 }}>
      <div style={{ fontSize: 11, fontWeight: 600, color: "var(--fg-3)", textTransform: "uppercase", letterSpacing: "0.07em", marginBottom: 7 }}>{label}</div>
      <div style={{ display: "flex", flexWrap: "wrap", gap: 6 }}>{children}</div>
    </div>
  );

  const Chip = ({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) => (
    <button
      onClick={onClick}
      style={{
        padding: "6px 11px", borderRadius: 999, fontSize: 12.5, fontWeight: 500,
        background: active ? "var(--accent-soft)" : "var(--panel)",
        color: active ? "var(--accent-2)" : "var(--fg-2)",
        border: `1px solid ${active ? "var(--accent-border)" : "var(--border)"}`,
      }}
    >
      {children}
    </button>
  );

  return (
    <Modal title="Lock your technology stack" onClose={onClose} width={520}>
      <p style={{ fontSize: 12.5, color: "var(--fg-3)", marginTop: 0, marginBottom: 16, lineHeight: 1.5 }}>
        Answers in this conversation are restricted to the stack you choose. Vendors and platforms are never mixed — e.g. Databricks and Snowflake, or Informatica and SAP, cannot appear together. Start a new conversation to switch stacks.
      </p>

      <Group label="MDM vendor">
        {(dims?.mdm_vendor ?? ["informatica"]).map((v) => (
          <Chip key={v} active={vendor === v} onClick={() => setVendor(v)}>{cap(v)}</Chip>
        ))}
      </Group>

      <Group label="Data platform">
        {(dims?.data_platform ?? ["databricks", "snowflake"]).map((p) => (
          <Chip key={p} active={platform === p} onClick={() => setPlatform(p)}>{cap(p)}</Chip>
        ))}
      </Group>

      <Group label="Financial data model (optional)">
        <Chip active={financial === ""} onClick={() => setFinancial("")}>None</Chip>
        {(dims?.financial_model ?? ["isda-cdm", "fpml"]).map((f) => (
          <Chip key={f} active={financial === f} onClick={() => setFinancial(f)}>{cap(f)}</Chip>
        ))}
      </Group>

      <Group label="Domain focus">
        {(dims?.domain ?? ["customer", "product", "vendor", "supplier", "finance", "healthcare"])
          .filter((d) => d !== "general")
          .map((d) => (
            <Chip key={d} active={domains.includes(d)} onClick={() => toggleDomain(d)}>{cap(d)}</Chip>
          ))}
      </Group>

      {(dims?.extension?.length ?? 0) > 0 && (
        <Group label="Extensions — verticals & add-ons (optional)">
          {(dims?.extension ?? []).map((e) => (
            <Chip key={e} active={extensions.includes(e)} onClick={() => toggleExtension(e)}>{cap(e)}</Chip>
          ))}
        </Group>
      )}

      {err && <div style={{ color: "var(--danger)", fontSize: 12.5, marginBottom: 10 }}>{err}</div>}

      <button
        onClick={create}
        disabled={busy}
        className="hov-lift"
        style={{
          width: "100%", padding: "10px 12px", borderRadius: 9, color: "white", border: "1px solid oklch(0.48 0.18 33)",
          background: "linear-gradient(180deg, oklch(0.66 0.17 38), oklch(0.56 0.18 33))",
          fontSize: 13, fontWeight: 600, opacity: busy ? 0.6 : 1,
        }}
      >
        {busy ? "Creating…" : "Start conversation"}
      </button>
    </Modal>
  );
}
