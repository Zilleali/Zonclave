{{-- Panel-wide attribution footer (client request 2026-07-25), rendered
     via the FOOTER hook so it appears on both the login page and every
     authenticated page (Filament's simple.blade.php and index.blade.php
     layouts both render this hook - see panel/vendor/filament/filament/
     resources/views/components/layout/{simple,index}.blade.php).

     Uses the same --zc-text-strong/--zc-text-muted variables
     theme-enhancements.blade.php defines - hardcoded white text here was
     invisible on light backgrounds once dark mode stopped being forced
     panel-wide (2026-07-28), a bug this design never surfaced before that
     change. --}}
<footer class="px-6 py-4 text-center text-xs" style="color: var(--zc-text-muted);">
    Peng Balous is developed by <span style="color: var(--zc-text-strong);">Mikrotik Certified ZILLEALI</span>,
    and is a product of <span style="color: var(--zc-text-strong);">Developer Zon</span>.
</footer>
