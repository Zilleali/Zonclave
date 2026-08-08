<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\VlanPlan;
use App\Models\ProvisionedVlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

// Section 5 formula: 10.30.X.0/24 where X = VLAN - 300, names per
// Section 6. Which VLANs are provisioned now lives in the provisioned_vlans
// table (Section 16.5), seeded from config('zonclave.vlan_min'/'vlan_max')
// by its migration - RefreshDatabase gives every test the default 300-304
// range, matching what these assertions have always expected.
class VlanPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_derives_plan_for_vlan_300(): void
    {
        $this->assertSame([
            'vlan_id' => 300,
            'subnet' => '10.30.0.0/24',
            'wireguard_interface' => 'WG_VLAN300',
            'wireguard_gateway' => 'GW_WG_VLAN300',
        ], VlanPlan::forVlan(300));
    }

    public function test_derives_plan_for_vlan_304(): void
    {
        $plan = VlanPlan::forVlan(304);

        $this->assertSame('10.30.4.0/24', $plan['subnet']);
        $this->assertSame('WG_VLAN304', $plan['wireguard_interface']);
        $this->assertSame('GW_WG_VLAN304', $plan['wireguard_gateway']);
    }

    public function test_rejects_vlan_below_block(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VlanPlan::forVlan(299);
    }

    public function test_rejects_vlan_above_block(): void
    {
        $this->expectException(InvalidArgumentException::class);

        VlanPlan::forVlan(305);
    }

    public function test_options_cover_the_phase_1_block(): void
    {
        $options = VlanPlan::options();

        $this->assertSame([300, 301, 302, 303, 304], array_keys($options));
        $this->assertStringContainsString('10.30.0.0/24', $options[300]);
    }

    public function test_options_puts_the_friendly_name_first_when_set(): void
    {
        ProvisionedVlan::query()->where('vlan_id', 300)->update(['name' => 'Office main']);

        $options = VlanPlan::options();

        $this->assertSame('Office main - VLAN 300 (10.30.0.0/24 via WG_VLAN300)', $options[300]);
        $this->assertStringContainsString('VLAN 301 (10.30.1.0/24', $options[301]);
        $this->assertStringNotContainsString(' - VLAN 301', $options[301]);
    }
}
