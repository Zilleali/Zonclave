#!/usr/bin/env bash
#
# Zonclave - seed 2 test PPSK groups
# CLAUDE.md Sections 8.2, 14, 23.1
#
# Inserts two throwaway PPSK groups into ppsk_groups and derives their
# radcheck/radreply rows in the same transaction per group, exactly as the
# panel's registry-to-RADIUS path will. Idempotent by radius_username: safe
# to re-run, existing groups are left untouched.
#
# This mirrors seed_test_groups() in installer/install.sh. If the derivation
# logic changes, change it in both places (or better, extract to one place
# once the panel exists).
#
# Usage:
#   sudo bash seed_test_ppsk_groups.sh              # local psql as postgres
#   DB_NAME=ppsk PSQL="psql -h 127.0.0.1 -U ppsk" bash seed_test_ppsk_groups.sh
set -euo pipefail

DB_NAME="${DB_NAME:-ppsk}"
PSQL="${PSQL:-sudo -u postgres psql}"

# PSK generator per CLAUDE.md Section 14: 24 chars, A-Za-z0-9, ambiguous
# characters (0 O 1 l I) excluded. Identical to gen_psk in install.sh.
gen_psk() {
  local charset='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
  local out=""
  while [ "${#out}" -lt 24 ]; do
    out+=$(head -c 64 /dev/urandom | LC_ALL=C tr -dc "$charset" | head -c 24)
  done
  echo "${out:0:24}"
}

SEED_PSK_1="$(gen_psk)"
SEED_PSK_2="$(gen_psk)"

# NOTE: for these THROWAWAY test rows the cleartext PSK is stored in the
# password_hash column too. Real entries created via the panel are encrypted
# at rest per CLAUDE.md Section 14; the panel owns that encryption.
rows=(
  "ppsk_group001|VLAN300_TEST_A|300|10.30.0.0/24|WG_VLAN300|GW_WG_VLAN300|${SEED_PSK_1}"
  "ppsk_group002|VLAN301_TEST_B|301|10.30.1.0/24|WG_VLAN301|GW_WG_VLAN301|${SEED_PSK_2}"
)

for r in "${rows[@]}"; do
  IFS='|' read -r user label vlan subnet wgif wggw psk <<<"$r"
  # Registry row and derived RADIUS rows commit or roll back together
  # (transactional projection, Section 23.1).
  ${PSQL} -d "${DB_NAME}" -v ON_ERROR_STOP=1 <<SQL
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
  echo "[OK] seeded ${user} (${label})"
done

echo
echo "Seed PPSK #1: user ppsk_group001  VLAN 300  psk ${SEED_PSK_1}"
echo "Seed PPSK #2: user ppsk_group002  VLAN 301  psk ${SEED_PSK_2}"
echo "PSKs shown once only. Verify with:"
echo "  radtest ppsk_group001 '<psk>' 127.0.0.1 0 '<radius-secret>'"
