# Zonclave - Project Reference

**Product name:** Zonclave (a Developer Zon product)
**System:** PPSK / VLAN / WireGuard Multi-Tunnel System
**Repository:** github.com/zilleali/Zonclave
**Status:** Phase 1 - In Progress (dev environment active)
**Client:** Sancover
**Developer & Network Engineer:** ZILL E ALI (Developer Zon)
**Last updated:** 2026-07-22

This file is the single source of truth for the project. Anyone picking up implementation work, human or AI-assisted, should read it in full before writing any config or code. Section numbers are stable. Do not renumber sections 1 to 22, since the kickoff prompt references them directly. Add new material as new trailing sections.

---

## 1. Project Goal (one paragraph)

Build a system where a single Wi-Fi SSID accepts many unique pre-shared keys (PPSKs). Each PPSK maps to a dedicated VLAN. Each VLAN is policy-routed on OPNsense through its own dedicated WireGuard tunnel to a residential VPN provider, so each group of devices egresses to the internet through its own distinct public ISP IP address. The system must scale to 100+ PPSK/VLAN/tunnel groups and must be manageable through a web panel rather than direct config or database editing.

**Core architectural principle (applies to every phase):** FreeRADIUS is responsible only for authentication and VLAN assignment. OPNsense is responsible only for routing, firewalling, and VPN policy. Never blur this line. Do not put routing logic in RADIUS attributes beyond the VLAN handoff, and do not put credential logic in OPNsense. This separation is what keeps the system debuggable at 100+ tunnels.

This project has two centers of gravity that carry equal weight. The **network and operations layer** (VLANs, subnets, DNS, kill-switch, firewall isolation, WireGuard health) is what makes the deployment correct and safe. The **software layer** (panel architecture, the authoritative registry, coding standards, testing) is what keeps it maintainable and scalable. Treat both as first-class. A change that is clean in code but leaks traffic on the wire is a failure, and a change that routes correctly but bypasses the registry is also a failure.

## 2. Architecture

```text
Devices
  |  (connect to SSID, enter unique PPSK password)
  v
UniFi U6+ APs (x3)  --->  RADIUS Access-Request  --->  FreeRADIUS server
  |                                                        |
  |  <--- Access-Accept + Tunnel-Private-Group-Id (VLAN) --
  v
UniFi USW-48-PoE (tags frame with VLAN, trunks to OPNsense)
  v
OPNsense
  |- VLAN sub-interface (per PPSK group)
  |- Firewall rule: source = VLAN subnet -> gateway = matching WG tunnel
  |- WireGuard interface (per group) ---> Residential VPN provider ---> Public ISP IP
  v
Internet (device traffic appears to originate from that tunnel's residential IP)
```

**Management plane (separate from data plane):**

```text
Admin browser ---> Web Panel (auth-gated) ---> ppsk_groups (source of truth)
                                            |-> FreeRADIUS DB (radcheck/radreply, generated)
                                            |-> (Phase 2) OPNsense API for tunnel/VLAN provisioning
```

## 3. Hardware / Software Inventory

### 3.1 Deployment locations (3 total, all using OPNsense Protectli FW6E)

| Location | Router | Status |
| --- | --- | --- |
| Office SancoMedia Kelder (basement) | Protectli FW6E - OPNsense | Phase 1 start point |
| Location 2 | Protectli FW6E - OPNsense | Phase 1, after Location 1 validated |
| Location 3 | Protectli FW6E - OPNsense | Phase 1, after Location 1 validated |

Note: client has 4 x Protectli FW6E units total. The 4th is a spare or future location. Phase 1 covers the 3 active locations.

### 3.2 Per-location hardware

| Component | Item | Notes |
| --- | --- | --- |
| Router / Firewall | Protectli Vault FW6E (Intel Quad Core i7, AES-NI) | OPNsense installed, 6 ports |
| Switch | UniFi USW-16-PoE (confirmed at Kelder) | Tagged VLAN trunk to OPNsense |
| Access Points | 5 x UniFi U6+ (confirmed at Kelder: AP-Stairs, AP-Back, AP-Room, U6-UK1, U6-UK2) | All healthy and up to date |
| Wi-Fi Controller | UniFi Cloud Key Gen2 Plus (UCK-G2-Plus) | Manages all APs, central |

### 3.3 Shared infrastructure (central, serves all locations)

| Component | Item | Notes |
| --- | --- | --- |
| Zonclave server hardware | Beelink SER5 Pro (AMD Ryzen 7 5800H, 16GB RAM, 466GB NVMe SSD) | Running Windows 11, hosting Zonclave as a Hyper-V VM |
| Host OS | Windows 11 (SancoverPC-4) | Hyper-V enabled, VM set to auto-start |
| Host static IP | 192.168.1.174 | Final, confirmed 2026-07-16 - inside the DHCP pool (see note below) |
| VM name | Zonclave | Hyper-V VM, Ubuntu, 8.23 GB RAM assigned |
| VM OS | Ubuntu 22.04 LTS | Confirmed running, internet working via External Switch |
| VM network | Hyper-V External Switch bound to Realtek PCIe GbE (Ethernet 2, MAC B0-41-6F-13-BD-BA) | Was Internal switch (no network) - fixed 2026-07-16 |
| VM static IP | 192.168.1.175 | Set in netplan. Final, confirmed 2026-07-16 (updated from the originally planned 192.168.1.250 - see Section 3.4) |
| VPN | Residential WireGuard peer configs from VPN provider | 5 per router, 15 total for Phase 1 |
| Remote access (ZILL) | RustDesk to Windows host + SSH to Ubuntu VM | Tailscale also available on the Windows host for future use |

**Hyper-V deployment notes (recorded 2026-07-16):**

The Beelink is running Windows 11 as its host OS, not bare metal Ubuntu as originally planned. The Zonclave Ubuntu server runs as a Hyper-V VM. This is a production deployment decision made by the client and is acceptable with the following three Windows host settings locked in:

1. Windows automatic reboot on update: **must be disabled** (a surprise reboot at 3am drops FreeRADIUS and takes down all PPSK authentication at all three locations)
2. VM auto-start on Windows boot: **must be set to Start** (`AutomaticStartAction = Start`) so the VM comes back after any planned or unplanned Windows reboot
3. Sleep and hibernate: **must be set to Never** (power plan: High Performance or equivalent)

These were confirmed with Sancover 2026-07-16. Verify they are still in place at any future support session. PowerShell check commands:

```powershell
# Check VM auto-start
Get-VM -Name "Zonclave" | Select-Object Name, AutomaticStartAction, AutomaticStartDelay, AutomaticStopAction

# Check sleep settings
powercfg /query SCHEME_CURRENT SUB_SLEEP

# Check Windows Update reboot policy
Get-ItemProperty "HKLM:\SOFTWARE\Policies\Microsoft\Windows\WindowsUpdate\AU" 2>$null
```

### 3.4 Management network (server access)

**Decision recorded 2026-07-14 (supersedes the VLAN 205 decision below):** checking the live OPNsense config at Office SancoMedia Kelder confirmed the VLAN/trunk work described below was never actually built - OPNsense's LAN interface is still on its factory network, and the switch, Cloud Key, and APs all sit there too. Given that, Phase 1 at Kelder skips a dedicated management VLAN entirely: the Zonclave server, switch, Cloud Key, and APs all stay on the existing flat LAN. RADIUS (UDP 1812/1813) and the panel (HTTP) don't require VLAN isolation to function, and the devices sharing this flat LAN are the office's own existing hardware, not the PPSK guest devices the isolation in Section 10 exists to contain - those stay on VLAN 300-304 exactly as designed, unaffected by this change. This is a Kelder-specific simplification, not a reversal of the PPSK VLAN mechanism itself.

| Item | Value |
| --- | --- |
| Network | 192.168.1.0/24 (flat, untagged - OPNsense's existing LAN) |
| Gateway (OPNsense) | 192.168.1.1 |
| Windows host static IP | 192.168.1.174 (final, confirmed 2026-07-16) |
| Zonclave VM static IP | 192.168.1.175 (final, confirmed 2026-07-16 - originally planned as 192.168.1.250, updated once the actual Hyper-V deployment was assigned real addresses) |
| Switch (USW-16-PoE) | 192.168.1.12 (existing) |
| UniFi Controller (Cloud Key Gen2+) | 192.168.1.191 (confirmed 2026-07-16) |

**DHCP pool overlap (flagged 2026-07-16, resolved same day):** 192.168.1.174, 192.168.1.175, and 192.168.1.191 all fall inside the existing DHCP pool (192.168.1.10-192.168.1.245), unlike the originally planned 192.168.1.250 which was deliberately chosen to sit outside it. Static mappings were added in OPNsense's Services > DHCPv4 > [LAN] for all three addresses (keyed to MAC address), closing the collision risk.

Remote access for ZILL: WireGuard admin tunnel on OPNsense peering to ZILL's machine, giving SSH access to the Zonclave server and web UI access to OPNsense and the UniFi controller from anywhere, without public port-forwarding.

Topology at Kelder:

```text
Internet
    |
Protectli FW6E (OPNsense) - LAN 192.168.1.0/24 (flat, untagged)
    |  (trunk: VLAN 300-304 tagged + existing VLANs; management traffic untagged)
UniFi USW-16-PoE (192.168.1.12)
    |
    ├── Cloud Key Gen2+ (management, 192.168.1.191)
    ├── U6+ AP x5 (broadcasts PPSK SSID)
    └── Zonclave server (192.168.1.175, Hyper-V VM on host 192.168.1.174)
         - FreeRADIUS :1812/1813
         - PostgreSQL :5432
         - Zonclave panel :80
```

**Superseded decision (kept for history, decision recorded 2026-07-14, same day):** VLAN 205 (subnet 172.16.74.0/24, gateway 172.16.74.1, server at 172.16.74.10, DHCP range 172.16.74.20-172.16.74.50) was originally planned as a dedicated management VLAN, later extended to also cover the switch/Cloud Key/APs. Neither was ever implemented on the live OPNsense box, and Section 3.4 above replaces it for Kelder. VLAN 205 remains reserved and unused (Section 5) - if a later phase or a different site wants the extra isolation, it can still be stood up the same way VLAN 300-304 are.

## 4. Phasing

### Phase 1 - MVP (build first, lowest cost)

- FreeRADIUS installed, database-backed, PPSK/VLAN assignment working
- UniFi SSID authenticating against FreeRADIUS, confirmed VLAN tagging works for a real test device
- OPNsense configured with **5 to 10** WireGuard tunnels and matching VLANs, full chain validated end-to-end (device connects, correct VLAN, correct public IP)
- Minimal web panel: create, edit, enable/disable, delete PPSK, assign VLAN + tunnel from a dropdown
- One-click installer for the FreeRADIUS + database + panel node (see Section 24)
- Everything architected so Phase 2 is pure repetition, not rework

**Definition of done for Phase 1:** A test device can connect using any of the provisioned PPSKs, land in the correct VLAN, and its outbound public IP matches the residential IP of the WireGuard tunnel assigned to that PPSK. Verified per-group via an external "what is my IP" check.

### Phase 2 - Scale-out (once budget allows)

**Confirmed with Sancover 2026-07-14:** the 5 WireGuard peer configs per router are ready and Phase 1 is explicitly scoped to 5 tunnels per router (15 total), not more. Sancover's stated goal is to add more PPSK/VLAN/tunnel groups later, which is exactly what this section already exists to cover - the VLAN block (Section 5) and WireGuard/gateway naming (Section 6) are already open-ended and designed to extend without rework, so "add more in the future" is a Phase 2 continuation, not a Phase 1 scope change. Do not provision more than 5 tunnels per router in Phase 1 on the strength of this stated future intent.

- Expand from 5 to 10 up to 100+ PPSK/VLAN/tunnel groups (repeat the Phase 1 pattern; scripting/automation of OPNsense config strongly recommended at this point)
- Add device activity logs to the panel (who authenticated, when, which PPSK, session duration/data use)
- Extend the installer to drive OPNsense provisioning via API (Section 19 and Section 24)
- Any additional features requested (see Section 20, Open Items)

## 5. Reserved Network Ranges (decided and locked)

**Decision confirmed 2026-07-13.** The original 101 to 200 block was ruled out after reviewing the client's existing VLAN table at Office SancoMedia Kelder. The following VLANs are already in use across the deployment: 1, 10, 20, 21, 30, 40, 50, 60, 70, 80, 90, 100, 110, 235, 236, 237, 238. Block 300+ is completely free across all three locations.

| Item | Reserved range | Notes |
| --- | --- | --- |
| Management VLAN | **205** (reserved, unused at Kelder) | Originally planned for the Zonclave server and admin access, subnet 172.16.74.0/24. Not implemented - Kelder uses the flat LAN instead, see Section 3.4. Still reserved for a future site or phase that wants the isolation. |
| PPSK VLAN ID block | **300 onward** | Phase 1 uses 300 to 304 (5 VLANs, replicated identically on all three routers). Block is open-ended; Phase 2 simply continues from 305 onward |
| PPSK subnet scheme | **10.30.X.0/24** where X = VLAN minus 300 | VLAN 300 = 10.30.0.0/24, VLAN 301 = 10.30.1.0/24, VLAN 314 = 10.30.14.0/24, and so on |

Subnet formula is intentional: VLAN 300 - 300 = 0, so 10.30.**0**.0/24. This means a VLAN ID alone tells you the subnet with no lookup needed. Simple, self-documenting, and scales cleanly past 100 groups.

**Existing VLAN table (Office SancoMedia Kelder, confirmed from UniFi screenshots):**

| In use | Safe to use |
| --- | --- |
| 1, 10, 20, 21, 30, 40, 50, 60, 70, 80, 90, 100, 110, 235, 236, 237, 238 | 205 (management), 300+ (Zonclave PPSK) |

**Phase 1 PPSK VLAN allocation per router (5 tunnels each):**

| VLAN | Subnet | WireGuard interface | Gateway |
| --- | --- | --- | --- |
| 300 | 10.30.0.0/24 | WG_VLAN300 | GW_WG_VLAN300 |
| 301 | 10.30.1.0/24 | WG_VLAN301 | GW_WG_VLAN301 |
| 302 | 10.30.2.0/24 | WG_VLAN302 | GW_WG_VLAN302 |
| 303 | 10.30.3.0/24 | WG_VLAN303 | GW_WG_VLAN303 |
| 304 | 10.30.4.0/24 | WG_VLAN304 | GW_WG_VLAN304 |

The same VLAN IDs and subnet scheme are replicated identically on all three OPNsense routers. Each router has its own 5 WireGuard tunnel interfaces mapped to VLANs 300 to 304, pointing to different residential peer configs. That means 15 tunnel instances total across the 3 routers, but only 5 unique VLAN IDs in use per site. A PPSK therefore selects the same VLAN number at every location, and which residential IP it egresses from depends on which site's router the device is behind.

**802.1Q ceiling note:** VLAN ID space runs 1 to 4094. Starting at 300 leaves 3,794 slots above the block, far more than this project will ever use.

## 6. Naming Conventions

Descriptive, self-documenting names are non-negotiable at this scale. Six months from now, a bare `wg1` tells you nothing, but `WG_VLAN300` tells you exactly what it is without checking a spreadsheet.

| Item | Convention | Example |
| --- | --- | --- |
| VLAN ID | 300 onward per Section 5 | VLAN 300 |
| WireGuard interface name | `WG_VLAN<id>` | `WG_VLAN300` |
| WireGuard gateway name (OPNsense) | `GW_WG_VLAN<id>` | `GW_WG_VLAN300` |
| PPSK / group label | `VLAN<id>_<GroupName>` | `VLAN300_GUESTA`, `VLAN301_GROUPB` |
| RADIUS username | `ppsk_group###` (zero-padded, matches `ppsk_groups.id`) by default | `ppsk_group001` |
| Firewall rule (allow) | `ALLOW_VLAN<id>_TO_<gateway>` | `ALLOW_VLAN300_TO_GW_WG_VLAN300` |
| Firewall rule (block) | `BLOCK_VLAN<id>_TO_RFC1918` | `BLOCK_VLAN300_TO_RFC1918` |
| Firewall alias (per VLAN subnet) | `NET_VLAN<id>` | `NET_VLAN300` |
| Management VLAN interface | `MGMT_VLAN205` | Fixed, not per-PPSK |
| OPNsense VLAN interface | `igb<n>_vlan<id>` | `igb0_vlan300`, `igb0_vlan205` |

Apply this consistently across OPNsense interface descriptions, gateway names, firewall rules and aliases, and the `ppsk_groups` table. The names should match across all of them so a search for `VLAN300` in any system surfaces everything related to it.

**RADIUS username manual entry (decision recorded 2026-07-18, client request from Sancover):** auto-generate (`ppsk_group###`) remains the default, but manual entry is now an explicit opt-in on the Create form, same pattern as the Section 14 password reversal - needed for the client's own naming scheme (e.g. `SancoUk1`, `SancoUk2`) rather than the sequential internal convention. Create-only: there is no "change username" action after creation, since an existing device is already paired against whatever username it authenticated with - fixing a mistake means delete and recreate, not edit. Every manual value passes through `App\Domain\RadiusUsername` (3 to 64 characters, letters/numbers/underscore/hyphen only) and a uniqueness check against `ppsk_groups.radius_username` before it is ever persisted.

## 7. Configuration Registry - Authoritative Inventory

This is the most important structural decision in the whole system. **`ppsk_groups` is not UI metadata. It is the single authoritative inventory table that everything else derives from.** FreeRADIUS entries, OPNsense firewall and interface config, and any future provisioning scripts are all generated *from* this table, never maintained independently of it. This is what prevents drift once you are past 50 to 100 tunnels and can no longer eyeball whether everything still matches.

```sql
CREATE TABLE ppsk_groups (
  id SERIAL PRIMARY KEY,
  label VARCHAR(128) NOT NULL,             -- e.g. "VLAN300_GUESTA"
  radius_username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,     -- see Section 14 for at-rest handling
  vlan_id INT NOT NULL,
  subnet VARCHAR(32) NOT NULL,             -- e.g. "10.30.0.0/24"
  wireguard_interface VARCHAR(32) NOT NULL,-- e.g. "WG_VLAN300"
  wireguard_gateway VARCHAR(32) NOT NULL,  -- e.g. "GW_WG_VLAN300"
  opnsense_interface VARCHAR(64),          -- e.g. "igb0_vlan300"
  status VARCHAR(16) NOT NULL DEFAULT 'active',  -- active / disabled / provisioning / error
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW()
);
```

Notes:

- `password_hash` column: FreeRADIUS needs the cleartext value to hand to the AP for PSK derivation, so the real design is "encrypted at rest, decrypted only at the point FreeRADIUS needs it," not a one-way hash. This is a build-time decision that affects the `radcheck` write path. See Section 14.
- `status` is broader than a boolean `enabled`. It also lets Phase 2 automation track `provisioning` or `error` states when OPNsense config is created via API instead of by hand.
- The FreeRADIUS `radcheck`/`radreply` rows (Section 8) and the OPNsense objects (Section 9) are **generated artifacts** of a `ppsk_groups` row, not independent sources of truth. If they ever disagree with `ppsk_groups`, `ppsk_groups` wins and the others get regenerated.
- All writes to `radcheck`/`radreply` go through the single registry-to-RADIUS code path defined in Section 23. No route, controller, seeder, or ad-hoc query writes to RADIUS tables directly.

## 8. FreeRADIUS Design

### 8.1 Hosting

Small VM or LXC container, 1 vCPU and 512MB to 1GB RAM is sufficient for 100 concurrent RADIUS clients. Can run on-site (no VPS required) if there is spare capacity on existing hardware.

### 8.2 Database schema (standard FreeRADIUS `rlm_sql` tables, generated from `ppsk_groups`)

```sql
-- radcheck: one row per PPSK, generated from ppsk_groups
INSERT INTO radcheck (username, attribute, op, value)
VALUES ('ppsk_group001', 'Cleartext-Password', ':=', '<unique-password>');

-- radreply: three rows per PPSK, generated from ppsk_groups.vlan_id
INSERT INTO radreply (username, attribute, op, value) VALUES
  ('ppsk_group001', 'Tunnel-Private-Group-Id', ':=', '101'),
  ('ppsk_group001', 'Tunnel-Type', ':=', 'VLAN'),
  ('ppsk_group001', 'Tunnel-Medium-Type', ':=', 'IEEE-802');
```

"Disable" a PPSK means set `ppsk_groups.status = 'disabled'`, which removes or withholds the corresponding `radcheck` row. `radcheck`/`radreply` are never edited directly. Always through the panel writing to `ppsk_groups` first, per Section 7 and Section 23.

### 8.3 UniFi integration

- Confirm the SSID security mode supports RADIUS-based Private PSK / Identity PSK on the installed Network application version. This has shifted across UniFi firmware versions, so verify against current UniFi docs at build time rather than assuming a fixed UI path.
- Point the SSID RADIUS profile at the FreeRADIUS server IP and shared secret.
- Confirm the trunk port from USW-48-PoE to OPNsense passes all PPSK VLANs tagged.

## 9. OPNsense Design

Per VLAN/tunnel group, repeated for each PPSK group (naming per Section 6):

1. **VLAN interface** on the LAN-facing trunk (e.g. `igb0_vlan300`)
2. **WireGuard instance** (`WG_VLAN300`) using the corresponding residential peer config already provided by the VPN provider
3. **Gateway** (`GW_WG_VLAN300`) created for that WireGuard interface
4. **Firewall rule**: source = that VLAN subnet (`NET_VLAN300`), gateway = `GW_WG_VLAN300` (explicit, not default-route reliance; this prevents cross-group leakage)
5. **Failure behavior:** fail-closed by default. See Section 12 for the exact definition.

**Scale note for Phase 2:** 100+ simultaneous WireGuard interfaces on one OPNsense box is a real CPU and interrupt load question. Load-test before committing to the full 100+ figure on existing hardware, and consider whether config should be automated via the OPNsense API or `config.xml` rather than built by hand at that scale (see Section 19 and Section 24).

## 10. Firewall Isolation Policy

Every VLAN gets **two** rule groups, not just the allow rule in Section 9. Without the block rule, a device on one PPSK VLAN could reach another VLAN's devices or the internal network if any route happens to exist. This must be explicitly closed, not assumed away.

**Allow group** (per VLAN):

```text
VLAN300 -> Internet -> Gateway GW_WG_VLAN300
```

**Block group** (per VLAN):

```text
VLAN300 -> RFC1918 (all private address space)   [BLOCKED]
  Exceptions (explicitly allowed, scoped to specific server IPs):
  - DNS      (to designated resolver, see Section 11)
  - RADIUS   (to FreeRADIUS server)
  - DHCP     (to OPNsense DHCP service for that VLAN)
  - Management services as required (e.g. captive portal, if used)
```

Implementation notes:

- Block rules reference the `NET_VLAN<id>` alias (Section 6) as source and an `RFC1918` alias as destination, placed **above** any allow-all rule in OPNsense rule ordering (OPNsense evaluates top-down, first match wins).
- The DNS, RADIUS, and DHCP exceptions are scoped to the *specific* server IPs, not "any," to avoid accidentally re-opening lateral movement.
- Test this explicitly per Section 21. Do not assume it works because it was configured. Verify a device on VLAN300 genuinely cannot reach VLAN301 or the OPNsense management interface.

## 11. DNS Design

Decide this now. Retrofitting it after tunnels are live risks a DNS-leak period where queries go out unencrypted or through the wrong path.

Two options:

1. **OPNsense Unbound (local resolver):** VLANs resolve via OPNsense itself, which then forwards upstream. Simpler to manage centrally, but the DNS query still exits via whatever OPNsense upstream path is unless explicitly routed through each WireGuard tunnel too.
2. **DNS through the WireGuard tunnel:** each VLAN's DNS queries are forced through its own WireGuard tunnel to the VPN provider DNS (or a resolver reachable only via that tunnel). This is what most residential VPN providers recommend, specifically to prevent DNS leaks that would reveal the real ISP or location despite the tunneled traffic.

**Recommendation:** default to option 2 (DNS via tunnel) to match VPN provider guidance, unless there is a specific reason to centralize on Unbound. Whichever is chosen, apply it uniformly across all VLANs and confirm with an actual DNS leak test per group (Section 21). Do not assume the firewall block rules in Section 10 alone prevent leakage, since DNS leaks often happen through the WAN default resolver, not RFC1918 space.

**Decision recorded 2026-07-13:** option 2, DNS through each VLAN's own WireGuard tunnel, applied uniformly across all PPSK VLANs. Confirmed with a per-group DNS leak test per Section 21.

## 12. Kill-Switch / Fail-Closed Design

Section 4 says "fail closed." This section defines exactly what that means so it is unambiguous at implementation time.

```text
WireGuard tunnel goes down (handshake expires / interface drops)
        v
Associated gateway (GW_WG_VLAN300) becomes unreachable
        v
Firewall rule set has NO fallback/default rule permitting that VLAN
traffic out any other interface (specifically: no implicit or explicit
rule allowing VLAN300 -> WAN directly)
        v
Result: all outbound traffic from that VLAN is dropped, not
silently rerouted through the plain WAN connection
        v
No automatic fallback. Recovery only when the tunnel reconnects,
or via manual/admin intervention.
```

Implementation requirement: the **allow rule in Section 9 must be the only path out**, and OPNsense default "allow LAN to any" rule (present by default on a fresh install) must be explicitly overridden or removed for these VLAN interfaces. Otherwise OPNsense default behavior could route around a dead gateway back out the WAN, defeating the entire point of per-VLAN tunnels. Verify this specifically during acceptance testing (Section 21, test 7) by killing a tunnel and confirming the client shows *no internet*, not the real WAN IP.

## 13. WireGuard Health Monitoring

Not required to hand-build for Phase 1's 5 to 10 tunnels, but design the panel and DB so this slots in without rework once useful at higher tunnel counts. Per tunnel, track:

- Handshake age (time since last successful handshake)
- RX / TX bytes
- Latency (periodic ping through the tunnel to a known host)
- Packet loss
- Public IP verification (periodic check that traffic actually egresses via the expected residential IP; catches silent misconfiguration, not just tunnel-down)

Phase 1: manual spot-checks using these criteria during acceptance testing are sufficient. Phase 2: feed these into the management panel as a per-tunnel status column (healthy / degraded / down), likely via a scheduled script hitting the OPNsense API or parsing WireGuard status directly.

## 14. Password Generation Standard

By default, every PPSK is randomly generated by the system to guarantee a consistent security floor across 100+ credentials. Manual entry is also available as an explicit opt-in (decision reversed 2026-07-17, client request from Sancover) - auto-generate remains the default and the recommended path; manual entry exists for cases where a specific password needs to be assigned rather than generated.

- **Length:** 24 characters (auto-generated case)
- **Character set:** A-Z, a-z, 0-9
- **Excluded:** ambiguous characters (e.g. `0`/`O`, `1`/`l`/`I`) to reduce transcription errors if a password is ever read aloud or hand-copied
- Generated server-side at creation time by default, shown once in the panel. The "Password" field in Section 16.3 offers a choice - **Auto-generate (default)** or **Enter manually** - rather than being generate-only as originally decided. Whichever path is used, the value is shown once and is never retrievable afterward except by regenerating it.

**Validation boundary (unchanged by the above):** every PSK, generated or manually entered, passes through the same dedicated PSK value type before it is accepted anywhere in the system. That type enforces the WPA2 personal PSK constraint of 8 to 63 characters, and rejects anything outside it - this is what keeps a manually-entered password from ever being out-of-spec, even though the "always random" guarantee no longer holds. This keeps a single, testable place that owns "what is a legal PSK," so no code path can ever persist an out-of-spec key. See Section 23.

**Superseded reasoning (kept for history):** the original generate-only decision was "to guarantee a consistent security floor across 100+ credentials" and avoid weak or reused admin-chosen passwords. That tradeoff is now the client's explicit choice to accept for the flexibility of assigning specific passwords - the length/charset boundary above remains the only enforced floor for manually-entered values.

**At-rest handling:** because FreeRADIUS must present the cleartext PSK to the AP for key derivation, the value cannot be one-way hashed. Store it encrypted at rest and decrypt only at the point the registry-to-RADIUS path writes `radcheck`. The encryption key lives outside the database (env or secrets store), never in git.

## 15. Secure Storage of WireGuard Configs

Do not leave `.conf` files scattered across the filesystem where anyone with shell access can read every provider private key at once. Two acceptable patterns:

1. **Filesystem registry with restricted permissions:**

   ```text
   wg_configs/
     WG_VLAN300.conf
     WG_VLAN301.conf
     ...
   ```

   Root-owned, `600` permissions, on the OPNsense host only. Never copied to the FreeRADIUS box, the web panel host, or any backup location without equivalent protection.

2. **Encrypted metadata in the database, keys protected on OPNsense:** store non-sensitive metadata (interface name, gateway, associated VLAN) in `ppsk_groups` or a `wireguard_tunnels` table, while private keys remain only on OPNsense itself, never in the application database at all, encrypted or not. A private key does not need to leave the system that uses it.

**Recommendation:** pattern 2. The panel and database should only ever need to *reference* a tunnel by name (`WG_VLAN300`). They never need to read or display the actual private key. This also limits blast radius if the web panel or its database is ever compromised.

**Decision recorded 2026-07-13:** pattern 2. Private keys live on OPNsense only. The panel and database store tunnel names and metadata, never key material.

## 16. Web Panel Specification (Phase 1)

**Product name:** the panel built in this section is **Zonclave**. It is the customer-facing name for the whole management surface (PPSK, VLAN, and tunnel administration). Use "Zonclave" in the UI title, login page, and any client-facing documentation. Internally, code and file paths may still use `ppsk`/`radius` naming per Section 6, since those names describe the domain concept, not the product brand.

**Purpose:** let the admin manage PPSKs without touching the database or FreeRADIUS config directly.

### Tech stack (budget-first, one recorded decision)

Three candidate stacks are documented so the choice is explicit. All three can meet Phase 1 function. They differ in build effort, installer footprint, and long-term maintainability.

| Option | Strength | Trade-off | Installer footprint |
| --- | --- | --- | --- |
| **Laravel + Filament** (recommended) | Fastest to build for a Laravel developer; admin CRUD UI, auth, and validation come for free; cleanest long-term maintenance | Heavier install (composer + node asset build) | Larger, but fine on a pinned OS in the Section 24 installer |
| **Lightweight PHP** (plain PHP + PDO) | Smallest runtime footprint; leanest installer | More manual work (hand-built UI, auth, validation) | Smallest |
| **Python / Flask** | Option if Python is preferred over PHP | Most glue work for an admin UI; a second language in a PHP/RADIUS stack | Medium |

**Recommendation:** Laravel + Filament. For this developer it is the lowest build effort (which is the real budget cost here) and the most maintainable, and Filament generates the create/list/edit/disable/delete flows in Section 16.2 to 16.4 with minimal custom code. The one honest trade-off is a heavier installer, which the Section 24 script absorbs on a pinned OS. Choose plain PHP only if a minimal installer footprint outweighs build speed. Record the final choice here before Section 16 work begins.

Whichever stack is chosen, it reads and writes `ppsk_groups` as the source of truth and derives `radcheck`/`radreply` through the single path in Section 23. The stack choice does not change the data model or the RADIUS boundary.

**Access (decision recorded 2026-07-13):** local network only. The panel is reached via the flat management LAN or ZILL's WireGuard admin tunnel (Section 3.4 - updated 2026-07-14, Kelder has no dedicated management VLAN). No internet exposure, no public port-forwarding.

### Page-by-page spec

#### 16.1 Login Page

- Single admin account (decision recorded 2026-07-13). Multi-admin roles remain Phase 2.
- No self-registration.
- Session-based auth; timeout after inactivity (default 30 min, configurable).

#### 16.2 Dashboard / PPSK List (home page)

- Table of all PPSK groups: Label, RADIUS username, VLAN ID, WireGuard tunnel, Status (active/disabled), Created date.
- Search/filter by label or VLAN.
- Action buttons per row: Edit, Enable/Disable toggle, Delete (with confirmation).
- "Add New PPSK" button (top of page) opens the Create form.
- Phase 1: no live connection status here (that is Phase 2's device activity log and Section 13 health data). Phase 1 dashboard is inventory only.
- No polling. Live data loads on demand only (manual refresh or explicit button). Do not add timed auto-refresh.

#### 16.3 Create / Edit PPSK Form

Fields:

- Label (free text, human-friendly name, follows the `VLAN<id>_<GroupName>` convention from Section 6)
- RADIUS username: **auto-generate (default) or enter manually**, per Section 6 (create-only choice, decision recorded 2026-07-18). Manual entry still passes through `RadiusUsername` validation (3-64 chars, letters/numbers/underscore/hyphen) and a uniqueness check before it is persisted.
- Password: **auto-generate (default) or enter manually**, per Section 14 (a choice, not generate-only as originally decided; either way shown once on creation with a "copy" action). Manual entry still passes through the Section 14 validation boundary (8-63 chars).
- VLAN ID: dropdown populated from the pre-provisioned VLAN list (Phase 1: 5 to 10 options)
- WireGuard tunnel: dropdown populated from pre-provisioned tunnels, 1:1 matched to VLAN choice (selecting a VLAN auto-selects its paired tunnel, since they are fixed 1:1 in this design)
- Enabled (checkbox, default on)

On submit: writes/updates `ppsk_groups` (Section 7) first, which in turn generates the corresponding `radcheck`/`radreply` rows (Section 8.2) through the Section 23 path. Change takes effect immediately (FreeRADIUS reads from DB live; no service restart required for standard `rlm_sql` config). Every create/edit/delete/enable/disable action is logged per Section 17.

#### 16.4 Delete Confirmation

- Standard "Are you sure?" modal before removing a PPSK's DB rows.
- Deleting a PPSK does not delete or affect the underlying VLAN/WireGuard interface config on OPNsense (those are managed separately in Phase 1; only the credential mapping is removed).

#### 16.5 Settings Page (minimal, Phase 1)

- Change admin password.
- (Phase 2 candidate, not required Phase 1: manage the list of available VLAN/tunnel pairs from the UI instead of a fixed pre-provisioned list.)

**Decision recorded 2026-07-13:** no separate Settings page. Filament's built-in Profile page (`/admin/profile`) already covers "change admin password" (Section 16.1's single admin, no self-registration), so a second page with the same one field would be pure duplication. The email field on that page is locked read-only (see the `App\Filament\Pages\EditProfile` override), since there is no second admin account to reconcile it against. Revisit only if Phase 2 needs settings beyond password (e.g. managing the VLAN/tunnel list from the UI).

### Phase 2 additions to the panel (not built in Phase 1, listed for planning only)

- Device activity log page: which device/MAC authenticated with which PPSK, timestamp, session duration, data used (requires RADIUS accounting, `radacct` table).
- Per-tunnel health status (Section 13) surfaced as a dashboard column.
- Bulk PPSK import/export (CSV).
- Per-VLAN bandwidth/usage view.
- Multi-admin roles.
- **Instant session revocation on disable/delete** (identified 2026-07-23 during Section 21.1 test 5): disabling a PPSK correctly blocks re-authentication, but an already-connected device keeps its WPA2 session until it disconnects - RADIUS is only consulted at association time, so this is standard behavior, not a bug. Phase 1 workaround: kick the client manually in the UniFi controller after disabling. Phase 2: have `PpskService`'s disable/delete path send an RFC 5176 Disconnect-Message (CoA) to the AP so revocation is immediate - requires enabling RADIUS Dynamic Authorization in the UniFi RADIUS profile and verifying the APs honor it (test with `radclient -x <AP-IP>:3799 disconnect` first).

## 17. Administrative Logging (Phase 1)

Lightweight but built from day one. Cheap to add now, and the first thing you will want when troubleshooting "why did this PPSK stop working."

Log at minimum:

- Admin login (success/failure)
- PPSK created
- PPSK deleted
- PPSK enabled
- PPSK disabled
- PPSK password regenerated

A simple `admin_log` table (`id`, `timestamp`, `admin_user`, `action`, `target_ppsk_id`, `detail`) is sufficient for Phase 1. No dedicated logging service needed at this scale.

## 18. Application Architecture (Service Layer)

Even though Phase 1 writes "directly" to the database in the sense that there is no OPNsense API integration yet, the application must not embed raw SQL throughout the UI/route layer. Use a layered structure from the start:

```text
Browser
   v
Routes           (HTTP endpoints)
   v
Controllers      (request/response handling, validation)
   v
Services         (business logic: "create a PPSK" = generate password,
                   write ppsk_groups, derive radcheck/radreply, log action)
   v
Repositories     (the only layer that talks to the database)
   v
Database
```

Why this matters now rather than later: the "create a PPSK" service function in Phase 1 does password generation plus a few DB writes. In Phase 2, that *same* function gains steps that call the OPNsense API (Section 19). The routes and controllers above it do not need to change at all if the service-layer boundary was respected from the start. Skipping this now makes Phase 2 a rewrite instead of an addition.

This maps cleanly onto whichever stack is chosen in Section 16. In Laravel this is routes, controllers or Filament resources, service classes, and repository classes. In plain PHP or Flask it is the same four layers under different filenames. The layer boundaries are mandatory regardless of framework.

## 19. Future Automation Workflow (Phase 2)

Once scaling beyond the initial 5 to 10 tunnels, introduce a provisioning workflow that turns "create a PPSK" from a partly-manual process (OPNsense config still built by hand in Phase 1) into a fully automated one:

```text
Create PPSK (via panel)
   v
Generate password (Section 14)
   v
Insert FreeRADIUS entries (radcheck/radreply, derived from ppsk_groups)
   v
Create VLAN interface (via OPNsense API)
   v
Create WireGuard interface (via OPNsense API)
   v
Assign gateway
   v
Create firewall rules (allow + block group, per Section 10)
   v
Verify tunnel (handshake + public IP check, per Section 13)
   v
Mark ppsk_groups.status = 'active'
```

This is only feasible cleanly because of the service-layer separation in Section 18 and the authoritative-registry design in Section 7. The automation job becomes "make OPNsense match what `ppsk_groups` says it should be," which is a much simpler and safer operation than free-form scripting against live config.

## 20. Open Items - Need Decisions Before Build Starts

**Resolved (closed):**

- [x] VLAN ID range: **300 onward**, subnet 10.30.X.0/24 (VLAN minus 300 = X). Confirmed 2026-07-13 after reviewing existing VLAN table at Office SancoMedia Kelder.
- [x] Panel stack: **Laravel + Filament**
- [x] Database: **PostgreSQL**
- [x] Installer OS: **Ubuntu Server 24.04 LTS** (updated 2026-07-13, was 22.04; 24.04 is what will be installed on the Beelink)
- [x] Server: Beelink SER5 Pro, flat LAN, static IP 192.168.1.175 (updated 2026-07-16, was 192.168.1.250; before that, VLAN 205 static IP 172.16.74.10 - see Section 3.4)
- [x] Management VLAN: **none at Kelder** - flat LAN 192.168.1.0/24 used directly (decision reversed 2026-07-14, see Section 3.4). VLAN 205 / subnet 172.16.74.0/24 remains reserved and unused (Section 5)
- [x] FreeRADIUS hosting: Beelink SER5 Pro, co-located with the panel
- [x] Phase 1 tunnel count: **5 per router, 15 total** (3 routers x 5 tunnels)
- [x] Scope: **3 locations**, all Protectli FW6E / OPNsense. 4th unit is spare.
- [x] Project name: **Zonclave**
- [x] Budget: **$600 total** for all three routers

- [x] DNS design: **DNS-through-tunnel per VLAN** (Section 11). Decided 2026-07-13.
- [x] Storage pattern for WireGuard configs: **pattern 2, keys stay on OPNsense only** (Section 15). Decided 2026-07-13.
- [x] Panel admin accounts: **single admin** (Section 16.1). Decided 2026-07-13.
- [x] Panel access: **local network only**, via the flat management LAN or ZILL's WireGuard admin tunnel (Section 16). Decided 2026-07-13, network detail updated 2026-07-14 per Section 3.4.

- [x] UniFi Network application version: **10.4.57** confirmed on the Office SancoMedia Kelder Cloud Key Gen2+, well above the 7.5.187 minimum for RADIUS-based PPSK (Section 8.3). Decided 2026-07-14; re-verify per site for the other two locations.
- [x] WireGuard peer configs for Office SancoMedia Kelder: **5 per router, confirmed ready** by Sancover 2026-07-14. Sancover's stated goal is to add more groups later; Phase 1 stays scoped to 5 per router regardless (see Section 4).

- [x] Server deployment model: **Hyper-V VM on Windows 11** (Beelink SER5 Pro). Confirmed 2026-07-16. Not bare metal as originally planned. Windows host hardening (no auto-reboot, VM auto-start, no sleep) confirmed with Sancover same day.
- [x] VM OS: **Ubuntu 22.04 LTS** (confirmed running, not 24.04 as noted in earlier planning). PHP 8.3 was added via the ondrej/php PPA. Resolved 2026-07-16: `installer/install-ubuntu22.04.sh` is the one officially supported installer (Section 24.4). Briefly ran both 24.04 and 22.04 as dual-supported the same day (ADR 0002), reverted back to single-target 22.04 hours later (ADR 0003) once the 22.04 script needed real-world fixes on the actual Kelder VM - `install.sh` (24.04) was removed from the repo entirely, not just deprecated in place.
- [x] Hyper-V virtual switch: **External Switch** bound to Ethernet 2 (Realtek PCIe GbE, MAC B0-41-6F-13-BD-BA). Fixed 2026-07-16, was Internal switch with no network access.
- [x] Dev environment superseded by production: `install-ubuntu22.04.sh` now deploys the real panel to `/opt/zonclave` on PostgreSQL (the earlier `/var/www/Zonclave/panel` SQLite dev copy is separate and not the production path). Panel confirmed reachable and admin login working at `http://192.168.1.175/admin` (2026-07-16).
- [x] Host machine compatibility: **any hypervisor-capable machine works** (Windows, Linux, or Mac), since the installer only ever targets the Ubuntu 22.04 guest, never the host. No installer rewrite needed. Confirmed 2026-07-16, see Section 24.4.
- [x] Installer bugs found running against the real Kelder VM, all fixed 2026-07-16: (1) `install_db()` used `psql -f` to load the FreeRADIUS schema as the `postgres` OS user, which cannot read FreeRADIUS's config files - switched to shell redirection so root reads the file instead; (2) `self_check()`'s RADIUS smoke test used the wrong client secret against 127.0.0.1 (RADIUS_SECRET belongs to the AP-subnet client, not the default `localhost` client) - now reads the actual `localhost` client secret from `clients.conf`; (3) `deploy_panel()` used substitute-only `sed` for `.env`'s `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`, which silently did nothing since `.env.example` doesn't ship those keys - replaced with a set-or-append `set_env` helper. FreeRADIUS confirmed returning `Access-Accept` with the correct VLAN, and the panel confirmed loading with real PostgreSQL data.
- [x] Static IPs finalized 2026-07-16: Windows host 192.168.1.174, Zonclave VM 192.168.1.175, UniFi Controller 192.168.1.191 (superseding the originally planned 192.168.1.250 for the server - see Section 3.4).
- [x] DHCP static mappings added in OPNsense 2026-07-16 for .174, .175, and .191 (Section 3.4) - closes the collision risk those addresses had sitting unreserved inside the existing DHCP pool.
- [x] Public `/docs` pages (installation guide, command reference, OPNsense configuration guide) render `docs/*.md` live instead of a hand-copied Blade view, per client request 2026-07-21 to keep docs and the published page from drifting apart. See ADR 0004 for the mechanism, the link-sanitization safety property, and the resulting installer contract change (`docs/` now syncs to `/opt/docs` alongside `/opt/zonclave`). `docs/site-configuration.md`/`docs/troubleshooting.md` are explicitly not part of this - those two public pages remain hand-written (no general-purpose markdown source).

**Still open:**

- [ ] Confirm the 5 WireGuard peer configs per router are ready for Location 2 and Location 3 (10 more, 15 total) before OPNsense config begins there
- [ ] Install Tailscale on the Ubuntu VM for persistent remote SSH access (optional but recommended given the Hyper-V setup)
- [ ] OPNsense manual config at Kelder: VLANs 300-304, WireGuard tunnels, gateways, firewall allow/block rules (Sections 9-12; see `docs/opnsense-configuration.md` and `docs/runbook/phase1-opnsense-unifi.md`)
- [ ] UniFi: RADIUS profile + SSID pointed at 192.168.1.175 with the shared secret from the install summary (Section 8.3)
- [ ] Full Section 21 acceptance test pass end to end, once the network side above is in place

## 21. Acceptance Testing (Phase 1)

Both manual end-to-end acceptance and automated tests are required. The manual list proves the physical chain works; the automated tests protect the registry-to-RADIUS logic from regression as the panel changes.

### 21.1 Manual end-to-end acceptance (run on real hardware)

1. Provision 2 test PPSKs via the panel, each mapped to a different VLAN/tunnel.
2. Connect a test device with PPSK #1 and confirm the device receives an IP in the correct VLAN subnet.
3. Check the outbound public IP from that device and confirm it matches WireGuard tunnel #1's residential IP.
4. Repeat with PPSK #2 and confirm a different VLAN and a different public IP.
5. Disable PPSK #1 via the panel and confirm that credential no longer authenticates.
6. Delete a PPSK via the panel and confirm it is removed from FreeRADIUS and no longer usable.
7. Kill WireGuard tunnel #1 and confirm the exact fail-closed behavior in Section 12 (no internet, no silent fallback to WAN; verify the client public IP is *not* the real WAN IP).
8. From a device on VLAN300, attempt to reach a device on VLAN301 and the OPNsense management interface, and confirm both are blocked per Section 10.
9. Run a DNS leak test from a connected device and confirm queries follow the design chosen in Section 11.
10. Confirm every action in tests 1 to 6 produced a corresponding row in `admin_log` (Section 17).

Helper commands for tests 3, 7, and 9: `wg show` on OPNsense (tunnel and handshake state), `curl -s ifconfig.me` from a client on each VLAN (egress IP), and `radtest <user> <pass> 127.0.0.1 0 <secret>` on the FreeRADIUS host (auth and returned VLAN attribute).

### 21.2 Automated tests (protect the software layer)

- **Unit:** PSK value type (8 to 63 constraint, ambiguous-char exclusion in the generator), status transitions, and the derivation logic that turns a `ppsk_groups` row into the correct `radcheck`/`radreply` rows. Fast, no database, dependencies mocked.
- **Integration:** service plus real test database. Assert that creating a PPSK writes the correct `ppsk_groups` row and the derived RADIUS rows in one transaction; that disabling withholds authentication; that deleting leaves no orphan RADIUS rows; and that a failure in the RADIUS write rolls the whole operation back (no partial state).
- **Coverage:** the registry-to-RADIUS path (Section 23) must have every write branch exercised by integration tests. Aim for high coverage on service and derivation code; UI is covered at smoke level through the create/disable/delete flows.

## 22. Explicitly Out of Scope (Phase 1)

- RADIUS accounting / device activity logs
- WireGuard health monitoring dashboard (manual checks only in Phase 1, per Section 13)
- Multi-admin roles / permissions
- Automated OPNsense provisioning via API/scripting (manual config for the initial 5 to 10 tunnels, per Section 9; automation is the Section 19 Phase 2 workflow)
- Installer automation of OPNsense and UniFi (the Section 24 installer covers the FreeRADIUS + database + panel node only in Phase 1)
- Any tunnels/VLANs beyond the agreed Phase 1 count

## 23. Coding Standards and the RADIUS Write Boundary

### 23.1 The RADIUS write boundary (security-relevant, non-negotiable)

This is the single most sensitive boundary in the codebase. The mapping between a PPSK, its VLAN, and its identity in the RADIUS store is a security boundary, not a convenience.

- `ppsk_groups` is the source of truth. `radcheck`/`radreply` are a transactional projection of it.
- All writes to `radcheck`/`radreply` go through **one** service method (the registry-to-RADIUS path). No route, controller, Filament resource, seeder, or ad-hoc query writes to RADIUS tables directly.
- Projection is transactional. A create, edit, disable, or delete either fully materializes into the RADIUS tables or rolls back. No partial state.
- Disable and delete both flow through this path. Disabling must revoke the ability to authenticate, not just hide a row in the UI.
- The VLAN handed to the AP is derived only from the stored `ppsk_groups.vlan_id`. Never compute or accept a VLAN from client input at authentication time.

### 23.2 Always do

- Validate all external input at the boundary (controllers / form requests). Never trust input past the first layer.
- Route every PSK through the value type in Section 14 before persistence.
- Wrap any operation that touches both `ppsk_groups` and RADIUS tables in a single transaction opened at the service layer.
- Index every column used in a where, join, or order by on `ppsk_groups`, `radcheck`, `radreply`, and `admin_log`.
- Redact secrets (PSK, keys, shared secret) from all logs and error output.
- If the stack is Laravel: run Laravel Pint and Larastan clean before every commit, declare `strict_types=1`, and use typed signatures and native enums for fixed sets like status.
- Keep a short ADR note in `docs/` for any decision that changes the data model, the RADIUS boundary, or the installer contract.

### 23.3 Never do

- Never write to a RADIUS table outside the single Section 23.1 path.
- Never derive a VLAN or tunnel from client-supplied data at auth time.
- Never add polling or timed auto-refresh to the panel. On-demand loads only.
- Never let a tunnel failure fall back to the default WAN (Section 12).
- Never commit secrets, real peer configs, or `.env`.
- Never add AI attribution or co-author trailers to commit messages.
- Never use em dashes or en dashes anywhere in code, comments, docs, or output. Hyphens only.

### 23.4 Git workflow

- `develop` is the integration branch; feature branches merge into it. `main` receives release merges only, with `--no-ff`.
- Conventional Commits, tied to section numbers where useful (e.g. `feat: Section 8 rlm_sql schema + seed`).
- One logical change per commit; keep RADIUS-boundary changes in their own reviewable commit.

## 24. One-Click Installer Script (Phase 1 scope)

### 24.1 What it is

An encrypted, single-command installer that provisions the **auth and panel node**: the database, FreeRADIUS with `rlm_sql`, and the web panel, on one Linux host. The goal is that the client runs one command and the node comes up configured, seeded, and ready, with the panel URL, admin login, and RADIUS shared secret printed at the end for pasting into UniFi.

### 24.2 Honest boundary (read before promising this to the client)

The installer configures one host. It does **not** configure OPNsense (VLAN interfaces, WireGuard, gateways, firewall rules) or UniFi (SSID, RADIUS profile), because those are separate appliances. OPNsense automation depends on its API and is Phase 2 (Section 19). So in Phase 1, "one click" means the FreeRADIUS + database + panel node is fully automated, and the OPNsense and UniFi steps remain a documented manual runbook. Do not describe the Phase 1 installer as setting up the entire end-to-end chain.

### 24.3 What the installer does (auth + panel node)

1. Preflight: require root, detect and enforce the supported OS, check for an existing install and offer safe re-run (idempotent).
2. Install dependencies: database engine (PostgreSQL or MariaDB per Section 3 choice), FreeRADIUS, web server, PHP-FPM or Python runtime per the Section 16 stack, plus openssl and git.
3. Database: create the database and a least-privilege user, load the FreeRADIUS `rlm_sql` schema, `ppsk_groups`, and `admin_log`, and seed 2 test PPSK groups.
4. FreeRADIUS: configure the `sql` module and site, write `clients.conf` with the UniFi controller/AP source and a generated shared secret, enable and start the service, and run a config self-test (`radiusd -XC`).
5. Panel: deploy the app, generate `.env` (DB credentials, app key, admin credentials), run migrations, build assets if the Laravel stack is chosen, and install a web server vhost and a service unit.
6. Secrets: generate the RADIUS shared secret, the panel admin password, and any app key at runtime. Never hardcode them. Display them once at the end and write them to a root-only summary file.
7. Post-install: print the panel URL, admin login, shared secret, and the exact next manual steps for OPNsense and UniFi. Write a full install log for support.

### 24.4 Design rules for the script

- Target and pin one OS: **Ubuntu Server 22.04 LTS** (reverted 2026-07-16, was briefly dual 24.04/22.04 support earlier the same day - see ADR 0003, which supersedes ADR 0002). `installer/install-ubuntu22.04.sh` is the one officially supported installer, since 22.04 is what the Office SancoMedia Kelder deployment actually runs (Section 26). PHP 8.3 is added via the `ondrej/php` PPA, since 22.04's base repos only ship 8.1. The original Ubuntu 24.04 script (`installer/install.sh`) was removed from the repo rather than kept deprecated-in-place - an untested installer sitting around looking supported is worse than not having it; re-add 24.04 support as a fresh decision if it's ever needed again, not by resurrecting a stale file. State the requirement plainly; do not attempt to support multiple distros or versions at once, or one-click reliability breaks.
- `set -euo pipefail`, root and OS checks up front, idempotent functions, clear stage logging.
- Modular internal structure even though it ships as one blob: `preflight`, `install_db`, `install_freeradius`, `deploy_panel`, `configure_services`, `self_check`, `summary`.
- Minimal input: prompt only for the essentials (UniFi/AP subnet, admin email) or read a small answers file; generate sane defaults for the rest.
- A `self_check` stage at the end verifies the FreeRADIUS config test passes, services are active, and the panel responds, before printing success.

**Host machine compatibility (decision recorded 2026-07-16):** the installer only ever targets the guest OS - it has no opinion about what physical or virtual host that guest runs on. This is what makes Zonclave deployable on any client's existing hardware without a rewrite: the host can be Windows (Hyper-V, VirtualBox, VMware Workstation), Linux (KVM/libvirt, VirtualBox), or macOS (VMware Fusion, Parallels, UTM) with Ubuntu running as a VM, or bare-metal Ubuntu directly. The Kelder deployment (Section 3.3, Section 26) runs Windows 11 + Hyper-V with an Ubuntu 22.04 guest because that is the hardware the client already had; a future client with a bare-metal Linux box or a Mac would run the same `install-ubuntu22.04.sh` unchanged, either inside a VM or directly. Do not write host-OS-specific branches into the installer to chase broader compatibility - the VM boundary already solves it for free, and adding OS branches to the script itself is exactly the one-click reliability risk the single-OS pin above exists to avoid.

### 24.5 Encryption and delivery (with the honest limitation)

The intent is to hand the client one opaque command and to protect the method as a deliverable. Two workable approaches:

- **openssl wrapper (portable):** AES-256 encrypt the real script into a payload, ship a tiny decryptor stub that takes a passphrase (which you provide), decrypts in memory, and pipes to bash. Portable, no per-architecture build.
- **shc (compiled):** compile the script to a per-architecture binary for casual obfuscation.

Honest limitation to keep in mind: any script the client executes must decrypt to run, so this is tamper-friction and IP-obfuscation, not true secrecy. A determined user with root can still recover the decrypted contents at runtime. That is acceptable if the goal is a clean, product-like deliverable and casual protection of the method. It is not a guarantee of confidentiality. Also note that asking a client to run an opaque encrypted blob as root is a trust ask; a technical client may prefer a checksum, a walk-through, or that you run it yourself over the access already discussed. Decide the delivery model (client-run vs you-run) and record it in Section 20.

### 24.6 Phasing of the installer

- **Installer Phase A (now):** the auth + panel node, fully one-click, as above.
- **Installer Phase B (with Section 19):** once OPNsense API automation exists, the same script (or a companion) drives VLAN, WireGuard, gateway, and firewall provisioning from `ppsk_groups`, moving the system toward genuine end-to-end one-click. This is a Phase 2 extension, not a Phase 1 promise.

## 25. AI Interaction Rules

For any AI assistant (Claude Code or otherwise) working in this repo.

### 25.1 Before writing code

- Read this file in full. Confirm the current phase and what is decided vs open (Section 20) before proposing anything.
- Do not assume answers to Section 20 open items. Ask directly.
- `ppsk_groups` (Section 7) is authoritative. Everything else is derived from it, never maintained independently.
- Respect the layer boundary in Section 1 and Section 18: FreeRADIUS does auth/VLAN only, OPNsense does routing/firewall/VPN only.
- If a task would write to a RADIUS table outside the Section 23.1 path, stop and flag it.

### 25.2 While writing code

- Build the Section 18 layered structure from the first line of panel code, even though Phase 1 does not need the OPNsense API yet. This is what makes Phase 2 an addition, not a rewrite.
- Produce production-ready output that passes the Section 23 standards. Lead with the change and exact file paths; keep explanations minimal and technical.
- Match existing patterns. Do not introduce a new pattern for a problem an existing one already solves.
- CLI-first for all networking guidance (OPNsense, WireGuard, MikroTik). No GUI click-paths unless asked.
- If a file is corrupted or a partial patch would be fragile, rewrite the whole file cleanly.

### 25.3 Do not, without explicit permission

- Do not refactor working, tested architecture as a side effect of an unrelated task.
- Do not add dependencies, change the folder structure, or alter the RADIUS boundary or installer contract.
- Do not describe the Phase 1 installer as setting up the full end-to-end chain (Section 24.2).
- Do not add AI attribution to commits or code.
- Do not use em dashes or en dashes in any output.

### 25.4 When finishing

- State which files changed and why, one line each.
- List any command to run (migrations, config test, service restart, tests).
- If a change has a network-side implication (new VLAN, tunnel, or rule), note the matching OPNsense, FreeRADIUS, or UniFi step. Do not assume the infrastructure side is done because the code compiles.
- Commit incrementally with messages tied to section numbers.

## 26. Dev Environment State (recorded 2026-07-16)

Current state of the Zonclave VM as of this date. Update this section whenever the environment changes significantly.

### 26.1 Server

| Item | Value |
| --- | --- |
| Host machine | Beelink SER5 Pro (SancoverPC-4), Windows 11 |
| Host static IP | 192.168.1.174 (final, confirmed 2026-07-16) |
| VM name | Zonclave |
| VM OS | Ubuntu 22.04 LTS |
| VM static IP | 192.168.1.175 (final, confirmed 2026-07-16 - was 192.168.1.250 in earlier planning) |
| VM RAM assigned | 8.23 GB |
| Hyper-V switch | External Switch (bound to Ethernet 2, Realtek PCIe GbE) |
| Panel URL | `http://192.168.1.175/admin` - confirmed reachable, admin login working (2026-07-16) |
| Remote access | RustDesk to Windows host, then SSH to 192.168.1.175 |

Note: both IPs are inside the existing DHCP pool (192.168.1.10-192.168.1.245) with no static mapping yet - see the open item in Section 20.

### 26.2 Installed software (on the Ubuntu VM)

| Software | Version | Status |
| --- | --- | --- |
| PHP | 8.3.6 (via ondrej/php PPA) | Running |
| Nginx | Default Ubuntu package | Running, serving /opt/zonclave/public |
| PostgreSQL | Installed | Running, wired to the panel (database `ppsk`) |
| FreeRADIUS | Installed via `install-ubuntu22.04.sh` | Running, confirmed returning Access-Accept with correct VLAN attributes (2026-07-16) |
| Composer | 2.x | Installed at /usr/local/bin/composer |

### 26.3 Panel deployment

The production deployment (`install-ubuntu22.04.sh`) lives at `/opt/zonclave`, separate from an earlier manual dev copy at `/var/www/Zonclave/panel` (SQLite, superseded - do not confuse the two when debugging).

| Item | Value |
| --- | --- |
| Code location | /opt/zonclave |
| Nginx site | /etc/nginx/sites-available/zonclave (symlinked to sites-enabled), `server_name 192.168.1.175` |
| Nginx document root | /opt/zonclave/public |
| PHP-FPM socket | /run/php/php8.3-fpm.sock |
| Ownership | www-data:www-data (set by the installer's `deploy_panel()`) |
| Database | PostgreSQL, database `ppsk`, confirmed working (2026-07-16) |
| Repo checkout (source) | /var/www/Zonclave - `git pull` here, then re-run `installer/install-ubuntu22.04.sh` to redeploy to /opt/zonclave |
| .git ownership | zille:zille (do not chown to www-data - breaks git pull) |

### 26.4 Critical permission rules (learned from incidents 2026-07-16)

- **Never run `chown -R` from /var/www/Zonclave (the repo root).** Always run from /var/www/Zonclave/panel. Running from the repo root changes .git ownership to www-data and breaks `git pull` with "Permission denied on .git/FETCH_HEAD".
- **storage/framework/views must be writable by www-data.** If Blade falls back to system tmp, Laravel raises a fatal ErrorException. Fix: `sudo chown -R www-data:www-data /var/www/Zonclave/panel/storage/framework`
- **database/ directory and database.sqlite must be writable by www-data** when using SQLite. Fix: `sudo chown www-data:www-data /var/www/Zonclave/panel/database /var/www/Zonclave/panel/database/database.sqlite`

### 26.5 Installer bugs found and fixed against this VM (2026-07-16)

Running `install-ubuntu22.04.sh` against this actual VM (not just in isolation) surfaced three real bugs, all now fixed:

1. **FreeRADIUS schema failed to load silently.** `install_db()` ran `psql -f` as the `postgres` OS user, which has no read permission on FreeRADIUS's config files - "Permission denied" was masked by a trailing `|| true`, so the installer carried on as if `radcheck`/`radreply` existed. They didn't, and seeding failed downstream with a confusing `relation "radcheck" does not exist` error. Fixed by using shell redirection so root reads the file instead of the `postgres` user.
2. **`self_check()`'s RADIUS smoke test used the wrong secret.** It tested `127.0.0.1` with `RADIUS_SECRET`, but that secret belongs to the `ppsk_unifi` client (scoped to the AP subnet) - `127.0.0.1` actually matches FreeRADIUS's own default `localhost` client with its own separate secret. Wrong secret against RADIUS produces total silence (RFC 2865), not a rejection, which reads as "FreeRADIUS is broken" when it isn't. Fixed by reading the `localhost` client's actual secret out of `clients.conf`.
3. **`.env`'s database settings were never actually written.** `deploy_panel()` used substitute-only `sed` (`s|^KEY=.*|KEY=value|`) for `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` - but `.env.example` only ships `DB_CONNECTION=sqlite`, with none of those other keys present to substitute. The app silently fell through to Laravel's hardcoded `config/database.php` defaults (database `laravel`, empty password), producing `SQLSTATE[08006] fe_sendauth: no password supplied`. Fixed with a set-or-append `set_env` helper.

Panel and FreeRADIUS are both confirmed working end to end as of this date: admin login succeeds at `http://192.168.1.175/admin`, and `radtest` against the seeded `ppsk_group001` returns `Access-Accept` with `Tunnel-Private-Group-Id = "300"`.

### 26.6 Next steps (in order)

Static DHCP mappings for .174, .175, and .191 are done (Section 3.4, Section 20).

1. **(Optional but recommended) Install Tailscale on the Ubuntu VM** so ZILL has persistent SSH access without depending on RustDesk to the Windows host first. The Windows host already has Tailscale installed.

   ```bash
   curl -fsSL https://tailscale.com/install.sh | sh
   sudo tailscale up
   ```

2. **OPNsense manual config**: VLAN300 is done and validated end to end (Section 26.7). VLANs 301-304 still need the same build - repeat `docs/runbook/phase1-opnsense-unifi.md` Section 3 for each, including the outbound NAT step (3.4a) that VLAN300 initially missed.
3. **UniFi**: done. SSID RADIUS profile points at 192.168.1.175 with the shared secret from the install summary; RADIUS-based PPSK confirmed working over WPA2-Enterprise/PEAP once the FreeRADIUS-side fix in Section 26.7 was applied.
4. **Create at least one real PPSK through the panel itself**: done - `ppsk_group046` (label `VLAN300_LAPTOPTEST`-style, VLAN 300), created via the panel UI, not a seeded row. Confirms the full software-layer chain - panel to `ppsk_groups` to `PpskService::projectToRadius()` to `radcheck`/`radreply` - works end to end on production data.
5. **Full Section 21 acceptance test pass**: partially done for VLAN300 (tests 1-4 pass - see Section 26.7). Tests 5-10 (disable/delete revoke access, kill-switch fails closed, VLAN isolation holds, DNS leak test passes, every action lands in `admin_log`) still need to be run explicitly before signing off VLAN300, and the whole pass repeats once VLANs 301-304 are built.
6. **Replicate to Location 2 and Location 3** once Kelder passes acceptance testing, after confirming their WireGuard peer configs are ready (Section 20).

### 26.7 First real PPSK/VLAN/tunnel test - issues found and fixed (2026-07-17)

A Windows laptop connected to `Zonclave-PPSK-TEST` with `ppsk_group046`
(WPA2-Enterprise, Microsoft Protected EAP) for the first genuine end-to-end
test of the chain: SSID to RADIUS to VLAN to WireGuard tunnel to residential
egress IP. Three real, previously-undiscovered gaps surfaced, none of them
caught by `radtest` or the panel's own test suite - both exercise the
software layer only, neither exercises PEAP or the live network path. All
three are now documented as explicit steps in
`docs/runbook/phase1-opnsense-unifi.md` (Sections 3.3, 3.4a, 4.2) so they
don't get rediscovered per VLAN or per site:

1. **FreeRADIUS was authenticating correctly but not handing off the VLAN
   over PEAP.** `radtest` against `ppsk_group046` returned the correct
   `Tunnel-Private-Group-Id = "300"`, but the real device still landed on
   the flat LAN. Root cause, found via `sudo freeradius -X` live debug:
   WPA2-Enterprise/PEAP resolves the actual identity and does its
   MSCHAPv2 exchange inside an encrypted **inner tunnel**
   (`sites-enabled/inner-tunnel`), and by default FreeRADIUS does not copy
   reply attributes resolved there (the VLAN, from `radreply`) out to the
   final outer `Access-Accept`. Fixed by setting `use_tunneled_reply = yes`
   in the `peap { }` block of `/etc/freeradius/3.0/mods-available/eap` and
   restarting FreeRADIUS. This is a one-time, per-FreeRADIUS-host setting,
   not per-VLAN or per-PPSK. `radtest` alone can never catch this class of
   bug, since it doesn't speak PEAP - it authenticates the same way `chap`/
   plain auth would. A real WPA2-Enterprise device test is the only thing
   that exercises this path.
2. **`GW_WG_VLAN300` showed permanently Offline in OPNsense despite a live
   WireGuard handshake.** The gateway's Monitor IP was left at the
   WireGuard peer's own tunnel-internal address, which the residential
   provider doesn't answer ICMP on (it's a tunnel endpoint, not a router).
   Section 12's fail-closed rule then correctly refused to route through a
   gateway OPNsense believed was down - a false negative with the same
   symptom as a real outage. Fixed by setting Monitor IP (a separate field
   from Gateway IP) to a real internet host reachable through the tunnel
   (`8.8.8.8`).
3. **Outbound NAT was missing for `WG_VLAN300`.** This OPNsense box's
   Firewall > NAT > Outbound mode is Manual, not Automatic, and only had
   rules for the pre-existing `WAN`/`OVPN` groups (LAN235-238) - nothing
   for the new WireGuard tunnel interfaces. Without translation, VLAN300
   client traffic left the tunnel still sourced as its private client
   address; the provider's WireGuard peer silently dropped it (WireGuard's
   cryptokey routing generally only accepts packets whose source matches
   the peer's configured `AllowedIPs`). Symptom was a clean connection
   timeout with the firewall rule, gateway, and tunnel all appearing
   healthy - genuinely hard to distinguish from a provider-side problem
   without checking NAT specifically. Fixed by adding a manual outbound
   NAT rule (interface `WG_VLAN300`, source `NET_VLAN300`, translation
   "Interface address"). Must be repeated per VLAN (301-304) and per
   router - this is the step most likely to be forgotten during Section
   26.6 item 2's replication, since it's easy to assume the firewall allow
   rule alone is sufficient.

Confirmed working after all three fixes: the laptop received `10.30.0.10`/
gateway `10.30.0.1` (correct VLAN300 subnet) and, with Ethernet ruled out
as a confounding route via `curl.exe --interface 10.30.0.10`, egressed
through a distinct public IP matching the `WG_VLAN300` residential tunnel,
not the router's real WAN address. This satisfies Section 21.1 tests 1-4
for VLAN300; tests 5-10 are still open (Section 26.6 item 5).

### 26.8 Operational CLI: `zonclave update`

Added 2026-07-17, in response to a real deployment pain point this
session: redeploying a code change previously meant either manually
copying files (which is what corrupted production `.env` in the incident
behind the original version of this section) or re-running the full
installer (which regenerates `DB_PASSWORD`/`RADIUS_SECRET` every run and
caused the FreeRADIUS/Postgres password-mismatch incident also fixed this
session).

`scripts/zonclave-update.sh` is the source of truth (installed as
`/usr/local/bin/zonclave` by the installer's `install_cli` stage, and
bundled into the encrypted package by `installer/package.sh`). It does
**only**: `git pull` in `/var/www/Zonclave` (as the checkout's actual
owner, never root, per Section 26.4), sync `panel/` into `/opt/zonclave`
(preserving the existing `.env` by backup/restore around the `cp -a`, so a
stale checkout `.env` can never overwrite production config again),
`composer install`, `php artisan migrate --force`, clear and rebuild all
caches, fix ownership, restart `php8.3-fpm`/reload `nginx`. It deliberately
never touches PostgreSQL, FreeRADIUS config, or secrets - that boundary is
what makes it safe to run on every code change, unlike the full installer.

Usage: `sudo zonclave update`. The same `.env`-preserving fix was also
applied to the installer's own `deploy_panel()` `cp -a` step, so a full
installer re-run can't reintroduce the original corruption either.

### 26.9 Fixed: re-running the installer silently rotated credentials

Reported 2026-07-17: "every time when i update code it will remove my
credentials, data will be lost." Traced to a real bug, not user error -
before `zonclave update` existed (Section 26.8), the only way to deploy a
code change was to re-run the full installer, and every run of
`gather_input()` generated a **brand-new random** `DB_PASSWORD`,
`RADIUS_SECRET`, and `ADMIN_PASSWORD`, unconditionally:

- `install_db()` ran `ALTER ROLE ${DB_USER} WITH PASSWORD '${DB_PASSWORD}'`
  on every run, resetting the live Postgres role's password even when the
  role already existed - this is the deeper mechanism behind the
  FreeRADIUS/Postgres password-mismatch incident already described
  earlier in this section.
- `create_admin_user()` called `panel:create-admin`, whose old
  implementation (`User::firstOrNew(...)->save()`) unconditionally
  overwrote the existing admin's password hash with whatever fresh
  `ADMIN_PASSWORD` had just been generated - silently invalidating the
  admin's known login on every single re-run. The actual PPSK/tunnel
  registry data (`ppsk_groups`, `radcheck`, `radreply`, `admin_log`) was
  never at risk - those inserts are already `ON CONFLICT DO NOTHING`/
  `WHERE NOT EXISTS` guarded - but the admin's own login credential really
  was silently replaced every time, which is what actually happened here.

**Fixed:**

1. `CreateAdminCommand` (`app/Console/Commands/CreateAdminCommand.php`) no
   longer upserts. If the email already has an account, it leaves the
   password alone and reports "already exists; password left unchanged."
   A password can now only ever be set once, at genuine account creation -
   changing it afterward is the Profile page's job (Section 16.1), not a
   side effect of redeploying code. Covered by
   `tests/Feature/CreateAdminCommandTest.php`.
2. `gather_input()` now reuses the existing `DB_PASSWORD` (read back from
   `/opt/zonclave/.env`) and `RADIUS_SECRET` (read back from
   `clients.conf`'s `ppsk_unifi` client block) when they're already
   present, instead of generating fresh ones on every run. A first install
   still generates both fresh, exactly as before.
3. `summary()` no longer prints a fabricated admin password when the
   account already existed and its password was left untouched - it shows
   "(unchanged - use your existing password, or reset it from the panel's
   Profile page)" instead, so the summary file can never show a password
   that isn't actually the one on the account.

Net effect: re-running the full installer is now genuinely idempotent
with respect to credentials, not just "self-consistent if it completes."
Routine code updates should still use `sudo zonclave update`
(Section 26.8), which never touched credentials in the first place - this
fix is defense in depth for the case where the full installer gets
re-run anyway.

### 26.10 VLANs 300-303 live with new provider configs - gateway next-hop bug and other findings (2026-07-22)

The provider issued updated WireGuard peer configs for VLAN300-303 (four
distinct endpoints `46.151.227.182/.213/.237/.252:31003`, one shared
public key, new tunnel addresses `10.20.0.183/.214/.238/.253`). The
configs included AmneziaWG obfuscation parameters (`Jc`, `Jmin`, `Jmax`,
`S1`, `S2`, `H1`-`H4`) - **confirmed not required**: OPNsense's stock
WireGuard connects and passes traffic with them omitted. VLAN301 was
updated in place; VLAN302/303 were built fresh. During bring-up and the
first multi-credential test (SancoUk1-4, client-named RADIUS usernames per
Section 6), the following real issues were found and fixed:

1. **The big one - gateway Gateway IP set to the tunnel's own address
   broke all client traffic while looking completely healthy.** All four
   `GW_WG_VLAN30x` gateways had Gateway IP set to their own interface's
   tunnel address (e.g. `10.20.0.183` on `wg1` itself). pf's `route-to`
   then resolved the next-hop to the firewall itself and delivered client
   packets locally instead of into the tunnel. Symptoms: client HTTPS to
   *any* IP answered by OPNsense's own web GUI (login page / DNS-rebind
   page - indistinguishable from a rogue port-forward or captive portal,
   neither of which existed); DNS redirect states stuck `NO_TRAFFIC`;
   later plain timeouts with a pf state created but zero packets on the
   tunnel in `tcpdump -n -i wg1`. Crucially, `wg show` handshakes stayed
   live and gateway monitoring stayed Online throughout (dpinger uses a
   kernel host route, not pf `route-to`, so it bypasses the bug) - so
   every health indicator said "working" while no client packet ever
   entered the tunnel. Fix: Gateway IP must be a **non-local** in-tunnel
   address with Far Gateway checked - Kelder now uses `10.10.20.1/.2/.3/
   .4` for VLAN300-303 with unique monitors `8.8.8.8`, `8.8.4.4`,
   `9.9.9.9`, `149.112.112.112`. Documented as a critical step in the
   runbook (Section 3.3), including the `tcpdump -i wg1` proof test.
2. **VLAN301's `BLOCK_VLAN301_TO_RFC1918` was created as action Pass**,
   not Block - name said block, rule passed everything, so VLAN301
   clients could reach all RFC1918 space (the exact Section 10 gap).
   Fixed to Block. The rule editor defaults to Pass; the runbook
   (Section 3.4) now says to verify the verb in the compiled ruleset.
3. **VLAN302/303 gateways initially had the Monitor IP in the Gateway IP
   field** (`8.8.8.8`/`8.8.4.4` as next-hop, no monitor at all). Fixed as
   part of finding 1's table.
4. **No DHCPv4 scopes existed for VLAN301-303** (only VLAN300 had one).
   Added `10.30.1.10-200`, `10.30.2.10-200`, `10.30.3.10-200`, DNS and
   gateway left blank (defaults to interface address, which the
   DNS-through-tunnel redirect expects).
5. **The UniFi RADIUS profile (`uk_ppsk`, renamed SSID `UK-PPSK`) had a
   wrong shared secret** - FreeRADIUS dropped requests with "invalid
   Message-Authenticator! (Shared secret is incorrect)" in `freeradius
   -X`. Fixed by copying the exact secret from `clients.conf`'s
   `ppsk_unifi` block.
6. Diagnostic note for the future: `pfctl -a 'filter/VLAN300' -sr` (as
   previously documented) fails with `DIOCGETRULES: Invalid argument` on
   this OPNsense version - use `pfctl -a '*' -sr` instead. Also, a
   dual-homed Windows test client binds source with `curl.exe
   --interface <ip>`, but Windows may still route out the other NIC;
   verifying with `pfctl -ss` that the state actually appears on OPNsense
   is the reliable check.
7. **VLAN301's DHCP scope had "Static ARP" ticked**, setting the kernel
   `STATICARP` flag on `igb5_vlan301` - the router could receive from
   clients but never ARP back to them, so every downstream packet (TCP
   replies, DNS answers, ping) was silently discarded in the kernel's ARP
   layer with nothing in any log; `ping <client>` from the box failed
   with `sendto: Invalid argument`. Clients still got leases (broadcast
   needs no ARP), making the VLAN look connected while nothing worked.
   Diagnosed by comparing `ifconfig igb5_vlan301` (STATICARP present)
   against working `igb5_vlan300` (absent). Fix: uncheck Static ARP in
   the scope. Runbook Section 3.1 now warns about this.
8. **Monitor host routes for GW_WG_VLAN301-303 were never installed**, so
   dpinger's monitor pings followed the default route out the WAN
   (proven: monitor ICMP state showed `origif: igb0`, NAT'd to the WAN
   address) - gateway status showed Online while measuring nothing about
   the tunnels, blinding Section 12's fail-closed. This also invalidated
   firewall-sourced test traffic (`ping -S`/`nc -s` follow the kernel
   table, not pf route-to, and silently "succeed" via WAN). Fix: ensure
   Far Gateway is applied so the monitor route lands on the tunnel;
   verify with `netstat -rn | grep <monitor-ip>` and `pfctl -ss | grep
   icmp` (origif must be the wg interface). Runbook Section 3.3 covers
   the verification.

Confirmed working after all fixes - **all four VLANs verified from real
clients on the single `UK-PPSK` SSID (2026-07-22)**:

| PPSK | VLAN | Egress (residential) |
| --- | --- | --- |
| SancoUk1 | 300 | 46.151.227.182 |
| SancoUk2 | 301 | 46.151.227.213 |
| SancoUk3 | 302 | 46.151.227.237 |
| SancoUk4 | 303 | 46.151.227.252 |

Four credentials, four VLANs, four distinct residential IPs, DNS
resolving through each tunnel. Section 21.1 tests 1-4 pass for all four.
Still open: the gateway monitor-route fix above (finding 8 - until done,
Online status on GW_WG_VLAN301-303 is not measuring the tunnels and
Section 12 fail-closed is blind for them), VLAN304 (no updated peer
config was issued for it), and Section 21.1 tests 5-10.

### 26.11 France tunnel expansion - VLANs 305-308 (client request, 2026-07-23)

Sancover supplied four additional WireGuard peer configs for France exit
IPs and requested four new PPSKs. This extends Kelder beyond the original
5-tunnels-per-router Phase 1 scope as an explicit client request (the
Section 4 rule against provisioning extra tunnels was about not acting on
*stated future intent*; this is a direct instruction with configs in
hand). Per Section 5's open-ended block, the new groups continue from 305:

| PPSK (manual username) | VLAN | Subnet | Tunnel | Gateway |
| --- | --- | --- | --- | --- |
| SancoFR1 | 305 | 10.30.5.0/24 | WG_VLAN305 | GW_WG_VLAN305 |
| SancoFR2 | 306 | 10.30.6.0/24 | WG_VLAN306 | GW_WG_VLAN306 |
| SancoFR3 | 307 | 10.30.7.0/24 | WG_VLAN307 | GW_WG_VLAN307 |
| SancoFR4 | 308 | 10.30.8.0/24 | WG_VLAN308 | GW_WG_VLAN308 |

VLAN304 stays reserved for the UK set's fifth tunnel, keeping the
original block intact.

**Shared password (client-directed):** all four France PPSKs use the same
manually-entered password, at Sancover's explicit request. Technically
valid under WPA2-Enterprise (the username selects the VLAN, not the
password) and permitted by Section 14's manual-entry decision. The
tradeoff was flagged to the client: with a shared password, only the
typed username separates the France groups - anyone holding the password
can join any of the four France VLANs by choosing a different username.
Accepted by the client.

**Panel change needed: none in code.** `App\Domain\VlanPlan` derives
subnet/tunnel/gateway from the VLAN ID; the dropdown range comes from
config. On the VM, set `ZONCLAVE_VLAN_MAX=308` in `/opt/zonclave/.env`
and run `php artisan config:cache` - VLANs 305-308 then appear in the
Create form with correct derived values. The repo default in
`panel/config/zonclave.php` stays 304 (per-site scope belongs in the
site's `.env`, not the shipped default).

Network side: repeat runbook Section 3 per new VLAN (now including the
2026-07-22 finding warnings), plus UniFi trunk/AP tagging for 305-308 and
the DNS redirect rules. Monitor IPs must be unique - continue with e.g.
`9.9.9.9`, `149.112.112.112`, `208.67.222.222`, `208.67.220.220`.
