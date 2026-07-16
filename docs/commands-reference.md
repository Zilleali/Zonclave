# Command Reference

Every command used across this project, grouped by which machine you run it on. This is a cheat sheet, not a tutorial - see [installation-guide.md](installation-guide.md) for the full walkthrough with explanations, or [CLAUDE.md](../CLAUDE.md) for the why behind any of it.

Three distinct environments appear in this project:

- **Windows** - your dev machine, for building/testing the panel.
- **Ubuntu Server 24.04** - the Beelink, running the installer and hosting FreeRADIUS/PostgreSQL/the panel in production.
- **OPNsense (FreeBSD shell)** - the Protectli router, for the manual network runbook's verification steps.

## Windows (panel development)

### One-time setup

```powershell
# Either use a package manager...
winget install PHP.PHP.8.3
winget install Composer.Composer
# ...or Chocolatey
choco install php composer -y
```

Enable required PHP extensions in `php.ini`: `mbstring`, `curl`, `zip`, `intl`, `pdo_sqlite`, `sqlite3`, `fileinfo`, `openssl`.

### Panel setup (PowerShell or Git Bash)

```powershell
cd panel
composer install
copy .env.example .env    # Git Bash: cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan panel:create-admin --email=you@example.com --password=<choose-one>
php artisan serve   # panel at http://127.0.0.1:8000/admin
```

### Quality gates (run all three before committing)

```sh
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Auto-fix Pint style violations instead of just checking:

```sh
vendor/bin/pint
```

### Docs linting

```sh
npx --yes markdownlint-cli2 README.md CLAUDE.md docs/**/*.md
npx --yes markdownlint-cli2 --fix <file>   # auto-fix what's fixable
```

### Building the encrypted installer package

Works in Git Bash on Windows (uses `git archive`, not `rsync` - see [ADR context in installer/package.sh](../installer/package.sh)):

```sh
bash installer/package.sh --passphrase '<choose one>'
# outputs installer/dist/zonclave-installer.enc and installer/dist/run.sh
```

### Git workflow

```sh
git status
git diff
git add <files>
git commit -m "type: Section N summary"
git push origin develop
git checkout develop && git merge main --no-ff   # reconciling branches, if ever needed
```

## Ubuntu Server (the Beelink - production auth + panel node)

### Running the installer

```sh
# Ubuntu 24.04:
sudo bash installer/install.sh
# Ubuntu 22.04 (what the Beelink VM actually runs):
sudo bash installer/install-ubuntu22.04.sh
# or non-interactive, reading a prepared answers file:
sudo bash installer/install.sh --config installer.conf
```

### Running the encrypted installer (client-run, or developer-run over SSH)

```sh
sudo bash run.sh
# auto-detects Ubuntu 24.04 vs 22.04 and runs the matching script
# with a prepared installer.conf forwarded to it:
sudo bash run.sh -- --config installer.conf
```

### Service checks (self_check equivalents, for manual troubleshooting)

```sh
systemctl status postgresql
systemctl status freeradius
systemctl status nginx
systemctl status php8.3-fpm
nginx -t                          # test nginx config without reloading
```

### Database

```sh
sudo -u postgres psql -d ppsk     # ppsk is the default DB_NAME
```

### FreeRADIUS auth smoke test

```sh
radtest ppsk_group001 '<the seeded PSK>' 127.0.0.1 0 '<RADIUS shared secret>'
# Expect: Access-Accept in the response
```

### Panel HTTP check

```sh
curl -I http://127.0.0.1/admin/login
# Expect: HTTP/1.1 200 OK
```

### Serving the panel with nginx manually (not via the installer - e.g. a standalone test node)

```sh
sudo apt update
sudo apt install -y nginx php8.3-fpm
cd panel
sed -i "s|^APP_URL=.*|APP_URL=http://<this box's LAN IP>|" .env
php artisan config:cache
```

Then create `/etc/nginx/sites-available/zonclave` (see [README.md's "Serving the panel with nginx" section](../README.md) for the exact vhost content), then:

```sh
sudo ln -sf /etc/nginx/sites-available/zonclave /etc/nginx/sites-enabled/zonclave
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl restart php8.3-fpm nginx
sudo ufw allow 80/tcp   # if ufw is enabled
```

## OPNsense (FreeBSD shell, over SSH or console)

These are verification commands from [docs/runbook/phase1-opnsense-unifi.md](runbook/phase1-opnsense-unifi.md) - the OPNsense GUI is used for the actual creation steps (see that doc for why), these are for confirming the result.

### Identify the real NIC driver name

```sh
ifconfig -a
pciconf -lv | grep -B4 network    # match driver name to chipset
```

### Confirm a VLAN interface is up and tagged

```sh
ifconfig | grep -A3 vlan300
```

### WireGuard tunnel status

```sh
wg show WG_VLAN300              # confirm a non-blank "latest handshake"
```

### Gateway status

```sh
configctl interface routes
```

### Inspect the compiled firewall ruleset (not just the GUI list)

```sh
pfctl -a 'filter/VLAN300' -sr
```

### Kill-switch test (simulate a dead tunnel, confirm no WAN fallback)

```sh
wg set WG_VLAN300 down
curl -m 5 -s -o /dev/null -w '%{http_code}\n' https://ifconfig.me   # run from a client on that VLAN; expect a timeout, not a real IP
wg set WG_VLAN300 up
```
