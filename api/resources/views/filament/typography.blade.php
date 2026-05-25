{{-- Align the admin with the Sidecar design system: Inter (sans, already loaded via ->font('Inter')) --}}
{{-- + JetBrains Mono (code), the same font-features/smoothing, and the app's compact reading size.   --}}
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500" />
<style>
    .fi-body {
        font-feature-settings: 'cv11', 'ss01', 'ss03';
        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
    }

    /* Monospace → JetBrains Mono, matching the front-end .mono usage. */
    .fi-body code,
    .fi-body pre,
    .fi-body kbd,
    .fi-body [class*="font-mono"] {
        font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, monospace;
    }

    /* Help / guide prose → the design system's compact reading size (~13px / 1.6). */
    .fi-body .prose {
        font-size: 0.8125rem;
        line-height: 1.6;
    }
    .fi-body .prose :is(h1, h2, h3, h4) {
        font-weight: 600;
        letter-spacing: -0.01em;
    }
    .fi-body .prose code {
        font-size: 0.82em;
    }
    .fi-body .prose pre code {
        font-size: inherit;
    }
</style>
