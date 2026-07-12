<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PpskStatus;
use App\Models\PpskGroup;
use App\Repositories\RadiusRepository;
use App\Services\PpskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

// Integration coverage of the registry-to-RADIUS path (CLAUDE.md
// Section 21.2): every write branch of PpskService against a real test
// database, including the no-partial-state rollback guarantee.
class PpskServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PpskService
    {
        return app(PpskService::class);
    }

    public function test_create_writes_registry_row_and_derived_radius_rows(): void
    {
        $result = $this->service()->create('VLAN300_TESTA', 300, true, 'admin@test');

        $group = $result['group'];

        $this->assertSame('ppsk_group'.sprintf('%03d', $group->id), $group->radius_username);
        $this->assertSame(300, $group->vlan_id);
        $this->assertSame('10.30.0.0/24', $group->subnet);
        $this->assertSame('WG_VLAN300', $group->wireguard_interface);
        $this->assertSame('GW_WG_VLAN300', $group->wireguard_gateway);
        $this->assertSame(PpskStatus::Active, $group->status);

        // PSK is 24 chars per Section 14 and returned once.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{24}$/', $result['psk']);

        // Stored encrypted at rest, decryptable back to the same value.
        $this->assertSame($result['psk'], Crypt::decryptString($group->password_hash));

        $this->assertDatabaseHas('radcheck', [
            'username' => $group->radius_username,
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
            'value' => $result['psk'],
        ]);
        $this->assertDatabaseHas('radreply', [
            'username' => $group->radius_username,
            'attribute' => 'Tunnel-Private-Group-Id',
            'value' => '300',
        ]);
        $this->assertDatabaseHas('radreply', ['username' => $group->radius_username, 'attribute' => 'Tunnel-Type', 'value' => 'VLAN']);
        $this->assertDatabaseHas('radreply', ['username' => $group->radius_username, 'attribute' => 'Tunnel-Medium-Type', 'value' => 'IEEE-802']);

        $this->assertDatabaseHas('admin_log', [
            'action' => 'ppsk_created',
            'admin_user' => 'admin@test',
            'target_ppsk_id' => $group->id,
        ]);
    }

    public function test_create_disabled_withholds_radius_rows(): void
    {
        $result = $this->service()->create('VLAN300_TESTA', 300, false, 'admin@test');

        $username = $result['group']->radius_username;

        $this->assertSame(PpskStatus::Disabled, $result['group']->status);
        $this->assertDatabaseMissing('radcheck', ['username' => $username]);
        $this->assertDatabaseMissing('radreply', ['username' => $username]);
    }

    public function test_disable_revokes_authentication(): void
    {
        $group = $this->service()->create('VLAN300_TESTA', 300, true, 'admin@test')['group'];

        $this->service()->disable($group, 'admin@test');

        $this->assertDatabaseMissing('radcheck', ['username' => $group->radius_username]);
        $this->assertDatabaseMissing('radreply', ['username' => $group->radius_username]);
        $this->assertDatabaseHas('admin_log', ['action' => 'ppsk_disabled', 'target_ppsk_id' => $group->id]);
    }

    public function test_enable_rematerializes_radius_rows(): void
    {
        $created = $this->service()->create('VLAN300_TESTA', 300, false, 'admin@test');
        $group = $created['group'];

        $this->service()->enable($group, 'admin@test');

        $this->assertDatabaseHas('radcheck', [
            'username' => $group->radius_username,
            'attribute' => 'Cleartext-Password',
            'value' => $created['psk'],
        ]);
        $this->assertDatabaseHas('admin_log', ['action' => 'ppsk_enabled', 'target_ppsk_id' => $group->id]);
    }

    public function test_update_vlan_rederives_plan_and_reprojects(): void
    {
        $group = $this->service()->create('VLAN300_TESTA', 300, true, 'admin@test')['group'];

        $group = $this->service()->update($group, 'VLAN302_TESTA', 302, 'admin@test');

        $this->assertSame('10.30.2.0/24', $group->subnet);
        $this->assertSame('WG_VLAN302', $group->wireguard_interface);
        $this->assertSame('GW_WG_VLAN302', $group->wireguard_gateway);

        $this->assertDatabaseHas('radreply', [
            'username' => $group->radius_username,
            'attribute' => 'Tunnel-Private-Group-Id',
            'value' => '302',
        ]);
        $this->assertDatabaseMissing('radreply', [
            'username' => $group->radius_username,
            'attribute' => 'Tunnel-Private-Group-Id',
            'value' => '300',
        ]);
    }

    public function test_delete_leaves_no_orphan_radius_rows(): void
    {
        $group = $this->service()->create('VLAN300_TESTA', 300, true, 'admin@test')['group'];
        $username = $group->radius_username;

        $this->service()->delete($group, 'admin@test');

        $this->assertDatabaseMissing('ppsk_groups', ['radius_username' => $username]);
        $this->assertDatabaseMissing('radcheck', ['username' => $username]);
        $this->assertDatabaseMissing('radreply', ['username' => $username]);
        $this->assertDatabaseHas('admin_log', ['action' => 'ppsk_deleted']);
    }

    public function test_regenerate_password_replaces_credential(): void
    {
        $created = $this->service()->create('VLAN300_TESTA', 300, true, 'admin@test');
        $group = $created['group'];

        $result = $this->service()->regeneratePassword($group, 'admin@test');

        $this->assertNotSame($created['psk'], $result['psk']);
        $this->assertDatabaseHas('radcheck', ['username' => $group->radius_username, 'value' => $result['psk']]);
        $this->assertDatabaseMissing('radcheck', ['username' => $group->radius_username, 'value' => $created['psk']]);
        $this->assertSame(1, DB::table('radcheck')->where('username', $group->radius_username)->count());
        $this->assertDatabaseHas('admin_log', ['action' => 'ppsk_password_regenerated', 'target_ppsk_id' => $group->id]);
    }

    public function test_failed_radius_write_rolls_back_the_whole_create(): void
    {
        // A failure inside the projection must leave no partial state
        // (Section 23.1): no registry row, no RADIUS rows, no log entry.
        $failing = new class extends RadiusRepository
        {
            public function replaceFor(string $username, array $radcheckRows, array $radreplyRows): void
            {
                throw new RuntimeException('simulated RADIUS write failure');
            }
        };
        app()->instance(RadiusRepository::class, $failing);

        try {
            $this->service()->create('VLAN300_TESTA', 300, true, 'admin@test');
            $this->fail('Expected the simulated failure to propagate.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, PpskGroup::query()->count());
        $this->assertSame(0, DB::table('radcheck')->count());
        $this->assertSame(0, DB::table('radreply')->count());
        $this->assertDatabaseMissing('admin_log', ['action' => 'ppsk_created']);
    }

    public function test_create_rejects_unprovisioned_vlan(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->create('VLAN305_TESTA', 305, true, 'admin@test');
    }
}
