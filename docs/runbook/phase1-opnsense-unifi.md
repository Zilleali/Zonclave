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
- [ ] All 5 residential WireGuard peer configs for this router are in hand
      (endpoint host:port, peer public key, allowed IPs, and this side's
      keypair or the provider's assigned one) before starting Section 3
      below. Provisioning tunnels with placeholder peers and forgetting to
      swap them in is the most likely way to accidentally leak traffic out
      the wrong path.
- [ ] The FreeRADIUS node (Beelink, Section 3.4) is already up, reachable at
      `172.16.74.10:1812/1813`, and `radtest` against a seeded PPSK returns
      `Access-Accept` locally on that host. Don't start wiring UniFi to a
      RADIUS server that isn't answering yet.

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
nine tagged VLANs: the four existing ones (235, 236, 237, 238) plus the
five new ones (300-304). Concretely, on the OPNsense side this means:

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
   (native/untagged VLAN on that port should stay whatever it already is,
   almost certainly not one of these nine).

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

**Resolved 2026-07-14 (Sancover):** the `ovpnc1` ("PIA UK Londen") OpenVPN
client was added for testing only and will be removed by Sancover. It was
never the intended provider for this project - confirmed the intention is
genuine residential VPN providers, not commercial/datacenter VPN services
like PIA. Do not reuse this tunnel or its config pattern for any of the 5
new WireGuard tunnels per router. Once removed (**VPN > OpenVPN > Clients**
on the OPNsense box, delete the PIA client, then confirm it no longer
appears under Interfaces > Assignments), the freed capacity has no bearing
on the trunk-port question above - OpenVPN clients are virtual interfaces
and were never occupying a physical NIC.

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

Management VLAN 205 (172.16.74.0/24) is fixed and shared, already covered by
Section 3.4; it is not part of the loop below.

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
- Gateway IP: the WireGuard peer's tunnel-side address (not `0.0.0.0`;
  OPNsense needs a monitorable target - use the provider's tunnel gateway
  IP if they publish one, or the peer's tunnel address itself)
- **Disable Gateway Monitoring only if the provider has no in-tunnel IP to
  ping.** If monitoring can be enabled, do so - Section 12's fail-closed
  behavior depends on OPNsense knowing the gateway is down.

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
   - Allow `NET_VLAN300` -> `172.16.74.10` port `1812/1813` (RADIUS, if this
     VLAN's clients ever need to reach it directly - normally they don't,
     the AP does, but keep this explicit and minimal rather than absent by
     accident)
   - Allow `NET_VLAN300` -> `10.30.0.1` port `53` (DNS resolver for this
     VLAN, see 3.5 below - this is intentionally the VLAN's own gateway,
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
pfctl -a 'filter/VLAN300' -sr
```

Read it top to bottom and confirm the block rule and its exceptions
genuinely precede the allow rule, exactly as intended - this is the one
thing worth re-reading twice.

### 3.5 DNS through the tunnel (Section 11 decision)

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

### 3.6 Kill-switch confirmation (Section 12)

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

### 3.7 Repeat

Do 3.1 through 3.6 for VLANs 301, 302, 303, 304 on this router, then repeat
the entire Section 3 for the next location.

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
- Auth Server: `172.16.74.10`, port `1812`, shared secret: the value the
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

### 4.3 Trunk port

Confirm the switch port trunking from the USW to OPNsense passes every
PPSK VLAN tagged, plus the management VLAN:

**UniFi Network > switch port profile for the OPNsense-facing port:**
tagged VLANs `205, 300, 301, 302, 303, 304` (native/untagged VLAN per your
existing convention, unrelated to this project's VLANs).

Verify from the OPNsense side once traffic starts flowing:

```sh
ifconfig | grep -B1 -A3 vlan300
```

Confirm frames are actually arriving tagged (interface up, non-zero
packet counters) once a test device associates.

## 5. Acceptance testing (Section 21.1, mapped)

Run all 10 Section 21.1 tests now, per router, before calling that site
done. This runbook doesn't repeat their text - see CLAUDE.md Section 21.1
directly - but here is where each one lands against what you just built:

| Test | Exercises |
| --- | --- |
| 1-2 | Panel (Section 16.3) create + this doc's Section 3 (VLAN assignment) |
| 3-4 | This doc's Section 3.2/3.3 (tunnel identity, egress IP) |
| 5-6 | Panel disable/delete (already covered by `PpskServiceTest`) |
| 7 | This doc's Section 3.6 (kill-switch) |
| 8 | This doc's Section 3.4 (block rule against RFC1918 and management) |
| 9 | This doc's Section 3.5 (DNS-through-tunnel) |
| 10 | Panel's Admin Log page - confirm every action from tests 1-6 has a row |

Only sign a site off once all 10 pass on real hardware. A green panel test
suite proves the software layer; it says nothing about the network layer
this document covers.
