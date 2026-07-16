@extends('layouts.public')

@section('title', 'Command Reference - Zonclave')
@section('description', 'Every command used to develop, test, and install Zonclave, grouped by environment.')

@section('content')

    <article class="doc-article">
        <a href="{{ url('/docs') }}" class="doc-back">&larr; Documentation</a>

        <h1>Command Reference</h1>
        <p class="doc-lede">
            Every command used across this project, grouped by which machine you run it on - a cheat sheet, not a
            tutorial.
        </p>

        <div class="env-grid">
            <div class="env-card">
                <p>Windows</p>
                <p>Your dev machine, for building/testing the panel.</p>
            </div>
            <div class="env-card">
                <p>Ubuntu Server 24.04</p>
                <p>The production host running the installer and hosting FreeRADIUS/PostgreSQL/the panel.</p>
            </div>
            <div class="env-card">
                <p>Router (FreeBSD shell)</p>
                <p>The network appliance, for manual network configuration verification steps.</p>
            </div>
        </div>

        <div class="doc-body">

            <section>
                <h2>Windows (panel development)</h2>

                <h3>One-time setup</h3>
                <pre># Either use a package manager...
winget install PHP.PHP.8.3
winget install Composer.Composer
# ...or Chocolatey
choco install php composer -y</pre>
                <p>Enable required PHP extensions in <code>php.ini</code>: <code>mbstring</code>, <code>curl</code>, <code>zip</code>, <code>intl</code>, <code>pdo_sqlite</code>, <code>sqlite3</code>, <code>fileinfo</code>, <code>openssl</code>.</p>

                <h3>Panel setup</h3>
                <pre>cd panel
composer install
copy .env.example .env    # Git Bash: cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan panel:create-admin --email=you@example.com --password=&lt;choose-one&gt;
php artisan serve   # panel at http://127.0.0.1:8000/admin</pre>

                <h3>Quality gates (run all three before committing)</h3>
                <pre>php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse</pre>

                <h3>Building the encrypted installer package</h3>
                <p>Works in Git Bash on Windows (uses <code>git archive</code>, not <code>rsync</code>):</p>
                <pre>bash installer/package.sh --passphrase '&lt;choose one&gt;'
# outputs installer/dist/zonclave-installer.enc and installer/dist/run.sh</pre>

                <h3>Git workflow</h3>
                <pre>git status
git add &lt;files&gt;
git commit -m "type: summary"
git push origin develop</pre>
            </section>

            <section>
                <h2>Ubuntu Server 22.04 (production auth + panel node)</h2>

                <h3>Running the installer</h3>
                <pre>sudo bash installer/install-ubuntu22.04.sh
# or non-interactive, reading a prepared answers file:
sudo bash installer/install-ubuntu22.04.sh --config installer.conf</pre>

                <h3>Running the encrypted installer</h3>
                <pre>sudo bash run.sh
# with a prepared installer.conf forwarded to it:
sudo bash run.sh -- --config installer.conf</pre>

                <h3>Service checks</h3>
                <pre>systemctl status postgresql
systemctl status freeradius
systemctl status nginx
systemctl status php8.3-fpm
nginx -t                          # test nginx config without reloading</pre>

                <h3>Database</h3>
                <pre>sudo -u postgres psql -d ppsk     # ppsk is the default DB_NAME</pre>

                <h3>FreeRADIUS auth smoke test</h3>
                <pre>radtest ppsk_group001 '&lt;the seeded PSK&gt;' 127.0.0.1 0 '&lt;RADIUS shared secret&gt;'
# Expect: Access-Accept in the response</pre>

                <h3>Panel HTTP check</h3>
                <pre>curl -I http://127.0.0.1/admin/login
# Expect: HTTP/1.1 200 OK</pre>
            </section>

            <section>
                <h2>Router (FreeBSD shell, over SSH or console)</h2>
                <p>The router's GUI is used for the actual network creation steps; these commands are for confirming the result.</p>

                <h3>Identify the real NIC driver name</h3>
                <pre>ifconfig -a
pciconf -lv | grep -B4 network    # match driver name to chipset</pre>

                <h3>Confirm a VLAN interface is up and tagged</h3>
                <pre>ifconfig | grep -A3 vlan300</pre>

                <h3>WireGuard tunnel status</h3>
                <pre>wg show WG_VLAN300              # confirm a non-blank "latest handshake"</pre>

                <h3>Gateway status</h3>
                <pre>configctl interface routes</pre>

                <h3>Inspect the compiled firewall ruleset</h3>
                <pre>pfctl -a 'filter/VLAN300' -sr</pre>

                <h3>Kill-switch test</h3>
                <pre>wg set WG_VLAN300 down
curl -m 5 -s -o /dev/null -w '%{http_code}\n' https://ifconfig.me   # expect a timeout, not a real IP
wg set WG_VLAN300 up</pre>
            </section>

            <section class="card doc-cta">
                <h2 style="font-size:1.125rem">Need the full walkthrough?</h2>
                <div class="hero-actions" style="margin-top:1rem">
                    <a href="{{ url('/docs/installation-guide') }}" class="btn btn-primary">Read the installation guide</a>
                </div>
            </section>

        </div>
    </article>

@endsection
