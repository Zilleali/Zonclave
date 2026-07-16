#!/usr/bin/env bash
#
# Zonclave - Encrypted Installer Launcher (CLAUDE.md Section 24.5)
#
# Decrypts and runs the Zonclave installer. Requires the passphrase
# provided separately by Developer Zon - it is never included in this
# file or in zonclave-installer.enc, and is not sent over the same
# channel as either.
#
# Honest limitation: this is tamper-friction and casual protection of the
# install method, not a secrecy guarantee. Anyone with root on this host
# can recover the decrypted installer at runtime regardless of encryption.
# See CLAUDE.md Section 24.5.
#
# Usage: sudo bash run.sh [-- INSTALL_SH_ARGS...]
#   Payload path defaults to zonclave-installer.enc next to this script;
#   override with ZONCLAVE_PAYLOAD=/path/to/file.enc.
#   Picks install.sh or install-ubuntu22.04.sh automatically based on this
#   host's /etc/os-release (CLAUDE.md Section 24.4: both are officially
#   supported). Any arguments after -- are forwarded to that script, e.g.:
#     sudo bash run.sh -- --config installer.conf
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR
PAYLOAD="${ZONCLAVE_PAYLOAD:-${SCRIPT_DIR}/zonclave-installer.enc}"
readonly PAYLOAD

[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo bash run.sh)." >&2; exit 1; }
[ -f "$PAYLOAD" ] || { echo "Payload not found: ${PAYLOAD}" >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "openssl is required." >&2; exit 1; }
command -v tar >/dev/null 2>&1 || { echo "tar is required." >&2; exit 1; }

# Everything after a literal "--" forwards to install.sh; drop it from $@
# before we consume our own (currently nonexistent) options.
INSTALL_ARGS=()
if [ "${1:-}" = "--" ]; then
  shift
  INSTALL_ARGS=("$@")
fi

WORKDIR="$(mktemp -d)"
trap 'rm -rf "$WORKDIR"' EXIT

echo "Zonclave installer - enter the passphrase provided separately by Developer Zon."
read -r -s -p "Passphrase: " PASSPHRASE
echo

if ! openssl enc -d -aes-256-cbc -pbkdf2 -iter 100000 -salt \
    -in "$PAYLOAD" -pass "pass:${PASSPHRASE}" 2>/dev/null \
    | tar -xzf - -C "$WORKDIR"; then
  unset PASSPHRASE
  echo "Decryption failed. Check the passphrase and try again." >&2
  exit 1
fi
unset PASSPHRASE

# Pick the installer matching this host's Ubuntu version. Both are shipped
# in every package built by package.sh, so the same .enc file works on
# either officially supported target (CLAUDE.md Section 24.4).
TARGET_SCRIPT=""
if [ -r /etc/os-release ]; then
  . /etc/os-release
  case "${ID:-}:${VERSION_ID:-}" in
    ubuntu:24.04) TARGET_SCRIPT="install.sh" ;;
    ubuntu:22.04) TARGET_SCRIPT="install-ubuntu22.04.sh" ;;
  esac
fi

if [ -z "$TARGET_SCRIPT" ]; then
  echo "Unsupported or undetected OS: ${PRETTY_NAME:-unknown}." >&2
  echo "Zonclave supports Ubuntu Server 24.04 LTS or 22.04 LTS only." >&2
  exit 1
fi

[ -f "${WORKDIR}/zonclave/${TARGET_SCRIPT}" ] || {
  echo "Decrypted payload is missing ${TARGET_SCRIPT}. Corrupt or wrong payload file?" >&2
  exit 1
}

cd "${WORKDIR}/zonclave"
exec bash "$TARGET_SCRIPT" "${INSTALL_ARGS[@]}"
