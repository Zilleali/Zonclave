{{-- Shared across the public /about page, the admin panel's "About
     Developer" page, and the public site footer (client request
     2026-07-28) - reads from config/socials.php, the single source of
     truth, so the three call sites can never drift apart. Text labels
     rather than brand logo glyphs, deliberately - there's no reliable way
     to hand-reproduce every platform's exact trademarked icon here, and a
     slightly-wrong logo is worse than a clear text label. Admin usage
     passes different class names ($linkClass/$containerClass) since the
     admin panel's Filament/Tailwind styling and the public site's plain
     hand-written CSS are two entirely separate stylesheets. --}}
@props(['containerClass' => 'social-links', 'linkClass' => 'social-pill'])

<div {{ $attributes->class([$containerClass]) }}>
    @foreach (config('socials') as $social)
        <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="{{ $linkClass }}">{{ $social['label'] }}</a>
    @endforeach
</div>
