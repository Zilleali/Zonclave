<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\VlanPlan;
use InvalidArgumentException;
use Tests\TestCase;

// Section 5 formula: 10.30.X.0/24 where X = VLAN - 300, names per
// Section 6. Uses the app container for config, hence Tests\TestCase.
class VlanPlanTest extends TestCase
{
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
}
