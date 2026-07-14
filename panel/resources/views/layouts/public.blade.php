<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', 'Zonclave') - Developer Zon</title>
        <meta name="description" content="@yield('description', 'Zonclave turns one Wi-Fi network into dozens of independently-routed, independently-secured tunnels, each with its own residential exit IP.')">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        {{-- Plain static stylesheet, not the Vite/Tailwind pipeline: the
             production installer never runs an npm/asset build (only the
             Filament admin panel ships its own separately-built assets),
             so these public pages must render correctly with zero build
             step in every environment. --}}
        <link rel="stylesheet" href="{{ asset('css/public.css') }}">
    </head>
    <body>
        <input type="checkbox" id="nav-toggle" class="nav-toggle">

        <header class="site-header">
            <div class="container">
                <a href="{{ url('/') }}" class="wordmark">Zon<span>clave</span></a>

                <nav class="nav-links">
                    <a href="{{ url('/#how-it-works') }}">How it works</a>
                    <a href="{{ url('/#features') }}">Features</a>
                    <a href="{{ url('/docs') }}">Documentation</a>
                    <a href="{{ url('/#about') }}">About</a>
                    <a href="mailto:hello@developerzon.com?subject=Zonclave%20inquiry" class="btn btn-primary btn-sm">Get in touch</a>
                </nav>

                <label for="nav-toggle" class="nav-toggle-label" aria-label="Toggle menu">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </label>
            </div>

            <nav class="mobile-nav">
                <a href="{{ url('/#how-it-works') }}">How it works</a>
                <a href="{{ url('/#features') }}">Features</a>
                <a href="{{ url('/docs') }}">Documentation</a>
                <a href="{{ url('/#about') }}">About</a>
                <a href="mailto:hello@developerzon.com?subject=Zonclave%20inquiry" class="btn btn-primary btn-sm">Get in touch</a>
            </nav>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="site-footer">
            <div class="container">
                <p>&copy; {{ date('Y') }} Zonclave, a Developer Zon product.</p>
                <div class="footer-links">
                    <a href="{{ url('/docs') }}">Documentation</a>
                    <a href="mailto:hello@developerzon.com?subject=Zonclave%20inquiry">Contact</a>
                </div>
            </div>
        </footer>
    </body>
</html>
