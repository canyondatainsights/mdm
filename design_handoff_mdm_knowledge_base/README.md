# Handoff: MDM Knowledge Base — Chat / LLM UI

## Overview

This is a design handoff for the **Master Data Management (MDM) Knowledge Base**, an internal tool that combines a conversational LLM assistant with a governed corpus of MDM policy, runbook, and configuration documents. Data stewards, platform engineers, and enterprise architects use it to:

- Ask grounded natural-language questions about master data policy, match/merge rules, survivorship, lineage, and stewardship workflows
- Get answers with **inline citations** linking back to source documents
- Inspect cited sources side-by-side with the chat (excerpts, lineage diagrams, related docs)
- Trigger downstream actions (open Jira tickets, open stewardship tasks)

The UI is a classic three-pane layout: **conversations sidebar | chat thread | source inspector**.

---

## About the Design Files

The files in `design/` are **design references created in HTML/JSX with React 18 + Babel standalone**. They are prototypes showing the intended look and behavior — **not production code to copy directly**.

The task is to **recreate these designs in the target codebase's existing environment** (e.g. a Next.js app, a Remix app, an existing internal-tools React monorepo) using its established patterns, component primitives, and styling system. If no codebase exists yet, choose the most appropriate framework for the project (React + Tailwind, or React + CSS-in-JS, are both natural fits given the inline-style heavy reference implementation) and implement there.

Do not:
- Ship the inline `style={{...}}` objects as-is — port them to your design system's tokens / Tailwind classes / styled-components
- Use Babel standalone in production
- Treat the mock data in `data.jsx` as a contract — it's illustrative content for layout fidelity only

Do:
- Match colors, spacing, type scale, and component proportions exactly
- Preserve the hierarchy and visual rhythm — particularly the citation chips, source cards, and the colored nav rail
- Reuse the iconography style (1.6px stroke, rounded line-caps, 24×24 viewBox, monochromatic with optional `currentColor` tinting)

---

## Fidelity

**High-fidelity (hifi).** Final colors, typography, spacing, and component states are all locked. Recreate pixel-perfectly using your codebase's existing libraries.

The only exceptions:
- Sample chat content (the SAP/Salesforce conflict question) is illustrative — real content will come from the LLM
- Source titles, owners, dates, and trust scores are illustrative

---

## Screens / Views

There is **one primary screen** with three panes. The right pane (Inspector) is collapsible. The left pane (Sidebar) is collapsible. The center pane (Chat) is always visible.

### 1. Left Sidebar — Conversations & Navigation

**Purpose**: navigation between conversations, primary product nav (Ask the hub, Knowledge sources, Data model, Stewardship queue, Audit log), workspace identity, account.

**Layout**:
- Width: **280px** fixed, full height
- Background: `var(--bg-2)` with a **soft radial color wash** behind the header area (top 220px): two radial gradients, one blue (oklch 0.93 0.07 252 / 55%) anchored top-left, one violet (oklch 0.93 0.07 295 / 40%) anchored top-right, both fading to transparent at 60%
- Border-right: 1px `var(--border)`
- Vertical stack (top → bottom):
  1. **Brand row** (14px padding) — 30×30 conic-gradient mark with white "M" + workspace title + status dot
  2. **New conversation** button — blue gradient, full-width, 9×11 padding, 9px radius
  3. **Search input** — `⌘K` hint chip on the right
  4. **Primary nav** (5 items) — each with a colored 24×24 icon chip
  5. **Pinned / dated conversation list** — colored left rail + domain tag pill
  6. **User footer** — gradient avatar + presence dot + name + role

**Brand mark**:
- 30×30, 8px radius
- Background: `conic-gradient(from 210deg at 50% 50%, oklch(0.55 0.18 252), oklch(0.58 0.18 295), oklch(0.62 0.16 195), oklch(0.55 0.18 252))`
- Glyph: white "M", 700 weight, 13.5px, letter-spacing -0.03em
- Shadow: `0 1px 0 rgba(15,22,36,0.06), 0 4px 14px -4px oklch(0.55 0.18 270 / 0.45)`

**New conversation button**:
- Full width, 9px vertical / 11px horizontal padding, 9px radius
- Background: `linear-gradient(180deg, oklch(0.55 0.16 252), oklch(0.46 0.16 258))`
- Border: 1px `oklch(0.40 0.16 258)`
- Color: white
- Inner highlight + outer glow shadow: `0 1px 0 oklch(0.99 0 0 / 0.30) inset, 0 6px 16px -6px oklch(0.40 0.16 258 / 0.55)`
- Right-side shortcut chip: `⌘N` in a translucent white pill, JetBrains Mono, 10.5px
- Hover: shadow intensifies to `0 10px 22px -6px oklch(0.40 0.16 258 / 0.60)`

**Primary nav items** — each has a distinct hue token. The token applies to: 24×24 icon chip background + border + matching badge pill:

| Item | Tone | fg | bg | bd |
|---|---|---|---|---|
| Ask the hub | accent (blue) | `oklch(0.50 0.14 252)` | `oklch(0.95 0.04 252)` | `oklch(0.86 0.06 252)` |
| Knowledge sources | violet | `oklch(0.50 0.16 295)` | `oklch(0.95 0.04 295)` | `oklch(0.86 0.07 295)` |
| Data model explorer | teal | `oklch(0.50 0.12 195)` | `oklch(0.95 0.04 195)` | `oklch(0.86 0.06 195)` |
| Stewardship queue | amber | `oklch(0.50 0.13 70)` | `oklch(0.96 0.05 80)` | `oklch(0.87 0.07 75)` |
| Audit log | green | `oklch(0.50 0.13 155)` | `oklch(0.96 0.04 155)` | `oklch(0.86 0.06 155)` |

Active item gets a white panel background with subtle border + shadow. Hover (inactive): background `oklch(0.99 0 0 / 0.6)`.

**Conversation row**:
- Padding 8px / 10px / 8px / 14px (extra left for color rail)
- 3px-wide color rail on the left (using the row's tone), full height minus 10px top/bottom inset
- Title: 13px, weight 400 (500 active), truncated single line
- Below title: domain tag pill (e.g. "Customer", "Product", "Vendor", "Privacy", "Platform", "Finance", "Stewardship") in the tone color, then preview text
- Active state: white background + border + subtle shadow

**User footer**:
- 1px top border, 10/12 padding
- Background: `linear-gradient(180deg, var(--bg-2), oklch(0.96 0.015 252))`
- 30×30 avatar with `linear-gradient(135deg, oklch(0.55 0.16 295), oklch(0.50 0.15 252))` and initials in white
- Presence dot: 10×10 green (`oklch(0.62 0.13 155)`) circle with 2px `var(--bg-2)` ring, positioned bottom-right of avatar

---

### 2. Center — Chat Header + Thread + Composer

**Purpose**: conversational interface. User asks questions; assistant returns answers grounded in MDM corpus.

**Layout**:
- Flex column, fills remaining width
- Header (52px) + scrollable thread + composer area
- Thread content is constrained to **760px max-width**, horizontally centered, 24px horizontal padding

**Header (52px)**:
- 1px bottom border
- Items L→R: sidebar toggle, vertical divider, conversation title (13.5px, weight 500), "Reasoning · v2" accent pill
- Right side: "PII redacted" green pill, "Domain · Customer" neutral pill, divider, share button, more, inspector toggle

**Day separator** (above first message):
- Horizontal hairline + centered text "Today · May 23, 2026" in 11px `var(--fg-4)`

**User message bubble**:
- Right-aligned, max-width 78%
- Padding: 11px / 14px
- Border-radius: `14px 14px 4px 14px` (note tail bottom-right)
- Background: `var(--accent)` (`oklch(0.50 0.14 252)`)
- Color: white
- Below bubble: "You · 10:42" in 11px `var(--fg-4)`

**Assistant message**:
- 30×30 left avatar with `linear-gradient(135deg, var(--accent), var(--accent-2))` and a sparkle icon
- Header row: "MDM Assistant" (13px, 600) + "High confidence" green pill + meta text ("· 3 sources · 10:42")
- Body blocks (gap 12px, line-height 1.6, color `var(--fg-2)`):
  - **Paragraph** — supports `**bold**` markdown
  - **Ordered list** — gap 6px, paddingLeft 20px
  - **Callout** — amber tinted card (bg `oklch(0.97 0.03 80)`, border `oklch(0.88 0.05 80)`) with sparkle icon
  - **Options grid** — 2-column grid of bordered cards (12/13 padding, 10px radius, white background) with label (12.5px, 600) + body (12.5px, `var(--fg-2)`)
- **Citation chips** — small numbered chips appended to paragraphs/list items
  - 5px horizontal padding, 16px height
  - `var(--accent-soft)` background, `var(--accent-2)` text, `var(--accent-border)` border
  - 4px radius, 10px / 600 weight
  - Clicking opens the corresponding source in the Inspector

**Sources block** (below assistant message body):
- 10/12 padding, `var(--bg-2)` background, 1px `var(--border)`, 10px radius
- Header: "SOURCES" label (11px, 600, uppercase, 0.07em tracking, `var(--fg-3)`) with book icon
- Source rows: white card per source, 8/9 padding, 7px radius
  - 18×18 numbered badge (accent-soft bg/border, accent-2 fg, 10.5px 700)
  - Doc-type badge (PDF / Confluence / DOCX / XLSX / PPTX — each with its own color, see below)
  - Source title (12.5px / 500) + anchor (e.g. "§4.2 Trust Hierarchy")
  - Italicized snippet quote underneath (12px / `var(--fg-2)`)
  - Hover: border becomes `var(--accent-border)`, background becomes `var(--accent-soft)`

**Doc-type badges** (monospace, 18px tall, 38px min-width, uppercase, 10px / 600):

| Type | bg | fg |
|---|---|---|
| PDF | `oklch(0.96 0.04 27)` | `oklch(0.50 0.16 27)` |
| PPTX | `oklch(0.96 0.04 50)` | `oklch(0.50 0.15 50)` |
| DOCX | `oklch(0.96 0.04 252)` | `oklch(0.45 0.14 252)` |
| XLSX | `oklch(0.96 0.04 155)` | `oklch(0.42 0.13 155)` |
| Confluence | `oklch(0.96 0.04 252)` | `oklch(0.45 0.14 252)` |

**Message toolbar** (under assistant body):
- 28×28 icon buttons: Copy, Regenerate, Helpful, Not helpful
- Divider
- Outlined action chips: "Create Jira ticket" (flow icon), "Open in stewardship" (external icon)

**Suggested prompts** (above composer):
- Horizontal flex, gap 6px, wraps
- Pill buttons: 6/10 padding, 999 radius, 12.5px / 500
- White background, 1px border, hover transitions to accent-soft

**Composer**:
- White card, 14px radius, 1px border, `var(--shadow)`, 10/12/8 padding
- Textarea (2 rows, no resize, 14px font)
- Tool row underneath:
  - L: Attach chip, Domain scope chip (active accent style with chevron), Filters chip
  - R: character counter (mono, 11px, `var(--fg-4)`) + 32×32 send button (`var(--fg)` background when input has text, `var(--bg-3)` when empty)
- Disclaimer line below: 11px, `var(--fg-4)`, centered, "Answers are grounded in your governed MDM corpus. Always verify before applying to production records."

---

### 3. Right — Source Inspector

**Purpose**: opens automatically when a citation chip or source card is clicked. Shows the excerpt, lineage, or related docs for the active source.

**Layout**:
- Width: **380px** fixed, full height
- Border-left: 1px `var(--border)`, background `var(--bg-2)`

**Header** (12/14 padding, 1px bottom border, white bg):
- "SOURCE INSPECTOR" label (11px / 600 / uppercase / 0.07em tracking) with book icon + close button on the right
- Doc-type badge + title (14px / 600 / -0.01em tracking) + meta line ("Owner · Updated date · pages")
- 3 tabs at the bottom: **Excerpt**, **Lineage**, **Related** — active tab has a 2px bottom border in `var(--fg)`

**Excerpt tab**:
- Tag row: source's tags as neutral pills + "Approved" green pill with shield icon
- Snippet card: white, 10px radius
  - Header strip: `var(--bg-2)` bg, 8/12 padding, "§ 4.2 TRUST HIERARCHY" label (mono / 10.5 / 600) + page indicator on the right
  - Body: 12/14 padding, 13px / 1.6
  - Highlighted phrase ("SAP S/4 is ranked above Salesforce") uses `<mark>` with bg `oklch(0.93 0.12 95)` (soft yellow)
  - Inline code-style values (trust scores like `92`) use `var(--bg-3)` bg, 3px radius, 4px horizontal padding, mono font
- **Trust scores table**: white card, list of source systems with horizontal bar chart
  - Each row: system name + 4px-tall progress bar + numeric score (mono / 600) + region label
  - Top-ranked row uses `var(--accent)` for the bar; others use `oklch(0.70 0.04 250)` (neutral)
- "Open full document" outlined button

**Lineage tab**:
- Intro line with mono code chip for the record ID
- Vertical lineage diagram:
  - **Source nodes** (SAP, Salesforce) — neutral background
  - 2px vertical connector
  - **Hub node** (MDM Hub · Customer Domain) — accent-tinted card with sparkle icon
  - 2px vertical connector
  - **Consumer nodes** (Snowflake, Marketing Cloud, ServiceNow) — small variant, neutral

**Related tab**:
- Vertical list of other documents (doc-type badge + title + owner/date), white cards

---

## Interactions & Behavior

- **Click a citation chip** → opens that source in the Inspector (right pane); if Inspector is closed, it opens
- **Click a sidebar conversation** → loads that conversation into the center pane (currently mocked — only visually highlights)
- **Click the inspector toggle in header** → toggles right pane open/closed
- **Click the sidebar toggle in header** → toggles left pane open/closed
- **Tab switching in Inspector** → state-local; no URL change
- **Hover states** — all interactive surfaces (nav items, conversation rows, source cards, prompt pills) have a subtle background or border-color change with `transition: 120ms`
- **Composer send button** — visually disabled (gray) when textarea is empty, dark (`var(--fg)` bg) when non-empty
- **Animations / transitions** — keep to 120ms `ease` for background, border-color, and box-shadow changes. No translate animations on hover.
- **Loading states** — when the assistant is generating a response, replace the message body with a typing indicator (3 dots, fade in/out, 1s cycle). Not implemented in the reference; add in production.
- **Error states** — failed message regenerate should show an inline error pill below the assistant message with a "Retry" link. Not implemented in the reference.

### Keyboard shortcuts (intended)

- `⌘N` — new conversation
- `⌘K` — focus search
- `Enter` — send message (Shift+Enter for newline)
- `Esc` — close inspector

### Responsive behavior

This design targets desktop only (>= 1280px). Below that, the Inspector collapses by default. Below 900px, the Sidebar collapses by default and becomes an overlay drawer. **Mobile is out of scope for this handoff.**

---

## State Management

Minimum required state (page-level):

```ts
type AppState = {
  activeConversationId: string | null;
  sidebarCollapsed: boolean;
  inspectorOpen: boolean;
  openSourceId: string | null;  // which source is shown in the inspector
  composerValue: string;
  composerScope: 'Customer domain' | 'Product domain' | 'Vendor domain' | 'All domains';
};

type Conversation = {
  id: string;
  title: string;
  pinned: boolean;
  domain: 'customer' | 'product' | 'vendor' | 'privacy' | 'platform' | 'finance' | 'stewardship';
  updatedAt: string;
  preview: string;
};

type Message = {
  id: string;
  role: 'user' | 'assistant';
  content: string | Block[];   // user is string, assistant is structured Block[]
  citations?: Citation[];      // assistant only
  confidence?: 'low' | 'medium' | 'high';
  createdAt: string;
};

type Block =
  | { type: 'p'; text: string }
  | { type: 'ol'; items: string[] }
  | { type: 'callout'; kind: 'tip' | 'warn'; text: string }
  | { type: 'options'; items: { label: string; body: string }[] };

type Citation = {
  sourceId: string;
  anchor: string;   // e.g. "§4.2 Trust Hierarchy"
  snippet: string;
};

type Source = {
  id: string;
  title: string;
  type: 'PDF' | 'PPTX' | 'DOCX' | 'XLSX' | 'Confluence';
  pages: number;
  owner: string;
  updatedAt: string;
  tags: string[];
};
```

### Data fetching

- `GET /api/conversations` — list user's conversations (paginated, sorted by `updatedAt`)
- `GET /api/conversations/:id/messages` — full message history for a conversation
- `POST /api/conversations/:id/messages` — submit a new user message; response streams the assistant reply (Server-Sent Events recommended) with citations attached at the end
- `GET /api/sources/:id` — fetch a source's full metadata + excerpt + lineage edges + related sources
- `POST /api/jira/tickets` — create a Jira ticket from an assistant message + action context
- `POST /api/stewardship/tasks` — create a stewardship task

---

## Design Tokens

### Colors (CSS custom properties, set on `:root`)

```css
--bg:            oklch(0.992 0.003 250);   /* page background */
--bg-2:          oklch(0.975 0.004 250);   /* sidebar / inspector background */
--bg-3:          oklch(0.955 0.005 250);   /* search input, code chip background */
--panel:         #ffffff;                  /* cards, popovers */
--border:        oklch(0.918 0.006 250);   /* hairline borders */
--border-strong: oklch(0.86 0.008 250);    /* lineage connectors */
--fg:            oklch(0.18 0.012 250);    /* primary text */
--fg-2:          oklch(0.36 0.012 250);    /* body text */
--fg-3:          oklch(0.55 0.012 250);    /* secondary / icons */
--fg-4:          oklch(0.70 0.010 250);    /* tertiary / meta */
--accent:        oklch(0.50 0.14 252);     /* primary blue */
--accent-2:      oklch(0.42 0.15 252);     /* darker blue (hover, text on light) */
--accent-soft:   oklch(0.96 0.025 252);    /* tinted background */
--accent-border: oklch(0.88 0.05 252);     /* tinted border */
--ok:            oklch(0.62 0.13 155);     /* success / approved */
--warn:          oklch(0.70 0.14 70);      /* amber warning */
--danger:        oklch(0.58 0.18 27);      /* destructive */
```

### Sidebar nav hue tokens (per item)

| Token | fg | bg | bd |
|---|---|---|---|
| accent | `oklch(0.50 0.14 252)` | `oklch(0.95 0.04 252)` | `oklch(0.86 0.06 252)` |
| violet | `oklch(0.50 0.16 295)` | `oklch(0.95 0.04 295)` | `oklch(0.86 0.07 295)` |
| teal | `oklch(0.50 0.12 195)` | `oklch(0.95 0.04 195)` | `oklch(0.86 0.06 195)` |
| amber | `oklch(0.50 0.13 70)` | `oklch(0.96 0.05 80)` | `oklch(0.87 0.07 75)` |
| rose | `oklch(0.55 0.16 15)` | `oklch(0.96 0.04 15)` | `oklch(0.87 0.06 15)` |
| green | `oklch(0.50 0.13 155)` | `oklch(0.96 0.04 155)` | `oklch(0.86 0.06 155)` |

### Typography

- **Primary**: Inter (Google Fonts), weights 400, 500, 600, 700
- **Mono**: JetBrains Mono, weights 400, 500
- Font features: `'cv11', 'ss01', 'ss03'` (Inter alternates)
- Antialiasing: `-webkit-font-smoothing: antialiased`

| Use | Size | Weight | Line-height | Notes |
|---|---|---|---|---|
| Section labels (uppercase) | 10.5px | 600 | — | tracking 0.07em |
| Doc-type badges | 10px | 600 | — | mono, uppercase |
| Meta / tertiary | 11px | 400 | 1.4 | `var(--fg-4)` |
| Body small | 11.5–12.5px | 400/500 | 1.45 | row previews |
| Body | 13px | 400/500 | 1.5 | nav, list items |
| Message body | 14px | 400 | 1.6 | chat thread |
| Heading / row title | 13–14px | 500/600 | 1.3 | letter-spacing -0.01em |

### Spacing scale

The design uses ad-hoc pixel values rather than a strict scale. Common values: 4, 6, 8, 10, 12, 14, 16, 20, 24px. When porting, snap to your token scale (e.g. Tailwind's 1/1.5/2/2.5/3/3.5/4/5/6).

### Radius

- 4px — citation chips, small badges
- 6–7px — nav items, list rows
- 8–9px — buttons, inputs
- 10–14px — cards, panels, message bubble
- 999px — pills

### Shadows

```css
--shadow-sm: 0 1px 0 rgba(15, 22, 36, 0.04), 0 1px 2px rgba(15, 22, 36, 0.04);
--shadow:    0 1px 0 rgba(15, 22, 36, 0.04), 0 8px 24px -8px rgba(15, 22, 36, 0.08);
```

Special: New conversation button uses an inner highlight + colored outer glow (see button spec above).

---

## Assets

- **Fonts**: Inter & JetBrains Mono — loaded from Google Fonts
- **Icons**: All icons are inline SVGs, 24×24 viewBox, 1.6px stroke (varies 1.7–2.2 in places), `stroke-linecap="round"`, `stroke-linejoin="round"`. See `design/ui.jsx` for the full set. Recommended port: use **Lucide React** (`lucide-react`) — the icons are stylistically identical. Mapping:

| Reference name | Lucide equivalent |
|---|---|
| search | Search |
| plus | Plus |
| send | Send |
| paperclip | Paperclip |
| sparkle | Sparkles |
| book | BookOpen |
| doc | FileText |
| pin | Pin |
| panel | PanelRight |
| sidebar | PanelLeft |
| chevron-down | ChevronDown |
| chevron-right | ChevronRight |
| check | Check |
| copy | Copy |
| thumbsup | ThumbsUp |
| thumbsdown | ThumbsDown |
| refresh | RotateCw |
| filter | SlidersHorizontal |
| tag | Tag |
| shield | Shield |
| link | Link2 |
| external | ExternalLink |
| database | Database |
| graph | Network |
| history | History |
| more | MoreHorizontal |
| arrow-up | ArrowUp |
| flow | Workflow |
| globe | Globe |

- **Brand mark**: rendered in CSS (conic gradient with "M" glyph) — no asset file needed
- **No emoji**, no illustrative SVGs

---

## Files

```
design/
├── index.html       # Entry — loads fonts, defines :root tokens, mounts React
├── app.jsx          # <App/> — state for activeId, inspectorOpen, openSource, sidebarCollapsed
├── data.jsx         # MDM_DATA mock — conversations, sources, activeMessages
├── ui.jsx           # Icon, Pill, IconButton, Avatar, DocTypeBadge primitives
├── sidebar.jsx      # <Sidebar/> + <ConversationRow/> + NAV_TONES + CONV_TAGS maps
├── chat.jsx         # <ChatArea/>, <ChatHeader/>, <UserMessage/>, <AssistantMessage/>,
│                    #   <CitationChip/>, <CitationsList/>, <Composer/>, <SuggestedPrompts/>
└── inspector.jsx    # <Inspector/>, <SourceExcerpt/>, <LineagePanel/>, <RelatedPanel/>,
                     #   <LineageNode/>, <LineageEdge/>
```

Open `design/index.html` in a browser to view the reference. No build step required — it uses Babel standalone for JSX transformation at runtime (development-only).

---

## Implementation notes

1. **OKLCH support** — All colors use `oklch()`. This is supported in all modern evergreen browsers (Chrome 111+, Safari 15.4+, Firefox 113+). If your target environment must support older browsers, convert to hex/RGB using a tool like [oklch.com](https://oklch.com).
2. **Inline styles** — The reference uses inline `style={{}}` objects extensively. **Do not port these as-is.** Extract to your styling system (Tailwind utility classes, CSS Modules, styled-components, etc.).
3. **Citation chips** — Inline element appended to `<p>` and `<li>` text. Make sure they don't break flow on wrap. Render `[1]` style number that's tappable and `aria-label`'d.
4. **Streaming responses** — The reference shows a completed message. For production, render assistant blocks progressively as they stream in. Token-level streaming is fine; consider committing block-by-block to avoid layout thrash.
5. **Accessibility**:
   - Every IconButton must have an `aria-label`
   - Citation chips should be `<button>` elements with `aria-label="Source 1: <title>"`
   - Conversation rows are `<button>` elements (correct in reference)
   - Color contrast: `var(--fg-2)` on `var(--bg)` is ~7.5:1, `var(--fg-3)` on `var(--bg)` is ~4.7:1 — both pass AA. Avoid using `--fg-4` for anything users need to read.
   - Provide a visible focus ring (the reference omits this; add `:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }` globally)

---

## Next steps for the developer

1. Decide on framework + styling system (e.g. Next.js + Tailwind, Remix + CSS Modules)
2. Set up the color tokens (`:root` CSS custom properties is fine; or port to Tailwind theme config)
3. Install Inter & JetBrains Mono via `next/font` or `@fontsource/inter` etc.
4. Install `lucide-react` for icons
5. Build the three-pane shell first (Sidebar, ChatArea, Inspector) — get the layout + responsive breakpoints solid before adding content
6. Build the message renderer with proper block types and streaming support
7. Wire up real API endpoints (see State Management section)
8. Add the loading + error states that are missing from the reference
9. Add a focus-visible style + run an a11y audit

Ask the design team for clarification on:
- The empty / first-time state (no conversations yet)
- The "Knowledge sources" page (linked from sidebar nav but not designed)
- The "Data model explorer" page (linked but not designed)
- The "Stewardship queue" page (linked but not designed)
- The "Audit log" page (linked but not designed)
- Mobile / responsive layout below 900px
