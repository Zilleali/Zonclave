@extends('layouts.public')

@section('title', 'OPNsense Configuration Guide - Zonclave')
@section('description', 'How the OPNsense network side of a Zonclave deployment is configured: VLANs, WireGuard tunnels, fail-closed firewall policy, DNS, and the UniFi integration.')

@section('content')

    <article class="doc-article">
        <a href="{{ url('/docs') }}" class="doc-back">&larr; Documentation</a>

        <h1>OPNsense Configuration Guide</h1>
        <p class="doc-lede">
            How the network side of a Zonclave deployment is built on OPNsense: one VLAN and one WireGuard tunnel
            per PPSK group, with a firewall policy that fails closed rather than leaking traffic.
        </p>

        <div class="doc-body">

            <section>
                <h2>Scope</h2>
                <p>
                    The Zonclave installer provisions one host: PostgreSQL, FreeRADIUS, and the admin panel. It does
                    not touch OPNsense or the Wi-Fi controller - those are separate appliances, and this side is
                    configured manually, once per router, following the pattern below. That separation is
                    deliberate: FreeRADIUS only ever does authentication and VLAN assignment, OPNsense only ever
                    does routing, firewalling, and VPN policy. Credential logic never lives on the router, and
                    routing logic never lives in RADIUS.
                </p>
                <p>
                    Exact interface names, IP ranges, and physical port assignments are specific to each site's
                    hardware and existing network - they're confirmed on the box itself before any changes are made,
                    never assumed from a previous deployment.
                </p>
            </section>

            <section>
                <h2>Naming convention</h2>
                <p>Every PPSK group's network artifacts share one predictable naming pattern, so a search for a VLAN number surfaces everything related to it:</p>
                <ul>
                    <li><strong>VLAN ID</strong>: a fixed block reserved for Zonclave (e.g. <code>VLAN300</code>, <code>VLAN301</code>...), chosen per deployment to avoid the site's existing VLANs.</li>
                    <li><strong>WireGuard interface</strong>: <code>WG_VLAN&lt;id&gt;</code></li>
                    <li><strong>Gateway</strong>: <code>GW_WG_VLAN&lt;id&gt;</code></li>
                    <li><strong>Firewall alias (subnet)</strong>: <code>NET_VLAN&lt;id&gt;</code></li>
                    <li><strong>Firewall rules</strong>: <code>ALLOW_VLAN&lt;id&gt;_TO_GW_WG_VLAN&lt;id&gt;</code> and <code>BLOCK_VLAN&lt;id&gt;_TO_RFC1918</code></li>
                </ul>
            </section>

            <section>
                <h2>Per-group setup</h2>
                <p>Repeated identically for every PPSK group being provisioned on a router:</p>
                <ul>
                    <li><strong>VLAN interface</strong>: a tagged sub-interface on the LAN-facing trunk, one per group, with its own <code>/24</code> and DHCP range.</li>
                    <li><strong>WireGuard tunnel</strong>: a dedicated tunnel to that group's residential VPN peer - never shared with another group.</li>
                    <li><strong>Gateway</strong>: created against that WireGuard interface, with monitoring enabled where the peer supports it, so OPNsense actually knows when the tunnel is down.</li>
                    <li><strong>Firewall rules, in this exact order</strong> (first match wins):
                        <ol>
                            <li>Block that VLAN's subnet to all private address space (RFC1918), with narrow, IP-scoped exceptions for DNS, RADIUS, and DHCP only.</li>
                            <li>Allow that VLAN's subnet out, with the gateway explicitly pinned to that group's WireGuard tunnel - never a default route.</li>
                        </ol>
                        The block rule must sit above the allow rule. This ordering is the entire mechanism that
                        keeps one group's devices from ever reaching another group, the management network, or the
                        plain internet connection.
                    </li>
                </ul>
            </section>

            <section>
                <h2>DNS through the tunnel</h2>
                <p>
                    Each group's DNS queries are forced through that same group's WireGuard tunnel, not resolved
                    centrally. A DNS leak is the most common way a "secure" tunnel setup quietly stops being secure -
                    the traffic itself might be tunneled correctly while DNS queries go out the plain connection and
                    reveal exactly what's being tunneled. Verified with an actual leak test per group, not assumed
                    from the firewall rules alone.
                </p>
            </section>

            <section>
                <h2>Fail-closed, not fail-open</h2>
                <p>
                    If a group's WireGuard tunnel drops, that group's traffic is dropped - not silently rerouted
                    onto the plain internet connection. This depends on there being no fallback rule anywhere in the
                    ruleset that would let a VLAN reach the internet through anything other than its pinned gateway.
                    OPNsense ships a default "allow LAN to any" rule on a fresh install; that default is explicitly
                    removed or never applied to these VLAN interfaces.
                </p>
                <p>
                    Verified by deliberately killing a tunnel and confirming a connected device loses internet
                    access entirely, rather than falling back to the router's real public IP.
                </p>
            </section>

            <section>
                <h2>The UniFi side</h2>
                <ul>
                    <li><strong>RADIUS profile</strong>: points the access points at the FreeRADIUS server and shared secret generated by the installer.</li>
                    <li><strong>SSID</strong>: configured for RADIUS-assigned PPSK, with the VLAN left unpinned at the SSID level - FreeRADIUS assigns it per credential via a RADIUS attribute, not a fixed network on the SSID. As of current UniFi firmware, this feature works on WPA2 (2.4GHz and 5GHz) - WPA3 and 6GHz aren't yet supported for RADIUS-assigned PPSK, worth confirming against the specific site's needs before committing to it.</li>
                    <li><strong>Trunk port</strong>: the switch port feeding the router carries every provisioned VLAN tagged.</li>
                </ul>
            </section>

            <section>
                <h2>Gotchas found running this against real hardware</h2>
                <p>
                    These surfaced provisioning a real group end to end, not from reasoning about the design - each
                    one leaves the tunnel, gateway, and firewall rule all looking healthy while the device still gets
                    no internet, so they're easy to mistake for something else being wrong.
                </p>
                <ul>
                    <li>
                        <strong>Gateway monitoring needs a real internet host, not the tunnel peer's own address.</strong>
                        Many residential WireGuard providers don't answer ICMP on their tunnel-internal IP - it's an
                        endpoint, not a router. If the gateway's Monitor IP is set to that same address, the router
                        shows the gateway as down even with a live handshake, and the fail-closed policy then
                        correctly (but confusingly) blocks a tunnel that's actually fine. Point Monitor IP at a
                        stable public host reachable through the tunnel instead.
                    </li>
                    <li>
                        <strong>Outbound NAT is not automatic on every configuration.</strong> A router already
                        running in Hybrid or Manual outbound NAT mode needs an explicit NAT rule added for each new
                        WireGuard tunnel interface, translating that VLAN's client subnet to the tunnel's own
                        address. Without it, traffic leaves the tunnel still carrying the client's private source
                        address, and most providers' WireGuard peers silently drop it - a clean, silent timeout with
                        no error anywhere.
                    </li>
                    <li>
                        <strong>RADIUS-assigned VLAN over WPA2-Enterprise/PEAP needs one extra RADIUS server
                        setting.</strong> PEAP resolves the real identity and does its challenge/response inside an
                        encrypted inner tunnel, separate from the outer RADIUS exchange. By default, the RADIUS
                        server does not copy VLAN attributes resolved during that inner exchange out to the final
                        outer Access-Accept - a basic auth smoke test won't catch this, since it doesn't speak PEAP.
                        A device can authenticate successfully and still land on the wrong network. The RADIUS
                        server needs its tunneled-reply setting enabled for the inner tunnel's attributes to
                        actually reach the client.
                    </li>
                </ul>
            </section>

            <section>
                <h2>Verifying the result</h2>
                <p>
                    A deployment is only proven once a real device connects with a PPSK, lands on the correct VLAN,
                    and its outbound public IP matches that VLAN's residential tunnel - checked from the device
                    itself, not inferred from the configuration. See the
                    <a href="{{ url('/docs/commands-reference') }}">command reference</a> for the exact verification
                    commands used at each step (confirming a tunnel handshake, inspecting the compiled firewall
                    ruleset, and the kill-switch test itself).
                </p>
            </section>

            <section class="card doc-cta">
                <h2 style="font-size:1.125rem">Deploying Zonclave on your own network?</h2>
                <div class="hero-actions" style="margin-top:1rem">
                    <a href="mailto:zilleali1245@gmail.com?subject=Zonclave%20inquiry" class="btn btn-primary">Get in touch</a>
                </div>
            </section>

        </div>
    </article>

@endsection
