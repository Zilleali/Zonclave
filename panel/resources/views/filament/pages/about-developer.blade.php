<x-filament-panels::page>
    <x-filament::section>
        <div class="zc-about-hero">
            <img src="{{ asset('images/zilleali-avatar.jpg') }}" alt="ZILL E ALI" class="zc-about-avatar" width="80" height="80">
            <p class="zc-about-name">ZILL E ALI</p>
            <p class="zc-about-role">Developer &amp; Network Engineer</p>
            <p class="zc-about-tagline">Mikrotik Certified. Building Developer Zon products - from the network layer up.</p>
        </div>

        <div class="zc-about-columns">
            <div class="zc-about-col zc-about-col--accent">
                <h3>Network Engineering</h3>
                <p>
                    VLAN design, WireGuard tunnel policy routing, and OPNsense firewall architecture - built
                    fail-closed by default, so a dropped tunnel never silently falls back to a shared connection.
                    Mikrotik Certified in routing and switching.
                </p>
            </div>

            <div class="zc-about-col zc-about-col--secondary">
                <h3>Software Development</h3>
                <p>
                    Laravel + Filament admin panels backed by an authoritative PostgreSQL registry, FreeRADIUS
                    integration for authentication and VLAN assignment, and one-command installers that bring a
                    whole node up configured and ready.
                </p>
            </div>
        </div>

        <p class="zc-about-bio">
            This panel - and the network behind it - is built and maintained end to end: the architecture that keeps
            every device group isolated on its own tunnel, and the software that makes managing a hundred of those
            groups as easy as managing one.
        </p>

        <div class="zc-about-skills">
            <span class="zc-skill-pill">Mikrotik Certified</span>
            <span class="zc-skill-pill">OPNsense</span>
            <span class="zc-skill-pill">WireGuard</span>
            <span class="zc-skill-pill">FreeRADIUS</span>
            <span class="zc-skill-pill zc-skill-pill--secondary">Laravel</span>
            <span class="zc-skill-pill zc-skill-pill--secondary">Filament</span>
            <span class="zc-skill-pill zc-skill-pill--secondary">PostgreSQL</span>
            <span class="zc-skill-pill zc-skill-pill--secondary">UniFi</span>
        </div>

        <x-social-links container-class="zc-social-links" link-class="zc-social-pill" />
    </x-filament::section>
</x-filament-panels::page>
