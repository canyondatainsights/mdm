{{-- Sidecar admin brand lockup: cobalt buddy-duo bubble + wordmark. Inline SVG so it needs
     no vite build and inherits crisp rendering at the panel's brandLogoHeight. --}}
<div style="display:flex;align-items:center;gap:.55rem;">
    <svg width="30" height="26" viewBox="0 0 140 110" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        {{-- host bubble --}}
        <path d="M14 8h84a14 14 0 0 1 14 14v44a14 14 0 0 1-14 14H58l-26 22V94H14A14 14 0 0 1 0 80V22A14 14 0 0 1 14 8Z" fill="#2447d6"/>
        {{-- buddy bubble --}}
        <circle cx="112" cy="78" r="26" fill="#1e2a52"/>
        <circle cx="104" cy="74" r="4.5" fill="#fff"/>
        <circle cx="120" cy="74" r="4.5" fill="#fff"/>
    </svg>
    <span style="font-weight:700;font-size:1.15rem;letter-spacing:-.01em;color:currentColor;">Sidecar</span>
</div>
