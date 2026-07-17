#!/usr/bin/env bash
#
# zonclave - operational CLI for the Zonclave auth + panel node.
# Installed as /usr/local/bin/zonclave by install-ubuntu22.04.sh
# (install_cli stage). Source of truth lives here; edit this file, not
# the copy on any server.
#
# Usage: zonclave update
#   Pulls the latest code in the git checkout, redeploys it to the
#   served copy, runs migrations, and clears/rebuilds all caches.
#
# Does NOT touch PostgreSQL, FreeRADIUS, or nginx vhost config, and
# never regenerates secrets - those are the full installer's job
# (installer/install-ubuntu22.04.sh), run explicitly, not on every
# code update. Re-running the full installer on every code change is
# what caused the RADIUS secret / DB password mismatches documented in
# CLAUDE.md Section 26.7; this script exists specifically to avoid that.
#
# shellcheck disable=SC1090
set -euo pipefail

readonly REPO_DIR="/var/www/Zonclave"
readonly PANEL_SOURCE="${REPO_DIR}/panel"
readonly PANEL_DIR="/opt/zonclave"
readonly LOG_FILE="/var/log/zonclave-update.log"

C_RESET="\033[0m"; C_BLUE="\033[1;34m"; C_GREEN="\033[1;32m"; C_RED="\033[1;31m"

log() { echo -e "${C_BLUE}[*]${C_RESET} $*" | tee -a "$LOG_FILE"; }
ok()  { echo -e "${C_GREEN}[OK]${C_RESET} $*" | tee -a "$LOG_FILE"; }
die() { echo -e "${C_RED}[FAIL]${C_RESET} $*" | tee -a "$LOG_FILE"; exit 1; }

usage() {
  echo "Usage: zonclave update"
  echo "  Pull the latest code and redeploy the panel (migrate, clear caches)."
}

cmd_update() {
  [ "$(id -u)" -eq 0 ] || die "Run as root: sudo zonclave update"
  [ -d "${REPO_DIR}/.git" ] || die "${REPO_DIR} is not a git checkout."
  [ -d "$PANEL_SOURCE" ] || die "Panel source not found at ${PANEL_SOURCE}."
  [ -f "${PANEL_DIR}/.env" ] || die "No existing deployment at ${PANEL_DIR} (.env missing) - run installer/install-ubuntu22.04.sh first."

  : >"$LOG_FILE"

  # .git is owned by the checkout's normal user (CLAUDE.md Section 26.4),
  # not root or www-data. Pulling as root here would flip that ownership
  # and break the next manual `git pull`, so pull as whoever already owns it.
  local repo_owner
  repo_owner="$(stat -c '%U' "${REPO_DIR}/.git")"

  log "Pulling latest code in ${REPO_DIR} (as ${repo_owner})"
  sudo -u "$repo_owner" git -C "$REPO_DIR" pull --ff-only >>"$LOG_FILE" 2>&1 \
    || die "git pull failed. See ${LOG_FILE}."
  ok "Code at $(sudo -u "$repo_owner" git -C "$REPO_DIR" rev-parse --short HEAD)"

  log "Syncing panel source to ${PANEL_DIR}"
  # cp -a would also overwrite the served .env with whatever .env (if any)
  # sits in the checkout's panel/ dir - a stale dev leftover is exactly
  # what corrupted production config in the incident behind CLAUDE.md
  # Section 26.7. Back up the real .env and restore it after the copy so
  # this can never happen again, regardless of what the checkout contains.
  local env_backup
  env_backup="$(mktemp)"
  cp "${PANEL_DIR}/.env" "$env_backup"
  cp -a "${PANEL_SOURCE}/." "$PANEL_DIR/"
  cp "$env_backup" "${PANEL_DIR}/.env"
  rm -f "$env_backup"

  cd "$PANEL_DIR"

  log "Installing PHP dependencies"
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader \
    >>"$LOG_FILE" 2>&1 || die "composer install failed. See ${LOG_FILE}."

  log "Running database migrations"
  php artisan migrate --force >>"$LOG_FILE" 2>&1 \
    || die "Migration failed. See ${LOG_FILE}."

  php artisan filament:assets >>"$LOG_FILE" 2>&1 || true
  php artisan storage:link >>"$LOG_FILE" 2>&1 || true

  log "Clearing and rebuilding caches"
  php artisan optimize:clear >>"$LOG_FILE" 2>&1
  php artisan config:cache   >>"$LOG_FILE" 2>&1
  php artisan route:cache    >>"$LOG_FILE" 2>&1
  php artisan view:cache     >>"$LOG_FILE" 2>&1

  chown -R www-data:www-data "$PANEL_DIR"
  find "$PANEL_DIR/storage" "$PANEL_DIR/bootstrap/cache" -type d -exec chmod 775 {} \; 2>/dev/null || true

  log "Restarting services"
  systemctl restart php8.3-fpm >>"$LOG_FILE" 2>&1 || die "php8.3-fpm restart failed. See ${LOG_FILE}."
  systemctl reload nginx >>"$LOG_FILE" 2>&1 || die "nginx reload failed. See ${LOG_FILE}."

  ok "Zonclave updated and redeployed. Log: ${LOG_FILE}"
}

case "${1:-}" in
  update) cmd_update ;;
  *) usage; exit 1 ;;
esac
