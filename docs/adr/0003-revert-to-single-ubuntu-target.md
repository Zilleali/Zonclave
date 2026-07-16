# ADR 0003: Revert to a single installer target (Ubuntu 22.04 LTS)

Date: 2026-07-16
Status: accepted

## Context

ADR 0002, decided earlier the same day, promoted `installer/install-ubuntu22.04.sh`
from a private local variant to an officially tracked installer, supported
alongside the original `installer/install.sh` (Ubuntu 24.04). Hours later,
running `install-ubuntu22.04.sh` against the actual Kelder VM surfaced real
problems - the script does not install cleanly there yet. Maintaining two
installer scripts in lockstep is real ongoing work (ADR 0002's own stated
consequence), and that cost is not justified while the one target that
actually matters - the live Kelder VM - is not yet passing on its own
script. Splitting attention across two targets before either is solid is
the wrong sequencing.

## Decision

- Ubuntu Server 22.04 LTS is the single officially supported installer
  target again, via `installer/install-ubuntu22.04.sh`. This matches
  reality: it is what Kelder runs and the only target with a live VM to
  test against (Section 3.3, Section 26).
- `installer/install.sh` (Ubuntu 24.04) is removed from the repository
  rather than kept deprecated-in-place, since there is no live target to
  test it against and an unmaintained, untested installer script sitting
  in the repo is a worse trap than not having it - it would look
  supported without being verified. Reintroducing 24.04 support is a
  future decision, not a default; it starts from git history
  (`git log -- installer/install.sh`) or this ADR's context, not from a
  stale file. The OS-detection machinery ADR 0002 added to
  `run.sh`/`package.sh` is removed along with it.
- Fixing whatever is actually broken in `install-ubuntu22.04.sh` on the
  real Kelder VM is the immediate next step (CLAUDE.md Section 20), ahead
  of any further installer scope changes.

## Consequences

`installer/run.sh` and `installer/package.sh` return to the simpler
single-script shape they had before ADR 0002 - no `/etc/os-release`
branching, no packaging two scripts. Documentation (README.md,
`docs/installation-guide.md`, `docs/commands-reference.md`, the public
docs pages, CLAUDE.md Section 24.4) is updated to present
`install-ubuntu22.04.sh` as "the installer" rather than one of two
choices. A future decision to re-add 24.04 (or any other version) support
should re-read ADR 0002 first - the mechanism it describes is still
correct, it was just premature here.
