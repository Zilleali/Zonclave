@extends('layouts.public')

@section('title', 'About')
@section('description', 'ZILL E ALI - Developer & Network Engineer at Developer Zon, and the person who builds and maintains Zonclave end to end.')

@section('content')

    {{-- About hero (client request 2026-07-28: its own dedicated page,
         expanded from the landing page's short "Built by" teaser, with an
         eye-catching gradient color treatment). --}}
    <section class="section">
        <div class="container">
            <div class="about-hero">
                <div class="about-avatar">ZA</div>
                <p class="name">ZILL E ALI</p>
                <p class="role">Developer &amp; Network Engineer</p>
                <p class="tagline">Mikrotik Certified. Building Developer Zon products - from the network layer up.</p>
            </div>

            <div class="about-columns">
                <div class="about-col accent-border">
                    <h3>Network Engineering</h3>
                    <p>
                        VLAN design, WireGuard tunnel policy routing, and OPNsense firewall architecture - built
                        fail-closed by default, so a dropped tunnel never silently falls back to a shared connection.
                        Mikrotik Certified in routing and switching.
                    </p>
                </div>

                <div class="about-col secondary-border">
                    <h3>Software Development</h3>
                    <p>
                        Laravel + Filament admin panels backed by an authoritative PostgreSQL registry, FreeRADIUS
                        integration for authentication and VLAN assignment, and one-command installers that bring a
                        whole node up configured and ready.
                    </p>
                </div>
            </div>

            <div class="about-bio">
                <p>
                    Zonclave is built and maintained end to end - the network architecture that keeps every device
                    group isolated on its own tunnel, and the software that makes managing a hundred of those groups
                    as easy as managing one. Both sides matter equally: a change that's clean in code but leaks
                    traffic on the wire is a failure, and a change that routes correctly but bypasses the software's
                    own registry is just as much of one.
                </p>
            </div>

            <div class="skill-pills">
                <span class="skill-pill">Mikrotik Certified</span>
                <span class="skill-pill">OPNsense</span>
                <span class="skill-pill">WireGuard</span>
                <span class="skill-pill">FreeRADIUS</span>
                <span class="skill-pill secondary">Laravel</span>
                <span class="skill-pill secondary">Filament</span>
                <span class="skill-pill secondary">PostgreSQL</span>
                <span class="skill-pill secondary">UniFi</span>
            </div>

            <x-social-links />

            <div class="hero-actions">
                <a href="mailto:zilleali1245@gmail.com?subject=Zonclave%20inquiry" class="btn btn-primary">Get in touch</a>
                <a href="{{ url('/') }}" class="btn btn-secondary">See Zonclave</a>
            </div>
        </div>
    </section>

@endsection
