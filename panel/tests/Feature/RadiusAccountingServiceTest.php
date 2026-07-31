<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminLogAction;
use App\Models\AdminLog;
use App\Models\PpskGroup;
use App\Models\RadiusAccounting;
use App\Services\RadiusAccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// RadiusAccountingService (CLAUDE.md Section 16.6, client request
// 2026-07-31) - the only write path for radacct rows. A deliberate,
// narrow exception to the panel's otherwise read-only relationship with
// FreeRADIUS's own accounting table: delete only, never create or edit,
// and never touches radcheck/radreply (Section 23.1's actual RADIUS write
// boundary is unaffected).
class RadiusAccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RadiusAccountingService
    {
        return app(RadiusAccountingService::class);
    }

    private function makeSession(array $overrides = []): RadiusAccounting
    {
        return RadiusAccounting::query()->create(array_merge([
            'acctsessionid' => 'sess-'.bin2hex(random_bytes(4)),
            'acctuniqueid' => 'uniq-'.bin2hex(random_bytes(8)),
            'username' => 'ppsk_group001',
            'callingstationid' => 'AA:BB:CC:DD:EE:FF',
            'acctstarttime' => now()->subHour(),
        ], $overrides));
    }

    public function test_delete_removes_the_row(): void
    {
        $session = $this->makeSession();

        $this->service()->delete($session, 'admin@test');

        $this->assertSame(0, RadiusAccounting::query()->count());
    }

    public function test_delete_logs_an_admin_log_entry_with_the_joined_ppsk_as_target(): void
    {
        $group = PpskGroup::factory()->create(['radius_username' => 'ppsk_group001']);
        $session = $this->makeSession();

        $this->service()->delete($session, 'admin@test');

        $log = AdminLog::query()->latest('ts')->first();
        $this->assertSame(AdminLogAction::SessionDeleted->value, $log->action);
        $this->assertSame('admin@test', $log->admin_user);
        $this->assertSame($group->id, $log->target_ppsk_id);
        $this->assertStringContainsString('ppsk_group001', (string) $log->detail);
        $this->assertStringContainsString('AA:BB:CC:DD:EE:FF', (string) $log->detail);
    }

    public function test_delete_logs_gracefully_when_no_ppsk_group_matches(): void
    {
        // The session's username doesn't correspond to any current
        // ppsk_groups row (e.g. the credential was renamed/deleted since -
        // Section 6's editable-username decision) - deleting the orphaned
        // session must not fail just because it can't resolve a target.
        $session = $this->makeSession(['username' => 'no_such_ppsk']);

        $this->service()->delete($session, 'admin@test');

        $log = AdminLog::query()->latest('ts')->first();
        $this->assertNull($log->target_ppsk_id);
        $this->assertStringContainsString('no_such_ppsk', (string) $log->detail);
    }
}
