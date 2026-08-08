{{-- Decorative cursor trail (client request 2026-08-08), same shared
     public/js/cursor-trail.js the public site uses. Color matches this
     panel's own primary accent (--fi-color-primary-400, the same sky used
     throughout theme-enhancements.blade.php) rather than the public
     site's separate palette variable.

     Tuned slower and more minimal than the public site's default
     (client feedback: the default felt too fast/busy for the admin
     panel) - fewer strands, a shorter tail, and a much lower spring
     constant, which is what actually controls how quickly the trail
     catches up to the pointer (lower = slower/laggier, not a frame-rate
     change). --}}
<script>
    {{-- Canvas strokeStyle can't parse a raw var() expression, so resolve it
         to an actual color value here before the shared script reads it. --}}
    window.zcCursorTrailColor = (
        getComputedStyle(document.documentElement).getPropertyValue('--fi-color-primary-400').trim()
        || '#38bdf8'
    );
    window.zcCursorTrailWormCount = 4;
    window.zcCursorTrailNodeCount = 16;
    window.zcCursorTrailSpring = 0.09;
    window.zcCursorTrailLineWidth = 1;
</script>
<script src="{{ asset('js/cursor-trail.js') }}"></script>
