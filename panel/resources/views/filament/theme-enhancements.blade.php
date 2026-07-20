{{-- Visual polish injected via render hook, not a custom Filament theme -
     the production installer never runs an npm/asset build (see the public
     layout's own comment on this), so a compiled Tailwind theme isn't an
     option here. Plain CSS overrides on Filament's existing fi- classes,
     same delivery mechanism as clipboard-script.blade.php.

     MUI-dark-inspired restyle (client request 2026-07-18): dark mode is
     forced panel-wide (AdminPanelProvider ->darkMode(true, isForced: true)),
     restyled here to approximate a Material-UI dark dashboard look -
     elevated card fills, pill-style active nav item, rounded corners,
     generous spacing - on top of Filament's own components. This is a
     CSS-only restyle, not a framework migration: Filament + Livewire stays
     the actual implementation (CLAUDE.md Section 16 tech-stack decision
     unchanged), so it won't be pixel-identical to a real MUI app, but gets
     visually close without new dependencies or a build step. --}}
<style>
    :root {
        --zc-bg: oklch(0.141 0.005 285.823);
        --zc-surface: oklch(0.19 0.007 285.823);
        --zc-surface-hover: oklch(0.225 0.008 285.823);
        --zc-border: oklch(1 0 0 / 8%);
        --zc-radius-lg: 1rem;
        --zc-radius-md: 0.75rem;
    }

    /* Elevation via a lighter fill plus a deliberately deep drop shadow -
       plain 1px shadows barely read on a near-black background, so this
       goes with MUI's own "elevation 24" style shadow instead (client
       request 2026-07-18: the original subtle shadow wasn't visible
       enough). */
    .fi-section,
    .fi-wi-widget,
    .fi-topbar-ctn,
    .fi-dropdown-panel {
        background-color: var(--zc-surface) !important;
        border-color: var(--zc-border) !important;
        border-radius: var(--zc-radius-lg) !important;
        transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        box-shadow: rgba(0, 0, 0, 0.3) 0px 19px 38px, rgba(0, 0, 0, 0.22) 0px 15px 12px;
    }

    .fi-section:hover {
        background-color: var(--zc-surface-hover) !important;
        transform: translateY(-2px);
    }

    .fi-section-content,
    .fi-wi-widget {
        border-radius: var(--zc-radius-lg) !important;
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

    @media (prefers-reduced-motion: reduce) {

        .fi-section,
        .fi-wi-widget,
        .fi-btn,
        .fi-icon-btn,
        .fi-ta-row,
        .fi-sidebar-item-btn,
        .fi-main {
            animation: none;
            transition: none;
        }
    }
</style>
