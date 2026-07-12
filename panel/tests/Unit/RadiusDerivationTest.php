<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Psk;
use App\Domain\RadiusDerivation;
use App\Enums\PpskStatus;
use PHPUnit\Framework\TestCase;

// Section 8.2 derivation: given a registry row, exactly which
// radcheck/radreply rows must exist. Pure logic, no database.
class RadiusDerivationTest extends TestCase
{
    public function test_active_group_derives_one_radcheck_row(): void
    {
        $rows = RadiusDerivation::radcheckRows('ppsk_group001', Psk::fromString('abcdefghjkmnpqrstuvwxyz2'), PpskStatus::Active);

        $this->assertSame([
            [
                'username' => 'ppsk_group001',
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => 'abcdefghjkmnpqrstuvwxyz2',
            ],
        ], $rows);
    }

    public function test_active_group_derives_three_radreply_rows(): void
    {
        $rows = RadiusDerivation::radreplyRows('ppsk_group001', 300, PpskStatus::Active);

        $this->assertSame([
            ['username' => 'ppsk_group001', 'attribute' => 'Tunnel-Private-Group-Id', 'op' => ':=', 'value' => '300'],
            ['username' => 'ppsk_group001', 'attribute' => 'Tunnel-Type', 'op' => ':=', 'value' => 'VLAN'],
            ['username' => 'ppsk_group001', 'attribute' => 'Tunnel-Medium-Type', 'op' => ':=', 'value' => 'IEEE-802'],
        ], $rows);
    }

    public function test_vlan_comes_only_from_the_stored_value(): void
    {
        $rows = RadiusDerivation::radreplyRows('ppsk_group002', 304, PpskStatus::Active);

        $this->assertSame('304', $rows[0]['value']);
    }

    public function test_disabled_group_derives_no_rows(): void
    {
        $psk = Psk::fromString('abcdefghjkmnpqrstuvwxyz2');

        $this->assertSame([], RadiusDerivation::radcheckRows('ppsk_group001', $psk, PpskStatus::Disabled));
        $this->assertSame([], RadiusDerivation::radreplyRows('ppsk_group001', 300, PpskStatus::Disabled));
    }

    public function test_non_active_statuses_cannot_authenticate(): void
    {
        foreach ([PpskStatus::Disabled, PpskStatus::Provisioning, PpskStatus::Error] as $status) {
            $this->assertFalse($status->canAuthenticate());
        }

        $this->assertTrue(PpskStatus::Active->canAuthenticate());
    }
}
