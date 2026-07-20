# ADR 0004: Render docs/*.md live on the public pages instead of hand-copying

Date: 2026-07-21
Status: accepted

## Context

The public `/docs` pages (`docs/installation-guide.md`, `docs/commands-reference.md`,
`docs/opnsense-configuration.md`) started as hand-transcribed HTML in
Blade views, deliberately avoiding a markdown-parser dependency at the
time (see the original landing-page work). By this point that hand-copy
had already drifted at least once - a doc got updated, the public page
did not - and the client asked for the sync to be automatic rather than
remembered.

## Decision

- Add `league/commonmark` (already present transitively via Laravel/
  Filament, now an explicit direct dependency) and render the three
  files above live, via `App\Support\DocsMarkdownRenderer`, from a fixed
  allowlist of slug to filename. No route parameter ever becomes a file
  path - path traversal is not a question that needs answering here, the
  slug is looked up in a constant array or the request 404s.
- `docs/site-configuration.md` and `docs/troubleshooting.md` do not
  exist - those two public pages are hand-written directly (real
  Sancover site detail, no general-purpose markdown source to render
  from) and are explicitly outside this mechanism.
- The source docs cross-reference `CLAUDE.md`, `docs/runbook/**`,
  `README.md`, and GitHub in ordinary prose, which is correct for a
  developer reading the repo and must not become a live, clickable link
  once published. The renderer rewrites same-directory links between the
  three public docs to their route and strips (unlinks, keeps the
  visible text) any link elsewhere - `PublicPagesTest` asserts no `href`
  on any public page ever points at `github.com` or `runbook/`.
- **Installer contract change**: `docs/` is not part of `panel/`, so
  `deploy_panel()`'s existing `cp -a` never reached it. A new
  `deploy_docs()` stage in `install-ubuntu22.04.sh` copies `docs/` to
  `/opt/docs` (a sibling of `/opt/zonclave`, mirroring the same sibling
  relationship `docs/` and `panel/` already have in the git checkout) so
  `config('zonclave.docs_path')`'s default (`base_path('../docs')`)
  resolves identically in local dev and production. `zonclave update`
  does the same sync on every code update. `package.sh` now bundles
  `docs/` into the encrypted delivery too.

## Consequences

One source of truth for these three documents; they cannot drift again
by omission. The cost is a real dependency addition (reversing the
earlier decision to avoid one) and a wider installer/update-script
surface - two new things that can fail (a missing `docs/` directory
degrades to those three pages 500ing, not a hard install failure,
`deploy_docs()` warns rather than dies). `docs/site-configuration.md`
and `docs/troubleshooting.md` remain a manual-sync exception to this
ADR by design, not an oversight - revisit only if those ever gain a
general-purpose markdown source worth rendering live too.
