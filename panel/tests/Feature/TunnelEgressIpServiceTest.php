<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminLogAction;
use App\Models\AdminLog;
use App\Models\TunnelEgressIp;
use App\Services\TunnelEgressIpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// TunnelEgressIpService (CLAUDE.md Section 16.6) - the only write path for
// tunnel_egress_ips. This is a manually-confirmed reference value, not a
// live measurement (no OPNsense API integration exists yet, Section 19).
class TunnelEgressIpServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TunnelEgressIpService
    {
        return app(TunnelEgressIpService::class);
    }

    public function test_update_creates_a_row_when_none_exists(): void
    {
        $record = $this->service()->update(300, '46.151.227.182', 'admin@test');

        $this->assertSame(300, $record->vlan_id);
        $this->assertSame('46.151.227.182', $record->egress_ip);
        $this->assertSame('admin@test', $record->updated_by);
        $this->assertNotNull($record->checked_at);
    }

    public function test_update_overwrites_an_existing_row_for_the_same_vlan(): void
    {
        $this->service()->update(300, '46.151.227.182', 'admin@test');
        $record = $this->service()->update(300, '46.151.227.213', 'admin2@test');

        $this->assertSame(1, TunnelEgressIp::query()->where('vlan_id', 300)->count());
        $this->assertSame('46.151.227.213', $record->egress_ip);
        $this->assertSame('admin2@test', $record->updated_by);
    }

    public function test_update_logs_an_admin_log_entry(): void
    {
        $this->service()->update(301, '46.151.227.213', 'admin@test');

        $this->assertSame(1, AdminLog::query()
            ->where('action', AdminLogAction::TunnelEgressIpUpdated->value)
            ->where('admin_user', 'admin@test')
            ->count());
    }
}
