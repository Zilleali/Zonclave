# Zonclave

PPSK / VLAN / WireGuard multi-tunnel system. A single Wi-Fi SSID accepts many unique pre-shared keys (PPSKs); each PPSK maps to a dedicated VLAN, and each VLAN is policy-routed on OPNsense through its own WireGuard tunnel to a residential VPN provider. Every PPSK group egresses to the internet from its own residential public IP. Managed through a web panel, designed to scale past 100 groups.

**Product:** Zonclave (a Developer Zon product) | **Client:** Sancover | **Status:** Phase 1 build

> **[CLAUDE.md](CLAUDE.md) is the single source of truth for this project.** Read it in full before writing any config or code. This README is an orientation page, not a specification.

## How it works

```text
Device --> SSID + unique PPSK --> UniFi AP --> FreeRADIUS (auth + VLAN assignment)
                                                 |
                                    Access-Accept + Tunnel-Private-Group-Id
                                                 v
UniFi switch (tags VLAN) --> OPNsense --> WG_VLAN<id> tunnel --> residential public IP
```

FreeRADIUS handles authentication and VLAN assignment only. OPNsense handles routing, firewalling, and VPN policy only. That boundary is never blurred (CLAUDE.md Section 1).

Each VLAN is fail-closed: if its WireGuard tunnel drops, traffic is dropped, never rerouted out the plain WAN (Section 12).

## Repository layout

| Path | Contents |
| --- | --- |
| `CLAUDE.md` | Project reference and specification. Source of truth |
| `panel/` | Zonclave web panel: Laravel 12 + Filament 5, PostgreSQL |
| `installer/install.sh` | One-command installer for the auth + panel node (Ubuntu Server 24.04 LTS) |
| `db/` | Reference SQL schema and seed scripts for dev/test databases |
| `docs/adr/` | Architecture decision records |
| `docs/runbook/` | Manual OPNsense + UniFi configuration steps (Section 22: not installer-automated in Phase 1) |

## Key design points

- `ppsk_groups` is the authoritative registry (Section 7). FreeRADIUS `radcheck`/`radreply` rows are a transactional projection of it, written only through `PpskService::projectToRadius()` (Section 23.1). Nothing else may write RADIUS tables.
- PSKs are always generated (24 chars, ambiguous characters excluded), validated by a single value type (8 to 63 chars), shown once, and stored encrypted at rest (Section 14).
- Phase 1 network plan: VLANs 300 to 304 with subnets `10.30.X.0/24` (X = VLAN - 300), replicated identically on all three OPNsense routers, 5 tunnels per router (Section 5).
- Every admin action (create, edit, enable, disable, delete, regenerate, login) is written to `admin_log` (Section 17).

## Panel development

Requires PHP 8.2+ (with `mbstring`, `xml`, `curl`, `zip`, `intl`, `sqlite3`/`pdo_sqlite` extensions), Composer 2, and (for production parity) PostgreSQL. Local dev runs on sqlite out of the box.

### Linux (Ubuntu 24.04 / Debian 12)

Install prerequisites, then set up the app:

```sh
sudo apt update
sudo apt install -y php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-intl php8.3-sqlite3 composer git

cd panel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan panel:create-admin --email=you@example.com --password=<choose-one>
php artisan serve   # panel at http://127.0.0.1:8000/admin
```

### Windows 10/11

Install PHP 8.2+ and Composer first. Either download PHP from <https://windows.php.net/download/> and Composer from <https://getcomposer.org/download/>, or use a package manager:

```powershell
# with winget
winget install PHP.PHP.8.3
winget install Composer.Composer

# or with Chocolatey
choco install php composer -y
```

Enable the required extensions in `php.ini` (uncomment `extension=mbstring`, `curl`, `zip`, `intl`, `pdo_sqlite`, `sqlite3`, `fileinfo`, `openssl`), then from PowerShell or Git Bash:

```powershell
cd panel
composer install
copy .env.example .env    # use `cp` in Git Bash
php artisan key:generate
php artisan migrate
php artisan panel:create-admin --email=you@example.com --password=<choose-one>
php artisan serve   # panel at http://127.0.0.1:8000/admin
```

Quality gates (run all three before committing, per Section 23.2):

```sh
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## Installer

The installer provisions the auth + panel node on one Ubuntu Server 24.04 LTS host: PostgreSQL, FreeRADIUS with `rlm_sql`, and the Zonclave panel, with all secrets generated at runtime and printed once. It is Linux-only by design (the OS is pinned per CLAUDE.md Section 24.4); Windows is supported for panel development only, never as a deployment target.

```sh
sudo bash installer/install.sh
# or non-interactive:
sudo bash installer/install.sh --config installer.conf
```

**Honest boundary (Section 24.2):** the installer configures that one host only. OPNsense (VLANs, WireGuard tunnels, gateways, firewall rules) and UniFi (SSID, RADIUS profile) are separate appliances and remain a documented manual runbook in Phase 1: see [docs/runbook/phase1-opnsense-unifi.md](docs/runbook/phase1-opnsense-unifi.md). Do not describe the installer as setting up the full end-to-end chain.

## Git workflow

`develop` is the integration branch; feature branches merge into it. `main` receives release merges only, with `--no-ff`. Conventional Commits tied to CLAUDE.md section numbers (Section 23.4).

Never commit secrets, real WireGuard peer configs, `.env`, `installer.conf`, or install summaries. Check `.gitignore` before committing anything under `opnsense/`, `installer/`, or `panel/`.
