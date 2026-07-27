# Developer Guide

An architecture and contribution overview for anyone picking up this codebase - what it's for, how it's laid out, and the conventions it follows. This is general and public; it deliberately does not cover any one client's actual deployment details. For that, and for the full specification behind every decision this project has made, see `CLAUDE.md` at the repo root - that file is the single source of truth for this project's own history and reasoning, but it is never published, since it contains real client and site information. This guide is what a new developer can read instead, before ever getting access to that file.

## 1. What this project is

Zonclave maps a single Wi-Fi SSID to many independent PPSK (pre-shared key) credentials. Each credential is tied to a dedicated VLAN, and each VLAN is policy-routed through its own WireGuard tunnel to a distinct residential exit IP. Two layers, kept strictly separate:

- **FreeRADIUS** does authentication and VLAN assignment. Nothing else.
- **OPNsense** does routing, firewalling, and VPN policy. Nothing else.

A Laravel + Filament admin panel (`panel/`) is the only supported way to manage credentials - nothing writes to the RADIUS tables directly outside of it.

## 2. Layered architecture

Every feature in `panel/app/` follows the same shape, regardless of which layer of the stack you're touching:

```text
Routes / Filament Resources   (HTTP endpoints, forms, tables)
        v
Services                      (business logic - "do the thing," one transaction)
        v
Repositories                  (the only layer that talks to the database)
        v
Database
```

Why this matters: a service method like `PpskService::create()` does a handful of database writes today. In a later phase, that *same* method gains extra steps (e.g. calling an external provisioning API) - the routes and Filament resources above it don't need to change at all, because they never talked to the database directly in the first place. Skipping this layering to save time on a "simple" feature is what turns the next feature into a rewrite instead of an addition.

## 3. The registry-to-external-system write boundary

This project has one authoritative table that everything else is derived from (in Zonclave's case, `ppsk_groups`) and one or more external systems that must always match it (FreeRADIUS's `radcheck`/`radreply` tables). The rule, regardless of what the actual tables are called in your fork or adaptation of this pattern:

- The authoritative table is the source of truth. The external system's rows are a **transactional projection** of it, never maintained independently.
- Exactly **one** method in the whole codebase is allowed to write to the external system's tables. Every create/update/enable/disable/delete path funnels through it.
- That projection is wrapped in a database transaction alongside the authoritative write - it either fully succeeds or fully rolls back. No partial state.
- Nothing computes a security-relevant value (like a VLAN assignment) from client-supplied input at the point of use - it's always read back from the authoritative table.

If you're adding a feature that needs to write to `radcheck`/`radreply`, don't. Go through the existing service method, or extend it - don't add a second write path.

Read-only relationships to an external system are simpler and don't need this ceremony - see `App\Models\RadiusAccounting`, which only ever reads FreeRADIUS's own `radacct` accounting table and never writes to it.

## 4. Coding standards

- `declare(strict_types=1);` at the top of every PHP file.
- Native PHP enums for any fixed set of values (status, action types, etc.) - not string constants, not a `match` scattered across multiple files.
- Three quality gates, all required to pass clean before any commit:

  ```sh
  php artisan test
  vendor/bin/pint --test
  vendor/bin/phpstan analyse
  ```

  Auto-fix Pint style issues with `vendor/bin/pint` (no `--test` flag).
- Comments explain *why*, not *what* - a well-named method already says what it does. A comment earns its place by capturing a non-obvious constraint, a past incident, or a decision that would otherwise get silently re-litigated.
- No em dashes or en dashes anywhere in code, comments, docs, or generated output - hyphens only.

## 5. Adding a new read-only admin page - a worked example

The Sessions and Tunnel Egress IPs pages are a good template for adding a new list-style Filament resource, since they're the newest and simplest (read-only, no create/edit ceremony beyond a single modal field). The pattern:

```text
app/Filament/Resources/YourThing/
  YourThingResource.php        # model, icon, canCreate(), getPages()
  Pages/ListYourThings.php      # extends ListRecords, usually empty getHeaderActions()
  Tables/YourThingsTable.php    # static configure(Table): Table - columns, filters, actions
```

- `discoverResources()` in `AdminPanelProvider` finds it automatically - no manual registration needed once the files exist in the right namespace.
- For a resource whose "rows" are a small, fixed set rather than a free-form list (see `TunnelEgressIpResource`, one row per provisioned VLAN), override `getEloquentQuery()` to lazily create any missing rows before the query runs, rather than depending on a seeder or migration to keep the set in sync as configuration changes.
- Read-only resources set `canCreate(): false`, register only an `index` page, and leave `recordActions([])`/`toolbarActions([])` empty unless a specific action (like an `EditAction` on a single field) is genuinely needed.
- Never add `->poll()` to a table. Every list in this panel loads data on demand only, with no timed auto-refresh - a deliberate choice to avoid surprising background reloads while an admin is mid-action.

## 6. Testing conventions

- **Unit tests** (`tests/Unit/`): pure logic, no database - value objects, enums, derivation functions.
- **Feature tests** (`tests/Feature/`): real test database (`RefreshDatabase` trait), exercising the actual service/repository/Filament layer together. This project favors integration-style feature tests over heavily mocked unit tests for anything that touches the database, since the two most damaging categories of bug (partial writes, drift between an authoritative table and its projection) can only be caught by exercising a real transaction.
- Filament resources are tested through Livewire's testing helpers:

  ```php
  Livewire::test(ListYourThings::class)
      ->assertCanSeeTableRecords([$expected], inOrder: true)
      ->set('tableFilters', ['some_filter' => ['value' => 'x']])
      ->assertCanNotSeeTableRecords([$excluded]);
  ```

- A resource that should have no create/edit/delete route gets an explicit test asserting that (`assertFalse(YourResource::canCreate())`), not just an absence of a button in a screenshot.

## 7. Public site and documentation

Everything under `panel/routes/web.php` outside of `/admin` is public and unauthenticated by design - the marketing landing page and the `/docs` pages. Three of those doc pages render their content live from this repo's own `docs/*.md` files (via `App\Support\DocsMarkdownRenderer`), so the published page and the source markdown can never drift apart - there's exactly one place to edit that content. Only files explicitly listed in that renderer's allow-list are ever reachable this way; anything containing real deployment specifics (an internal runbook, the full project specification) is either excluded entirely or hand-written as its own page with no markdown source, never added to that list.

If you add a new public doc, the pattern is: write `docs/your-doc.md`, add one line to the renderer's allow-list, and add one route reusing the existing generic `docs.markdown-page` view - no new Blade template needed.

## 8. Where to go next

- `CLAUDE.md` (not published) - the full specification and decision history for this project's actual deployment. Read this in full before making an architectural change, if you have access to it.
- [installation-guide.md](installation-guide.md) - how to set up, install, and run this.
- [user-guide.md](user-guide.md) - how to actually use the panel day to day.
- [commands-reference.md](commands-reference.md) - every command, no explanation.
- [changelog.md](changelog.md) - what's shipped, in order.
