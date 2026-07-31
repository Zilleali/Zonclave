<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdminLogAction;
use App\Models\RadiusAccounting;
use App\Repositories\AdminLogRepository;

// The only write path for radacct rows (client request 2026-07-31) - a
// deliberate, narrow exception to the read-only relationship CLAUDE.md
// Section 16.6/23.1 otherwise establishes between the panel and FreeRADIUS's
// own accounting table. FreeRADIUS remains the sole writer of session data
// itself: this service only ever DELETES a row an admin has identified as
// stale/irrelevant clutter, never creates or edits one, and never touches
// radcheck/radreply - the actual RADIUS write boundary Section 23.1 exists
// to protect is unaffected by this.
class RadiusAccountingService
{
    public function __construct(
        private readonly AdminLogRepository $auditLog,
    ) {}

    public function delete(RadiusAccounting $session, ?string $adminUser): void
    {
        $detail = sprintf(
            '%s (%s), connected %s',
            $session->username ?? 'unknown user',
            $session->callingstationid ?? 'unknown device',
            $session->acctstarttime?->toDateTimeString() ?? 'unknown time',
        );
        $targetPpskId = $session->ppskGroup?->id;

        $session->delete();

        $this->auditLog->log(AdminLogAction::SessionDeleted, $adminUser, $targetPpskId, $detail);
    }
}
