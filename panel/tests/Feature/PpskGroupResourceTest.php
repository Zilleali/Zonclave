<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PpskStatus;
use App\Filament\Resources\PpskGroups\Pages\ListPpskGroups;
use App\Models\PpskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

// UI smoke coverage of the create/edit/disable/delete/regenerate flows
// (CLAUDE.md Section 21.2). Create and Edit are modal actions on the List
// page (Sancover UX request 2026-07-17, Section 16.3), not dedicated pages
// - these exercise the actual Filament wiring, not PpskService directly, to
// confirm the UI never bypasses the service with a default Eloquent save.
class PpskGroupResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_create_flow_writes_registry_and_radius_rows(): void
    {
        Livewire::test(ListPpskGroups::class)
            ->callAction('create', data: [
                'label' => 'VLAN300_TESTA',
                'vlan_id' => 300,
                'enabled' => true,
                'password_source' => 'generate',
                'username_source' => 'generate',
            ])
            ->assertHasNoActionErrors();

        $group = PpskGroup::query()->sole();

        $this->assertSame('VLAN300_TESTA', $group->label);
        $this->assertSame(PpskStatus::Active, $group->status);
        $this->assertDatabaseHas('radcheck', ['username' => $group->radius_username]);
    }

    public function test_create_flow_rejects_a_vlan_outside_the_provisioned_block(): void
    {
        Livewire::test(ListPpskGroups::class)
            ->callAction('create', data: [
                'label' => 'VLAN305_TESTA',
                'vlan_id' => 305,
                'enabled' => true,
                'password_source' => 'generate',
                'username_source' => 'generate',
            ])
            ->assertHasActionErrors(['vlan_id']);
    }

    public function test_create_flow_with_manual_password_uses_the_supplied_value(): void
    {
        Livewire::test(ListPpskGroups::class)
            ->callAction('create', data: [
                'label' => 'VLAN300_TESTA',
                'vlan_id' => 300,
                'enabled' => true,
                'password_source' => 'manual',
                'manual_password' => 'MyChosenPassword1',
            ])
            ->assertHasNoActionErrors();

        $group = PpskGroup::query()->sole();

        $this->assertSame('MyChosenPassword1', Crypt::decryptString($group->password_hash));
        $this->assertDatabaseHas('radcheck', [
            'username' => $group->radius_username,
            'attribute' => 'Cleartext-Password',
            'value' => 'MyChosenPassword1',
        ]);
    }

    public function test_create_flow_rejects_a_manual_password_outside_the_psk_length_boundary(): void
    {
        Livewire::test(ListPpskGroups::class)
            ->callAction('create', data: [
                'label' => 'VLAN300_TESTA',
                'vlan_id' => 300,
                'enabled' => true,
                'password_source' => 'manual',
                'manual_password' => 'short',
            ])
            ->assertHasActionErrors(['manual_password']);

        $this->assertSame(0, PpskGroup::query()->count());
    }

    public function test_create_flow_with_manual_username_uses_the_supplied_value(): void
    {
        Livewire::test(ListPpskGroups::class)
            ->callAction('create', data: [
                'label' => 'VLAN301_SANCOUK1',
                'vlan_id' => 301,
                'enabled' => true,
                'password_source' => 'generate',
                'username_source' => 'manual',
                'manual_username' => 'SancoUk1',
            ])
            ->assertHasNoActionErrors();

        $group = PpskGroup::query()->sole();

        $this->assertSame('SancoUk1', $group->radius_username);
        $this->assertDatabaseHas('radcheck', ['username' => 'SancoUk1']);
    }

    public function test_create_flow_rejects_a_manual_username_outside_the_format_boundary(): void
    {
        Livewire::test(ListPpskGroups::class)
            ->callAction('create', data: [
                'label' => 'VLAN301_TESTA',
                'vlan_id' => 301,
                'enabled' => true,
                'password_source' => 'generate',
                'username_source' => 'manual',
                'manual_username' => 'has a space',
            ])
            ->assertHasActionErrors(['manual_username']);

        $this->assertSame(0, PpskGroup::query()->count());
    }

    public function test_create_flow_rejects_a_manual_username_already_taken(): void
    {
        PpskGroup::factory()->create(['radius_username' => 'SancoUk1']);

        Livewire::test(ListPpskGroups::class)
            ->callAction('create', data: [
                'label' => 'VLAN302_SANCOUK1DUP',
                'vlan_id' => 302,
                'enabled' => true,
                'password_source' => 'generate',
                'username_source' => 'manual',
                'manual_username' => 'SancoUk1',
            ])
            ->assertHasActionErrors(['manual_username']);

        $this->assertSame(1, PpskGroup::query()->count());
    }

    public function test_list_page_shows_created_groups(): void
    {
        $group = PpskGroup::factory()->create(['label' => 'VLAN301_TESTB']);

        Livewire::test(ListPpskGroups::class)
            ->assertCanSeeTableRecords([$group])
            ->assertTableColumnStateSet('label', 'VLAN301_TESTB', $group);
    }

    public function test_disable_action_revokes_authentication(): void
    {
        $group = PpskGroup::factory()->create(['status' => PpskStatus::Active]);

        Livewire::test(ListPpskGroups::class)
            ->callTableAction('toggleStatus', $group);

        $group->refresh();

        $this->assertSame(PpskStatus::Disabled, $group->status);
        $this->assertDatabaseMissing('radcheck', ['username' => $group->radius_username]);
        $this->assertDatabaseHas('admin_log', ['action' => 'ppsk_disabled', 'target_ppsk_id' => $group->id]);
    }

    public function test_delete_action_leaves_no_orphan_radius_rows(): void
    {
        $group = PpskGroup::factory()->create();
        $username = $group->radius_username;

        Livewire::test(ListPpskGroups::class)
            ->callTableAction('delete', $group);

        $this->assertDatabaseMissing('ppsk_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('radcheck', ['username' => $username]);
        $this->assertDatabaseMissing('radreply', ['username' => $username]);
        $this->assertDatabaseHas('admin_log', ['action' => 'ppsk_deleted', 'target_ppsk_id' => $group->id]);
    }

    public function test_edit_flow_updates_label_and_vlan(): void
    {
        $group = PpskGroup::factory()->create(['vlan_id' => 300]);

        Livewire::test(ListPpskGroups::class)
            ->callTableAction('edit', $group, data: ['label' => 'VLAN302_RENAMED', 'vlan_id' => 302])
            ->assertHasNoTableActionErrors();

        $group->refresh();

        $this->assertSame('VLAN302_RENAMED', $group->label);
        $this->assertSame('10.30.2.0/24', $group->subnet);
    }

    public function test_regenerate_password_action_issues_a_new_credential(): void
    {
        $group = PpskGroup::factory()->create();
        $originalHash = $group->password_hash;

        Livewire::test(ListPpskGroups::class)
            ->callTableAction('regeneratePassword', $group, data: ['password_source' => 'generate'])
            ->assertHasNoTableActionErrors();

        $group->refresh();

        $this->assertNotSame($originalHash, $group->password_hash);
        $this->assertDatabaseHas('admin_log', ['action' => 'ppsk_password_regenerated', 'target_ppsk_id' => $group->id]);
    }

    public function test_regenerate_password_action_with_manual_password_uses_the_supplied_value(): void
    {
        $group = PpskGroup::factory()->create();

        Livewire::test(ListPpskGroups::class)
            ->callTableAction('regeneratePassword', $group, data: [
                'password_source' => 'manual',
                'manual_password' => 'AnotherChosenPassword2',
            ])
            ->assertHasNoTableActionErrors();

        $group->refresh();

        $this->assertSame('AnotherChosenPassword2', Crypt::decryptString($group->password_hash));
    }
}
