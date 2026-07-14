@extends('layouts.public')

@section('title', 'Zonclave - One SSID, dozens of private tunnels')
@section('description', 'Zonclave maps every Wi-Fi credential to its own VLAN and its own WireGuard tunnel, so each device group egresses through its own residential IP - fail-closed, fully auditable, managed from one panel.')

@section('content')

    {{-- Hero --}}
    <section class="hero">
        <div class="container">
            <p class="eyebrow">A Developer Zon product</p>
            <h1>One Wi-Fi network. <span class="accent">Dozens of private tunnels.</span></h1>
            <p>
                Zonclave turns a single SSID into many independently-routed, independently-secured device groups.
                Each unique Wi-Fi password maps to its own VLAN and its own WireGuard tunnel, so each group egresses
                the internet through its own distinct residential IP - managed entirely from one clean admin panel.
            </p>
            <div class="hero-actions">
                <a href="mailto:hello@developerzon.com?subject=Zonclave%20inquiry" class="btn btn-primary">Get in touch</a>
                <a href="{{ url('/docs') }}" class="btn btn-secondary">View documentation</a>
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="section section-alt">
        <div class="container">
            <h2 class="section-title">How it works</h2>
            <p class="section-subtitle">Four steps, entirely automatic once a credential is provisioned - no manual routing per device.</p>

            <div class="step-grid">
                @foreach ([
                    ['n' => '1', 'title' => 'Unique password', 'body' => 'A device joins the SSID using a credential generated just for its group - never shared, never reused.'],
                    ['n' => '2', 'title' => 'RADIUS assigns a VLAN', 'body' => 'FreeRADIUS authenticates the credential and hands back that group’s dedicated VLAN. Nothing else.'],
                    ['n' => '3', 'title' => 'A private tunnel', 'body' => 'The VLAN is policy-routed through its own WireGuard tunnel - explicitly pinned, never a shared default route.'],
                    ['n' => '4', 'title' => 'Its own residential IP', 'body' => 'Traffic egresses through that tunnel’s residential exit - distinct from every other group on the network.'],
                ] as $step)
                    <div class="step-card">
                        <span class="step-number{{ $step['n'] === '4' ? ' secondary' : '' }}">{{ $step['n'] }}</span>
                        <h3>{{ $step['title'] }}</h3>
                        <p>{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <p class="note">If a tunnel ever drops, that group's traffic is dropped, too - never silently rerouted onto the plain internet connection.</p>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" class="section">
        <div class="container">
            <h2 class="section-title">Built for scale and for trust</h2>

            <div class="feature-grid">
                @foreach ([
                    ['icon' => 'shield-check', 'accent' => 'secondary', 'title' => 'Fail-closed by design', 'body' => 'A dropped tunnel means dropped traffic - never a silent fallback to the shared connection.'],
                    ['icon' => 'user-group', 'accent' => '', 'title' => 'Per-group isolation', 'body' => 'Every credential, VLAN, and tunnel is independent. One group can never see another’s traffic.'],
                    ['icon' => 'bolt', 'accent' => '', 'title' => 'One-click credentials', 'body' => 'Create, edit, disable, or delete a Wi-Fi credential from one panel - no router config editing.'],
                    ['icon' => 'chart-bar', 'accent' => '', 'title' => 'Scales past 100 groups', 'body' => 'The same pattern that provisions 5 tunnels provisions 100, with no architectural rework.'],
                    ['icon' => 'clipboard-document-list', 'accent' => '', 'title' => 'Full audit trail', 'body' => 'Every login and every credential change - who, what, when - logged and reviewable.'],
                    ['icon' => 'lock-closed', 'accent' => '', 'title' => 'Encrypted one-command install', 'body' => 'The auth and panel node deploys from a single encrypted, opaque command.'],
                ] as $feature)
                    <div class="feature-card">
                        <div class="feature-icon{{ $feature['accent'] ? ' '.$feature['accent'] : '' }}">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                @switch($feature['icon'])
                                    @case('shield-check')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a4.5 4.5 0 0 1-1.318 3.182l-5.16 5.16a4.5 4.5 0 0 0-1.318 3.182v1.044c0 .54-.384 1.006-.917 1.096a48.32 48.32 0 0 1-3.674.578" />
                                        @break
                                    @case('user-group')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                        @break
                                    @case('bolt')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5 10.5 3l-1.5 6.75h9L10.5 21l1.5-6.75h-9Z" />
                                        @break
                                    @case('chart-bar')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                        @break
                                    @case('clipboard-document-list')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                                        @break
                                    @case('lock-closed')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                        @break
                                @endswitch
                            </svg>
                        </div>
                        <h3>{{ $feature['title'] }}</h3>
                        <p>{{ $feature['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Screenshots (stylized mockups - swap for real screenshots when available) --}}
    <section class="section section-alt">
        <div class="container">
            <h2 class="section-title">The admin panel</h2>
            <p class="section-subtitle">Every credential, every VLAN mapping, every action - one panel, no config editing.</p>

            <div class="mockup-grid">
                <div class="mockup-card">
                    <div class="mockup-dots"><span></span><span></span><span></span></div>
                    <div class="mockup-stats">
                        <div class="mockup-stat"><div class="mockup-bar accent" style="width:2.5rem"></div><div class="mockup-bar" style="width:2rem;margin-top:0.5rem"></div></div>
                        <div class="mockup-stat"><div class="mockup-bar good" style="width:2.5rem"></div><div class="mockup-bar" style="width:2rem;margin-top:0.5rem"></div></div>
                        <div class="mockup-stat"><div class="mockup-bar neutral" style="width:2.5rem"></div><div class="mockup-bar" style="width:2rem;margin-top:0.5rem"></div></div>
                    </div>
                    <div class="mockup-block"></div>
                    <p class="mockup-caption">Dashboard</p>
                </div>

                <div class="mockup-card">
                    <div class="mockup-dots"><span></span><span></span><span></span></div>
                    @for ($i = 0; $i < 4; $i++)
                        <div class="mockup-row">
                            <div class="mockup-bar fill"></div>
                            <div class="mockup-bar" style="width:2rem"></div>
                            <div class="mockup-bar {{ $i === 2 ? 'neutral' : 'good' }}" style="width:1.5rem"></div>
                        </div>
                    @endfor
                    <p class="mockup-caption">PPSK groups</p>
                </div>

                <div class="mockup-card">
                    <div class="mockup-dots"><span></span><span></span><span></span></div>
                    @for ($i = 0; $i < 4; $i++)
                        <div class="mockup-row">
                            <div class="mockup-bar" style="width:4rem"></div>
                            <div class="mockup-bar accent fill"></div>
                        </div>
                    @endfor
                    <p class="mockup-caption">Admin log</p>
                </div>
            </div>
            <p class="note">Stylized previews - actual panel screenshots coming soon.</p>
        </div>
    </section>

    {{-- About --}}
    <section id="about" class="section">
        <div class="container about">
            <h2 class="section-title">Built by</h2>
            <p class="name">ZILL E ALI</p>
            <p class="role">Developer &amp; Network Engineer, Developer Zon</p>
            <p class="bio">
                Zonclave is built and maintained end to end - from the network architecture (VLANs, WireGuard, firewall policy)
                to the software that manages it (the admin panel, the registry, the installer).
            </p>
        </div>
    </section>

    {{-- Documentation --}}
    <section class="section section-alt">
        <div class="container cta">
            <h2 class="section-title">Documentation</h2>
            <p class="section-subtitle">Full setup instructions and a complete command reference.</p>
            <div class="hero-actions">
                <a href="{{ url('/docs') }}" class="btn btn-primary">Browse the docs</a>
            </div>
        </div>
    </section>

@endsection
