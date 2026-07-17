@extends('layouts.public')

@section('title', 'Troubleshooting - Zonclave')
@section('description', 'Real issues hit deploying Zonclave, with the actual symptom, root cause, and fix for each.')

@section('content')

    <article class="doc-article">
        <a href="{{ url('/docs') }}" class="doc-back">&larr; Documentation</a>

        <h1>Troubleshooting</h1>
        <p class="doc-lede">
            Real issues hit deploying and testing Zonclave at Office SancoMedia Kelder, each with the actual
            symptom, root cause, and fix - not hypothetical failure modes. Every one of these looked, at first
            glance, like a different and more serious problem than it actually was.
        </p>

        <div class="doc-body">

            <section>
                <h2>Panel shows old UI/UX after pulling new code on the server</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> <code>git pull</code> succeeds in the repo checkout, but the panel in the browser still shows the previous version.</p>
                    <p><strong>Cause:</strong> the served copy lives at <code>/opt/zonclave</code>, separate from the git checkout at <code>/var/www/Zonclave</code>. A <code>git pull</code> alone only updates the checkout - nothing re-syncs it to the served path, and Laravel's config/route/view caches (built at deploy time) also don't invalidate themselves.</p>
                    <p><strong>Fix:</strong> <code>sudo zonclave update</code> - pulls the checkout, resyncs to <code>/opt/zonclave</code>, runs migrations, and clears/rebuilds every cache in one step. See the installation guide's "Updating a running deployment" section.</p>
                </div>
            </section>

            <section>
                <h2>"no such table: sessions" / "no such table: cache" after a deploy</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> the panel throws <code>SQLSTATE[HY000]: General error: 1 no such table: sessions</code> or similar, and <code>APP_DEBUG=true</code> is visibly showing a full stack trace in the browser - even though production should have debug mode off.</p>
                    <p><strong>Cause:</strong> a stale <code>.env</code> - left over from an earlier manual/SQLite dev copy of the panel sitting in the git checkout - got copied over the real production <code>.env</code> (PostgreSQL) during a manual file copy. The app fell back to a SQLite connection pointing at a database file that doesn't have the expected tables, and <code>APP_DEBUG=true</code> being visible at all is itself the tell that the real production <code>.env</code> (which the installer always sets to <code>APP_DEBUG=false</code>) is not the one actually in use.</p>
                    <p><strong>Fix:</strong> re-run the actual installer (<code>sudo bash installer/install-ubuntu22.04.sh</code>) rather than any manual file-copy shortcut, and delete any stray <code>.env</code> sitting in the checkout's <code>panel/</code> directory so it can't get copied again. Both the installer's <code>deploy_panel()</code> and <code>zonclave update</code> now explicitly back up and restore the real <code>.env</code> around every file sync, so this specific failure mode can't recur.</p>
                </div>
            </section>

            <section>
                <h2>FreeRADIUS won't start / Postgres password mismatch after re-running the installer</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> <code>rlm_sql_postgresql: Connection failed... FATAL: password authentication failed for user "ppsk"</code>, and <code>sudo systemctl start freeradius</code> fails.</p>
                    <p><strong>Cause:</strong> the installer generates a fresh random <code>DB_PASSWORD</code>/<code>RADIUS_SECRET</code> on every run and is self-consistent within one <em>fully completed</em> run - but a partial or interrupted run (or one where an existing RADIUS client block is deliberately left untouched by its own "already present" guard) can leave Postgres, FreeRADIUS's config, and the printed install summary out of sync with each other.</p>
                    <p><strong>Fix:</strong> don't trust the latest install-summary.txt blindly after a partial re-run - check what's actually configured: <code>sudo grep -A4 "client ppsk_unifi" /etc/freeradius/3.0/clients.conf</code> shows the real active RADIUS secret, and comparing that against what Postgres actually has for the <code>ppsk</code> role's password (or simply re-running the installer to full completion) resolves the mismatch.</p>
                </div>
            </section>

            <section>
                <h2>Installer appears to die silently mid-run</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> the installer prints a stage header like <code>==== Installing dependencies ====</code>, then returns straight to the shell prompt with no error text at all.</p>
                    <p><strong>Cause:</strong> the script runs under <code>set -euo pipefail</code>, and most of its commands redirect output into the log file rather than the terminal - so a failing command kills the script immediately, but the actual error message went to the log, not the screen.</p>
                    <p><strong>Fix:</strong> <code>sudo tail -60 /var/log/ppsk-install.log</code> immediately after a silent failure - the real error is always at the tail end of that file.</p>
                </div>
            </section>

            <section>
                <h2>Device authenticates successfully but lands on the wrong network (flat LAN, not its assigned VLAN)</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> a WPA2-Enterprise/PEAP device (the standard Windows sign-in method for this SSID) connects, RADIUS authentication succeeds, and <code>radtest</code> against the same credential correctly returns <code>Tunnel-Private-Group-Id</code> - but the device still gets an IP on the flat LAN instead of its VLAN.</p>
                    <p><strong>Cause:</strong> PEAP resolves the real identity and does its MSCHAPv2 challenge/response inside an encrypted <strong>inner tunnel</strong>, separate from the outer RADIUS exchange. FreeRADIUS resolves the VLAN attribute correctly during that inner exchange but, by default, does not copy it out to the final outer Access-Accept. <code>radtest</code> can never catch this - it authenticates like plain PAP, not PEAP, so it never exercises the inner-tunnel code path at all.</p>
                    <p><strong>Fix:</strong> on the FreeRADIUS host, set <code>use_tunneled_reply = yes</code> inside the <code>peap { }</code> block of <code>/etc/freeradius/3.0/mods-available/eap</code>, then <code>sudo freeradius -CX &amp;&amp; sudo systemctl restart freeradius</code>. One-time, per-FreeRADIUS-host setting - not per VLAN or per PPSK. Confirm with <code>sudo freeradius -X</code> live debug that the final <code>Sent Access-Accept</code> (not an earlier Access-Challenge) actually carries the Tunnel attributes.</p>
                </div>
            </section>

            <section>
                <h2>WireGuard gateway shows Offline despite a live handshake</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> <code>wg show WG_VLAN300</code> confirms a recent handshake, but OPNsense's gateway status shows <code>GW_WG_VLAN300</code> as Offline.</p>
                    <p><strong>Cause:</strong> the gateway's Monitor IP was set to the WireGuard peer's own tunnel-internal address (<code>10.10.20.1</code>). Residential VPN providers generally don't answer ICMP on that address - it's a tunnel endpoint, not a router - so OPNsense's health check fails even though the tunnel itself is genuinely fine. The fail-closed firewall policy then correctly, but confusingly, blocks a tunnel that's actually healthy.</p>
                    <p><strong>Fix:</strong> in the gateway's edit form, set Monitor IP (a separate field from Gateway IP) to a real, always-up internet host reachable through the tunnel - <code>8.8.8.8</code> worked here. Confirmed Online afterward with a real ~13ms round trip and 0% loss.</p>
                </div>
            </section>

            <section>
                <h2>Client on the correct VLAN gets no internet, despite the gateway showing Online</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> the device has the correct VLAN IP, the gateway is Online, the firewall allow rule is confirmed correct - and outbound requests still time out with no response at all.</p>
                    <p><strong>Cause:</strong> outbound NAT was missing for the new WireGuard interface. This OPNsense box runs Manual outbound NAT mode; existing rules only covered the pre-existing LAN groups' <code>WAN</code>/<code>OVPN</code> interfaces, nothing for the new <code>WG_VLAN&lt;id&gt;</code> interfaces. Traffic left the tunnel still carrying the client's private source address, and the provider's WireGuard peer silently dropped it - WireGuard's cryptokey routing generally only accepts packets whose source matches what the peer was configured to expect. OPNsense's own gateway-monitor traffic (sourced differently) worked fine the whole time, which made this look like it couldn't possibly be a tunnel/NAT problem.</p>
                    <p><strong>Fix:</strong> add a manual outbound NAT rule - interface <code>WG_VLAN300</code>, source <code>NET_VLAN300</code>, destination any, translation "Interface address". Repeat per VLAN.</p>
                </div>
            </section>

            <section>
                <h2>Test results look wrong/inconsistent on a Windows laptop connected via both Wi-Fi and Ethernet</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> egress-IP checks return the router's real WAN IP, or nothing meaningful, even after VLAN assignment and NAT are confirmed correct.</p>
                    <p><strong>Cause:</strong> a laptop also connected over Ethernet (e.g. for a remote-support session) usually gets a lower routing metric on that adapter, so general traffic - including the test request - silently goes out Ethernet instead of the Wi-Fi/PPSK path being tested.</p>
                    <p><strong>Fix:</strong> don't disconnect Ethernet if it's needed for the session. Instead bind the test request to the Wi-Fi adapter's specific IP: <code>curl.exe --interface 10.30.0.10 -v https://ifconfig.me</code>. Bind by IP address, not adapter name - <code>--interface "Wi-Fi"</code> fails on Windows builds of curl with "Failed binding local connection end".</p>
                </div>
            </section>

            <section>
                <h2>"radiusd: command not found"</h2>
                <div class="card">
                    <p><strong>Symptom:</strong> <code>sudo radiusd -XC</code> fails with "command not found".</p>
                    <p><strong>Cause:</strong> <code>radiusd</code> is the Red Hat/CentOS binary name. On Debian/Ubuntu (this project's target OS), the binary is named <code>freeradius</code>.</p>
                    <p><strong>Fix:</strong> <code>sudo freeradius -CX</code> for a config self-test, <code>sudo freeradius -X</code> (after <code>sudo systemctl stop freeradius</code>) for a live debug capture.</p>
                </div>
            </section>

            <section class="card doc-cta">
                <h2 style="font-size:1.125rem">Hit something not covered here?</h2>
                <div class="hero-actions" style="margin-top:1rem">
                    <a href="mailto:zilleali1245@gmail.com?subject=Zonclave%20inquiry" class="btn btn-primary">Get in touch</a>
                </div>
            </section>

        </div>
    </article>

@endsection
