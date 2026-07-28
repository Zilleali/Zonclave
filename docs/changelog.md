# Changelog

What changed, and when. Newest first. The topmost entry is the current version - there is no separate version file to keep in sync with this one.

Version numbers are `0.x` on purpose: Phase 1 (CLAUDE.md's own definition of done) hasn't fully signed off yet - see the still-open items in CLAUDE.md Section 20 and the acceptance tests in Section 21.1. `1.0.0` is reserved for when that full pass is complete.

## 0.6.1 - 2026-07-28

- A backup is now also taken automatically whenever a PPSK or VLAN is created or deleted (not just on the daily schedule), so a meaningful change is never more than a few minutes from a fresh safety copy.
- Restoring a backup is documented (`docs/commands-reference.md`) as a deliberate command-line process, not a panel button - it replaces the entire database, which is too destructive for a one-click self-service feature.

## 0.6.0 - 2026-07-28

- Added **full database backups**: a "Backup now" button in the panel, plus an automatic backup every day, both kept for two weeks by default. Download or delete any backup directly from the Backups page.
- The Dashboard's Network Topology diagram now shows a live "connected now" count per VLAN, alongside the existing active/disabled PPSK counts.

## 0.5.1 - 2026-07-28

- The Sessions page now shows **why** a device disconnected (the RADIUS termination reason reported by the access point - e.g. a normal disconnect vs. an idle timeout vs. losing the connection), not just when.

## 0.5.0 - 2026-07-28

- Added a **VLANs** page: add a new VLAN slot or delete one you're no longer using, directly from the panel - no more editing `.env` and running a command on the server. Deleting a VLAN that still has a PPSK assigned to it (even a disabled one) is blocked, with an error naming which PPSK is in the way.
- Provisioned VLANs no longer have to be a contiguous block - an unused one (like the orphaned VLAN304) can simply be deleted, leaving a gap, instead of sitting there forever with no way to remove it.

## 0.4.0 - 2026-07-27

- Added a **Sessions** page: which PPSK is currently connected, the device's MAC address and VLAN-assigned IP, connect/disconnect time, duration, and data used - sourced from FreeRADIUS's own accounting table. A session with no update in 15 minutes and no recorded disconnect shows as **Stale** rather than staying "Connected" forever.
- Added a **Tunnel Egress IPs** page - a manually-confirmed reference of each VLAN's current residential exit IP, shown alongside sessions. This is a hand-updated value, not a live measurement (there's no automated way to ask a tunnel its public IP yet).
- RADIUS accounting, previously planned for a later phase, was brought forward at the client's request now that all tunnels are stable.

## 0.3.0 - 2026-07-25

- The Sancover deployment's admin panel was white-labeled to **Peng Balous**, with a footer crediting its developer.
- PPSK labels are now completely free text - the earlier `VLAN<id>_<GroupName>` naming convention is a suggestion (via autocomplete), not a requirement.
- The RADIUS username can now be changed after a PPSK is created, directly from the Edit screen.
- "Regenerate password" moved from its own button into the Edit screen, behind an off-by-default toggle - opening Edit can no longer accidentally issue a new credential.
- Disabling a PPSK now asks for confirmation first, since it immediately blocks that credential from reconnecting.

## 0.2.0 - 2026-07-22

- All 8 tunnels at the Office SancoMedia Kelder site (4 UK exit points, 4 France exit points) verified live, each showing its own distinct residential IP from a real connected device.
- Fixed several real network-configuration issues found only by testing with actual devices rather than synthetic checks: a gateway next-hop misconfiguration that silently blackholed traffic while every health indicator still showed green, a firewall isolation rule that had the allow/block direction backwards, and a DHCP setting that silently broke replies to connected clients.

## 0.1.1 - 2026-07-17

- First real device (not a synthetic test) successfully connected with a PPSK, landed on the correct VLAN, and egressed through that VLAN's own residential IP - the actual end-to-end goal of this whole project, confirmed for the first time.
- Fixed an installer bug where re-running it could silently reset the database password and the admin login on every run.
- Added `zonclave update` as the safe way to deploy a code change without touching the database, FreeRADIUS configuration, or any secret.

## 0.1.0 - 2026-07-16

- Initial working deployment: the panel, FreeRADIUS, and PostgreSQL all running together on the production node, with admin login confirmed working.
- Manual password entry, manual RADIUS username entry, and free-text PPSK labels were all still Phase 2 ideas at this point - all three shipped later, see 0.3.0 above.
