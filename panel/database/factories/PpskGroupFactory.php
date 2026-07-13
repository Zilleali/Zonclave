<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\VlanPlan;
use App\Enums\PpskStatus;
use App\Models\PpskGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * Test-only convenience for seeding ppsk_groups rows directly. Does not
 * project to RADIUS (Section 23.1 is PpskService's job); use this only to
 * set up pre-existing state for tests that exercise list/edit/disable/
 * delete against a row that already exists.
 *
 * @extends Factory<PpskGroup>
 */
class PpskGroupFactory extends Factory
{
    public function definition(): array
    {
        static $sequence = 0;
        $sequence++;

        $plan = VlanPlan::forVlan(300);

        return [
            'label' => 'VLAN300_FACTORY'.$sequence,
            'radius_username' => sprintf('ppsk_group%03d', 900 + $sequence),
            'password_hash' => Crypt::encryptString($this->faker->regexify('[A-Za-z0-9]{24}')),
            'vlan_id' => $plan['vlan_id'],
            'subnet' => $plan['subnet'],
            'wireguard_interface' => $plan['wireguard_interface'],
            'wireguard_gateway' => $plan['wireguard_gateway'],
            'status' => PpskStatus::Active,
        ];
    }
}
