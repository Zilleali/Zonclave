# db/ - schema and seed scripts

Reference SQL for the Zonclave database, per CLAUDE.md Sections 7, 8, and 17.

## Contents

- `schema/01_radius.sql` - standard FreeRADIUS `rlm_sql` tables (radcheck, radreply, groups, radacct, radpostauth, nas). Mirrors the schema shipped with the `freeradius-postgresql` package.
- `schema/02_registry.sql` - `ppsk_groups` (the authoritative registry) and `admin_log`, with indexes.
- `seed/seed_test_ppsk_groups.sh` - seeds 2 throwaway test PPSK groups (VLAN 300 and 301) and derives their radcheck/radreply rows transactionally, the same way the panel's registry-to-RADIUS path will.

## Relationship to the installer

On the production node, `installer/install.sh` is the execution path: it loads the package-shipped FreeRADIUS schema, creates the registry tables, and seeds the same 2 test groups. These files exist so a dev or test database can be bootstrapped identically without running the installer:

```sh
createdb ppsk
psql -d ppsk -f schema/01_radius.sql
psql -d ppsk -f schema/02_registry.sql
PSQL="psql" DB_NAME=ppsk bash seed/seed_test_ppsk_groups.sh
```

If the table definitions or seed derivation change, change them here and in `install.sh` together. They must stay in lockstep.

## Boundary rules (Section 23.1)

`ppsk_groups` is the source of truth. `radcheck`/`radreply` are a transactional projection of it and are never edited directly. Outside of these bootstrap scripts, all RADIUS-table writes go through the panel's single registry-to-RADIUS service method.
