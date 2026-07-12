# PPSK / VLAN / WireGuard Multi-Tunnel System - Project Reference

**Status:** Planning / Phase 1 Scoping
**Client:** Sancover
**Developer & Network Engineer:** ZILL E ALI (Developer Zon)
**Last updated:** 2026-07-12

This file is the single source of truth for the project. Anyone picking up implementation work, human or AI-assisted, should read it in full before writing any config or code. Section numbers are stable. Do not renumber sections 1 to 22, since the kickoff prompt references them directly. Add new material as new trailing sections.

---

## 1. Project Goal (one paragraph)

Build a system where a single Wi-Fi SSID accepts many unique pre-shared keys (PPSKs). Each PPSK maps to a dedicated VLAN. Each VLAN is policy-routed on OPNsense through its own dedicated WireGuard tunnel to a residential VPN provider, so each group of devices egresses to the internet through its own distinct public ISP IP address. The system must scale to 100+ PPSK/VLAN/tunnel groups and must be manageable through a web panel rather than direct config or database editing.

**Core architectural principle (applies to every phase):** FreeRADIUS is responsible only for authentication and VLAN assignment. OPNsense is responsible only for routing, firewalling, and VPN policy. Never blur this line. Do not put routing logic in RADIUS attributes beyond the VLAN handoff, and do not put credential logic in OPNsense. This separation is what keeps the system debuggable at 100+ tunnels.

This project has two centers of gravity that carry equal weight. The **network and operations layer** (VLANs, subnets, DNS, kill-switch, firewall isolation, WireGuard health) is what makes the deployment correct and safe. The **software layer** (panel architecture, the authoritative registry, coding standards, testing) is what keeps it maintainable and scalable. Treat both as first-class. A change that is clean in code but leaks traffic on the wire is a failure, and a change that routes correctly but bypasses the registry is also a failure.

## 2. Architecture

```
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

```
Admin browser ---> Web Panel (auth-gated) ---> ppsk_groups (source of truth)
                                            |-> FreeRADIUS DB (radcheck/radreply, generated)
                                            |-> (Phase 2) OPNsense API for tunnel/VLAN provisioning
```

## 3. Hardware / Software Inventory

| Component | Item |
|---|---|
| Wi-Fi Controller | UniFi Cloud Key Gen2 Plus |
| Switch | UniFi USW-48-PoE |
| Access Points | 3 x UniFi U6+ |
| Router / Firewall | OPNsense (already installed and accessible) |
| VPN | Residential WireGuard peer configs, already provided by VPN provider |
| Auth server | FreeRADIUS (to be deployed) |
| Database | MariaDB or PostgreSQL (to be deployed) |
| Web panel | To be built (see Section 16) |

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
- Expand from 5 to 10 up to 100+ PPSK/VLAN/tunnel groups (repeat the Phase 1 pattern; scripting/automation of OPNsense config strongly recommended at this point)
- Add device activity logs to the panel (who authenticated, when, which PPSK, session duration/data use)
- Extend the installer to drive OPNsense provisioning via API (Section 19 and Section 24)
- Any additional features requested (see Section 20, Open Items)

## 5. Reserved Network Ranges (decide once, never revisit)

The point of reserving this now is that Phase 2 never requires renumbering anything. It only ever fills in the next slot in a range sized for the full 100+ from day one.

| Item | Reserved range | Notes |
|---|---|---|
| VLAN ID block | **101 to 200** | 100 slots reserved up front, even though Phase 1 only uses 5 to 10 of them |
| Subnet block (Option A) | **10.101.0.0/24 to 10.200.0.0/24** (one /24 per VLAN, VLAN ID = third octet) | Clean, dedicated block. Do not carve small pieces out of 10.0.0.0/8 ad hoc elsewhere in the network |
| Subnet block (Option B) | **172.20.101.0/24 to 172.20.200.0/24** | Use this pattern instead if the 10.101 to 200.x block collides with anything already deployed |

Both subnet options are documented on purpose so the choice is a single recorded decision, not a rediscovery later. Pick one scheme and use it exclusively. Do not mix.

**802.1Q ceiling note:** the standard VLAN ID space runs 1 to 4094. The 101 to 200 block is a deliberate 100-slot reservation for this project, well inside that ceiling, leaving headroom for a second block later if a single site ever needs more than 100 groups.

**Decision needed before build:** confirm neither block collides with existing management or guest VLANs or subnets already in use on the USW-48-PoE or OPNsense, then record the chosen scheme here.

## 6. Naming Conventions

Descriptive, self-documenting names are non-negotiable at this scale. Six months from now, a bare `wg1` tells you nothing, but `WG_VLAN101` tells you exactly what it is without checking a spreadsheet.

| Item | Convention | Example |
|---|---|---|
| VLAN ID | 101 to 200 per Section 5 | VLAN 101 |
| WireGuard interface name | `WG_VLAN<id>` | `WG_VLAN101` |
| WireGuard gateway name (OPNsense) | `GW_WG_VLAN<id>` | `GW_WG_VLAN101` |
| PPSK / group label | `VLAN<id>_<GroupName>` | `VLAN101_GUESTA`, `VLAN102_GROUPB` |
| RADIUS username | `ppsk_group###` (zero-padded, matches `ppsk_groups.id`) | `ppsk_group001` |
| Firewall rule (allow) | `ALLOW_VLAN<id>_TO_<gateway>` | `ALLOW_VLAN101_TO_GW_WG_VLAN101` |
| Firewall rule (block) | `BLOCK_VLAN<id>_TO_RFC1918` | `BLOCK_VLAN101_TO_RFC1918` |
| Firewall alias (per VLAN subnet) | `NET_VLAN<id>` | `NET_VLAN101` |

Apply this consistently across OPNsense interface descriptions, gateway names, firewall rules and aliases, and the `ppsk_groups` table. The names should match across all of them so a search for `VLAN101` in any system surfaces everything related to it.

## 7. Configuration Registry - Authoritative Inventory

This is the most important structural decision in the whole system. **`ppsk_groups` is not UI metadata. It is the single authoritative inventory table that everything else derives from.** FreeRADIUS entries, OPNsense firewall and interface config, and any future provisioning scripts are all generated *from* this table, never maintained independently of it. This is what prevents drift once you are past 50 to 100 tunnels and can no longer eyeball whether everything still matches.

```sql
CREATE TABLE ppsk_groups (
  id SERIAL PRIMARY KEY,
  label VARCHAR(128) NOT NULL,             -- e.g. "VLAN101_GUESTA"
  radius_username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,     -- see Section 14 for at-rest handling
  vlan_id INT NOT NULL,
  subnet VARCHAR(32) NOT NULL,             -- e.g. "10.101.0.0/24"
  wireguard_interface VARCHAR(32) NOT NULL,-- e.g. "WG_VLAN101"
  wireguard_gateway VARCHAR(32) NOT NULL,  -- e.g. "GW_WG_VLAN101"
  opnsense_interface VARCHAR(64),          -- e.g. "igb0_vlan101"
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

1. **VLAN interface** on the LAN-facing trunk (e.g. `igb0_vlan101`)
2. **WireGuard instance** (`WG_VLAN101`) using the corresponding residential peer config already provided by the VPN provider
3. **Gateway** (`GW_WG_VLAN101`) created for that WireGuard interface
4. **Firewall rule**: source = that VLAN subnet (`NET_VLAN101`), gateway = `GW_WG_VLAN101` (explicit, not default-route reliance; this prevents cross-group leakage)
5. **Failure behavior:** fail-closed by default. See Section 12 for the exact definition.

**Scale note for Phase 2:** 100+ simultaneous WireGuard interfaces on one OPNsense box is a real CPU and interrupt load question. Load-test before committing to the full 100+ figure on existing hardware, and consider whether config should be automated via the OPNsense API or `config.xml` rather than built by hand at that scale (see Section 19 and Section 24).

## 10. Firewall Isolation Policy

Every VLAN gets **two** rule groups, not just the allow rule in Section 9. Without the block rule, a device on one PPSK VLAN could reach another VLAN's devices or the internal network if any route happens to exist. This must be explicitly closed, not assumed away.

**Allow group** (per VLAN):
```
VLAN101 -> Internet -> Gateway GW_WG_VLAN101
```

**Block group** (per VLAN):
```
VLAN101 -> RFC1918 (all private address space)   [BLOCKED]
  Exceptions (explicitly allowed, scoped to specific server IPs):
  - DNS      (to designated resolver, see Section 11)
  - RADIUS   (to FreeRADIUS server)
  - DHCP     (to OPNsense DHCP service for that VLAN)
  - Management services as required (e.g. captive portal, if used)
```

Implementation notes:
- Block rules reference the `NET_VLAN<id>` alias (Section 6) as source and an `RFC1918` alias as destination, placed **above** any allow-all rule in OPNsense rule ordering (OPNsense evaluates top-down, first match wins).
- The DNS, RADIUS, and DHCP exceptions are scoped to the *specific* server IPs, not "any," to avoid accidentally re-opening lateral movement.
- Test this explicitly per Section 21. Do not assume it works because it was configured. Verify a device on VLAN101 genuinely cannot reach VLAN102 or the OPNsense management interface.

## 11. DNS Design

Decide this now. Retrofitting it after tunnels are live risks a DNS-leak period where queries go out unencrypted or through the wrong path.

Two options:
1. **OPNsense Unbound (local resolver):** VLANs resolve via OPNsense itself, which then forwards upstream. Simpler to manage centrally, but the DNS query still exits via whatever OPNsense upstream path is unless explicitly routed through each WireGuard tunnel too.
2. **DNS through the WireGuard tunnel:** each VLAN's DNS queries are forced through its own WireGuard tunnel to the VPN provider DNS (or a resolver reachable only via that tunnel). This is what most residential VPN providers recommend, specifically to prevent DNS leaks that would reveal the real ISP or location despite the tunneled traffic.

**Recommendation:** default to option 2 (DNS via tunnel) to match VPN provider guidance, unless there is a specific reason to centralize on Unbound. Whichever is chosen, apply it uniformly across all VLANs and confirm with an actual DNS leak test per group (Section 21). Do not assume the firewall block rules in Section 10 alone prevent leakage, since DNS leaks often happen through the WAN default resolver, not RFC1918 space.

**Decision needed before build:** confirm which option, and record it here once chosen.

## 12. Kill-Switch / Fail-Closed Design

Section 4 says "fail closed." This section defines exactly what that means so it is unambiguous at implementation time.

```
WireGuard tunnel goes down (handshake expires / interface drops)
        v
Associated gateway (GW_WG_VLAN101) becomes unreachable
        v
Firewall rule set has NO fallback/default rule permitting that VLAN
traffic out any other interface (specifically: no implicit or explicit
rule allowing VLAN101 -> WAN directly)
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

Every PPSK is randomly generated by the system, never manually chosen by the admin, to guarantee a consistent security floor across 100+ credentials.

- **Length:** 24 characters
- **Character set:** A-Z, a-z, 0-9
- **Excluded:** ambiguous characters (e.g. `0`/`O`, `1`/`l`/`I`) to reduce transcription errors if a password is ever read aloud or hand-copied
- Generated server-side at creation time, shown once in the panel, and the "Password" field in Section 16.3 is generate-only (no manual override; arbitrary admin-chosen passwords undermine a consistent standard)

**Validation boundary:** the generated value passes through a dedicated PSK value type before it is accepted anywhere in the system. That type enforces the WPA2 personal PSK constraint of 8 to 63 characters, and rejects anything outside it. The 24-character generator output always sits inside this valid range. This keeps a single, testable place that owns "what is a legal PSK," so no code path can ever persist an out-of-spec key. See Section 23.

**At-rest handling:** because FreeRADIUS must present the cleartext PSK to the AP for key derivation, the value cannot be one-way hashed. Store it encrypted at rest and decrypt only at the point the registry-to-RADIUS path writes `radcheck`. The encryption key lives outside the database (env or secrets store), never in git.

## 15. Secure Storage of WireGuard Configs

Do not leave `.conf` files scattered across the filesystem where anyone with shell access can read every provider private key at once. Two acceptable patterns:

1. **Filesystem registry with restricted permissions:**
   ```
   wg_configs/
     WG_VLAN101.conf
     WG_VLAN102.conf
     ...
   ```
   Root-owned, `600` permissions, on the OPNsense host only. Never copied to the FreeRADIUS box, the web panel host, or any backup location without equivalent protection.

2. **Encrypted metadata in the database, keys protected on OPNsense:** store non-sensitive metadata (interface name, gateway, associated VLAN) in `ppsk_groups` or a `wireguard_tunnels` table, while private keys remain only on OPNsense itself, never in the application database at all, encrypted or not. A private key does not need to leave the system that uses it.

**Recommendation:** pattern 2. The panel and database should only ever need to *reference* a tunnel by name (`WG_VLAN101`). They never need to read or display the actual private key. This also limits blast radius if the web panel or its database is ever compromised.

## 16. Web Panel Specification (Phase 1)

**Purpose:** let the admin manage PPSKs without touching the database or FreeRADIUS config directly.

### Tech stack (budget-first, one recorded decision)

Three candidate stacks are documented so the choice is explicit. All three can meet Phase 1 function. They differ in build effort, installer footprint, and long-term maintainability.

| Option | Strength | Trade-off | Installer footprint |
|---|---|---|---|
| **Laravel + Filament** (recommended) | Fastest to build for a Laravel developer; admin CRUD UI, auth, and validation come for free; cleanest long-term maintenance | Heavier install (composer + node asset build) | Larger, but fine on a pinned OS in the Section 24 installer |
| **Lightweight PHP** (plain PHP + PDO) | Smallest runtime footprint; leanest installer | More manual work (hand-built UI, auth, validation) | Smallest |
| **Python / Flask** | Option if Python is preferred over PHP | Most glue work for an admin UI; a second language in a PHP/RADIUS stack | Medium |

**Recommendation:** Laravel + Filament. For this developer it is the lowest build effort (which is the real budget cost here) and the most maintainable, and Filament generates the create/list/edit/disable/delete flows in Section 16.2 to 16.4 with minimal custom code. The one honest trade-off is a heavier installer, which the Section 24 script absorbs on a pinned OS. Choose plain PHP only if a minimal installer footprint outweighs build speed. Record the final choice here before Section 16 work begins.

Whichever stack is chosen, it reads and writes `ppsk_groups` as the source of truth and derives `radcheck`/`radreply` through the single path in Section 23. The stack choice does not change the data model or the RADIUS boundary.

**Access:** local network only for Phase 1 unless internet-accessible access is explicitly required (open item, see Section 20).

### Page-by-page spec

#### 16.1 Login Page
- Single admin username/password (or a small user table if multiple admins are needed, TBD per Section 20).
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
- Password: **auto-generated only**, per Section 14 (no manual entry field; shown once on creation with a "copy" action)
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

### Phase 2 additions to the panel (not built in Phase 1, listed for planning only)
- Device activity log page: which device/MAC authenticated with which PPSK, timestamp, session duration, data used (requires RADIUS accounting, `radacct` table).
- Per-tunnel health status (Section 13) surfaced as a dashboard column.
- Bulk PPSK import/export (CSV).
- Per-VLAN bandwidth/usage view.
- Multi-admin roles.

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

```
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

```
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

- [ ] Exact VLAN ID range and subnet scheme: 10.101 to 200.x vs 172.20.101 to 200.x (Section 5)
- [ ] Panel hosting: local-network-only vs internet-accessible with login (Section 16)
- [ ] Panel stack final choice: Laravel + Filament (recommended) vs lightweight PHP vs Python/Flask (Section 16)
- [ ] DNS design: OPNsense Unbound vs DNS-through-tunnel per VLAN (Section 11)
- [ ] Storage pattern for WireGuard configs: filesystem registry vs DB metadata + OPNsense-only keys (Section 15)
- [ ] Single admin login vs multiple admin accounts for the panel (Section 16.1)
- [ ] Confirm the installed UniFi Network application version supports RADIUS-based Private PSK on the SSID (verify against current UniFi docs at build time)
- [ ] Where FreeRADIUS will be hosted (spare on-site hardware, VM, small dedicated box)
- [ ] Phase 1 tunnel count: confirmed at 5 to 10, or a different number preferred for initial validation
- [ ] Installer target OS and who runs it (Section 24)

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
8. From a device on VLAN101, attempt to reach a device on VLAN102 and the OPNsense management interface, and confirm both are blocked per Section 10.
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
- Target and pin one OS (recommended: Ubuntu 22.04 LTS or Debian 12). State the requirement plainly; do not attempt to support every distro in Phase 1, or one-click reliability breaks.
- `set -euo pipefail`, root and OS checks up front, idempotent functions, clear stage logging.
- Modular internal structure even though it ships as one blob: `preflight`, `install_db`, `install_freeradius`, `deploy_panel`, `configure_services`, `self_check`, `summary`.
- Minimal input: prompt only for the essentials (UniFi/AP subnet, admin email) or read a small answers file; generate sane defaults for the rest.
- A `self_check` stage at the end verifies the FreeRADIUS config test passes, services are active, and the panel responds, before printing success.

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
