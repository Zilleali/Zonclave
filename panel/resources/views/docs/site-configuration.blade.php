@extends('layouts.public')

@section('title', 'Site Configuration - Office SancoMedia Kelder - Zonclave')
@section('description', 'The real, live Zonclave deployment for Sancover at Office SancoMedia Kelder: hardware, addressing, VLAN/tunnel build, and current status.')

@section('content')

    <article class="doc-article">
        <a href="{{ url('/docs') }}" class="doc-back">&larr; Documentation</a>

        <h1>Site Configuration - Office SancoMedia Kelder</h1>
        <p class="doc-lede">
            The actual, live Zonclave deployment for Sancover - real hardware, real addressing, real VLAN and
            tunnel configuration. This is Location 1 of 3 planned; Locations 2 and 3 will replicate this same
            pattern once their own WireGuard peer configs are ready.
        </p>

        <div class="doc-body">

            <section>
                <h2>Hardware</h2>
                <ul>
                    <li><strong>Zonclave server host</strong>: Beelink SER5 Pro (AMD Ryzen 7 5800H, 16GB RAM, 466GB NVMe SSD), running Windows 11 with Hyper-V. The Zonclave panel/FreeRADIUS/PostgreSQL stack runs inside an Ubuntu 22.04 LTS VM on this host, not bare metal.</li>
                    <li><strong>Router/Firewall</strong>: Protectli Vault FW6E (Intel Quad Core i7, AES-NI, 6 ports), running OPNsense.</li>
                    <li><strong>Switch</strong>: UniFi USW-16-PoE.</li>
                    <li><strong>Access points</strong>: 5x UniFi U6+ (AP-Stairs, AP-Back, AP-Room, U6-UK1, U6-UK2).</li>
                    <li><strong>Wi-Fi controller</strong>: UniFi Cloud Key Gen2 Plus (UCK-G2-Plus).</li>
                </ul>
            </section>

            <section>
                <h2>Network addressing</h2>
                <p>
                    No dedicated management VLAN at this site - the Zonclave server, switch, Cloud Key, and APs all
                    share the site's existing flat LAN. RADIUS and the panel don't require VLAN isolation to
                    function, and everything on this flat LAN is the office's own existing hardware, not the PPSK
                    guest devices the VLAN isolation below exists to contain.
                </p>
                <ul>
                    <li><strong>Flat LAN</strong>: <code>192.168.1.0/24</code>, gateway (OPNsense) <code>192.168.1.1</code></li>
                    <li><strong>Windows host</strong>: <code>192.168.1.174</code></li>
                    <li><strong>Zonclave VM (panel + FreeRADIUS + PostgreSQL)</strong>: <code>192.168.1.175</code></li>
                    <li><strong>UniFi switch (USW-16-PoE)</strong>: <code>192.168.1.12</code></li>
                    <li><strong>UniFi Cloud Key Gen2+</strong>: <code>192.168.1.191</code></li>
                </ul>
                <p>
                    All three of <code>.174</code>, <code>.175</code>, and <code>.191</code> sit inside the site's
                    existing DHCP pool (<code>192.168.1.10</code>-<code>192.168.1.245</code>) and have static DHCP
                    mappings in OPNsense keyed to MAC address, closing the collision risk.
                </p>
            </section>

            <section>
                <h2>PPSK VLAN block</h2>
                <p>
                    VLANs 1, 10, 20, 21, 30, 40, 50, 60, 70, 80, 90, 100, 110, 235, 236, 237, and 238 were already in
                    use on this site's existing UniFi VLAN table. The PPSK block starts at 300 to avoid all of them,
                    with the subnet chosen so the VLAN ID alone tells you the subnet: VLAN ID minus 300 = the third
                    octet.
                </p>
                <table>
                    <thead><tr><th>VLAN</th><th>Subnet</th><th>WireGuard interface</th><th>Gateway</th></tr></thead>
                    <tbody>
                        <tr><td>300</td><td>10.30.0.0/24</td><td>WG_VLAN300</td><td>GW_WG_VLAN300</td></tr>
                        <tr><td>301</td><td>10.30.1.0/24</td><td>WG_VLAN301</td><td>GW_WG_VLAN301</td></tr>
                        <tr><td>302</td><td>10.30.2.0/24</td><td>WG_VLAN302</td><td>GW_WG_VLAN302</td></tr>
                        <tr><td>303</td><td>10.30.3.0/24</td><td>WG_VLAN303</td><td>GW_WG_VLAN303</td></tr>
                        <tr><td>304</td><td>10.30.4.0/24</td><td>WG_VLAN304</td><td>GW_WG_VLAN304</td></tr>
                    </tbody>
                </table>
                <p>5 VLANs per router for Phase 1, replicated identically across all three routers - 15 WireGuard tunnel instances total, but the same 5 VLAN IDs at every site. A PPSK selects the same VLAN number everywhere; which residential IP it egresses from depends on which router the device is behind.</p>
            </section>

            <section>
                <h2>Trunk and interfaces</h2>
                <p>
                    NIC driver on this box is <code>igb</code>. Current assignment: <code>igb0</code> = WAN,
                    <code>igb1</code> = LAN (untagged, unchanged). <code>igb5</code> - originally a plain untagged
                    leg for one of the existing LAN groups - was converted into an 802.1Q trunk carrying nine tagged
                    VLANs: the four pre-existing groups (235, 236, 237, 238) plus the five new PPSK VLANs
                    (300-304). <code>igb2</code>-<code>igb4</code> were freed up by that migration and are currently
                    idle. On the switch side, the port feeding <code>igb5</code> is an 802.1Q trunk allowing all nine
                    tags, with the native/untagged VLAN staying the flat LAN.
                </p>
            </section>

            <section>
                <h2>Per-VLAN build (VLAN 300, done - pattern for 301-304)</h2>
                <p>Repeated once per VLAN. VLAN 300 is fully built and validated; 301-304 follow the identical steps.</p>
                <ul>
                    <li><strong>VLAN interface</strong>: tagged sub-interface on <code>igb5</code>, assignment named <code>VLAN300</code>, static IPv4 <code>10.30.0.1/24</code>, DHCP range <code>10.30.0.10</code>-<code>10.30.0.200</code>.</li>
                    <li><strong>WireGuard tunnel</strong>: <code>WG_VLAN300</code>, tunnel-side peer address <code>10.10.20.1</code>, connected to the residential VPN provider's assigned peer for this slot.</li>
                    <li><strong>Gateway</strong>: <code>GW_WG_VLAN300</code>. <strong>Monitor IP set to <code>8.8.8.8</code>, not the tunnel peer address</strong> - the provider's peer doesn't answer ICMP on its own tunnel-internal IP, so leaving Monitor IP at <code>10.10.20.1</code> showed the gateway permanently Offline despite a live handshake. Confirmed Online with real replies (~13ms, 0% loss) once switched.</li>
                    <li>
                        <strong>Firewall rules, VLAN300 interface, in order</strong> (first match wins):
                        <ol>
                            <li><code>ALLOW_VLAN300_TO_RADIUS</code> - UDP, dest <code>192.168.1.175:1812-1813</code></li>
                            <li><code>ALLOW_VLAN300_TO_DNS</code> - TCP/UDP, dest <code>10.30.0.1:53</code></li>
                            <li><code>ALLOW_VLAN300_TO_DHCP</code> - UDP, dest <code>10.30.0.1:67-68</code></li>
                            <li><code>ALLOW_VLAN300_TO_DNS_TUNNEL</code> - TCP/UDP, dest <code>10.10.20.1:53</code>, gateway <code>GW_WG_VLAN300</code> (DNS-through-tunnel, so this specific RFC1918 exception must sit above the block rule below)</li>
                            <li><code>BLOCK_VLAN300_TO_RFC1918</code> - blocks everything else in private address space</li>
                            <li><code>ALLOW_VLAN300_TO_GW_WG_VLAN300</code> - source <code>NET_VLAN300</code>, dest any, gateway pinned explicitly to <code>GW_WG_VLAN300</code></li>
                        </ol>
                    </li>
                    <li>
                        <strong>Outbound NAT</strong> - this box's Firewall &gt; NAT &gt; Outbound is set to <strong>Manual</strong>, not Automatic. Existing manual rules only covered <code>WAN</code> and <code>OVPN</code> for the pre-existing LAN235-238 groups; nothing existed for the new WireGuard interfaces. Without a rule, VLAN300 client traffic left the tunnel still sourced as its private client address and the provider's peer silently dropped it. Added: interface <code>WG_VLAN300</code>, source <code>NET_VLAN300</code>, destination any, translation "Interface address", static port <code>NO</code>. Must be repeated per VLAN.
                    </li>
                </ul>
            </section>

            <section>
                <h2>UniFi</h2>
                <ul>
                    <li><strong>RADIUS profile</strong>: named <code>Zonclave</code>, auth server <code>192.168.1.175</code>, port <code>1812</code>.</li>
                    <li><strong>SSID</strong>: <code>Zonclave-PPSK-TEST</code>, Security Protocol WPA2-Enterprise, External RADIUS Server pointed at the <code>Zonclave</code> profile. RADIUS Assigned VLAN Support enabled for Wireless Networks. UniFi Network application version confirmed 10.4.57 on this Cloud Key.</li>
                    <li>
                        <strong>Required FreeRADIUS-side setting for PEAP</strong>: WPA2-Enterprise/PEAP (Microsoft Protected EAP, the standard Windows sign-in method) resolves the real identity inside an encrypted inner tunnel. By default FreeRADIUS does not copy the VLAN attribute resolved there out to the final Access-Accept. Fixed with <code>use_tunneled_reply = yes</code> inside the <code>peap { }</code> block of <code>/etc/freeradius/3.0/mods-available/eap</code> on <code>192.168.1.175</code>, followed by <code>sudo freeradius -CX &amp;&amp; sudo systemctl restart freeradius</code>.
                    </li>
                </ul>
            </section>

            <section>
                <h2>Current status</h2>
                <p>
                    <strong>VLAN300 confirmed working end to end on real hardware</strong> (2026-07-17): a Windows
                    laptop connected to <code>Zonclave-PPSK-TEST</code> with credential <code>ppsk_group046</code>,
                    received <code>10.30.0.10</code> / gateway <code>10.30.0.1</code> (correct VLAN300 subnet), and
                    - verified with the Ethernet adapter explicitly ruled out as a confounding route - egressed
                    through a public IP distinct from the router's real WAN address, matching the
                    <code>WG_VLAN300</code> residential tunnel.
                </p>
                <p>Still open:</p>
                <ul>
                    <li>VLANs 301-304 - same build as above, not yet done.</li>
                    <li>Full acceptance pass for VLAN300: disable/delete revoke access, kill-switch fails closed, VLAN isolation holds, DNS leak test, every action lands in the panel's Admin Log.</li>
                    <li>Locations 2 and 3 - same pattern, once their WireGuard peer configs are confirmed ready.</li>
                </ul>
            </section>

            <section class="card doc-cta">
                <h2 style="font-size:1.125rem">Setting up a similar deployment?</h2>
                <div class="hero-actions" style="margin-top:1rem">
                    <a href="mailto:zilleali1245@gmail.com?subject=Zonclave%20inquiry" class="btn btn-primary">Get in touch</a>
                </div>
            </section>

        </div>
    </article>

@endsection
