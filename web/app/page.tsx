"use client";

import { api, auth } from "@/lib/api";
import type { Conversation, Message, User } from "@/lib/types";
import { useCallback, useEffect, useState } from "react";
import { BrowseModal } from "@/components/BrowseModal";
import { ChatArea } from "@/components/ChatArea";
import { Inspector } from "@/components/Inspector";
import { Login } from "@/components/Login";
import { SettingsModal } from "@/components/SettingsModal";
import { Sidebar } from "@/components/Sidebar";
import { StackLockModal } from "@/components/StackLockModal";
import { UploadModal } from "@/components/UploadModal";

export default function Home() {
  const [user, setUser] = useState<User | null>(null);
  const [booted, setBooted] = useState(false);

  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [active, setActive] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);

  const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
  const [inspectorOpen, setInspectorOpen] = useState(false);
  const [openSource, setOpenSource] = useState<string | null>(null);

  const [modal, setModal] = useState<null | "stacklock" | "settings" | "browse" | "upload">(null);
  const [browseView, setBrowseView] = useState("sources");
  const [navView, setNavView] = useState("chat");

  // boot: restore session
  useEffect(() => {
    if (!auth.token()) { setBooted(true); return; }
    api.me().then(setUser).catch(() => auth.clear()).finally(() => setBooted(true));
  }, []);

  const refreshConversations = useCallback(() => {
    api.conversations().then(setConversations).catch(() => {});
  }, []);

  useEffect(() => { if (user) refreshConversations(); }, [user, refreshConversations]);

  const selectConversation = useCallback(async (id: number) => {
    setNavView("chat");
    const c = await api.conversation(id);
    setActive(c);
    setMessages(c.messages ?? []);
  }, []);

  const removeConversation = useCallback(async (id: number) => {
    try { await api.deleteConversation(id); } catch { /* ignore */ }
    setConversations((prev) => prev.filter((c) => c.id !== id));
    if (active?.id === id) { setActive(null); setMessages([]); }
  }, [active]);

  const onCreated = (c: Conversation) => {
    setModal(null);
    setConversations((prev) => [c, ...prev]);
    setActive(c);
    setMessages([]);
    setNavView("chat");
  };

  const onNav = (key: string) => {
    if (key === "chat") { setNavView("chat"); return; }
    if (key === "settings") { setModal("settings"); return; }
    setBrowseView(key);
    setNavView(key);
    setModal("browse");
  };

  const openSourceInInspector = (path: string) => {
    setOpenSource(path);
    setInspectorOpen(true);
    setModal(null);
  };

  const logout = async () => {
    try { await api.logout(); } catch {}
    auth.clear();
    setUser(null);
    setConversations([]);
    setActive(null);
    setMessages([]);
  };

  if (!booted) return <div style={{ height: "100%" }} />;
  if (!user) return <Login onAuthed={setUser} />;

  const canUpload = user.roles.includes("Admin") || user.roles.includes("Steward");
  const openUpload = canUpload ? () => setModal("upload") : undefined;

  return (
    <div style={{ height: "100%", display: "flex", background: "var(--bg)" }}>
      {!sidebarCollapsed && (
        <Sidebar
          user={user}
          conversations={conversations}
          activeId={active?.id ?? null}
          onSelect={selectConversation}
          onNew={() => setModal("stacklock")}
          onNav={onNav}
          onLogout={logout}
          view={navView}
          onDelete={removeConversation}
        />
      )}

      <ChatArea
        conversation={active}
        initialMessages={messages}
        onOpenSource={openSourceInInspector}
        onToggleSidebar={() => setSidebarCollapsed((c) => !c)}
        sidebarCollapsed={sidebarCollapsed}
        onToggleInspector={() => setInspectorOpen((o) => !o)}
        inspectorOpen={inspectorOpen}
        onChanged={refreshConversations}
        onNeedKey={() => { if (user.roles.includes("Admin")) setModal("settings"); }}
        onNew={() => setModal("stacklock")}
        onAttach={openUpload}
      />

      {inspectorOpen && <Inspector path={openSource} onClose={() => setInspectorOpen(false)} />}

      {modal === "stacklock" && <StackLockModal onClose={() => setModal(null)} onCreated={onCreated} />}
      {modal === "settings" && <SettingsModal onClose={() => setModal(null)} />}
      {modal === "browse" && (
        <BrowseModal view={browseView} user={user} onClose={() => { setModal(null); setNavView("chat"); }} onOpenSource={openSourceInInspector} onOpenUpload={openUpload} />
      )}
      {modal === "upload" && <UploadModal onClose={() => setModal(null)} />}
    </div>
  );
}
