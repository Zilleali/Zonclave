# ADR 0001: The registry-to-RADIUS path in the panel

Date: 2026-07-13
Status: accepted

## Context

CLAUDE.md Section 23.1 requires that all writes to radcheck/radreply flow
through one service method, transactionally, derived only from ppsk_groups.
The panel skeleton (Laravel 12 + Filament 5) had to fix where that path
lives and how the layers around it are shaped.

## Decision

- `PpskService::projectToRadius()` is the single registry-to-RADIUS path.
  It reads the stored ppsk_groups row, decrypts the PSK at that point only,
  derives rows via the pure `App\Domain\RadiusDerivation`, and hands them to
  `App\Repositories\RadiusRepository::replaceFor()`, the only class touching
  radcheck/radreply. No Eloquent models exist for RADIUS tables on purpose.
- Projection is replace-based and idempotent: delete the username's rows,
  insert exactly the derived set. A disabled/provisioning/error group
  derives an empty set, so disable genuinely revokes authentication.
- Every mutation (create, update, enable, disable, delete, regenerate)
  opens one transaction in PpskService covering the registry write, the
  projection, and the admin_log entry.
- PSKs are encrypted at rest with Laravel Crypt (APP_KEY) in
  ppsk_groups.password_hash, per Section 14. The column name is kept from
  the Section 7 schema even though the content is reversible encryption,
  not a hash, to stay in lockstep with the installer-created table.
- `App\Domain\Psk` is the Section 14 validation boundary (8 to 63 chars);
  `App\Domain\PskGenerator` mirrors gen_psk() in installer/install.sh.
- Filament pages/tables call PpskService only (handleRecordCreation,
  handleRecordUpdate, DeleteAction->using(), status toggle action). Bulk
  actions are removed so no default Eloquent path can bypass the boundary.
- Registry and RADIUS migrations are guarded with Schema::hasTable() so
  they are no-ops on the production node (tables created by the installer)
  but bootstrap dev/test databases identically.

## Consequences

Phase 2 OPNsense automation extends PpskService methods without touching
Filament or the boundary. Any new mutation must call projectToRadius()
inside its transaction; adding a second RadiusRepository caller is a
review-blocking violation.
