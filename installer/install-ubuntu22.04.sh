#!/usr/bin/env bash
#
# Zonclave - Auth + Panel Node Installer (Ubuntu 22.04.5 LTS)
# System    : PPSK / VLAN / WireGuard Multi-Tunnel System
# Target OS : Ubuntu Server 22.04.5 LTS
#
# The one officially supported installer target (CLAUDE.md Section 24.4,
# ADR 0003), since Ubuntu 22.04 is what the Office SancoMedia Kelder
# deployment actually runs (see CLAUDE.md Section 26). Ubuntu 22.04's
# default repos ship PHP 8.1, which is below Laravel 12 / Filament 5's
# PHP 8.2+ requirement, so this script adds the ondrej/php PPA to get
# PHP 8.3 before installing PostgreSQL, FreeRADIUS, nginx, and the panel.
#
# Scope     : PostgreSQL + FreeRADIUS + Laravel/Filament web panel (Zonclave)
#             on ONE host. Does NOT configure OPNsense or UniFi (separate
#             appliances, Phase 1 manual step). See CLAUDE.md Section 24.
#
# Usage     : sudo bash install-ubuntu22.04.sh [--config /path/to/installer.conf]
#
# shellcheck disable=SC2024,SC1090
set -euo pipefail

# ---------------------------------------------------------------------------
# 0. Constants and defaults
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR
readonly STATE_DIR="/etc/ppsk-installer"
readonly SUMMARY_FILE="${STATE_DIR}/install-summary.txt"
readonly LOG_FILE="/var/log/ppsk-install.log"

if [ -z "${PANEL_SOURCE:-}" ]; then
  if [ -d "${SCRIPT_DIR}/panel" ]; then
    PANEL_SOURCE="${SCRIPT_DIR}/panel"
  else
    PANEL_SOURCE="${SCRIPT_DIR}/../panel"
  fi
fi
PANEL_GIT_URL="${PANEL_GIT_URL:-}"
PANEL_DIR="/opt/zonclave"

DB_NAME="${DB_NAME:-ppsk}"
DB_USER="${DB_USER:-ppsk}"

RADIUS_CLIENT_SUBNET="${RADIUS_CLIENT_SUBNET:-}"
ADMIN_EMAIL="${ADMIN_EMAIL:-}"
APP_URL="${APP_URL:-}"
ASSUME_YES="${ASSUME_YES:-false}"

readonly FR_DIR="/etc/freeradius/3.0"
readonly FR_SQL_SCHEMA="${FR_DIR}/mods-config/sql/main/postgresql/schema.sql"

# ---------------------------------------------------------------------------
# 1. Logging helpers
# ---------------------------------------------------------------------------
readonly C_RESET="\033[0m"; readonly C_BLUE="\033[1;34m"
readonly C_GREEN="\033[1;32m"; readonly C_YELLOW="\033[1;33m"; readonly C_RED="\033[1;31m"

log()  { echo -e "${C_BLUE}[*]${C_RESET} $*" | tee -a "$LOG_FILE"; }
ok()   { echo -e "${C_GREEN}[OK]${C_RESET} $*" | tee -a "$LOG_FILE"; }
warn() { echo -e "${C_YELLOW}[!]${C_RESET} $*" | tee -a "$LOG_FILE"; }
die()  { echo -e "${C_RED}[X]${C_RESET} $*" | tee -a "$LOG_FILE"; exit 1; }
step() { echo -e "\n${C_BLUE}==== $* ====${C_RESET}" | tee -a "$LOG_FILE"; }

# ---------------------------------------------------------------------------
# 2. Secret and password generation
# ---------------------------------------------------------------------------
gen_psk() {
  local charset='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
  local out=""
  while [ "${#out}" -lt 24 ]; do
    out+=$(head -c 64 /dev/urandom | LC_ALL=C tr -dc "$charset" | head -c 24)
  done
  echo "${out:0:24}"
}

gen_hex()    { openssl rand -hex "${1:-24}"; }
gen_admin()  { openssl rand -base64 18 | tr -d '/+=' | head -c 20; }

resolved_app_url() {
  if [ -n "$APP_URL" ]; then
    echo "$APP_URL"
  else
    echo "http://$(hostname -I | awk '{print $1}')"
  fi
}

# ---------------------------------------------------------------------------
# 3. Preflight
# ---------------------------------------------------------------------------
preflight() {
  step "Preflight checks"

  [ "$(id -u)" -eq 0 ] || die "Run as root (use sudo)."

  # OS check: Ubuntu 22.04 only, for this local variant.
  if [ -r /etc/os-release ]; then
    . /etc/os-release
    if [ "${ID:-}" != "ubuntu" ] || [ "${VERSION_ID:-}" != "22.04" ]; then
      die "Unsupported OS: ${PRETTY_NAME:-unknown}. This variant targets Ubuntu Server 22.04.5 LTS."
    fi
  else
    die "Cannot read /etc/os-release. Aborting."
  fi
  ok "Ubuntu 22.04 LTS confirmed."

  if ! curl -fsS --max-time 8 https://deb.debian.org >/dev/null 2>&1 \
     && ! curl -fsS --max-time 8 https://archive.ubuntu.com >/dev/null 2>&1; then
    warn "No obvious internet reachability. Package installs may fail."
    confirm "Continue anyway?" || die "Aborted by operator."
  fi

  mkdir -p "$STATE_DIR"; chmod 700 "$STATE_DIR"
  : > "$LOG_FILE" || true

  if [ -f "${STATE_DIR}/installed" ]; then
    warn "A previous install was detected. Re-running is safe and will reconcile config."
  fi
  ok "Preflight complete."
}

confirm() {
  local prompt="${1:-Continue?}"
  [ "$ASSUME_YES" = "true" ] && return 0
  read -r -p "$(echo -e "${C_YELLOW}[?]${C_RESET} ${prompt} [y/N] ")" ans
  [[ "$ans" =~ ^[Yy]$ ]]
}

# ---------------------------------------------------------------------------
# 4. Gather required input (minimal, essentials only)
# ---------------------------------------------------------------------------
gather_input() {
  step "Configuration"

  if [ -z "$RADIUS_CLIENT_SUBNET" ]; then
    if [ "$ASSUME_YES" = "true" ]; then
      die "RADIUS_CLIENT_SUBNET is required in non-interactive mode. Set it in installer.conf."
    fi
    read -r -p "$(echo -e "${C_BLUE}[?]${C_RESET} UniFi controller/AP subnet (e.g. 192.168.1.0/24): ")" RADIUS_CLIENT_SUBNET
    [ -n "$RADIUS_CLIENT_SUBNET" ] || die "RADIUS client subnet is required."
  fi

  if [ -z "$ADMIN_EMAIL" ]; then
    if [ "$ASSUME_YES" = "true" ]; then
      ADMIN_EMAIL="admin@sancover.local"
      warn "ADMIN_EMAIL not set; defaulting to ${ADMIN_EMAIL}."
    else
      read -r -p "$(echo -e "${C_BLUE}[?]${C_RESET} Panel admin email: ")" ADMIN_EMAIL
      [ -n "$ADMIN_EMAIL" ] || die "Admin email is required."
    fi
  fi

  DB_PASSWORD="$(gen_hex 20)"
  RADIUS_SECRET="$(gen_hex 24)"
  ADMIN_PASSWORD="$(gen_admin)"
  SEED_PSK_1="$(gen_psk)"
  SEED_PSK_2="$(gen_psk)"

  ok "Configuration gathered. Secrets generated."
}

# ---------------------------------------------------------------------------
# 5. Install OS dependencies
# ---------------------------------------------------------------------------
install_dependencies() {
  step "Installing dependencies"

  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y >>"$LOG_FILE" 2>&1

  # Ubuntu 22.04's default repos only ship PHP 8.1 (Laravel 12/Filament 5
  # need 8.2+), so pull PHP 8.3 from ondrej/php instead of the base repos.
  apt-get install -y software-properties-common >>"$LOG_FILE" 2>&1
  add-apt-repository -y ppa:ondrej/php >>"$LOG_FILE" 2>&1
  apt-get update -y >>"$LOG_FILE" 2>&1

  apt-get install -y \
    postgresql postgresql-contrib \
    freeradius freeradius-postgresql freeradius-utils \
    nginx \
    php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl \
    git unzip curl openssl ca-certificates \
    >>"$LOG_FILE" 2>&1

  if ! command -v composer >/dev/null 2>&1; then
    curl -fsS https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer >>"$LOG_FILE" 2>&1
    rm -f /tmp/composer-setup.php
  fi

  systemctl enable --now postgresql >>"$LOG_FILE" 2>&1
  ok "Dependencies installed."
}

# ---------------------------------------------------------------------------
# 6. Database: role, DB, FreeRADIUS schema, registry tables, seed data
# ---------------------------------------------------------------------------
install_db() {
  step "Configuring PostgreSQL"

  sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" \
    | grep -q 1 || sudo -u postgres psql -c \
      "CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASSWORD}';" >>"$LOG_FILE" 2>&1

  sudo -u postgres psql -c \
    "ALTER ROLE ${DB_USER} WITH PASSWORD '${DB_PASSWORD}';" >>"$LOG_FILE" 2>&1

  sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" \
    | grep -q 1 || sudo -u postgres createdb -O "${DB_USER}" "${DB_NAME}" >>"$LOG_FILE" 2>&1

  if [ -f "$FR_SQL_SCHEMA" ]; then
    # Root (this script) opens the file via redirection and streams it to
    # psql's stdin, rather than passing -f and letting the postgres OS user
    # try to open it directly - the postgres user has no read permission on
    # FreeRADIUS's config files, so -f fails with "Permission denied" there.
    sudo -u postgres psql -d "${DB_NAME}" >>"$LOG_FILE" 2>&1 < "$FR_SQL_SCHEMA" \
      || die "Failed to load the FreeRADIUS SQL schema from ${FR_SQL_SCHEMA}. See ${LOG_FILE}."
    sudo -u postgres psql -d "${DB_NAME}" -c \
      "GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ${DB_USER};
       GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ${DB_USER};" >>"$LOG_FILE" 2>&1
  else
    die "FreeRADIUS PostgreSQL schema not found at ${FR_SQL_SCHEMA}."
  fi

  sudo -u postgres psql -d "${DB_NAME}" >>"$LOG_FILE" 2>&1 <<'SQL'
CREATE TABLE IF NOT EXISTS ppsk_groups (
  id SERIAL PRIMARY KEY,
  label VARCHAR(128) NOT NULL,
  radius_username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  vlan_id INT NOT NULL,
  subnet VARCHAR(32) NOT NULL,
  wireguard_interface VARCHAR(32) NOT NULL,
  wireguard_gateway VARCHAR(32) NOT NULL,
  opnsense_interface VARCHAR(64),
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ppsk_groups_vlan   ON ppsk_groups (vlan_id);
CREATE INDEX IF NOT EXISTS idx_ppsk_groups_status ON ppsk_groups (status);
CREATE INDEX IF NOT EXISTS idx_ppsk_groups_label  ON ppsk_groups (label);

CREATE TABLE IF NOT EXISTS admin_log (
  id SERIAL PRIMARY KEY,
  ts TIMESTAMP DEFAULT NOW(),
  admin_user VARCHAR(128),
  action VARCHAR(64) NOT NULL,
  target_ppsk_id INT,
  detail TEXT
);
CREATE INDEX IF NOT EXISTS idx_admin_log_ts     ON admin_log (ts);
CREATE INDEX IF NOT EXISTS idx_admin_log_target ON admin_log (target_ppsk_id);
CREATE INDEX IF NOT EXISTS idx_admin_log_action ON admin_log (action);
SQL
  sudo -u postgres psql -d "${DB_NAME}" -c \
    "GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ${DB_USER};
     GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ${DB_USER};" >>"$LOG_FILE" 2>&1

  ok "Database, schema, and registry tables ready."
  seed_test_groups
}

seed_test_groups() {
  # NOTE: for these THROWAWAY test rows the cleartext PSK is stored in the
  # password_hash column too. Real entries created via the panel are encrypted
  # at rest per CLAUDE.md Section 14; the panel owns that encryption.
  local rows=(
    "ppsk_group001|VLAN300_TEST_A|300|10.30.0.0/24|WG_VLAN300|GW_WG_VLAN300|${SEED_PSK_1}"
    "ppsk_group002|VLAN301_TEST_B|301|10.30.1.0/24|WG_VLAN301|GW_WG_VLAN301|${SEED_PSK_2}"
  )
  for r in "${rows[@]}"; do
    IFS='|' read -r user label vlan subnet wgif wggw psk <<<"$r"
    sudo -u postgres psql -d "${DB_NAME}" -v ON_ERROR_STOP=1 >>"$LOG_FILE" 2>&1 <<SQL
BEGIN;

INSERT INTO ppsk_groups (label, radius_username, password_hash, vlan_id, subnet, wireguard_interface, wireguard_gateway, status)
VALUES ('${label}', '${user}', '${psk}', ${vlan}, '${subnet}', '${wgif}', '${wggw}', 'active')
ON CONFLICT (radius_username) DO NOTHING;

INSERT INTO radcheck (username, attribute, op, value)
SELECT '${user}', 'Cleartext-Password', ':=', '${psk}'
WHERE NOT EXISTS (SELECT 1 FROM radcheck WHERE username = '${user}' AND attribute = 'Cleartext-Password');

INSERT INTO radreply (username, attribute, op, value)
SELECT v.username, v.attribute, v.op, v.value
FROM (VALUES
  ('${user}', 'Tunnel-Private-Group-Id', ':=', '${vlan}'),
  ('${user}', 'Tunnel-Type',             ':=', 'VLAN'),
  ('${user}', 'Tunnel-Medium-Type',      ':=', 'IEEE-802')
) AS v(username, attribute, op, value)
WHERE NOT EXISTS (
  SELECT 1 FROM radreply r WHERE r.username = v.username AND r.attribute = v.attribute
);

COMMIT;
SQL
  done
  ok "Seeded 2 test PPSK groups (VLAN 300, VLAN 301)."
}

# ---------------------------------------------------------------------------
# 7. FreeRADIUS: SQL module (postgresql), site wiring, clients, service
# ---------------------------------------------------------------------------
install_freeradius() {
  step "Configuring FreeRADIUS"

  local sqlmod="${FR_DIR}/mods-available/sql"
  cp -n "$sqlmod" "${sqlmod}.orig" 2>/dev/null || true
  sed -i \
    -e 's|^\s*driver\s*=.*|\tdriver = "rlm_sql_postgresql"|' \
    -e 's|^\s*dialect\s*=.*|\tdialect = "postgresql"|' \
    -e "s|^\s*#\?\s*server\s*=.*|\tserver = \"localhost\"|" \
    -e "s|^\s*#\?\s*port\s*=.*|\tport = 5432|" \
    -e "s|^\s*#\?\s*login\s*=.*|\tlogin = \"${DB_USER}\"|" \
    -e "s|^\s*#\?\s*password\s*=.*|\tpassword = \"${DB_PASSWORD}\"|" \
    -e "s|^\s*radius_db\s*=.*|\tradius_db = \"${DB_NAME}\"|" \
    "$sqlmod"

  ln -sf ../mods-available/sql "${FR_DIR}/mods-enabled/sql"

  local site="${FR_DIR}/sites-available/default"
  local inner="${FR_DIR}/sites-available/inner-tunnel"
  for f in "$site" "$inner"; do
    [ -f "$f" ] || continue
    cp -n "$f" "${f}.orig" 2>/dev/null || true
    sed -i 's|^\(\s*\)#\s*sql\s*$|\1sql|' "$f"
    sed -i 's|^\(\s*\)-sql|\1sql|' "$f"
  done

  local clients="${FR_DIR}/clients.conf"
  if ! grep -q "client ppsk_unifi" "$clients"; then
    cat >>"$clients" <<EOF

# Added by PPSK installer - UniFi controller / APs
client ppsk_unifi {
    ipaddr = ${RADIUS_CLIENT_SUBNET}
    secret = ${RADIUS_SECRET}
    shortname = unifi
    nas_type = other
}
EOF
  else
    warn "RADIUS client already present; leaving existing block (edit clients.conf to rotate secret)."
  fi

  chown -R freerad:freerad "$FR_DIR" 2>/dev/null || true

  if freeradius -XC >>"$LOG_FILE" 2>&1; then
    ok "FreeRADIUS configuration test passed."
  else
    die "FreeRADIUS configuration test failed. See ${LOG_FILE}."
  fi

  systemctl enable --now freeradius >>"$LOG_FILE" 2>&1
  systemctl restart freeradius >>"$LOG_FILE" 2>&1
  ok "FreeRADIUS running."
}

# ---------------------------------------------------------------------------
# 8. Deploy the Laravel + Filament panel
# ---------------------------------------------------------------------------
deploy_panel() {
  step "Deploying web panel"

  if [ -n "$PANEL_GIT_URL" ]; then
    if [ -d "${PANEL_DIR}/.git" ]; then
      git -C "$PANEL_DIR" pull --ff-only >>"$LOG_FILE" 2>&1 || true
    else
      rm -rf "$PANEL_DIR"
      git clone "$PANEL_GIT_URL" "$PANEL_DIR" >>"$LOG_FILE" 2>&1
    fi
  elif [ -d "$PANEL_SOURCE" ]; then
    mkdir -p "$PANEL_DIR"
    cp -a "${PANEL_SOURCE}/." "$PANEL_DIR/"
  else
    warn "Panel source not found (PANEL_SOURCE=${PANEL_SOURCE}, PANEL_GIT_URL unset)."
    warn "Skipping panel deploy. Database and FreeRADIUS are fully provisioned."
    warn "Re-run with the panel source present to complete the web panel."
    PANEL_DEPLOYED="false"
    return 0
  fi

  cd "$PANEL_DIR"

  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader \
    >>"$LOG_FILE" 2>&1

  [ -f .env ] || cp .env.example .env
  {
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env
    sed -i "s|^APP_URL=.*|APP_URL=$(resolved_app_url)|" .env
    sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|" .env
    sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
    sed -i "s|^DB_PORT=.*|DB_PORT=5432|" .env
    sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
    sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
  } 2>>"$LOG_FILE"

  php artisan key:generate --force >>"$LOG_FILE" 2>&1

  php artisan migrate --force >>"$LOG_FILE" 2>&1 || \
    warn "Some migrations skipped (tables may already exist). Review ${LOG_FILE}."

  php artisan filament:assets >>"$LOG_FILE" 2>&1 || true
  php artisan storage:link >>"$LOG_FILE" 2>&1 || true
  php artisan config:cache >>"$LOG_FILE" 2>&1 || true
  php artisan route:cache  >>"$LOG_FILE" 2>&1 || true
  php artisan view:cache   >>"$LOG_FILE" 2>&1 || true

  create_admin_user

  chown -R www-data:www-data "$PANEL_DIR"
  find "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" -type d -exec chmod 775 {} \; 2>/dev/null || true

  PANEL_DEPLOYED="true"
  ok "Panel deployed at ${PANEL_DIR}."
}

create_admin_user() {
  if php artisan list 2>/dev/null | grep -q "panel:create-admin"; then
    php artisan panel:create-admin --email="${ADMIN_EMAIL}" --password="${ADMIN_PASSWORD}" \
      >>"$LOG_FILE" 2>&1 && return 0
  fi
  php artisan tinker --execute="
    \$u = \App\Models\User::firstOrNew(['email' => '${ADMIN_EMAIL}']);
    \$u->name = 'Administrator';
    \$u->password = bcrypt('${ADMIN_PASSWORD}');
    \$u->save();
  " >>"$LOG_FILE" 2>&1 || warn "Admin user creation via fallback failed; create it manually."
}

# ---------------------------------------------------------------------------
# 9. Web server (nginx vhost for Laravel)
# ---------------------------------------------------------------------------
configure_services() {
  step "Configuring web server"

  [ "${PANEL_DEPLOYED:-false}" = "true" ] || { warn "Panel not deployed; skipping nginx vhost."; return 0; }

  local app_host
  app_host="$(resolved_app_url)"
  app_host="${app_host#http://}"
  app_host="${app_host#https://}"

  cat >/etc/nginx/sites-available/zonclave <<EOF
server {
    listen 80;
    server_name ${app_host} _;
    root ${PANEL_DIR}/public;

    index index.php;
    charset utf-8;

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
EOF

  ln -sf /etc/nginx/sites-available/zonclave /etc/nginx/sites-enabled/zonclave
  rm -f /etc/nginx/sites-enabled/default

  nginx -t >>"$LOG_FILE" 2>&1 || die "nginx config test failed. See ${LOG_FILE}."
  systemctl enable --now php8.3-fpm >>"$LOG_FILE" 2>&1
  systemctl restart php8.3-fpm nginx >>"$LOG_FILE" 2>&1
  ok "Web server configured for $(resolved_app_url)/"
}

# ---------------------------------------------------------------------------
# 10. Self-check
# ---------------------------------------------------------------------------
self_check() {
  step "Self-check"
  local fails=0

  systemctl is-active --quiet postgresql && ok "PostgreSQL active" || { warn "PostgreSQL not active"; fails=$((fails+1)); }
  systemctl is-active --quiet freeradius && ok "FreeRADIUS active" || { warn "FreeRADIUS not active"; fails=$((fails+1)); }

  if command -v radtest >/dev/null 2>&1; then
    # Test against the "localhost" client's own secret, not RADIUS_SECRET -
    # that secret belongs to the ppsk_unifi client, scoped to the AP subnet
    # (RADIUS_CLIENT_SUBNET), which 127.0.0.1 does not match. Using the
    # wrong secret here doesn't get an Access-Reject, it gets total
    # silence (RFC 2865: a server never replies to an unrecognized secret),
    # which reads as "FreeRADIUS is broken" when it isn't.
    local localhost_secret
    localhost_secret="$(sed -n '/^client localhost {/,/^}/{/secret/p}' "${FR_DIR}/clients.conf" \
      | head -1 | sed -E 's/.*secret[[:space:]]*=[[:space:]]*"?([^"[:space:]]+)"?.*/\1/')"
    localhost_secret="${localhost_secret:-$RADIUS_SECRET}"

    if radtest ppsk_group001 "${SEED_PSK_1}" 127.0.0.1 0 "${localhost_secret}" 2>>"$LOG_FILE" | grep -q "Access-Accept"; then
      ok "RADIUS auth smoke test passed (ppsk_group001 -> Access-Accept)."
    else
      warn "RADIUS auth smoke test did not return Access-Accept. Review ${LOG_FILE}."
      fails=$((fails+1))
    fi
  fi

  if [ "${PANEL_DEPLOYED:-false}" = "true" ]; then
    systemctl is-active --quiet nginx && ok "nginx active" || { warn "nginx not active"; fails=$((fails+1)); }
    if curl -fsS --max-time 8 "http://127.0.0.1/" >/dev/null 2>&1; then
      ok "Panel responds on $(resolved_app_url)/"
    else
      warn "Panel did not respond on HTTP. Review ${LOG_FILE}."
      fails=$((fails+1))
    fi
  fi

  [ "$fails" -eq 0 ] || warn "${fails} check(s) reported issues. Installation completed with warnings."
}

# ---------------------------------------------------------------------------
# 11. Summary
# ---------------------------------------------------------------------------
summary() {
  step "Summary"
  local ip; ip="$(hostname -I | awk '{print $1}')"

  umask 077
  cat >"$SUMMARY_FILE" <<EOF
Zonclave - Install Summary (Ubuntu 22.04.5 LTS local variant)
PPSK / VLAN / WireGuard - Auth + Panel Node
Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")

Panel URL           : $(resolved_app_url)/
Panel admin email   : ${ADMIN_EMAIL}
Panel admin password: ${ADMIN_PASSWORD}

RADIUS server IP    : ${ip}
RADIUS client subnet: ${RADIUS_CLIENT_SUBNET}
RADIUS shared secret: ${RADIUS_SECRET}
RADIUS ports        : UDP 1812 (auth), 1813 (accounting)

Database            : ${DB_NAME} (PostgreSQL, localhost)
DB user             : ${DB_USER}
DB password         : ${DB_PASSWORD}

Seed PPSK #1        : user ppsk_group001  VLAN 300  psk ${SEED_PSK_1}
Seed PPSK #2        : user ppsk_group002  VLAN 301  psk ${SEED_PSK_2}

Panel deployed      : ${PANEL_DEPLOYED:-false}
EOF
  chmod 600 "$SUMMARY_FILE"
  touch "${STATE_DIR}/installed"

  echo -e "\n${C_GREEN}Installation complete.${C_RESET}"
  echo -e "Full credentials saved (root only): ${SUMMARY_FILE}\n"
  echo    "-------------------------------------------------------------"
  echo    " Panel URL            : $(resolved_app_url)/"
  echo    " Panel admin email    : ${ADMIN_EMAIL}"
  echo    " Panel admin password : ${ADMIN_PASSWORD}"
  echo    " RADIUS server IP     : ${ip}"
  echo    " RADIUS shared secret : ${RADIUS_SECRET}"
  echo    "-------------------------------------------------------------"
  echo -e "\n${C_YELLOW}Next (manual, Phase 1):${C_RESET}"
  echo    "  1. UniFi: set the SSID RADIUS profile to ${ip} with the shared secret above."
  echo    "  2. UniFi: enable RADIUS-based Private PSK on the SSID (verify version support)."
  echo    "  3. OPNsense: create VLANs, WireGuard tunnels, gateways, and firewall rules"
  echo    "     per CLAUDE.md Sections 9 to 12 (fail-closed, no WAN fallback)."
  echo    "  4. Run the Section 21 acceptance tests end to end."
  echo
}

# ---------------------------------------------------------------------------
# 12. Main
# ---------------------------------------------------------------------------
load_config() {
  if [ "${1:-}" = "--config" ] && [ -n "${2:-}" ]; then
    # shellcheck disable=SC1090
    [ -f "$2" ] || die "Config file not found: $2"
    . "$2"
    log "Loaded config from $2"
  fi
}

main() {
  load_config "$@"
  preflight
  gather_input
  install_dependencies
  install_db
  install_freeradius
  deploy_panel
  configure_services
  self_check
  summary
}

main "$@"
