<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminLogAction;
use App\Exceptions\VlanInUseException;
use App\Models\AdminLog;
use App\Models\PpskGroup;
use App\Models\ProvisionedVlan;
use App\Models\TunnelEgressIp;
use App\Services\ProvisionedVlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// ProvisionedVlanService (CLAUDE.md Section 16.5) - the only write path for
// provisioned_vlans. Deleting a VLAN still used by a PPSK must be blocked,
// not silently allowed to orphan that credential's registry row.
class ProvisionedVlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ProvisionedVlanService
    {
        return app(ProvisionedVlanService::class);
    }

    public function test_provision_creates_a_vlan_and_logs_it(): void
    {
        $vlan = $this->service()->provision(305, 'admin@test');

        $this->assertSame(305, $vlan->vlan_id);
        $this->assertSame(1, AdminLog::query()
            ->where('action', AdminLogAction::VlanProvisioned->value)
            ->where('admin_user', 'admin@test')
            ->count());
    }

    public function test_deprovision_removes_an_unused_vlan_and_its_egress_ip_reference(): void
    {
        $vlan = ProvisionedVlan::query()->where('vlan_id', 301)->firstOrFail();
        TunnelEgressIp::query()->create(['vlan_id' => 301, 'egress_ip' => '46.151.227.213']);

        $this->service()->deprovision($vlan, 'admin@test');

        $this->assertSame(0, ProvisionedVlan::query()->where('vlan_id', 301)->count());
        $this->assertSame(0, TunnelEgressIp::query()->where('vlan_id', 301)->count());
        $this->assertSame(1, AdminLog::query()
            ->where('action', AdminLogAction::VlanDeprovisioned->value)
            ->count());
    }

    public function test_deprovision_is_blocked_when_a_ppsk_still_uses_the_vlan(): void
    {
        $vlan = ProvisionedVlan::query()->where('vlan_id', 300)->firstOrFail();
        PpskGroup::factory()->create(['label' => 'VLAN300_LAPTOP', 'radius_username' => 'ppsk_group901', 'vlan_id' => 300]);

        try {
            $this->service()->deprovision($vlan, 'admin@test');
            $this->fail('Expected VlanInUseException to be thrown.');
        } catch (VlanInUseException $e) {
            $this->assertStringContainsString('VLAN300_LAPTOP', $e->getMessage());
        }

        $this->assertSame(1, ProvisionedVlan::query()->where('vlan_id', 300)->count());
        $this->assertSame(0, AdminLog::query()->where('action', AdminLogAction::VlanDeprovisioned->value)->count());
    }

    public function test_deprovision_is_blocked_even_when_the_ppsk_is_disabled(): void
    {
        $vlan = ProvisionedVlan::query()->where('vlan_id', 300)->firstOrFail();
        PpskGroup::factory()->create([
            'label' => 'VLAN300_DISABLED',
            'radius_username' => 'ppsk_group902',
            'vlan_id' => 300,
            'status' => 'disabled',
        ]);

        $this->expectException(VlanInUseException::class);

        $this->service()->deprovision($vlan, 'admin@test');
    }
}
