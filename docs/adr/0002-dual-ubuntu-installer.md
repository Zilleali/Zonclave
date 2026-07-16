# ADR 0002: Two officially supported installer targets (Ubuntu 24.04 and 22.04)

Date: 2026-07-16
Status: accepted

## Context

CLAUDE.md Section 24.4 originally pinned Ubuntu Server 24.04 LTS as the
single supported installer target, specifically to avoid the reliability
risk of chasing multi-distro support in Phase 1. In practice, the Office
SancoMedia Kelder deployment ended up running Ubuntu 22.04 LTS inside a
Hyper-V VM on a Windows 11 host (Section 3.3, Section 26) - not bare metal
24.04 as planned. A private local variant, `installer/install-ubuntu22.04.sh`,
was written to install correctly on 22.04 (Section 24.4's PHP 8.3 assumption
does not hold there; 22.04's base repos ship 8.1, so the `ondrej/php` PPA is
needed instead), but it was gitignored rather than tracked, leaving the
script actually running production untested by CI, undocumented in the
install guides, and absent from the encrypted delivery path.

## Decision

- Two Ubuntu versions are officially supported, not one: `installer/install.sh`
  for 24.04, `installer/install-ubuntu22.04.sh` for 22.04. Both are tracked,
  documented, and functionally identical except for the PHP 8.3 install
  method (base repos vs the `ondrej/php` PPA). No third version is added on
  the strength of this decision; the reliability reasoning behind pinning to
  a small, known set of targets still holds, it now covers two, not one.
- The host machine is explicitly out of scope for OS support. The installer
  only ever provisions the Ubuntu guest; Windows (Hyper-V, VirtualBox,
  VMware), Linux (KVM, VirtualBox), macOS (VMware Fusion, Parallels, UTM),
  or bare metal are all equally valid ways to get to that guest. No
  host-OS-detection branches are added to either script.
- `installer/package.sh` archives both scripts into the encrypted payload;
  `installer/run.sh` reads `/etc/os-release` on the target and runs whichever
  script matches (`ubuntu:24.04` -> `install.sh`, `ubuntu:22.04` ->
  `install-ubuntu22.04.sh`), refusing to run on anything else. A single
  `.enc` file now works on either target without the operator needing to
  know in advance which one a given client's host will end up running.
- `installer/hyperv-ubuntu22.04-setup.md` (the Hyper-V-on-Windows walkthrough
  that pairs with the 22.04 script) is published alongside it.

## Consequences

Any future change to `install_db()`, `install_freeradius()`, `deploy_panel()`,
or `configure_services()` must be made in both scripts, or the two installers
drift out of lockstep - there is no shared library between them by design
(each ships as one self-contained blob per Section 24.4). A third Ubuntu
LTS version would need the same treatment (write it, track it, teach
`run.sh` its `VERSION_ID`) rather than growing the existing two scripts with
conditionals.
