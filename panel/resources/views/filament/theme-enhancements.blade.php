{{-- Visual polish injected via render hook, not a custom Filament theme -
     the production installer never runs an npm/asset build (see the public
     layout's own comment on this), so a compiled Tailwind theme isn't an
     option here. Plain CSS overrides on Filament's existing fi- classes,
     same delivery mechanism as clipboard-script.blade.php.

     MUI-dark-inspired restyle (client request 2026-07-18): originally
     built dark-only, since dark mode was forced panel-wide at the time -
     elevated card fills, pill-style active nav item, rounded corners,
     generous spacing - on top of Filament's own components. This is a
     CSS-only restyle, not a framework migration: Filament + Livewire stays
     the actual implementation (CLAUDE.md Section 16 tech-stack decision
     unchanged), so it won't be pixel-identical to a real MUI app, but gets
     visually close without new dependencies or a build step.

     Light theme + widget glass style (client request 2026-07-28): dark
     mode is no longer forced (AdminPanelProvider), so every color below is
     now a CSS variable defined twice - light values on :root (the default,
     no .dark class present), dark values re-declared under .dark (the
     class Filament's own theme switcher adds to <html>). Equal selector
     specificity, so source order is what makes .dark win when present -
     :root must stay declared first. Dashboard widgets (Network Topology,
     the PPSK stat cards) get a distinct frosted-glass treatment; every
     other card/section keeps the plain solid-surface look from the
     original restyle - a deliberate scope choice, not an oversight. --}}
<style>
    :root {
        --zc-radius-lg: 1rem;
        --zc-radius-md: 0.75rem;

        /* Light theme (default - no .dark class present) */
        --zc-bg: oklch(0.98 0.006 250);
        --zc-surface: oklch(1 0 0);
        --zc-surface-hover: oklch(0.965 0.014 240);
        --zc-border: oklch(0.685 0.169 237.323 / 22%);
        --zc-text-strong: oklch(0.24 0.03 260);
        --zc-text-muted: oklch(0.45 0.02 260);
        /* Deliberately dark, not a light/soft shadow (client feedback
           2026-07-28: light theme's shadow read as too faint to actually
           look "elevated") - a light-colored shadow on a light surface
           barely registers, so this stays a strong, dark-tinted shadow
           the same way the dark theme's own shadow always has been. */
        --zc-shadow-ambient: rgba(15, 23, 42, 0.35);
        --zc-scrollbar-thumb: oklch(0.4 0.05 260 / 25%);
        --zc-scrollbar-thumb-hover: oklch(0.4 0.05 260 / 40%);

        /* Ambient background wash behind the whole app - gives the widget
           glass effect something colorful to show through, and keeps the
           light theme from reading as flat white (client feedback
           2026-07-28: "light theme also need prominent colors"). */
        --zc-wash-a: oklch(0.75 0.12 237 / 30%);
        --zc-wash-b: oklch(0.78 0.11 300 / 20%);

        /* Widget glass - light */
        --zc-glass-bg: linear-gradient(155deg, oklch(1 0 0 / 78%), oklch(1 0 0 / 55%));
        --zc-glass-border: oklch(0.685 0.169 237.323 / 40%);
        --zc-glass-shadow:
            0 24px 48px -12px rgba(15, 23, 42, 0.38),
            0 2px 8px rgba(56, 189, 248, 0.18),
            inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }

    .dark {
        --zc-bg: oklch(0.141 0.005 285.823);
        --zc-surface: oklch(0.19 0.007 285.823);
        --zc-surface-hover: oklch(0.225 0.008 285.823);
        --zc-border: oklch(1 0 0 / 8%);
        --zc-text-strong: white;
        --zc-text-muted: rgba(255, 255, 255, 0.6);
        --zc-shadow-ambient: rgba(0, 0, 0, 0.3);
        --zc-scrollbar-thumb: oklch(1 0 0 / 18%);
        --zc-scrollbar-thumb-hover: oklch(1 0 0 / 32%);

        --zc-wash-a: oklch(0.5 0.15 237 / 14%);
        --zc-wash-b: oklch(0.5 0.16 300 / 10%);

        /* Widget glass - dark */
        --zc-glass-bg: linear-gradient(155deg, oklch(1 0 0 / 9%), oklch(1 0 0 / 3%));
        --zc-glass-border: oklch(1 0 0 / 16%);
        --zc-glass-shadow:
            0 24px 48px -12px rgba(0, 0, 0, 0.55),
            0 2px 10px rgba(56, 189, 248, 0.12),
            inset 0 1px 0 rgba(255, 255, 255, 0.06);
    }

    /* Ambient wash: fixed, behind everything, purely decorative - this is
       what makes the widgets' transparency actually read as "glass"
       instead of just a plain tinted box. */
    body {
        position: relative;
    }

    body::before {
        content: "";
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background:
            radial-gradient(640px circle at 12% 8%, var(--zc-wash-a), transparent 60%),
            radial-gradient(560px circle at 88% 28%, var(--zc-wash-b), transparent 60%);
    }

    /* Elevation via a lighter fill plus a deliberately deep drop shadow -
       plain 1px shadows barely read against either theme's background, so
       this goes with MUI's own "elevation 24" style shadow instead (client
       request 2026-07-18: the original subtle shadow wasn't visible
       enough). Everyday cards/tables/menus - NOT dashboard widgets, which
       get their own glass treatment below. */
    .fi-section,
    .fi-topbar-ctn,
    .fi-dropdown-panel {
        background-color: var(--zc-surface) !important;
        border-color: var(--zc-border) !important;
        border-radius: var(--zc-radius-lg) !important;
        transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        box-shadow: var(--zc-shadow-ambient) 0px 19px 38px, rgba(0, 0, 0, 0.15) 0px 15px 12px;
    }

    .fi-section:not(.fi-wi-widget .fi-section):hover {
        background-color: var(--zc-surface-hover) !important;
        transform: translateY(-2px);
    }

    .fi-section-content {
        border-radius: var(--zc-radius-lg) !important;
    }

    /* Dashboard widgets: transparent glass style, strong border/shadow
       (client request 2026-07-28) - scoped deliberately to just the
       widgets (Network Topology, the PPSK stat cards), not every card in
       the panel. */
    .fi-wi-widget,
    .fi-wi-widget .fi-section,
    .fi-wi-stats-overview-stat {
        background: var(--zc-glass-bg) !important;
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border: 1px solid var(--zc-glass-border) !important;
        border-radius: var(--zc-radius-lg) !important;
        box-shadow: var(--zc-glass-shadow) !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .fi-wi-widget .fi-section:hover,
    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-3px);
        border-color: var(--fi-color-primary-400, #38bdf8) !important;
    }

    /* Scrollbars: the default OS/browser scrollbar (light gray on Windows
       Chromium) reads as a jarring, generic strip against either theme's
       background (client feedback 2026-07-28) - a slim, translucent,
       theme-aware bar instead. Firefox via scrollbar-width/-color,
       everything else via the ::-webkit-scrollbar family; both are needed
       since neither covers every engine. */
    * {
        scrollbar-width: thin;
        scrollbar-color: var(--zc-scrollbar-thumb) transparent;
    }

    ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }

    ::-webkit-scrollbar-track {
        background: transparent;
    }

    ::-webkit-scrollbar-thumb {
        background-color: var(--zc-scrollbar-thumb);
        border-radius: 999px;
        border: 2px solid transparent;
        background-clip: content-box;
    }

    ::-webkit-scrollbar-thumb:hover {
        background-color: var(--zc-scrollbar-thumb-hover);
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }

    /* Sidebar: pill-style active item, matching the MUI minimal-ui look. */
    .fi-sidebar {
        background-color: var(--zc-bg) !important;
        border-inline-end-color: var(--zc-border) !important;
    }

    .fi-sidebar-item-btn {
        border-radius: var(--zc-radius-md) !important;
        transition: background-color 0.15s ease, color 0.15s ease;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-btn {
        background-color: color-mix(in oklch, var(--fi-color-primary-500, oklch(0.685 0.169 237.323)) 16%, transparent) !important;
    }

    .fi-sidebar-group-items {
        gap: 0.125rem !important;
    }

    /* Buttons and inputs: rounded, matching the card radius scale. */
    .fi-btn {
        border-radius: var(--zc-radius-md) !important;
    }

    .fi-input-wrp {
        border-radius: var(--zc-radius-md) !important;
        background-color: var(--zc-surface) !important;
    }

    /* Generous card padding, MUI dashboards read as spacious rather than
       dense. */
    .fi-section-content-ctn {
        padding-block: 0.5rem;
    }

    .fi-btn,
    .fi-icon-btn,
    .fi-ta-row,
    .fi-sidebar-item-btn {
        transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
    }

    .fi-btn:active {
        transform: scale(0.97);
    }

    .fi-main {
        animation: fi-fade-in 0.25s ease;
    }

    /* Loading indicator: replaces Filament's default spinning ring
       (a single SVG with `animate-spin`, see helpers.php's
       generate_loading_indicator_html()) with an 8-dot ring loader, used
       everywhere Filament shows one - buttons, table refreshes, action
       modals. Painted as the element's own background rather than via
       ::before/::after - pseudo-elements don't reliably paint on <svg> in
       this Chromium build even though computed style reports them
       correctly, so this avoids that failure mode entirely. Single
       rotating layer (not the original two-counter-rotating-layers
       design) since one element's own background can't be split into two
       independently-rotating pieces without pseudo-elements. */
    .fi-loading-indicator {
        animation: fi-loader-spin 0.9s infinite linear !important;
        color: transparent !important;
        overflow: visible !important;
        --R: 5px;
        background:
            radial-gradient(farthest-side, var(--fi-color-primary-400, #38bdf8) 94%, #0000) calc(var(--R) + 0.866*var(--R) - var(--R)) calc(var(--R) - 0.5*var(--R) - var(--R)),
            radial-gradient(farthest-side, rgba(255, 255, 255, 0.85) 94%, #0000) calc(var(--R) + 0.5*var(--R) - var(--R)) calc(var(--R) - 0.866*var(--R) - var(--R)),
            radial-gradient(farthest-side, var(--fi-color-primary-400, #38bdf8) 94%, #0000) 0 calc(-1*var(--R)),
            radial-gradient(farthest-side, rgba(255, 255, 255, 0.6) 94%, #0000) calc(var(--R) - 0.5*var(--R) - var(--R)) calc(var(--R) - 0.866*var(--R) - var(--R)),
            radial-gradient(farthest-side, var(--fi-color-primary-400, #38bdf8) 94%, #0000) calc(var(--R) - 0.866*var(--R) - var(--R)) calc(var(--R) - 0.5*var(--R) - var(--R)),
            radial-gradient(farthest-side, rgba(255, 255, 255, 0.4) 94%, #0000) calc(-1*var(--R)) 0,
            radial-gradient(farthest-side, var(--fi-color-primary-400, #38bdf8) 94%, #0000) calc(var(--R) - 0.866*var(--R) - var(--R)) calc(var(--R) + 0.5*var(--R) - var(--R)),
            radial-gradient(farthest-side, rgba(255, 255, 255, 0.25) 94%, #0000) calc(var(--R) + 0.866*var(--R) - var(--R)) calc(var(--R) + 0.5*var(--R) - var(--R));
        background-size: calc(2*var(--R)) calc(2*var(--R));
        background-repeat: no-repeat;
    }

    @keyframes fi-loader-spin {
        100% {
            transform: rotate(1turn);
        }
    }

    @keyframes fi-fade-in {
        from {
            opacity: 0;
            transform: translateY(6px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Network topology widget: a static org-chart-style diagram, not a
       live device map (Section 13 - real tunnel/device health stays a
       Phase 2 OPNsense/UniFi API integration). Sits inside the glass
       .fi-wi-widget .fi-section above, so its own node colors are plain
       (not glass) - only text colors here need to stay theme-aware. */
    .zc-topology {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding-block: 1rem;
    }

    .zc-topo-node {
        background-color: var(--zc-surface-hover);
        border: 1px solid var(--zc-border);
        border-radius: var(--zc-radius-md);
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        color: var(--zc-text-strong);
        text-align: center;
    }

    .zc-topo-line--vertical {
        width: 2px;
        height: 1.5rem;
        background-color: var(--zc-border);
    }

    .zc-topo-line--short {
        height: 1rem;
    }

    .zc-topo-branches {
        position: relative;
        display: flex;
        justify-content: center;
        gap: 1.5rem;
        flex-wrap: wrap;
        width: 100%;
        margin-top: 0;
    }

    .zc-topo-branches::before {
        content: "";
        position: absolute;
        top: 0;
        left: 12%;
        right: 12%;
        height: 2px;
        background-color: var(--zc-border);
    }

    .zc-topo-branch {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .zc-topo-node--vlan {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 10rem;
        text-decoration: none;
        transition: border-color 0.15s ease, transform 0.15s ease;
    }

    .zc-topo-node--vlan:hover {
        border-color: var(--fi-color-primary-400, #38bdf8);
        transform: translateY(-2px);
    }

    .zc-topo-vlan-id {
        font-size: 0.9375rem;
        font-weight: 700;
        color: var(--fi-color-primary-400, #38bdf8);
    }

    .zc-topo-vlan-detail {
        font-size: 0.75rem;
        color: var(--zc-text-muted);
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    }

    .zc-topo-vlan-counts {
        margin-top: 0.375rem;
        display: flex;
        gap: 0.25rem;
        flex-wrap: wrap;
        justify-content: center;
    }

    .zc-topo-badge {
        font-size: 0.6875rem;
        font-weight: 600;
        padding: 0.125rem 0.5rem;
        border-radius: 999px;
    }

    /* Live session count (Section 16.6/16's Network Topology enhancement,
       2026-07-28) - distinct sky accent so it reads as "happening right
       now" rather than the green/gray/muted registry-state badges below,
       which only ever reflect what's stored, not what's connected. */
    .zc-topo-badge--live {
        background-color: rgba(56, 189, 248, 0.15);
        color: #0ea5e9;
    }

    .zc-topo-badge--active {
        background-color: rgba(34, 197, 94, 0.15);
        color: #16a34a;
    }

    .zc-topo-badge--disabled {
        background-color: rgba(148, 163, 184, 0.15);
        color: #64748b;
    }

    .zc-topo-badge--empty {
        background-color: rgba(148, 163, 184, 0.1);
        color: var(--zc-text-muted);
    }

    .dark .zc-topo-badge--live {
        color: #38bdf8;
    }

    .dark .zc-topo-badge--active {
        color: #4ade80;
    }

    .dark .zc-topo-badge--disabled {
        color: #94a3b8;
    }

    /* "About Developer" page (App\Filament\Pages\AboutDeveloper, client
       request 2026-07-28) - in-panel counterpart to the public /about
       page, same eye-catching gradient treatment adapted to the admin
       panel's own --zc-*/--fi-color-primary-* variables rather than the
       public site's separate --accent/--secondary palette. */
    .zc-about-hero {
        text-align: center;
        max-width: 40rem;
        margin: 0 auto;
    }

    .zc-about-avatar {
        width: 5rem;
        height: 5rem;
        margin: 0 auto;
        display: block;
        border-radius: 999px;
        object-fit: cover;
        background: linear-gradient(135deg, var(--fi-color-primary-400, #38bdf8) 0%, #34d399 100%);
        box-shadow: 0 0 0 6px rgba(56, 189, 248, 0.15), 0 20px 40px -12px rgba(56, 189, 248, 0.5);
    }

    .zc-about-name {
        margin-top: 1.25rem;
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--zc-text-strong);
    }

    .zc-about-role {
        margin-top: 0.25rem;
        font-size: 1rem;
        font-weight: 600;
        background: linear-gradient(90deg, var(--fi-color-primary-400, #38bdf8), #34d399);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .zc-about-tagline {
        margin-top: 0.5rem;
        color: var(--zc-text-muted);
    }

    .zc-about-columns {
        margin-top: 2.5rem;
        display: grid;
        gap: 1.5rem;
        grid-template-columns: 1fr;
    }

    @media (min-width: 768px) {
        .zc-about-columns { grid-template-columns: repeat(2, 1fr); }
    }

    .zc-about-col {
        border-radius: var(--zc-radius-md);
        border: 1px solid var(--zc-border);
        background-color: var(--zc-surface);
        padding: 1.5rem;
        transition: border-color 0.2s ease, transform 0.2s ease;
    }

    .zc-about-col:hover {
        transform: translateY(-2px);
    }

    .zc-about-col--accent:hover {
        border-color: rgba(56, 189, 248, 0.45);
    }

    .zc-about-col--secondary:hover {
        border-color: rgba(52, 211, 153, 0.45);
    }

    .zc-about-col h3 {
        margin: 0;
        font-size: 1rem;
        color: var(--zc-text-strong);
    }

    .zc-about-col p {
        margin-top: 0.5rem;
        font-size: 0.875rem;
        color: var(--zc-text-muted);
    }

    .zc-about-bio {
        margin-top: 2rem;
        max-width: 40rem;
        margin-inline: auto;
        text-align: center;
        color: var(--zc-text-muted);
    }

    .zc-about-skills {
        margin-top: 2rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
    }

    .zc-skill-pill {
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid rgba(56, 189, 248, 0.3);
        background-color: rgba(56, 189, 248, 0.1);
        color: #0284c7;
    }

    .dark .zc-skill-pill {
        color: #7dd3fc;
    }

    .zc-skill-pill--secondary {
        border-color: rgba(52, 211, 153, 0.3);
        background-color: rgba(52, 211, 153, 0.1);
        color: #059669;
    }

    .dark .zc-skill-pill--secondary {
        color: #6ee7b7;
    }

    .zc-social-links {
        margin-top: 2rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
    }

    .zc-social-pill {
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1px solid var(--zc-border);
        background-color: var(--zc-surface-hover);
        color: var(--zc-text-strong);
        text-decoration: none;
        transition: border-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }

    .zc-social-pill:hover {
        border-color: var(--fi-color-primary-400, #38bdf8);
        color: var(--fi-color-primary-400, #38bdf8);
        transform: translateY(-2px);
    }

    @media (prefers-reduced-motion: reduce) {

        .fi-section,
        .fi-wi-widget,
        .fi-wi-stats-overview-stat,
        .fi-btn,
        .fi-icon-btn,
        .fi-ta-row,
        .fi-sidebar-item-btn,
        .fi-main,
        .zc-topo-node--vlan {
            animation: none;
            transition: none;
        }
    }
</style>
