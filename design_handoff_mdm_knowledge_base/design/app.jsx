// App root

const App = () => {
  const [activeId, setActiveId] = React.useState('c1');
  const [inspectorOpen, setInspectorOpen] = React.useState(true);
  const [openSource, setOpenSource] = React.useState('s2');
  const [sidebarCollapsed, setSidebarCollapsed] = React.useState(false);

  const handleOpenSource = (id) => {
    setOpenSource(id);
    setInspectorOpen(true);
  };

  return (
    <div style={{ height: '100%', display: 'flex', background: 'var(--bg)' }}>
      <Sidebar activeId={activeId} onSelect={setActiveId} collapsed={sidebarCollapsed}/>
      <ChatArea
        onOpenSource={handleOpenSource}
        onToggleInspector={() => setInspectorOpen(o => !o)}
        inspectorOpen={inspectorOpen}
        onToggleSidebar={() => setSidebarCollapsed(c => !c)}
        sidebarCollapsed={sidebarCollapsed}
      />
      {inspectorOpen && <Inspector openId={openSource} onClose={() => setInspectorOpen(false)}/>}
      <ThemeSwitcher/>
    </div>
  );
};

// Mount after dependent scripts load
const mount = () => {
  if (!window.MDM_DATA || !window.Sidebar || !window.ChatArea || !window.Inspector || !window.ThemeSwitcher) {
    setTimeout(mount, 30);
    return;
  }
  ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
};
mount();
