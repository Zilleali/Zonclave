@extends('layouts.public')

@section('title', 'Installation Guide - Zonclave')
@section('description', 'The complete start-to-finish manual for Zonclave: panel setup, production installation, and how to use it day to day.')

@section('content')

    <article class="doc-article">
        <a href="{{ url('/docs') }}" class="doc-back">&larr; Documentation</a>

        <h1>Installation Guide</h1>
        <p class="doc-lede">
            A complete, start-to-finish manual for Zonclave: what it is, how to set up the panel for development, how to
            install it in production, and how to actually use it day to day.
        </p>

        <div class="doc-body">

            <section>
                <h2>What Zonclave is</h2>
                <p>
                    Zonclave lets a single Wi-Fi SSID accept many unique pre-shared keys (PPSKs). Each PPSK maps to a
                    dedicated VLAN. Each VLAN is policy-routed through its own dedicated WireGuard tunnel to a
                    residential VPN provider, so each group of devices egresses to the internet through its own
                    distinct public ISP IP address.
                </p>
                <p>Two layers make this work, and both matter equally:</p>
                <ul>
                    <li><strong>The network layer</strong>: access points authenticate devices against a RADIUS server, which hands back a VLAN assignment. The router routes each VLAN through its own WireGuard tunnel, with a fail-closed firewall policy - if a tunnel drops, that VLAN's traffic is dropped, never silently rerouted onto the plain internet connection.</li>
                    <li><strong>The software layer</strong>: the Zonclave panel is where an administrator creates, edits, enables/disables, and deletes PPSK credentials. Every credential's mapping to a VLAN flows through one authoritative registry and one transactional write path - nothing else touches the RADIUS tables directly.</li>
                </ul>
                <p>
                    The RADIUS layer only ever does authentication and VLAN assignment. The router only ever does
                    routing, firewalling, and VPN policy. That boundary is never blurred.
                </p>
            </section>

            <section>
                <h2>Prerequisites</h2>
                <ul>
                    <li><strong>For panel development</strong>: PHP 8.2+ (with <code>mbstring</code>, <code>xml</code>, <code>curl</code>, <code>zip</code>, <code>intl</code>, <code>sqlite3</code>/<code>pdo_sqlite</code>), Composer 2. Works on Windows or Linux.</li>
                    <li><strong>For production installation</strong>: a dedicated Ubuntu Server 24.04 LTS host. Root access.</li>
                    <li><strong>For the network side</strong>: a compatible router and a Wi-Fi controller/AP deployment, both already physically installed.</li>
                </ul>
            </section>

            <section>
                <h2>Setting up the panel for development</h2>
                <pre>cd panel
composer install
cp .env.example .env          # Windows PowerShell: copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan panel:create-admin --email=you@example.com --password=&lt;choose-one&gt;
php artisan serve</pre>
                <p>
                    Visit <code>http://127.0.0.1:8000/admin</code> and log in with the admin account you just created.
                    This runs on SQLite locally - no PostgreSQL needed for development.
                </p>
                <p>Before committing any change, run all three quality gates:</p>
                <pre>php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse</pre>
                <p>All three must pass clean. This is not optional.</p>

                <h3>What you can actually do in the panel</h3>
                <ul>
                    <li><strong>Dashboard</strong>: stat cards for total/active/disabled PPSK groups, each clickable through to a pre-filtered list, plus a 7-day registry-growth chart.</li>
                    <li><strong>PPSK Groups</strong>: the inventory list. Create a new PPSK (label + VLAN, password is always generated, never typed in), edit a group's label/VLAN, enable/disable, delete, or regenerate a group's password. Every PSK is shown exactly once, with a copy-to-clipboard button.</li>
                    <li><strong>Admin Log</strong>: a read-only audit trail of every login and every PPSK create/edit/enable/disable/delete/regenerate action. Append-only by design.</li>
                    <li><strong>Profile</strong>: change the admin password.</li>
                </ul>
            </section>

            <section>
                <h2>Installing in production</h2>
                <p>
                    The installer provisions <strong>one host</strong>: PostgreSQL, FreeRADIUS, and
                    the Zonclave panel, with all secrets generated at runtime and printed once. It is Linux-only by
                    design (Ubuntu Server 24.04 LTS).
                </p>

                <h3>Plain install</h3>
                <pre>sudo bash installer/install.sh</pre>
                <p>
                    You'll be prompted for the AP subnet and an admin email; everything else is generated
                    automatically. Or, for a fully non-interactive run, prepare an <code>installer.conf</code> and run:
                </p>
                <pre>sudo bash installer/install.sh --config installer.conf</pre>
                <p>
                    At the end, the installer prints (and saves to a root-only file) the panel URL, the admin login,
                    the RADIUS shared secret, and the seeded test credentials. <strong>Write these
                    down or save the summary file securely - they are shown once.</strong>
                </p>

                <h3>Encrypted delivery</h3>
                <p>For handing this to a client, or running it yourself over SSH as a single opaque command:</p>
                <pre>bash installer/package.sh --passphrase '&lt;choose one&gt;'
# outputs installer/dist/zonclave-installer.enc and installer/dist/run.sh</pre>
                <p>
                    Deliver both output files together. Deliver the passphrase <strong>separately</strong>,
                    over a different channel - never in the same message as the files. The recipient runs:
                </p>
                <pre>sudo bash run.sh</pre>
                <p>
                    This is tamper-friction and casual protection of the install method, not a secrecy guarantee -
                    anyone with root on the target can recover the decrypted installer at runtime regardless.
                </p>

                <h3>What the installer does not do</h3>
                <p>
                    It configures the auth + panel node only. It does not touch the router or Wi-Fi controller -
                    those are separate appliances, configured through a documented manual process.
                </p>
            </section>

            <section>
                <h2>Verifying it all actually works</h2>
                <p>
                    A full deployment is only proven by a real acceptance test:
                    <strong>a real device connects with a PPSK, lands on the correct VLAN, and its
                    outbound public IP matches that VLAN's residential WireGuard tunnel.</strong> No amount of passing
                    automated tests substitutes for this - the automated suite protects the software layer; only a
                    real device on real hardware proves the network layer.
                </p>
                <p>
                    In short, per PPSK group: provision it, connect a device, confirm the VLAN and egress IP, disable
                    it and confirm it stops working, delete it and confirm it's gone, kill its tunnel and confirm the
                    device loses internet (not falls back to the real connection), confirm VLAN isolation from other
                    groups, run a DNS leak test, and confirm every action left a row in the Admin Log.
                </p>
            </section>

            <section class="card doc-cta">
                <h2 style="font-size:1.125rem">Questions about deploying Zonclave?</h2>
                <div class="hero-actions" style="margin-top:1rem">
                    <a href="mailto:hello@developerzon.com?subject=Zonclave%20inquiry" class="btn btn-primary">Get in touch</a>
                </div>
            </section>

        </div>
    </article>

@endsection
