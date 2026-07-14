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
| [`docs/installation-guide.md`](docs/installation-guide.md) | Full start-to-finish manual: setup, install, network config, day-to-day use |
| [`docs/commands-reference.md`](docs/commands-reference.md) | Every command in this project, no explanation - a cheat sheet |

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

## Serving the panel with nginx (Linux)

`php artisan serve` is fine for quick iteration, but to validate the panel the way it will actually run in production, serve it through nginx + PHP-FPM on a Linux box. This is exactly what `installer/install.sh`'s `configure_services()` stage automates; doing it by hand once on a test node is a good way to confirm `APP_URL` and the vhost are correct before trusting the full installer.

Install nginx and PHP-FPM (Ubuntu/Debian):

```sh
sudo apt update
sudo apt install -y nginx php8.3-fpm
```

Point the app at the host you'll browse to. Replace `192.168.0.102` with your test node's own LAN IP (find it with `hostname -I`):

```sh
cd panel
sed -i "s|^APP_URL=.*|APP_URL=http://192.168.0.102|" .env
php artisan config:cache
```

Create the nginx site (same shape as the installer's generated vhost):

```sh
sudo tee /etc/nginx/sites-available/zonclave >/dev/null <<'EOF'
server {
    listen 80;
    server_name 192.168.0.102 _;
    root /full/path/to/panel/public;

    index index.php;
    charset utf-8;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF
```

Replace `/full/path/to/panel/public` with the absolute path to your `panel/public` directory, then enable the site and restart services:

```sh
sudo ln -sf /etc/nginx/sites-available/zonclave /etc/nginx/sites-enabled/zonclave
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart php8.3-fpm nginx
```

If `ufw` is enabled, allow HTTP: `sudo ufw allow 80/tcp`.

Verify: `curl -I http://192.168.0.102/admin/login` should return `200`, and the rendered page's links should reference `192.168.0.102`, not `localhost`.

On the real installer, `APP_URL` is resolved the same way (auto-detected LAN IP, or a fixed value if you set `APP_URL` in `installer.conf`, e.g. `http://172.16.74.10` per CLAUDE.md Section 3.4) and kept in sync with the generated nginx vhost automatically, so nothing above needs to be repeated once the full installer runs.

## Installer

The installer provisions the auth + panel node on one Ubuntu Server 24.04 LTS host: PostgreSQL, FreeRADIUS with `rlm_sql`, and the Zonclave panel, with all secrets generated at runtime and printed once. It is Linux-only by design (the OS is pinned per CLAUDE.md Section 24.4); Windows is supported for panel development only, never as a deployment target.

```sh
sudo bash installer/install.sh
# or non-interactive:
sudo bash installer/install.sh --config installer.conf
```

### Encrypted delivery (Section 24.5)

For handing the installer to a client (or running it yourself over SSH) as a single opaque command, `installer/package.sh` builds an AES-256 encrypted payload plus a tiny decrypt-and-run stub from the current git `HEAD` (not the working tree, so uncommitted edits can't ship by accident):

```sh
bash installer/package.sh --passphrase '<choose one>'
# outputs installer/dist/zonclave-installer.enc and installer/dist/run.sh
```

Deliver both output files together; deliver the passphrase separately, over a different channel, never alongside the files. The recipient (or you, over SSH) runs:

```sh
sudo bash run.sh
```

**Honest limitation:** this is tamper-friction and casual protection of the install method, not a secrecy guarantee - anyone with root on the target can recover the decrypted installer at runtime regardless. See CLAUDE.md Section 24.5.

**Honest boundary (Section 24.2):** the installer configures that one host only. OPNsense (VLANs, WireGuard tunnels, gateways, firewall rules) and UniFi (SSID, RADIUS profile) are separate appliances and remain a documented manual runbook in Phase 1: see [docs/runbook/phase1-opnsense-unifi.md](docs/runbook/phase1-opnsense-unifi.md). Do not describe the installer as setting up the full end-to-end chain.

## Git workflow

`develop` is the integration branch; feature branches merge into it. `main` receives release merges only, with `--no-ff`. Conventional Commits tied to CLAUDE.md section numbers (Section 23.4).

Never commit secrets, real WireGuard peer configs, `.env`, `installer.conf`, or install summaries. Check `.gitignore` before committing anything under `opnsense/`, `installer/`, or `panel/`.
