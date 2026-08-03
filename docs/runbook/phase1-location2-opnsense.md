# Phase 1 Handoff: Location 2 (Office SancoMedia main) OPNsense - VLAN401-405

Status: internal handoff document, not linked from any public page (same
convention as `docs/runbook/phase1-opnsense-unifi.md`). Written for a new
person taking over the OPNsense side of Location 2 specifically - finishing
and stabilizing VLAN401-405, which are already built but not yet fully
verified stable.

**Read `docs/runbook/phase1-opnsense-unifi.md` first.** That document is the
canonical, detailed, step-by-step GUI walkthrough for building a VLAN +
WireGuard tunnel + gateway + firewall rule set from scratch on this
project's OPNsense boxes - the GUI steps, field names, and reasoning in its
Section 3 apply here unchanged. This document does not repeat those steps.
It exists only to give you Location 2's actual values (which differ from
Kelder's), the current state of what's already built, and the specific real
bugs already found at this site so you don't lose time rediscovering them.

If anything here contradicts CLAUDE.md, CLAUDE.md wins - update this doc,
not the other way around (same rule as the Kelder runbook).

## 0. What you are responsible for

Finishing and stabilizing VLAN401-405 at Location 2 (`SancoverPC-5`'s site,
Office SancoMedia main). Concretely:

- Confirm all 5 tunnels are correctly configured and stable (not just
  "enabled" - see Section 3 below for what "stable" actually means at this
  site).
- Close the two known open bugs listed in Section 4.
- Run the Section 21.1 acceptance tests (mapped in Section 6) against this
  site once the above is done.

**Not your responsibility:** the Windows/Hyper-V host (`SancoverPC-5`) that
the Zonclave VM runs on. That machine had its own separate networking
issue today (unrelated Windows/Hyper-V virtual switch problem, already
fixed - CLAUDE.md Section 27.5) - if the server PC's Ethernet ever looks
broken again, rule out the OPNsense-side items in this document first
(Section 3/4), and if those are all clean, that's a Windows-side issue for
ZILL, not something to chase in OPNsense.

**Secrets:** the WireGuard peer configs (private keys, provider endpoint
credentials) and the FreeRADIUS shared secret are not in this document or
in git. Get them from ZILL/the client directly. Never commit a real peer
config, private key, or shared secret to the repository (CLAUDE.md Section
23.3).

## 1. Location 2's fixed values (differ from Kelder - do not copy Kelder's)

| Item | Kelder (Location 1) | Location 2 (this site) |
| --- | --- | --- |
| VLAN block | 300-304 | **401-405** |
| Subnet formula | `10.30.(vlan-300).0/24` | Same formula, applied to this block: `10.30.(vlan-300).0/24` -> VLAN401 = `10.30.101.0/24`, VLAN402 = `10.30.102.0/24`, VLAN403 = `10.30.103.0/24`, VLAN404 = `10.30.104.0/24`, VLAN405 = `10.30.105.0/24` |
| LAN trunk NIC | `igb5` | `igb1` (the plain LAN interface itself - VLAN sub-interfaces are tagged directly on `igb1`, e.g. `igb1_vlan401`) |
| WireGuard kernel interfaces | `wg1`-`wg4` | `wg1`-`wg5` (instance number matches VLAN order: `WG_VLAN401`=`wg1` … `WG_VLAN405`=`wg5`) |
| Zonclave panel/VM IP | 192.168.1.175 | 192.168.1.4 |
| Host static IP | 192.168.1.174 | 192.168.1.5 |
| Gateway IP scheme | `10.10.20.1`-`.4` | `10.10.20.1`-`.5` (one per tunnel, same non-local-in-tunnel-address pattern, Far Gateway checked - see the Kelder runbook Section 3.3 for why this field matters) |
| Monitor IPs | `8.8.8.8`, `8.8.4.4`, `9.9.9.9`, `149.112.112.112` | `8.8.8.8` (VLAN401), `8.8.4.4` (VLAN402), `9.9.9.9` (VLAN403), `149.112.112.112` (VLAN404), `208.67.222.220` (VLAN405) |

Full current mapping, confirmed live 2026-08-03:

| VLAN | Subnet | OPNsense if. | WireGuard local | Gateway | Gateway IP | Monitor IP |
| --- | --- | --- | --- | --- | --- | --- |
| 401 | 10.30.101.0/24 | igb1_vlan401 (opt) | WG_VLAN401 (wg1) | GW_WG_VLAN401 | 10.10.20.1 | 8.8.8.8 |
| 402 | 10.30.102.0/24 | igb1_vlan402 (opt7) | WG_VLAN402 (wg2) | GW_WG_VLAN402 | 10.10.20.2 | 8.8.4.4 |
| 403 | 10.30.103.0/24 | igb1_vlan403 (opt8) | WG_VLAN403 (wg3) | GW_WG_VLAN403 | 10.10.20.3 | 9.9.9.9 |
| 404 | 10.30.104.0/24 | igb1_vlan404 (opt9) | WG_VLAN404 (wg4) | GW_WG_VLAN404 | 10.10.20.4 | 149.112.112.112 |
| 405 | 10.30.105.0/24 | igb1_vlan405 (opt10) | WG_VLAN405 (wg5) | GW_WG_VLAN405 | 10.10.20.5 | 208.67.222.220 |

**Provider peer note:** this provider issues one shared public key across a
batch of endpoint IPs, same pattern already confirmed legitimate at Kelder
(CLAUDE.md Section 26.10) - `WG_VLAN401_PEER`/`402_PEER`/`403_PEER` share
one public key (`6/afsLkpg17...`), `404_PEER`/`405_PEER` share a different
one (`j+bZXnblTk...`). **This is expected, not a misconfiguration.** Each
peer object still has its own distinct `endpoint` host:port - that's the
field that actually matters for correct routing, not the shared key. One
side effect: OPNsense's **VPN > WireGuard > Diagnostics** page looks up a
peer's display name by public key, so when a key is shared across multiple
peer objects, this page will show a misleading name (e.g. `wg1`'s session
displaying as `WG_VLAN403_PEER` even though it's correctly using
`WG_VLAN401_PEER`'s own endpoint). **Do not trust that page's Peer Name
column when keys are shared - verify actual wiring with the script in
Section 5 instead.**

## 2. Current status (as of 2026-08-03)

- VLAN401-404: interfaces, tunnels, gateways up, DHCP enabled, firewall
  rules in place. Handshakes confirmed live and passing real traffic.
- VLAN405: just brought up today, interface/tunnel/gateway/DHCP enabled,
  handshake confirmed live. Has not yet been run through a full PPSK
  device test or the Section 21.1 acceptance pass - treat as "up but
  unverified," not "done."
- Two real bugs found and fixed today at the whole-router level
  (Section 4) - both are now fixed, but re-verify they haven't regressed
  before doing anything else, since one of them (`disableroutes`) had
  already silently regressed once today.
- One known, not-yet-fixed gap (Section 4.3) - outbound NAT missing for
  some VLAN/tunnel interfaces. This is real and needs fixing before any
  PPSK is actually assigned to the affected VLANs, but it hasn't blocked
  today's testing because no real device traffic has gone through those
  specific VLANs yet.

## 3. Verify before touching anything else

Run these first, every time you sit down to work on this site - they take
under a minute and rule out the two bugs in Section 4 regressing silently
again (which already happened once today).

```sh
sh
# disableroutes must show 1 for ALL FIVE - if any show 0, see Section 4.1
grep -A15 "<name>WG_VLAN40[1-5]</name>" /conf/config.xml | grep -E "name|disableroutes"

# routing table must show ONLY the plain default via igb0 - no 0.0.0.0/1
# or 128.0.0.0/1 entries. If either appears, see Section 4.1.
netstat -rn -f inet | grep -E "^0.0.0.0/1|^128.0.0.0/1|^default"

# all five tunnels should show a recent handshake timestamp
wg show all latest-handshakes
```

## 4. Known bugs at this site (read before debugging anything from scratch)

### 4.1 `disableroutes` must be `1` on every tunnel, not just the ones you built most recently

**Symptom:** enabling or reconfiguring a WireGuard tunnel breaks internet
for the entire LAN, not just that VLAN - the OPNsense GUI itself, the
UniFi controller, and every other device on 192.168.1.0/24 lose internet
at the same time.

**Cause:** if a tunnel's Local instance has **Disable Routes unchecked**
(`disableroutes = 0` in config.xml), OPNsense tries to install it as a
system-wide default route (the split `0.0.0.0/1` + `128.0.0.0/1`
technique) every time that tunnel starts. With multiple tunnels doing this
at once, general LAN routing breaks. Full incident writeup: CLAUDE.md
Sections 27.4 and 27.5.

**This bug was found and fixed on all five tunnels today, but VLAN401 and
VLAN402 were initially believed fine and turned out not to be** - don't
assume any tunnel is safe just because it's older or was built first.
Re-run the Section 3 check above after touching any WireGuard setting.

**Fix if you find one at `0`:** VPN > WireGuard > Local > edit that
tunnel > check **Disable Routes** > Save > Apply. If the LAN is already
broken when you find this, also clear any already-installed hijack route
immediately:

```sh
route delete -net 0.0.0.0/1
route delete -net 128.0.0.0/1
```

### 4.2 `8.8.8.8` (and the other monitor IPs) are not valid general connectivity test targets on this network

**Symptom:** you fix a real problem, test with `ping 8.8.8.8` (or
`8.8.4.4`, `9.9.9.9`, `149.112.112.112`, `208.67.222.220`), and it still
fails - looks like the fix didn't work.

**Cause:** those five addresses are this site's configured WireGuard
gateway Monitor IPs (Section 1's table above). Each one has its own host
route pinned to its tunnel, completely independent of the general default
route. Testing with one of them will always route through that specific
tunnel, by design - it proves nothing about whether general internet
routing is healthy.

**Fix:** use a different address for connectivity testing -
**`1.1.1.1`** is confirmed not one of this site's monitor IPs and safe to
use. If you ever add a sixth VLAN/gateway, give it its own unique monitor
IP too and update this table.

### 4.3 Outbound NAT gap - not yet fixed, needs doing before any PPSK goes live on the affected VLANs

**Symptom (once a real device is assigned a PPSK on an affected VLAN):**
the gateway shows Online, `wg show` shows a live handshake, the firewall
allow rule is correct - and the client still gets a plain connection
timeout with nothing in any log. This is the exact same failure mode
Kelder hit and documented in its own runbook, Section 3.4a.

**Status as of 2026-08-03:** `pfctl -s nat` was checked and the automatic
outbound NAT rule list was missing entries for some of this site's VLAN
interfaces and tunnels (the list jumped from covering VLAN402 straight to
VLAN404, and only showed `wg1`/`wg2` where it should show all five). This
has not been fully re-verified or fixed since VLAN405 was brought up
today - **check this before doing anything else:**

```sh
pfctl -s nat | grep -E "igb1_vlan40|wg1|wg2|wg3|wg4|wg5"
```

You should see one outbound NAT line per VLAN interface (`igb1_vlan401`
through `igb1_vlan405`) and, if this box's Outbound NAT mode is Manual or
Hybrid, one per WireGuard interface (`wg1` through `wg5`) too. If any are
missing, follow Kelder's runbook Section 3.4a exactly (same fix, same
verification method with `curl.exe --interface`) for each missing one on
this site's VLAN/interface names instead of Kelder's.

### 4.4 Enabling any WireGuard setting restarts all five tunnels together for about a second - this is normal

**Symptom:** you enable or edit one VLAN's tunnel, and the WireGuard log
(VPN > WireGuard > Log File) shows all five tunnels stopping and starting
within the same second, and `dmesg` shows all five link states flapping
down and up together.

**Cause:** this OPNsense plugin's reconfigure action restarts the entire
WireGuard service, not just the tunnel you touched - it doesn't support a
per-interface hot reload. This is expected plugin behavior, not a bug.

**What to do:** nothing, if it self-recovers within a few seconds (check
`wg show all latest-handshakes` - all five should show a fresh timestamp
again shortly after). If something is still down more than 30 seconds
after a change, that's a real problem - go back to Section 3's checks,
not this one.

## 5. Verifying WireGuard peer wiring is actually correct (don't rely on the Diagnostics page's name column - see Section 1's note)

Save this as a `.php` file via SFTP/WinSCP (do not paste large scripts
directly into an SSH terminal over a multi-hop remote connection - line
order can get scrambled) and run `php <filename>.php` on the router:

```php
<?php
$xml = simplexml_load_file('/conf/config.xml');
if ($xml === false) { echo "FAILED to parse config.xml\n"; exit(1); }

echo "=== LOCAL INSTANCES (servers) ===\n";
foreach ($xml->OPNsense->wireguard->server->servers->server as $s) {
    echo (string)$s->name . " (instance " . (string)$s->instance . ") -> peers: " . (string)$s->peers . "\n";
}

echo "\n=== PEERS (endpoints/clients) ===\n";
foreach ($xml->OPNsense->wireguard->client->clients->client as $c) {
    echo (string)$c->name . " | uuid=" . (string)$c['uuid'] . " | pubkey=" . (string)$c->pubkey . " | endpoint=" . (string)$c->serveraddress . ":" . (string)$c->serverport . "\n";
}
```

Cross-check: `WG_VLAN401`'s `peers` UUID must equal `WG_VLAN401_PEER`'s own
UUID, and so on for each of the five - not any other VLAN's peer, even if
they share a public key (Section 1's note above).

## 6. Acceptance testing

Once Section 3's checks are clean and Section 4.3 is fixed, run the same
Section 21.1 tests the Kelder runbook maps in its own Section 6, against
VLAN401-405 and real PPSK credentials created through the panel at
`http://192.168.1.4/admin`. Do not sign off VLAN405 (or any VLAN here) as
done until it has been through a real device test, not just a tunnel
handshake check - a live handshake proves the tunnel is up, not that a
client device can actually pass traffic through it end to end.
