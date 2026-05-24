// Sidecar — Bubble Direction Deep Dive
// Sections: Type · Mark Variations · Color Systems · Recommended Combos

// =============================================================
// SHARED BUILDING BLOCKS
// =============================================================
const Lockup = ({ Mark, palette, tf = 'manrope', wordmarkSize = 60, sloganProps = {}, vertical = false, gap = 22, onDark = false }) => {
  const color = onDark ? palette.bg : palette.ink;
  const sloganColor = onDark ? 'rgba(255,255,255,0.62)' : palette.inkSoft;
  return (
    <div style={{
      display: 'flex',
      flexDirection: vertical ? 'column' : 'row',
      alignItems: vertical ? 'flex-start' : 'center',
      gap,
    }}>
      <Mark palette={palette} color={onDark ? palette.bg : undefined} accent={palette.accent}/>
      <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 8 }}>
        <Wordmark size={wordmarkSize} color={color} accent={palette.accent} tf={tf}/>
        <Slogan {...sloganProps} color={sloganColor} palette={palette}/>
      </div>
    </div>
  );
};

// =============================================================
// SECTION 1 — TYPE EXPLORATIONS
// =============================================================

const TypeIntro = () => (
  <Frame bg={DEFAULT_P.bg} pad={36}>
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
      <div>
        <span style={{ fontFamily:"'Manrope',sans-serif", fontSize: 24, fontWeight: 700, color: DEFAULT_P.ink, letterSpacing:'-0.02em' }}>
          Typeface
        </span>
        <div style={{ marginTop: 6, fontFamily:"'Manrope',sans-serif", fontSize: 13, color: DEFAULT_P.inkSoft, maxWidth: 480 }}>
          Six contenders for the wordmark. Each renders the dotless-i with a coral disc, so the brand mechanic stays consistent — only the letterform shifts.
        </div>
      </div>
      <TagChip>6 typefaces</TagChip>
    </div>
    <div style={{
      marginTop: 24, padding: '20px 24px',
      background: DEFAULT_P.cream, borderRadius: 14,
      border: `1px solid ${DEFAULT_P.inkFaint}`,
      display: 'flex', alignItems: 'center', gap: 32, flex: 1,
    }}>
      <BubbleDefault size={120}/>
      <div style={{ flex: 1, display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 6 }}>
        {TYPEFACES.map(t => (
          <span key={t.id} style={{
            fontFamily:"'JetBrains Mono',monospace", fontSize: 11,
            color: DEFAULT_P.inkMute, letterSpacing:'0.04em',
          }}>· {t.family}</span>
        ))}
      </div>
    </div>
  </Frame>
);

const TypeRow = ({ tf }) => {
  const T = TF(tf);
  return (
    <Frame bg={DEFAULT_P.bg2} pad={32}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <span style={{ fontFamily:"'JetBrains Mono',monospace", fontSize: 10, color: DEFAULT_P.inkMute, letterSpacing:'0.10em', textTransform:'uppercase' }}>
            {T.family} · {T.weight}
          </span>
          <span style={{ fontFamily:"'Manrope',sans-serif", fontSize: 12, color: DEFAULT_P.inkSoft, fontStyle: 'italic' }}>
            {T.blurb}
          </span>
        </div>
        <TagChip>{T.id}</TagChip>
      </div>
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 28 }}>
        <BubbleDefault size={110}/>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <Wordmark size={72} tf={tf}/>
          <Slogan size={16} tf={tf} variant="fetch"/>
        </div>
      </div>
    </Frame>
  );
};

// =============================================================
// SECTION 2 — MARK VARIATIONS
// =============================================================

const MarkVariationCard = ({ variant, focus = false }) => {
  const { Comp, name, blurb } = variant;
  return (
    <Frame bg={focus ? DEFAULT_P.cream : DEFAULT_P.bg} pad={32}
           style={focus ? { border: `1px solid ${DEFAULT_P.accent}`, boxShadow: `0 0 0 4px oklch(0.97 0.04 35)` } : { border: `1px solid ${DEFAULT_P.inkFaint}` }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
          <span style={{ fontFamily:"'Manrope',sans-serif", fontSize: 13, fontWeight: 600, color: DEFAULT_P.ink, letterSpacing:'-0.01em' }}>{name}</span>
          <span style={{ fontFamily:"'Manrope',sans-serif", fontSize: 12, color: DEFAULT_P.inkSoft, lineHeight: 1.4 }}>{blurb}</span>
        </div>
        {focus ? <TagChip color={DEFAULT_P.accentDeep} border={DEFAULT_P.accent}>Current</TagChip> : null}
      </div>
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <Comp size={160}/>
      </div>
      {/* sizes */}
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-around', borderTop: `1px solid ${DEFAULT_P.inkFaint}`, paddingTop: 14 }}>
        <Comp size={64}/>
        <Comp size={36}/>
        <Comp size={20}/>
      </div>
    </Frame>
  );
};

// =============================================================
// SECTION 3 — COLOR SYSTEMS
// =============================================================

const PaletteHero = ({ palette, variant = BubbleDefault, recommended = false }) => {
  const P = palette;
  const Comp = variant;
  return (
    <Frame bg={P.bg} palette={P} pad={36}
           style={recommended ? { border: `1px solid ${P.accent}`, boxShadow: `0 0 0 4px ${P.accent.replace(')', ' / 0.10)')}` } : {}}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <div>
          <span style={{ fontFamily:"'Manrope',sans-serif", fontSize: 16, fontWeight: 600, color: P.ink, letterSpacing:'-0.01em' }}>{P.name}</span>
          <div style={{ marginTop: 3, fontFamily:"'Manrope',sans-serif", fontSize: 12, color: P.inkSoft }}>{P.blurb}</div>
        </div>
        {recommended ? <TagChip color={P.accentDeep} border={P.accent} palette={P}>Recommended</TagChip> : null}
      </div>

      {/* Lockup */}
      <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '12px 0' }}>
        <div style={{ display:'flex', alignItems:'center', gap: 22 }}>
          <Comp palette={P} size={120}/>
          <div style={{ display:'flex', flexDirection:'column', gap: 8 }}>
            <Wordmark size={54} color={P.ink} accent={P.accent}/>
            <Slogan palette={P} size={14} variant="fetch"/>
          </div>
        </div>
      </div>

      {/* Swatches */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 6 }}>
        {[
          { name: 'bg',     val: P.bg },
          { name: 'ink',    val: P.ink },
          { name: 'accent', val: P.accent },
          { name: 'deep',   val: P.accentDeep },
          { name: 'dark',   val: P.dark },
        ].map(s => (
          <div key={s.name} style={{
            background: s.val,
            border: s.name === 'bg' ? `1px solid ${P.inkFaint}` : 'none',
            height: 40, borderRadius: 6,
            display: 'flex', alignItems: 'flex-end', padding: 6,
          }}>
            <span style={{
              fontFamily: "'JetBrains Mono', monospace", fontSize: 9,
              color: s.name === 'bg' ? P.inkMute : 'rgba(255,255,255,0.85)',
              letterSpacing: '0.05em', textTransform: 'uppercase',
            }}>{s.name}</span>
          </div>
        ))}
      </div>
    </Frame>
  );
};

const PaletteAppIcon = ({ palette, variant = BubbleDefault }) => {
  const P = palette;
  const Comp = variant;
  return (
    <Frame bg={P.dark} pad={28}>
      <TagChip color="rgba(255,255,255,0.6)" border="rgba(255,255,255,0.18)" palette={P}>
        APP ICON · {P.name.split(' & ')[0].toUpperCase()}
      </TagChip>
      <div style={{ flex:1, display:'flex', alignItems:'center', justifyContent:'center' }}>
        <div style={{
          width: 150, height: 150, borderRadius: 34,
          background: `linear-gradient(160deg, ${P.accent}, ${P.accentDeep})`,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
          boxShadow: '0 24px 60px -20px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.25)',
        }}>
          <Comp palette={P} size={108} color={P.bg} accent={P.ink}/>
        </div>
      </div>
      <div style={{ textAlign: 'center', fontFamily: "'Manrope',sans-serif", fontSize: 13, color: P.bg, fontWeight: 500, letterSpacing: '-0.01em' }}>
        Sidecar
      </div>
    </Frame>
  );
};

// =============================================================
// SECTION 4 — RECOMMENDED COMBOS
// =============================================================

const HeroCombo = ({ palette, variant, tf, blurb, label }) => {
  const P = palette;
  const Comp = variant;
  return (
    <Frame bg={P.bg} pad={48} palette={P}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
        <TagChip palette={P}>{label}</TagChip>
        <span style={{ fontFamily:"'JetBrains Mono',monospace", fontSize: 10, color: P.inkMute, letterSpacing:'0.08em', textTransform:'uppercase' }}>
          {P.name.split(' & ')[0]} · {TF(tf).family} · {variant === BubbleDefault ? 'Default' : variant === BubbleRound ? 'Round' : variant === BubbleStacked ? 'Stacked' : variant === BubbleConjoined ? 'Conjoined' : 'Comic'}
        </span>
      </div>
      <div style={{ flex:1, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 32 }}>
        <Comp palette={P} size={180}/>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <Wordmark size={96} tf={tf} color={P.ink} accent={P.accent}/>
          <Slogan size={20} variant="fetch" tf={tf} palette={P}/>
        </div>
      </div>
      <div style={{
        marginTop: 12, padding: '14px 18px',
        background: P.cream, borderRadius: 10,
        border: `1px solid ${P.inkFaint}`,
        fontFamily:"'Manrope',sans-serif", fontSize: 13, color: P.inkSoft, lineHeight: 1.5,
      }}>{blurb}</div>
    </Frame>
  );
};

// =============================================================
// LAYOUT
// =============================================================
const App = () => (
  <DesignCanvas>

    {/* ============ INTRO ============ */}
    <DCSection id="intro" title="Sidecar — refined" subtitle="Two polished concepts based on your references, plus the broader exploration">
      <DCArtboard id="hero-face" label="Concept A · Friendly face (bubble with character)" width={540} height={520}>
        <Frame bg={PALETTES.coral.bg} pad={36} palette={PALETTES.coral}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
            <TagChip palette={PALETTES.coral}>CONCEPT A · FRIENDLY FACE</TagChip>
            <TagChip color={PALETTES.coral.accentDeep} border={PALETTES.coral.accent}>★ Recommended</TagChip>
          </div>
          <div style={{ flex:1, display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', gap: 28 }}>
            <div style={{
              width: 320, height: 320, borderRadius: 28,
              background: PALETTES.coral.cream,
              border: `1px solid ${PALETTES.coral.inkFaint}`,
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: '0 24px 60px -24px rgba(0,0,0,0.18)',
            }}>
              <BubbleFace palette={PALETTES.coral} size={220}/>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
              <Wordmark size={64} palette={PALETTES.coral} tf="manrope"/>
              <Slogan palette={PALETTES.coral} size={16} variant="side"/>
            </div>
          </div>
        </Frame>
      </DCArtboard>

      <DCArtboard id="hero-buddy" label="Concept B · Buddy duo (big bubble + little buddy)" width={540} height={520}>
        <Frame bg={PALETTES.coral.bg} pad={36} palette={PALETTES.coral}>
          <TagChip palette={PALETTES.coral}>CONCEPT B · BUDDY DUO</TagChip>
          <div style={{ flex:1, display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', gap: 28 }}>
            <div style={{
              width: 320, height: 320, borderRadius: 28,
              background: PALETTES.coral.cream,
              border: `1px solid ${PALETTES.coral.inkFaint}`,
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: '0 24px 60px -24px rgba(0,0,0,0.18)',
            }}>
              <BubbleDefault palette={PALETTES.coral} size={240}/>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
              <Wordmark size={64} palette={PALETTES.coral} tf="manrope"/>
              <Slogan palette={PALETTES.coral} size={16} variant="side"/>
            </div>
          </div>
        </Frame>
      </DCArtboard>

      <DCArtboard id="face-applications" label="Friendly face · in context" width={1100} height={300}>
        <Frame bg={PALETTES.coral.bg2} pad={28} palette={PALETTES.coral}>
          <TagChip palette={PALETTES.coral}>FRIENDLY FACE · APPLICATIONS</TagChip>
          <div style={{ flex:1, display:'flex', alignItems:'center', justifyContent:'space-around', gap: 20, marginTop: 12 }}>
            {/* App icon */}
            <div style={{ display:'flex', flexDirection:'column', alignItems:'center', gap: 10 }}>
              <div style={{
                width: 120, height: 120, borderRadius: 28,
                background: PALETTES.coral.cream,
                display:'flex', alignItems:'center', justifyContent:'center',
                boxShadow: '0 14px 36px -10px rgba(0,0,0,0.18)',
                border: `1px solid ${PALETTES.coral.inkFaint}`,
              }}>
                <BubbleFaceCompact palette={PALETTES.coral} size={84}/>
              </div>
              <span style={{ fontFamily:"'JetBrains Mono',monospace", fontSize: 10, color: PALETTES.coral.inkMute, letterSpacing:'0.08em' }}>APP ICON</span>
            </div>
            {/* Avatar */}
            <div style={{ display:'flex', flexDirection:'column', alignItems:'center', gap: 10 }}>
              <div style={{
                width: 64, height: 64, borderRadius: '50%',
                background: PALETTES.coral.cream,
                display:'flex', alignItems:'center', justifyContent:'center',
                border: `1px solid ${PALETTES.coral.inkFaint}`,
              }}>
                <BubbleFaceCompact palette={PALETTES.coral} size={42}/>
              </div>
              <span style={{ fontFamily:"'JetBrains Mono',monospace", fontSize: 10, color: PALETTES.coral.inkMute, letterSpacing:'0.08em' }}>AVATAR</span>
            </div>
            {/* Tiny */}
            <div style={{ display:'flex', flexDirection:'column', alignItems:'center', gap: 10 }}>
              <div style={{ display:'flex', alignItems:'center', gap: 8 }}>
                <BubbleFaceCompact palette={PALETTES.coral} size={22}/>
                <Wordmark size={20} palette={PALETTES.coral} tf="manrope"/>
              </div>
              <span style={{ fontFamily:"'JetBrains Mono',monospace", fontSize: 10, color: PALETTES.coral.inkMute, letterSpacing:'0.08em' }}>INLINE</span>
            </div>
            {/* On dark */}
            <div style={{ display:'flex', flexDirection:'column', alignItems:'center', gap: 10 }}>
              <div style={{
                width: 120, height: 120, borderRadius: 28,
                background: PALETTES.coral.dark,
                display:'flex', alignItems:'center', justifyContent:'center',
                boxShadow: '0 14px 36px -10px rgba(0,0,0,0.30)',
              }}>
                <BubbleFaceCompact palette={PALETTES.coral} size={84} accent={PALETTES.coral.accent} color={PALETTES.coral.bg}/>
              </div>
              <span style={{ fontFamily:"'JetBrains Mono',monospace", fontSize: 10, color: PALETTES.coral.inkMute, letterSpacing:'0.08em' }}>ON DARK</span>
            </div>
            {/* Coral fill */}
            <div style={{ display:'flex', flexDirection:'column', alignItems:'center', gap: 10 }}>
              <div style={{
                width: 120, height: 120, borderRadius: 28,
                background: `linear-gradient(160deg, ${PALETTES.coral.accent}, ${PALETTES.coral.accentDeep})`,
                display:'flex', alignItems:'center', justifyContent:'center',
                boxShadow: '0 14px 36px -10px rgba(0,0,0,0.20), inset 0 1px 0 rgba(255,255,255,0.25)',
              }}>
                <BubbleFaceCompact palette={PALETTES.coral} size={84} accent={PALETTES.coral.bg} color={PALETTES.coral.ink}/>
              </div>
              <span style={{ fontFamily:"'JetBrains Mono',monospace", fontSize: 10, color: PALETTES.coral.inkMute, letterSpacing:'0.08em' }}>CORAL FIELD</span>
            </div>
          </div>
        </Frame>
      </DCArtboard>

      <DCArtboard id="slogans" label="Slogan options" width={1100} height={260}>
        <Frame bg={PALETTES.coral.bg} pad={32} palette={PALETTES.coral}>
          <TagChip palette={PALETTES.coral}>SLOGAN · 3 OPTIONS</TagChip>
          <div style={{ flex:1, display:'grid', gridTemplateColumns:'repeat(3, 1fr)', gap: 16, marginTop: 12 }}>
            {[
              { v: 'side',  label: '★ NEW',     blurb: 'Companion reading — calm and warm' },
              { v: 'fetch', label: 'ALT',      blurb: 'Doubles up: dog fetches + LLM retrieves' },
              { v: 'ride',  label: 'CLASSIC',  blurb: 'The literal sidecar metaphor' },
            ].map((s, i) => (
              <div key={s.v} style={{
                background: PALETTES.coral.cream,
                border: i === 0 ? `1px solid ${PALETTES.coral.accent}` : `1px solid ${PALETTES.coral.inkFaint}`,
                boxShadow: i === 0 ? `0 0 0 4px oklch(0.97 0.04 35)` : 'none',
                borderRadius: 12, padding: 20,
                display: 'flex', flexDirection: 'column', gap: 10,
              }}>
                <span style={{ fontFamily:"'JetBrains Mono',monospace", fontSize: 10, color: i===0 ? PALETTES.coral.accentDeep : PALETTES.coral.inkMute, letterSpacing:'0.10em', textTransform:'uppercase', fontWeight: 600 }}>{s.label}</span>
                <Slogan palette={PALETTES.coral} size={20} variant={s.v}/>
                <span style={{ fontFamily:"'Manrope',sans-serif", fontSize: 12, color: PALETTES.coral.inkSoft, lineHeight: 1.5 }}>{s.blurb}</span>
              </div>
            ))}
          </div>
        </Frame>
      </DCArtboard>
    </DCSection>

    {/* ============ SECTION 1 · TYPE ============ */}
    <DCSection id="type" title="Type" subtitle="Six typefaces for the wordmark — all with the dotless-i + coral disc mechanic">
      <DCArtboard id="type-intro" label="Type · overview" width={1080} height={280}>
        <TypeIntro/>
      </DCArtboard>
      {TYPEFACES.map(t => (
        <DCArtboard key={t.id} id={`type-${t.id}`} label={t.family} width={540} height={280}>
          <TypeRow tf={t.id}/>
        </DCArtboard>
      ))}
    </DCSection>

    {/* ============ SECTION 2 · MARK VARIATIONS ============ */}
    <DCSection id="marks" title="Mark variations" subtitle="Five constructions of the bubble idea — same DNA, different rhythm">
      {BUBBLE_VARIANTS.map((v, i) => (
        <DCArtboard key={v.id} id={`mv-${v.id}`} label={v.name} width={400} height={400}>
          <MarkVariationCard variant={v} focus={i === 0}/>
        </DCArtboard>
      ))}
    </DCSection>

    {/* ============ SECTION 3 · COLOR ============ */}
    <DCSection id="color" title="Color systems" subtitle="Five palettes — each shifts the brand temperature without losing the geometric DNA">
      {Object.entries(PALETTES).map(([key, P], i) => (
        <DCArtboard key={key} id={`color-${key}`} label={P.name} width={580} height={360}>
          <PaletteHero palette={P} variant={BubbleDefault} recommended={key === 'indigo'}/>
        </DCArtboard>
      ))}
      <DCArtboard id="app-row" label="App icons across palettes" width={1480} height={300}>
        <Frame bg={DEFAULT_P.bg2} pad={28}>
          <TagChip>APP ICONS · ALL FIVE PALETTES</TagChip>
          <div style={{ flex:1, display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 16, alignItems: 'center', marginTop: 12 }}>
            {Object.entries(PALETTES).map(([key, P]) => (
              <div key={key} style={{ display:'flex', flexDirection:'column', alignItems:'center', gap: 10 }}>
                <div style={{
                  width: 116, height: 116, borderRadius: 26,
                  background: `linear-gradient(160deg, ${P.accent}, ${P.accentDeep})`,
                  display:'flex', alignItems:'center', justifyContent:'center',
                  boxShadow: '0 14px 36px -12px rgba(0,0,0,0.30), inset 0 1px 0 rgba(255,255,255,0.25)',
                }}>
                  <BubbleDefault palette={P} size={82} color={P.bg} accent={P.ink}/>
                </div>
                <span style={{ fontFamily:"'Manrope',sans-serif", fontSize: 12, fontWeight: 500, color: DEFAULT_P.ink, letterSpacing:'-0.01em' }}>
                  {P.name.split(' & ')[0]}
                </span>
              </div>
            ))}
          </div>
        </Frame>
      </DCArtboard>
    </DCSection>

    {/* ============ SECTION 4 · RECOMMENDED COMBOS ============ */}
    <DCSection id="combos" title="Recommended combos" subtitle="Three palette × type × variant pairings worth a closer look">
      <DCArtboard id="combo-1" label="Indigo · Inter Tight · Default" width={840} height={400}>
        <HeroCombo
          palette={PALETTES.indigo}
          variant={BubbleDefault}
          tf="inter-tight"
          label="COMBO 01 · CLASSIC TECH"
          blurb="The most fundable, enterprise-ready combination. Indigo signals trust, Inter Tight is the workhorse, Default bubble is the most legible at every size."
        />
      </DCArtboard>
      <DCArtboard id="combo-2" label="Coral · Manrope · Round" width={840} height={400}>
        <HeroCombo
          palette={PALETTES.coral}
          variant={BubbleRound}
          tf="manrope"
          label="COMBO 02 · FRIENDLY"
          blurb="Most personable. Round bubbles read like two friends. Coral keeps the warmth. Manrope's soft terminals back up the buddy reading."
        />
      </DCArtboard>
      <DCArtboard id="combo-3" label="Forest · Bricolage · Conjoined" width={840} height={400}>
        <HeroCombo
          palette={PALETTES.forest}
          variant={BubbleConjoined}
          tf="bricolage"
          label="COMBO 03 · CHARACTERFUL"
          blurb="Distinctive and literary. Conjoined bubbles feel architectural; Bricolage adds personality without being twee; Forest grounds it all."
        />
      </DCArtboard>
    </DCSection>

  </DesignCanvas>
);

ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
