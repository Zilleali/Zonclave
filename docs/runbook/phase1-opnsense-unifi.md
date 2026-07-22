# Phase 1 Manual Runbook: OPNsense + UniFi

Status: manual, per CLAUDE.md Section 22 and Section 24.2. This is exactly what
the Section 24 installer does **not** automate. It configures the FreeRADIUS +
database + panel node only; everything in this document is done by hand,
repeated identically at all three locations (Section 3.1).

If anything here contradicts CLAUDE.md, CLAUDE.md wins - update this doc, not
the other way around.

## 0. Before you start

Confirm these Section 20 items are actually true for the site you are about
to configure, not assumed:

- [x] **Resolved 2026-07-14:** the UniFi Network application version
      installed on the Cloud Key Gen2+ (Office SancoMedia Kelder) is
      **10.4.57**, confirmed via the app's own footer. Ubiquiti added PPSK
      support in Network 7.5.187 (October 2023), so 10.4.57 is well within
      range. Ubiquiti also publishes a dedicated guide for this project's
      exact flow - PPSK combined with RADIUS for dynamic per-SSID VLAN
      assignment - see [Using PPSK / RADIUS for Multiple VLANs On an SSID
      in UniFi Network](https://help.ui.com/hc/en-us/articles/29887064407319-Using-PPSK-RADIUS-for-Multiple-VLANs-On-an-SSID-in-UniFi-Network),
      follow it for the exact current field names/steps in Section 4.2
      rather than assuming a fixed UI path (Section 8.3's own caveat).
      One real constraint confirmed from Ubiquiti's docs: PPSK currently
      only works with **WPA2, on 2.4GHz and 5GHz** - no WPA3, no 6GHz, and
      it cannot be combined with a captive portal or RADIUS MAC auth. If
      the SSID needs WPA3 or 6GHz for any reason, that needs resolving
      before Section 4.2.
      Re-verify this per site (Section 0's own advice) - the other two
      locations may run a different Network version.
- [x] **Confirmed 2026-07-14 (Sancover):** all 5 residential WireGuard peer
      configs for this router are ready. Sancover's stated goal is to add
      more groups in the future - Phase 1 still stops at 5 per router
      regardless (CLAUDE.md Section 4); don't provision more on the
      strength of that stated intent. Before starting Section 3 below,
      double check the actual peer config values in hand (endpoint
      host:port, peer public key, allowed IPs, and this side's keypair or
      the provider's assigned one) - "confirmed ready" isn't the same as
      "the exact values are open in front of you" when you sit down to do
      3.2. Provisioning a tunnel with a placeholder peer and forgetting to
      swap it in is the most likely way to accidentally leak traffic out
      the wrong path.
- [x] **Confirmed 2026-07-16:** the FreeRADIUS node (the Zonclave Hyper-V VM,
      Section 3.4) is up and reachable at `192.168.1.175:1812/1813` - not
      `172.16.74.10`. The VLAN 205 management network this runbook's
      Section 3.5 originally described was superseded by a flat-LAN
      decision the same day (CLAUDE.md Section 3.4); see the note at the
      top of Section 3.5 below before building anything from that section.
      `radtest` against the seeded `ppsk_group001` returns `Access-Accept`
      with `Tunnel-Private-Group-Id = "300"`, confirmed locally on that
      host.
- [x] **Confirmed 2026-07-17: VLAN300 validated end to end on real
      hardware**, a Windows laptop over WPA2-Enterprise/PEAP with
      `ppsk_group046`. This is the first full run of the chain this
      document describes, and it surfaced three real gaps not caught by
      `radtest` or the panel's own test suite - all three are folded into
      the steps below (3.3, 3.4a, 4.2) so they aren't rediscovered per
      VLAN or per site. Full incident detail: CLAUDE.md Section 26.7.

### A note on interface names

**Confirmed 2026-07-13 from the Office SancoMedia Kelder box's Interfaces >
Assignments page:** the driver is `igb`, not `igc` as originally guessed
here from the Intel I225-V datasheet. Current assignment on that box:

| Assignment | Port |
| --- | --- |
| WAN | `igb0` |
| LAN | `igb1` |
| LAN235 | `igb2` |
| LAN236 | `igb3` |
| LAN237 | `igb4` |
| LAN238 | `igb5` |

Re-confirm this per box (per Section 0's own advice) rather than assuming
it's identical at every site - Protectli units can ship with different NIC
revisions even across the same model line.

**Resolved 2026-07-14 (Sancover):** confirmed no 802.1Q trunk exists on
this box today - LAN235 through LAN238 are genuinely four separate
physical legs (`igb2`-`igb5`), not tagged sub-interfaces of a shared
trunk. Sancover's proposed direction: free up one physical port and run
both the existing LAN VLANs (235-238) *and* the new PPSK VLANs (300-304)
through the single trunk this project creates on that port. This matches
the architecture Section 9 already assumes (VLAN sub-interfaces on one
LAN-facing trunk); the existing box just wasn't wired that way yet.

**Resolved 2026-07-14 (Sancover): trunk port is `igb5`.** `igb1` (LAN)
stays untagged, unchanged - it is not part of this migration. `igb5`
(currently the plain, untagged LAN238 leg) becomes the trunk, carrying
**nine** tagged VLANs: the four existing ones (235, 236, 237, 238) and the
five new PPSK ones (300-304). **Updated 2026-07-16:** management VLAN 205
is deliberately not part of this trunk - CLAUDE.md Section 3.4 superseded
the dedicated management VLAN plan the same day this was originally
written, in favor of the existing flat LAN. Device management traffic
(Zonclave server, switch, Cloud Key, APs) rides untagged/native on `igb5`,
same as it already does today; only 235-304 need explicit tags. See the
note at the top of Section 3.5 below - do not build a VLAN 205
sub-interface. Concretely, on the OPNsense side this means:

1. Remove the flat `igb5` assignment currently backing LAN238.
2. Create tagged VLAN sub-interfaces on `igb5` for 235, 236, 237, and 238
   (e.g. `igb5_vlan235` ... `igb5_vlan238`), and re-point each existing
   interface assignment (LAN235-238) at its new tagged sub-interface
   instead of its old dedicated NIC. Keep the same IPv4 addresses, DHCP
   ranges, and firewall rules on each - the goal is that end devices on
   235-238 see no change at all, only the underlying wiring/tagging
   changes.
3. `igb2`, `igb3`, and `igb4` become free once 235, 236, and 237 are moved
   off them. Nothing in this project uses them; leave them idle unless
   Sancover wants them for something else.
4. Create the five new tagged VLAN 300-304 sub-interfaces on `igb5`
   alongside the migrated ones, per Section 3.1 below.
5. On the UniFi switch side, the port feeding `igb5` needs to become an
   802.1Q trunk allowing tags 235, 236, 237, 238, 300, 301, 302, 303, 304
   (native/untagged VLAN on that port stays whatever it already is - the
   flat LAN, 192.168.1.0/24 - carrying management traffic untagged).

**Treat this as a live-production change, not a green-field install.**
Migrating LAN235-238 off dedicated ports onto a shared trunk touches
VLANs already carrying real traffic. Schedule a maintenance window, do
the migration and the switch-side trunk config together (a half-migrated
state - OPNsense expecting tags the switch isn't sending yet, or vice
versa - is a guaranteed outage for 235-238), and afterward re-run a
version of the Section 10 isolation test (test 8 in Section 21.1) against
the *existing* VLANs too, not just the new ones - confirm 235-238 still
can't reach each other or the management interface post-migration,
exactly as you'll confirm for 300-304.

**Resolved and removed 2026-07-14 (Sancover):** the `ovpnc1` ("PIA UK
Londen") OpenVPN client was added for testing only and has been removed.
It was never the intended provider for this project - confirmed the
intention is genuine residential VPN providers, not commercial/datacenter
VPN services like PIA. Do not reuse this tunnel or its config pattern for
any of the 5 new WireGuard tunnels per router. Before starting Section 3,
confirm on the actual box that it no longer appears under Interfaces >
Assignments - the freed capacity has no bearing on the trunk-port plan
above either way, since OpenVPN clients are virtual interfaces and were
never occupying a physical NIC.

## 1. Quick reference: the fixed Phase 1 block

Per Section 5, this table is identical at every site. Only the WireGuard
peer (the actual tunnel endpoint/keys) differs per router.

| VLAN | Subnet | WireGuard if. | Gateway | Firewall alias |
| --- | --- | --- | --- | --- |
| 300 | 10.30.0.0/24 | WG_VLAN300 | GW_WG_VLAN300 | NET_VLAN300 |
| 301 | 10.30.1.0/24 | WG_VLAN301 | GW_WG_VLAN301 | NET_VLAN301 |
| 302 | 10.30.2.0/24 | WG_VLAN302 | GW_WG_VLAN302 | NET_VLAN302 |
| 303 | 10.30.3.0/24 | WG_VLAN303 | GW_WG_VLAN303 | NET_VLAN303 |
| 304 | 10.30.4.0/24 | WG_VLAN304 | GW_WG_VLAN304 | NET_VLAN304 |

**Updated 2026-07-16:** there is no dedicated management VLAN at Kelder.
CLAUDE.md Section 3.4 superseded the VLAN 205 plan the same day this
runbook was originally written - management traffic (the Zonclave server,
switch, Cloud Key, APs) rides the existing flat LAN (192.168.1.0/24)
untagged, not a dedicated tag. Section 3.5 below is kept for history only;
do not build it. VLAN 205 (172.16.74.0/24) remains reserved and unused,
per CLAUDE.md Section 5, should a future site want the extra isolation.

## 2. Why the GUI, not raw `config.xml`, for creation steps

Section 25.2 asks for CLI-first networking guidance, and the verification
steps below are all CLI (`ifconfig`, `wg show`, `pfctl`, `configctl`). For
the *creation* steps, though, this doc uses OPNsense's web GUI deliberately,
not direct `config.xml` edits over SSH. Reasoning, so this isn't just
defaulting to habit:

- OPNsense keeps the running config in memory and rewrites the whole
  `config.xml` on save. A manual edit made while the GUI is open elsewhere
  (or before the next GUI save) can be silently clobbered.
- Section 10's entire safety property depends on the **block rule sitting
  above the allow rule** in evaluation order. The GUI enforces and displays
  rule order explicitly at every step; hand-editing XML node order for 5
  VLANs across 3 routers with no built-in validation is a materially higher
  risk of a silent ordering mistake than the one thing this project cannot
  tolerate: a leak.
- Raw `config.xml`/API scripting is exactly what Section 19 describes as
  the Phase 2 automation target, once the pattern has been proven by hand
  at this scale (5-10 tunnels) and is worth building against a stable API
  rather than fragile XML surgery.

Every creation step below still ends with a CLI command to *verify* the
result independently of the GUI, which is the actual safety net.

## 3. Per-router OPNsense configuration

Repeat this entire section once per location (Section 3.1), for VLANs 300
through 304. The steps are written for one VLAN; do all five before moving
to Section 4.

### 3.1 VLAN interface

**Interfaces > Other Types > VLAN > Add.**

- Parent interface: the confirmed LAN trunk NIC (Section 0's note above)
- VLAN tag: `300` (…304)
- Description: `VLAN300` (…`VLAN304`)

Then **Interfaces > Assignments**, assign the new VLAN, name the assignment
`VLAN300` (matches the label convention in Section 6), enable it, set the
static IPv4 to the `.1` address of that subnet (e.g. `10.30.0.1/24` for
VLAN 300), and enable the DHCP server for that interface's subnet
(**Services > DHCPv4 > [VLAN300]**), range e.g. `10.30.0.10`-`10.30.0.200`,
leaving `.1`-`.9` free for infrastructure.

Verify:

```sh
ifconfig | grep -A3 vlan300
```

Confirm the interface is up and carrying the tag you expect.

### 3.2 WireGuard tunnel (`WG_VLAN<id>`)

Requires the `os-wireguard` plugin (**System > Firmware > Plugins**, install
once per router if not already present).

**VPN > WireGuard > Local > Add** (this router's own keypair for this
tunnel, or the keypair the VPN provider issued):

- Name: `WG_VLAN300`
- Listen port: a unique port per tunnel on this router (the provider
  usually assigns this, or pick sequential unused ports and confirm with
  them)
- Tunnel address: whatever address the provider assigns for this peer

**VPN > WireGuard > Endpoints > Add** (the provider's server as the peer):

- Name: `WG_VLAN300_PEER` (or the provider's own naming, cross-referenced)
- Public key: from the provider's peer config for this tunnel slot
- Allowed IPs: `0.0.0.0/0` (this tunnel should carry all of that VLAN's
  default traffic)
- Endpoint: the provider's residential exit host:port for this slot

Attach the endpoint to the local instance, then **VPN > WireGuard >
General**, enable WireGuard.

Verify the handshake before doing anything else with this tunnel:

```sh
wg show WG_VLAN300
```

Confirm a `latest handshake` timestamp appears (not blank) before
continuing. A tunnel with no handshake is a tunnel that will fail closed
per Section 12 anyway, but confirm it's actually working, not just
configured, before wiring a PPSK to it.

### 3.3 Gateway (`GW_WG_VLAN<id>`)

**System > Gateways > Single > Add:**

- Interface: `WG_VLAN300`
- Name: `GW_WG_VLAN300`
- Gateway IP: an in-tunnel address that is **not** this box's own tunnel
  address - use the provider's inner-network gateway/DNS IP if they
  publish one (e.g. `10.10.20.1` at Kelder), or a distinct dummy in-tunnel
  address per gateway. See the critical finding below - getting this field
  wrong produces the single most confusing failure mode in the whole
  project.
- **Far Gateway: checked** (the address is not on a directly attached
  network; on a point-to-point tunnel this is what makes any non-local
  next-hop valid).
- **Disable Gateway Monitoring only if the provider has no in-tunnel IP to
  ping.** If monitoring can be enabled, do so - Section 12's fail-closed
  behavior depends on OPNsense knowing the gateway is down.

**Critical real-world finding, 2026-07-22 (cost a full day of debugging):
the Gateway IP must never be the WireGuard interface's own tunnel
address.** At Kelder, all four gateways had been built with Gateway IP set
to each tunnel's own local address (e.g. `GW_WG_VLAN300` = `10.20.0.183`,
the same address assigned to `wg1` itself). pf's `route-to` then resolves
the next-hop to the firewall itself and short-circuits the packet locally
instead of pushing it into the tunnel. The resulting symptoms are
spectacularly misleading:

- HTTPS requests from a VLAN client to **any** destination IP were
  answered by OPNsense's **own web GUI** (login page, or the DNS-rebind
  error page when a hostname was involved) - looking exactly like some
  hidden port-forward or captive portal, of which there was none.
- DNS redirect states showed `NO_TRAFFIC` (queries forwarded to the
  provider's resolver, no reply ever returned).
- After partial fixes, the symptom shifted to plain timeouts: pf created
  the state (`SYN_SENT`), but `tcpdump -n -i wg1` showed the packet
  **never entered the tunnel at all**.
- Meanwhile everything *looked* healthy: live handshake in `wg show`,
  gateway **Online** in Status, correct rules in `pfctl -sr`. The gateway
  monitor (dpinger) kept working throughout because it uses a kernel host
  route, not pf `route-to` - so it never hits this trap. Monitor traffic
  passing is **not** evidence that client traffic passes.

**Fix (per gateway):** set Gateway IP to a non-local in-tunnel address
with Far Gateway checked. Kelder's working values:

| Gateway | Gateway IP | Monitor IP |
| --- | --- | --- |
| GW_WG_VLAN300 | 10.10.20.1 | 8.8.8.8 |
| GW_WG_VLAN301 | 10.10.20.2 | 8.8.4.4 |
| GW_WG_VLAN302 | 10.10.20.3 | 9.9.9.9 |
| GW_WG_VLAN303 | 10.10.20.4 | 149.112.112.112 |

On a point-to-point tunnel the exact next-hop value is irrelevant to
packet delivery (the interface itself defines the path); it only has to be
distinct per gateway (so the GUI accepts it) and **not local to the box**.
Monitor IPs must each be unique across gateways - OPNsense installs a host
route per monitor target.

**The one test that proves it:** from a client on the VLAN, run a request
while capturing on the tunnel:

```sh
tcpdump -n -i wg1 host 1.1.1.1
```

A correctly built gateway shows the client's NAT'd SYN leaving
(`10.20.0.x > 1.1.1.1.443`) *and* the SYN-ACK returning. If pf has a state
for the connection but nothing appears in this capture, the next-hop is
short-circuiting locally - recheck this section.

**Real-world finding, 2026-07-17:** on the actual Kelder box, leaving
**Monitor IP** at the WireGuard peer's own tunnel address (the same value
as Gateway IP, e.g. `10.10.20.1`) left the gateway showing **Offline** in
`System > Gateways > Status` permanently, even though `wg show WG_VLAN300`
confirmed a live handshake. The residential provider's peer simply doesn't
answer ICMP on its own tunnel-internal address - it's a tunnel endpoint,
not a router. Since Section 12's fail-closed rule pins traffic to a gateway
that OPNsense believes is down, this silently blocked all VLAN300 traffic
despite the tunnel being genuinely healthy - a false negative, not a real
outage, but with the exact same symptom (no internet) from the client's
side.

**Fix:** in the Gateway edit form, **Monitor IP is a separate field from
Gateway IP.** Set Monitor IP to a real, always-up internet host reachable
*through* the tunnel - `8.8.8.8` or `1.1.1.1` both work - instead of the
peer's own tunnel address. Save, apply, and confirm **System > Gateways >
Status** flips to Online with a real round-trip time and 0% loss before
moving on. Do this for every `GW_WG_VLAN<id>` on every router; it is not a
Kelder-specific quirk, it's how most residential WireGuard providers behave.

Verify:

```sh
configctl interface routes
```

Confirm `GW_WG_VLAN300` resolves to the WireGuard interface and shows a
sane status (not permanently "offline" if the tunnel handshake in 3.2 is
healthy).

### 3.4 Firewall rules (allow + block, per Section 10)

First, create the alias: **Firewall > Aliases > Add**, name `NET_VLAN300`,
type Network, content `10.30.0.0/24`.

Then on the `VLAN300` interface tab, **Firewall > Rules > VLAN300**, add
rules **in this order** (OPNsense evaluates top to bottom, first match
wins - this order is the entire point of Section 10):

1. **`BLOCK_VLAN300_TO_RFC1918`** (block, placed first/highest):
   source `NET_VLAN300`, destination alias `RFC1918` (create this alias
   once, type Network, content `10.0.0.0/8`, `172.16.0.0/12`,
   `192.168.0.0/16` - reuse it across all 5 VLANs, don't recreate per
   VLAN), action Block, log enabled.
2. Exceptions **above** rule 1 (evaluated first, so they must sit higher in
   the list), each scoped to the specific server IP, not "any":
   - Allow `NET_VLAN300` -> `192.168.1.175` port `1812/1813` (RADIUS, if this
     VLAN's clients ever need to reach it directly - normally they don't,
     the AP does, but keep this explicit and minimal rather than absent by
     accident)
   - Allow `NET_VLAN300` -> `10.30.0.1` port `53` (DNS resolver for this
     VLAN, see 3.6 below - this is intentionally the VLAN's own gateway,
     not a shared resolver, to keep DNS-through-tunnel per-VLAN)
   - Allow `NET_VLAN300` -> `10.30.0.1` port `67/68` (DHCP)
3. **`ALLOW_VLAN300_TO_GW_WG_VLAN300`** (allow, placed after the block/
   exception rules): source `NET_VLAN300`, destination any, gateway
   `GW_WG_VLAN300` (this is the critical field - the rule must pin the
   gateway explicitly, not rely on the system default route).

**Do not add or leave** any rule allowing `VLAN300 -> WAN` or `VLAN300 ->
any` without the `GW_WG_VLAN300` gateway pinned. OPNsense ships a default
"allow LAN to any" rule on a fresh LAN interface; VLAN interfaces don't
inherit it, but double check none was added by hand.

Verify the compiled ruleset, not just the GUI list:

```sh
pfctl -a '*' -sr | grep vlan300
```

(The anchor-specific form `pfctl -a 'filter/VLAN300' -sr` fails with
`DIOCGETRULES: Invalid argument` on the Kelder box's OPNsense version -
the wildcard anchor dump above works everywhere. Found 2026-07-22.)

Read it top to bottom and confirm the block rule and its exceptions
genuinely precede the allow rule, exactly as intended - this is the one
thing worth re-reading twice.

**Real-world finding, 2026-07-22: check the Action field, not just the
name.** At Kelder, VLAN301's `BLOCK_VLAN301_TO_RFC1918` rule had been
created with action **Pass** instead of Block - the name said block, the
icon and the compiled ruleset said pass, and VLAN301 clients could reach
the entire RFC1918 space. The rule editor defaults to Pass, so this slip
is easy to make when cloning the rule set per VLAN. In the compiled output
above, the rule must read `block drop in quick ...`, not `pass in
quick ...` - verify the verb itself for every VLAN.

### 3.4a Outbound NAT - required, easy to miss

**Real-world finding, 2026-07-17:** the firewall allow rule in 3.4 is not
enough by itself. On the actual Kelder box, **Firewall > NAT > Outbound**
is set to **Manual outbound NAT rule generation** (not Automatic) - this
box already had manual rules for `WAN` and `OVPN` covering the existing
LAN235-238 groups, and nothing was ever added for the new `WG_VLAN<id>`
interfaces. With no translation rule, VLAN300 client traffic left the
tunnel still sourced as its private client address (`10.30.0.10`), and the
residential provider's WireGuard peer silently dropped it - WireGuard
peers generally only accept packets whose source matches their configured
`AllowedIPs` (cryptokey routing), and a client-subnet address the provider
never assigned is outside that range. Symptom: the gateway shows Online,
`wg show` shows a live handshake, the firewall rule in 3.4 correctly
allows the traffic out - and the client still gets a plain connection
timeout, no response, nothing in any log. Confirm your box's Outbound NAT
mode before assuming this step is unnecessary; if it's Automatic, OPNsense
may already handle this per-interface and this section can be skipped -
but verify with the same test at the end of this section rather than
assuming.

If Manual (or Hybrid) mode is in use, add one rule per VLAN, matching the
existing pattern for the other groups on this box:

**Firewall > NAT > Outbound > Add:**

- Interface: `WG_VLAN300`
- Source: `NET_VLAN300` (`10.30.0.0/24`)
- Destination: `*` (any)
- Translation / NAT Address: **Interface address**
- Static Port: `NO`
- Description: `NAT_VLAN300_TO_WG_VLAN300`

Save, apply, and repeat for `WG_VLAN301` through `WG_VLAN304`. Verify from
a real client on that VLAN (not from OPNsense itself - OPNsense's own
gateway-monitor traffic is sourced differently and will falsely appear to
work even when this rule is missing):

```powershell
# Windows client, forcing the request out a specific local address so a
# dual-homed test machine (Wi-Fi + Ethernet) can't mask the result:
curl.exe --interface 10.30.0.10 -v https://ifconfig.me
```

Expect a `200` with a public IP that matches the tunnel's residential
address, not a timeout.

### 3.5 Management VLAN - SUPERSEDED, do not build (kept for history)

**This entire section is superseded as of 2026-07-16 and should not be
followed.** It originally described building a dedicated Management VLAN
205 (172.16.74.0/24) to carry the Zonclave server's and UniFi devices' own
management traffic. CLAUDE.md Section 3.4 reversed that decision the same
day this was first written, in favor of keeping everything on the
existing flat LAN (192.168.1.0/24) - simpler, and the isolation a
dedicated VLAN would buy doesn't protect against anything in this
project's actual threat model (the office's own hardware, not PPSK guest
devices). VLAN 205 remains reserved and unused per CLAUDE.md Section 5,
should a future site want the extra isolation. The original steps are
kept below only as a reference for what that would look like.

What to actually do instead, on the UniFi switch side: **leave AP-facing
ports' native/untagged VLAN as whatever it already is today** (the flat
LAN), and add VLANs 300-304 as additional **tagged** VLANs on the same
ports for the PPSK SSID. No new native VLAN, no switch-level "management
VLAN" setting change, no new OPNsense interface for this. Confirm during
the Section 10 isolation test (test 8, Section 21.1) that the flat LAN
still cannot reach PPSK client devices on 300-304 and vice versa.

> **Original superseded steps (VLAN 205), not to be built:**
>
> Not a PPSK group - this described extending CLAUDE.md Section 3.4's
> originally-planned Management VLAN 205 to also carry the UniFi switch's,
> Cloud Key's, and APs' own device-management traffic, not just the
> Zonclave server.
>
> **Interfaces > Other Types > VLAN > Add:** parent `igb5`, tag `205`,
> description `MGMT_VLAN205` (Section 6's fixed name for this interface).
>
> **Interfaces > Assignments:** assign it, name it `MGMT_VLAN205`, static
> IPv4 `172.16.74.1/24`. DHCP range `172.16.74.20`-`172.16.74.50` (the
> Beelink server itself would keep its static `172.16.74.10`, outside the
> DHCP range).
>
> No WireGuard tunnel, no `GW_WG_VLAN205` gateway, and no per-VLAN
> firewall pair - this network's whole purpose would have been admin
> access via the WireGuard admin tunnel to ZILL's machine.
>
> On the UniFi switch side, per AP-facing port: set Native/Untagged VLAN
> = 205, with VLANs 300-304 allowed (tagged) on the same port. Also set
> the UniFi switch's own device-management VLAN to 205, and the Beelink
> server's own switch port to native VLAN 205.

### 3.6 DNS through the tunnel (Section 11 decision)

Section 11 records DNS-through-tunnel as the decision, not central Unbound.
Point each VLAN's DHCP-issued DNS server at that VLAN's own gateway
(`10.30.0.1` for VLAN 300), and configure Unbound (or dnsmasq, whichever
OPNsense resolver is active) with a **per-interface** override so that
`VLAN300`'s DNS queries are answered by forwarding through `WG_VLAN300`
specifically, not the system-wide upstream:

**Services > Unbound DNS > General**, under **Network Interfaces**, bind
Unbound to `VLAN300` (and each other VLAN) individually rather than "all
interfaces", and under **Services > Unbound DNS > Forwarding** (or a
per-domain override if the provider gives you a specific resolver IP),
set the forward target to the residential VPN provider's DNS server
reachable only via `WG_VLAN300` - not `1.1.1.1`/`8.8.8.8`/the OPNsense
default WAN resolver.

This is the step most likely to be silently wrong, because a broken
DNS-through-tunnel config still resolves names successfully (via WAN
fallback) with no visible error. Section 21.1 test 9 (DNS leak test) is
what actually catches this - don't skip it.

### 3.7 Kill-switch confirmation (Section 12)

Before moving to the next VLAN, confirm fail-closed behavior for this one
right now, not at the end:

```sh
# Simulate the tunnel dying:
wg set WG_VLAN300 down    # or: ifconfig wg_vlan300 down, depending on plugin version

# From a test client on VLAN300 (or via a scratch host on that subnet):
curl -m 5 -s -o /dev/null -w '%{http_code}\n' https://ifconfig.me
# Expect: this to time out / fail, NOT return a WAN IP.

# Restore:
wg set WG_VLAN300 up
```

If the `curl` succeeds and returns the router's real WAN IP, the allow
rule in 3.4 is not pinned to `GW_WG_VLAN300` correctly, or a default-route
fallback rule exists somewhere above it. Fix this before provisioning any
PPSK against this VLAN - this is the one failure mode the whole
architecture exists to prevent.

### 3.8 Repeat

Do 3.1 through 3.4 and 3.6-3.7 for VLANs 301, 302, 303, 304 on this router
(3.5, the management VLAN, is a once-per-router step, already done above -
don't repeat it per VLAN), then repeat the entire Section 3 for the next
location.

## 4. UniFi configuration (Section 8.3)

Ubiquiti's Network application does not expose an interactive CLI for SSID
or RADIUS profile configuration (unlike the APs themselves, which are
SSH-reachable for diagnostics only) - this is a genuine platform limitation,
not a stylistic choice to skip Section 25.2's CLI-first guidance. The
values below are what has to be set, wherever the current Network app
version's UI puts them (verify the exact path against your installed
version per Section 8.3's own caveat).

### 4.1 RADIUS profile

**Settings > Profiles > RADIUS > Create New RADIUS Profile:**

- Name: `Zonclave`
- Auth Server: `192.168.1.175`, port `1812`, shared secret: the value the
  installer generated and printed in its summary (Section 24.3 step 6) -
  never hand-type a different one.
- Accounting: leave disabled for Phase 1 (Section 22: RADIUS accounting is
  explicitly out of scope).

### 4.2 SSID

Confirmed 2026-07-14: Network 10.4.57 (this site) supports this. Follow
Ubiquiti's own current guide rather than the field names below verbatim -
[Using PPSK / RADIUS for Multiple VLANs On an SSID in UniFi
Network](https://help.ui.com/hc/en-us/articles/29887064407319-Using-PPSK-RADIUS-for-Multiple-VLANs-On-an-SSID-in-UniFi-Network)

- since Section 8.3 already flags that exact menu labels move across
versions. The essentials, regardless of label:

**Settings > WiFi > [create/edit SSID]:**

- Security Protocol: **WPA2 only** - Ubiquiti's PPSK feature does not
  support WPA3 or 6GHz as of this writing, and cannot be combined with a
  captive portal or RADIUS MAC auth. Confirm nothing else about this
  deployment needs WPA3/6GHz on this SSID before proceeding.
- RADIUS profile: `Zonclave` (from 4.1)
- Network/VLAN: leave this **not** pinned to a single VLAN - the entire
  point of PPSK is that FreeRADIUS assigns the VLAN per credential via
  `Tunnel-Private-Group-Id` (Section 8.2), so the SSID itself must allow
  RADIUS-assigned VLANs rather than forcing one.

**Real-world finding, 2026-07-17 - required FreeRADIUS-side config, not an
OPNsense/UniFi setting:** a device connecting with WPA2-Enterprise/PEAP
(Microsoft Protected EAP - the standard Windows sign-in method for this
SSID) authenticated successfully and got a `radtest`-confirmed-correct
`Tunnel-Private-Group-Id`, but still landed on the flat LAN instead of its
assigned VLAN. Root cause, found via `sudo freeradius -X` live debug on the
FreeRADIUS host (`192.168.1.175`): PEAP resolves the actual username and
does its MSCHAPv2 challenge/response **inside an encrypted inner tunnel**
(`sites-enabled/inner-tunnel`), separate from the outer RADIUS
conversation. The VLAN attributes from `radreply` get correctly resolved
during that inner exchange, but FreeRADIUS does not copy them out to the
final outer `Access-Accept` unless explicitly told to - by default it
doesn't, for isolation reasons that don't apply to this project's
single-purpose deployment. The final Access-Accept came back with
`MS-MPPE-*` keys and nothing else - no `Tunnel-Private-Group-Id` at all -
which is why the AP/switch had nothing to assign the device to and it fell
through to the untagged/native VLAN.

**Fix, on the FreeRADIUS host (not OPNsense):**

```sh
sudo grep -n "use_tunneled_reply" /etc/freeradius/3.0/mods-available/eap
```

Two matches appear (`peap { }` and `ttls { }` blocks each have their own).
Edit the one inside `peap { }` specifically (leave `ttls` alone unless
that's also in use):

```sh
sudo nano /etc/freeradius/3.0/mods-available/eap
# inside peap { ... }:  use_tunneled_reply = no   ->   use_tunneled_reply = yes
sudo freeradius -CX          # config self-test - correct binary name on
                              # Debian/Ubuntu is freeradius, not radiusd
sudo systemctl restart freeradius
```

This is a one-time, per-FreeRADIUS-host setting - it does not need
repeating per VLAN or per PPSK, only once per site's FreeRADIUS
installation. Forget the network on the test device and reconnect to force
a fresh PEAP handshake (Windows can otherwise resume a cached session and
skip the round-trip that would exercise the fix); confirm with a live
`sudo freeradius -X` capture that the final `Sent Access-Accept` now
includes `Tunnel-Private-Group-Id`, not just the Access-Challenge partway
through the exchange.

### 4.3 Trunk port

Confirm the switch port trunking to `igb5` (Section 0's confirmed trunk
port) passes all nine tags: the five PPSK VLANs and the four migrated
existing VLANs. No management tag - that traffic rides native/untagged
(Section 3.5's update, 2026-07-16):

**UniFi Network > switch port profile for the `igb5`-facing port:** tagged
VLANs `235, 236, 237, 238, 300, 301, 302, 303, 304` (native/untagged VLAN
stays the existing flat LAN).

This is the switch-to-router uplink, not the AP-facing ports - those are
covered separately in Section 3.5 above (native VLAN stays the flat LAN,
tagged 300-304 only, not all nine - APs don't need to see 235-238's
traffic).

Verify from the OPNsense side once traffic starts flowing:

```sh
ifconfig | grep -B1 -A3 vlan300
```

Confirm frames are actually arriving tagged (interface up, non-zero
packet counters) once a test device associates.

## 5. Creating a PPSK credential in the panel

Section 3 and Section 4 are one-time, once-per-VLAN infrastructure. This
step is the repeatable one - do it once per group, any time a new PPSK is
needed against an already-provisioned VLAN/tunnel pair.

1. Log in to the panel at `http://192.168.1.175/admin` (or the current
   `resolved_app_url`) with the admin credentials from the installer summary
   (`/etc/ppsk-installer/install-summary.txt`, root-only).
2. On the PPSK list (home page), click **Add New PPSK** - this opens a
   modal, not a separate page (CLAUDE.md Section 16.3).
3. Fill in:
   - **Label**: `VLAN<id>_<GroupName>` (e.g. `VLAN300_LAPTOPTEST`) - the
     regex validation rejects anything off this pattern.
   - **VLAN / tunnel**: pick from the dropdown (Section 1's fixed block -
     picking the VLAN also fixes the tunnel, they're paired 1:1).
   - **Password**: **Auto-generate (recommended)** or **Enter manually**
     (CLAUDE.md Section 14, client-requested option). Auto-generate unless
     there's a specific reason a device needs a pre-chosen password.
   - **Enabled**: leave checked.
4. Save. A notification shows the **RADIUS username and password together,
   once** - copy both immediately (each has its own copy button). The
   password cannot be retrieved again after this notification closes;
   regenerating (the row's "Regenerate password" action) is the only way
   to get a new one later, and it invalidates the old one immediately.
5. Verify the credential resolves correctly before handing it to a device:

   ```sh
   radtest <radius_username> '<the password just shown>' 127.0.0.1 0 '<localhost client secret from clients.conf>'
   ```

   Expect `Access-Accept` with `Tunnel-Private-Group-Id = "<vlan id>"`.
   This confirms the panel-to-`radcheck`/`radreply` path (CLAUDE.md
   Section 23) is correct - it does **not** confirm the device will
   actually land on that VLAN over Wi-Fi, since PEAP has its own failure
   mode not exercised by `radtest` (see Section 4.2's note on
   `use_tunneled_reply` above). Both checks matter; neither substitutes
   for the other.

## 6. Acceptance testing (Section 21.1, mapped)

Run all 10 Section 21.1 tests now, per router, before calling that site
done. This runbook doesn't repeat their text - see CLAUDE.md Section 21.1
directly - but here is where each one lands against what you just built:

| Test | Exercises |
| --- | --- |
| 1-2 | This doc's Section 5 (panel create) + Section 3 (VLAN assignment) |
| 3-4 | This doc's Section 3.2/3.3/3.4a (tunnel identity, outbound NAT, egress IP) |
| 5-6 | Panel disable/delete (already covered by `PpskServiceTest`) |
| 7 | This doc's Section 3.7 (kill-switch) |
| 8 | This doc's Section 3.4 and 3.5 (block rule against RFC1918/management, and the management VLAN's own isolation) |
| 9 | This doc's Section 3.6 (DNS-through-tunnel) |
| 10 | Panel's Admin Log page - confirm every action from tests 1-6 has a row |

Only sign a site off once all 10 pass on real hardware. A green panel test
suite proves the software layer; it says nothing about the network layer
this document covers.
