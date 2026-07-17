#!/usr/bin/env bash
#
# Zonclave - Installer Packaging Tool (CLAUDE.md Section 24.5)
#
# Builds the encrypted, single-file distributable from install-ubuntu22.04.sh
# (the one officially supported installer, ADR 0003) and the panel source:
# an AES-256 encrypted payload plus a tiny decrypt-and-run stub (run.sh).
# This script is run by the developer only, on a trusted machine; it is
# never itself distributed to the client. What ships is only this script's
# output: zonclave-installer.enc and run.sh.
#
# Delivery model (CLAUDE.md Section 20, decided): both client-run (hand
# them run.sh + the .enc file, plus the passphrase over a separate secure
# channel) and developer-run (copy both files over SSH and run.sh yourself
# on the target). Same two output files serve both.
#
# Honest limitation (Section 24.5): this is tamper-friction and casual
# protection of the install method, not a secrecy guarantee. Anyone with
# root on the target host can recover the decrypted installer at runtime
# regardless of encryption. Never send the passphrase in the same email,
# chat thread, or channel as the .enc file or run.sh.
#
# Usage: bash package.sh [--passphrase PASSPHRASE] [--out DIR]
#
#   --passphrase   Encryption passphrase. If omitted, one is generated and
#                   printed once - write it down immediately, it is not
#                   saved anywhere by this script.
#   --out          Output directory (default: installer/dist).
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR
readonly REPO_ROOT="${SCRIPT_DIR}/.."

readonly C_RESET="\033[0m"; readonly C_BLUE="\033[1;34m"
readonly C_GREEN="\033[1;32m"; readonly C_YELLOW="\033[1;33m"; readonly C_RED="\033[1;31m"
log()  { echo -e "${C_BLUE}[*]${C_RESET} $*"; }
ok()   { echo -e "${C_GREEN}[OK]${C_RESET} $*"; }
warn() { echo -e "${C_YELLOW}[!]${C_RESET} $*"; }
die()  { echo -e "${C_RED}[X]${C_RESET} $*" >&2; exit 1; }

OUT_DIR="${SCRIPT_DIR}/dist"
PASSPHRASE=""

while [ $# -gt 0 ]; do
  case "$1" in
    --passphrase) PASSPHRASE="${2:-}"; shift 2 ;;
    --out) OUT_DIR="${2:-}"; shift 2 ;;
    *) die "Unknown argument: $1" ;;
  esac
done

command -v openssl >/dev/null 2>&1 || die "openssl is required."
command -v tar >/dev/null 2>&1 || die "tar is required."
command -v git >/dev/null 2>&1 || die "git is required."
git -C "$REPO_ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1 \
  || die "Not a git repository: ${REPO_ROOT}."

# Packages what is committed, not the working tree, so a stray uncommitted
# edit can't silently ship. Warn (don't block) if HEAD isn't what's on disk.
if ! git -C "$REPO_ROOT" diff --quiet -- installer/install-ubuntu22.04.sh panel/ 2>/dev/null; then
  warn "Uncommitted changes exist under installer/install-ubuntu22.04.sh or panel/."
  warn "Packaging the last COMMITTED state (HEAD), not your working tree."
fi

if [ -z "$PASSPHRASE" ]; then
  PASSPHRASE="$(openssl rand -base64 24 | tr -d '/+=' | head -c 28)"
  warn "No --passphrase given; generated one. This is shown ONCE below."
fi

mkdir -p "$OUT_DIR"
STAGE_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGE_DIR"' EXIT

log "Staging installer and panel source from git HEAD..."
mkdir -p "${STAGE_DIR}/zonclave"

# git archive exports exactly what's tracked at HEAD: anything gitignored
# (.env, vendor/, node_modules/, a local database.sqlite, IDE folders) was
# never tracked in the first place, so there is no manual exclude list to
# keep in sync with panel/.gitignore, and no dependency on rsync (not
# installed by default on Windows Git Bash, where this may run).
git -C "$REPO_ROOT" archive HEAD -- installer/install-ubuntu22.04.sh \
  | tar -x -C "${STAGE_DIR}/zonclave" --strip-components=1
git -C "$REPO_ROOT" archive HEAD -- panel \
  | tar -x -C "${STAGE_DIR}/zonclave"
git -C "$REPO_ROOT" archive HEAD -- scripts/zonclave-update.sh \
  | tar -x -C "${STAGE_DIR}/zonclave" --strip-components=1

[ -f "${STAGE_DIR}/zonclave/install-ubuntu22.04.sh" ] || die "install-ubuntu22.04.sh missing from HEAD; nothing to package."
[ -d "${STAGE_DIR}/zonclave/panel" ] || die "panel/ missing from HEAD; nothing to package."
[ -f "${STAGE_DIR}/zonclave/zonclave-update.sh" ] || warn "scripts/zonclave-update.sh missing from HEAD; packaging without the 'zonclave update' CLI."

if [ -f "${STAGE_DIR}/zonclave/panel/.env" ]; then
  die "Refusing to package: a tracked .env exists in panel/. Aborting for safety."
fi

log "Building tarball..."
tar -czf "${STAGE_DIR}/payload.tar.gz" -C "$STAGE_DIR" zonclave

log "Encrypting payload (AES-256, PBKDF2)..."
openssl enc -aes-256-cbc -pbkdf2 -iter 100000 -salt \
  -in "${STAGE_DIR}/payload.tar.gz" \
  -out "${OUT_DIR}/zonclave-installer.enc" \
  -pass "pass:${PASSPHRASE}"

log "Writing decrypt-and-run stub..."
cp "${SCRIPT_DIR}/run.sh" "${OUT_DIR}/run.sh"
chmod +x "${OUT_DIR}/run.sh"

ok "Package built in ${OUT_DIR}:"
echo "    zonclave-installer.enc"
echo "    run.sh"
echo
warn "Passphrase (shown once, not saved anywhere): ${PASSPHRASE}"
echo
echo "Deliver run.sh + zonclave-installer.enc together (email, USB, SCP)."
echo "Deliver the passphrase separately, over a different channel (voice"
echo "call, SMS, a second message thread) - never alongside the files."
echo
echo "Client-run: sudo bash run.sh"
echo "Developer-run (over SSH): scp both files to the target, then"
echo "  ssh root@<target> 'bash run.sh'"
