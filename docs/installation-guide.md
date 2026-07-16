# Zonclave: Installation Guide and User Manual

A complete, start-to-finish manual for Zonclave: what it is, how to set up the panel for development, how to install it in production, how to wire up the network side, and how to actually use it day to day.

This document is the narrative walkthrough. For the authoritative specification behind every decision here, see [CLAUDE.md](../CLAUDE.md) - if the two ever disagree, CLAUDE.md wins. For a bare command cheat sheet with no explanation, see [commands-reference.md](commands-reference.md).

## 1. What Zonclave is

Zonclave lets a single Wi-Fi SSID accept many unique pre-shared keys (PPSKs). Each PPSK maps to a dedicated VLAN. Each VLAN is policy-routed on OPNsense through its own dedicated WireGuard tunnel to a residential VPN provider, so each group of devices egresses to the internet through its own distinct public ISP IP address.

Two layers make this work, and both matter equally:

- **The network layer**: UniFi APs authenticate devices against FreeRADIUS, which hands back a VLAN assignment. OPNsense routes each VLAN through its own WireGuard tunnel, with a fail-closed firewall policy - if a tunnel drops, that VLAN's traffic is dropped, never silently rerouted out the plain WAN.
- **The software layer**: the Zonclave panel (this repo's `panel/` directory) is where an administrator creates, edits, enables/disables, and deletes PPSK credentials. Every credential's mapping to a VLAN and RADIUS identity flows from one authoritative registry table (`ppsk_groups`) through one transactional write path - nothing else is allowed to touch the RADIUS tables directly.

FreeRADIUS only ever does authentication and VLAN assignment. OPNsense only ever does routing, firewalling, and VPN policy. That boundary is never blurred - see CLAUDE.md Section 1.

## 2. Repository layout

| Path | What it is |
| --- | --- |
| `CLAUDE.md` | The full specification. Read this for the "why" behind anything. |
| `panel/` | The Zonclave web panel: Laravel 12 + Filament 5, PostgreSQL in production (sqlite for local dev). |
| `installer/install-ubuntu22.04.sh` | The one-command installer for the auth + panel node, Ubuntu Server 22.04 LTS (the officially supported target, Section 24.4). |
| `installer/hyperv-ubuntu22.04-setup.md` | Full walkthrough for running the installer inside a Hyper-V VM on a Windows host. |
| `installer/package.sh` / `installer/run.sh` | Build and deliver an encrypted, single-command version of the installer. |
| `db/` | Reference SQL schema and seed scripts, for understanding the data model without spinning up the full app. |
| `docs/adr/` | Architecture decision records - short notes on why a non-obvious technical choice was made. |
| `docs/runbook/` | The manual OPNsense + UniFi network configuration steps (Section 22: not automated by the installer in Phase 1). |
| `docs/commands-reference.md` | Every command in this project, with no explanation - a cheat sheet. |
| `docs/installation-guide.md` | This file. |
| `docs/opnsense-configuration.md` | The general OPNsense configuration pattern (VLANs, tunnels, firewall ordering), independent of any one site. |

## 3. Prerequisites

- **For panel development**: PHP 8.2+ (with `mbstring`, `xml`, `curl`, `zip`, `intl`, `sqlite3`/`pdo_sqlite`), Composer 2. Works on Windows or Linux.
- **For production installation**: Ubuntu Server 22.04 LTS (the installer refuses to run on anything else) - bare metal, or as a VM under any hypervisor (Hyper-V, VMware, VirtualBox, KVM, and so on), so the host machine itself can be Windows, Linux, or macOS. Root access on the Ubuntu guest.
- **For the network side**: an OPNsense router (Protectli FW6E in this project) and a UniFi controller/AP deployment, both already physically installed.

## 4. Setting up the panel for development

See [README.md](../README.md#panel-development) for the exact platform-specific commands (Linux and Windows both covered), or the condensed version in [commands-reference.md](commands-reference.md#windows-panel-development). In short:

```sh
cd panel
composer install
cp .env.example .env          # Windows PowerShell: copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan panel:create-admin --email=you@example.com --password=<choose-one>
php artisan serve
```

Visit `http://127.0.0.1:8000/admin` and log in with the admin account you just created. This runs on sqlite locally - no PostgreSQL needed for development.

Before committing any change to `panel/`, run all three quality gates:

```sh
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

All three must pass clean. This is not optional - CLAUDE.md Section 23.2 requires it.

### What you can actually do in the panel

- **Dashboard** (`/admin`): stat cards for total/active/disabled PPSK groups, each clickable through to a pre-filtered list, plus a 7-day registry-growth chart.
- **PPSK Groups** (`/admin/ppsk-groups`): the inventory list. Create a new PPSK (label + VLAN, password is always generated, never typed in), edit a group's label/VLAN, enable/disable, delete, or regenerate a group's password. Every PSK is shown exactly once, with a copy-to-clipboard button.
- **Admin Log** (`/admin/admin-logs`): a read-only audit trail of every login and every PPSK create/edit/enable/disable/delete/regenerate action. No way to edit or delete an entry through the panel - it's append-only by design.
- **Profile** (`/admin/profile`): change the admin password. Email is locked read-only (Section 16.1: single admin, nothing to reconcile it against).

## 5. Installing in production

The installer provisions **one host**: PostgreSQL, FreeRADIUS with `rlm_sql`, and the Zonclave panel, with all secrets generated at runtime and printed once. It is Linux-only by design, targeting the one officially supported version, **Ubuntu Server 22.04 LTS** (CLAUDE.md Section 24.4, ADR 0003) - `installer/install-ubuntu22.04.sh` adds PHP 8.3 via the `ondrej/php` PPA, since 22.04's base repos only ship 8.1. This is what the Office SancoMedia Kelder deployment actually runs - see `installer/hyperv-ubuntu22.04-setup.md` for the full Hyper-V walkthrough if the host is Windows.

An earlier Ubuntu 24.04 script was removed from the repo when this decision reverted (ADR 0003) - see that ADR if 24.04 support is ever needed again.

### Plain install

```sh
sudo bash installer/install-ubuntu22.04.sh
```

You'll be prompted for the UniFi controller/AP subnet and an admin email; everything else is generated automatically. Or, for a fully non-interactive run, prepare an `installer.conf` (see the variables documented at the top of the script) and run:

```sh
sudo bash installer/install-ubuntu22.04.sh --config installer.conf
```

At the end, the installer prints (and saves to a root-only file) the panel URL, the admin login, the RADIUS shared secret, and the seeded test PPSK credentials. **Write these down or save the summary file securely - they are shown once.**

### Encrypted delivery (for handing this to a client, or running it yourself over SSH)

If you'd rather not hand someone a raw shell script, or want to run this yourself over SSH as a single opaque command:

```sh
bash installer/package.sh --passphrase '<choose one>'
# outputs installer/dist/zonclave-installer.enc and installer/dist/run.sh
```

Deliver both output files together (email, USB, `scp`). Deliver the passphrase **separately**, over a different channel - never in the same message as the files. The recipient (or you, over SSH) runs:

```sh
sudo bash run.sh
```

This is tamper-friction and casual protection of the install method, not a secrecy guarantee - anyone with root on the target can recover the decrypted installer at runtime regardless. See CLAUDE.md Section 24.5.

### What the installer does NOT do

It configures the auth + panel node only. It does not touch OPNsense or UniFi - those are separate appliances, and Phase 1 leaves that configuration manual (Section 24.2). That's what Section 6 below is for.

## 6. Setting up the network (OPNsense + UniFi)

This is the part the installer can't automate. See [docs/opnsense-configuration.md](opnsense-configuration.md) for the general pattern, then follow [docs/runbook/phase1-opnsense-unifi.md](runbook/phase1-opnsense-unifi.md) in full for this project's site-specific steps - it covers, per router:

1. Confirming the real NIC driver names and the physical trunk port topology (every site is different - do not assume the last site's answers apply to this one).
2. Creating the five PPSK VLAN interfaces (300-304), their WireGuard tunnels, gateways, and the paired allow/block firewall rules that make the fail-closed design actually fail closed.
3. The management VLAN (205), extended to also carry device-management traffic for the UniFi switch, Cloud Key, and APs - not just the Zonclave server.
4. DNS-through-tunnel configuration, so DNS queries don't leak outside the encrypted path.
5. A kill-switch test you run yourself, per VLAN, before trusting it.
6. The UniFi side: a RADIUS profile pointing at the panel node, an SSID configured for RADIUS-assigned PPSK (WPA2 only - no WPA3/6GHz, per Ubiquiti's current PPSK feature limits), and confirming the switch trunk passes every VLAN tagged.

This is a live-network change if any of the router's ports are already in production use - read the runbook's own cautions about maintenance windows and re-testing existing VLAN isolation, not just the new one.

## 7. Verifying it all actually works (acceptance testing)

CLAUDE.md Section 21.1 lists the 10 manual acceptance tests that prove Phase 1's actual goal: **a real device connects with a PPSK, lands on the correct VLAN, and its outbound public IP matches that VLAN's residential WireGuard tunnel.** No amount of passing automated tests substitutes for this - the automated suite (Section 21.2) protects the software layer; only these 10 tests prove the network layer.

In short, per PPSK group: provision it, connect a device, confirm the VLAN and egress IP, disable it and confirm it stops working, delete it and confirm it's gone, kill its tunnel and confirm the device loses internet (not falls back to the real WAN), confirm VLAN isolation from other VLANs and the management network, run a DNS leak test, and confirm every action left a row in the Admin Log.

## 8. Where to go for more detail

| Question | Where to look |
| --- | --- |
| Why was X designed this way? | [CLAUDE.md](../CLAUDE.md) - search for the relevant section number. |
| What's the exact command for Y? | [commands-reference.md](commands-reference.md) |
| How do I configure the router/switch for this specific site? | [docs/runbook/phase1-opnsense-unifi.md](runbook/phase1-opnsense-unifi.md) |
| Why did we choose this specific technical approach for Z? | [docs/adr/](adr/) |
| What does the database schema actually look like? | [db/README.md](../db/README.md) and `db/schema/` |
