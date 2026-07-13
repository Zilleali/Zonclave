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

## Key design points

- `ppsk_groups` is the authoritative registry (Section 7). FreeRADIUS `radcheck`/`radreply` rows are a transactional projection of it, written only through `PpskService::projectToRadius()` (Section 23.1). Nothing else may write RADIUS tables.
- PSKs are always generated (24 chars, ambiguous characters excluded), validated by a single value type (8 to 63 chars), shown once, and stored encrypted at rest (Section 14).
- Phase 1 network plan: VLANs 300 to 304 with subnets `10.30.X.0/24` (X = VLAN - 300), replicated identically on all three OPNsense routers, 5 tunnels per router (Section 5).
- Every admin action (create, edit, enable, disable, delete, regenerate, login) is written to `admin_log` (Section 17).

## Panel development

Requires PHP 8.2+, Composer, and (for production parity) PostgreSQL. Local dev runs on sqlite out of the box.

```sh
cd panel
composer install
cp .env.example .env
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

The installer provisions the auth + panel node on one Ubuntu Server 24.04 LTS host: PostgreSQL, FreeRADIUS with `rlm_sql`, and the Zonclave panel, with all secrets generated at runtime and printed once.

```sh
sudo bash installer/install.sh
# or non-interactive:
sudo bash installer/install.sh --config installer.conf
```

**Honest boundary (Section 24.2):** the installer configures that one host only. OPNsense (VLANs, WireGuard tunnels, gateways, firewall rules) and UniFi (SSID, RADIUS profile) are separate appliances and remain a documented manual runbook in Phase 1. Do not describe the installer as setting up the full end-to-end chain.

## Git workflow

`develop` is the integration branch; feature branches merge into it. `main` receives release merges only, with `--no-ff`. Conventional Commits tied to CLAUDE.md section numbers (Section 23.4).

Never commit secrets, real WireGuard peer configs, `.env`, `installer.conf`, or install summaries. Check `.gitignore` before committing anything under `opnsense/`, `installer/`, or `panel/`.
