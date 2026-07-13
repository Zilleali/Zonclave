<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PpskStatus;
use App\Filament\Resources\PpskGroups\Pages\CreatePpskGroup;
use App\Filament\Resources\PpskGroups\Pages\EditPpskGroup;
use App\Filament\Resources\PpskGroups\Pages\ListPpskGroups;
use App\Models\PpskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// UI smoke coverage of the create/disable/delete flows (CLAUDE.md
// Section 21.2). These exercise the actual Filament pages, not PpskService
// directly, to confirm the UI wiring calls the service correctly and never
// bypasses it with a default Eloquent save.
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
        Livewire::test(CreatePpskGroup::class)
            ->fillForm(['label' => 'VLAN300_TESTA', 'vlan_id' => 300, 'enabled' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = PpskGroup::query()->sole();

        $this->assertSame('VLAN300_TESTA', $group->label);
        $this->assertSame(PpskStatus::Active, $group->status);
        $this->assertDatabaseHas('radcheck', ['username' => $group->radius_username]);
    }

    public function test_create_flow_rejects_a_vlan_outside_the_provisioned_block(): void
    {
        Livewire::test(CreatePpskGroup::class)
            ->fillForm(['label' => 'VLAN305_TESTA', 'vlan_id' => 305, 'enabled' => true])
            ->call('create')
            ->assertHasFormErrors(['vlan_id']);
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

        Livewire::test(EditPpskGroup::class, ['record' => $group->getRouteKey()])
            ->fillForm(['label' => 'VLAN302_RENAMED', 'vlan_id' => 302])
            ->call('save')
            ->assertHasNoFormErrors();

        $group->refresh();

        $this->assertSame('VLAN302_RENAMED', $group->label);
        $this->assertSame('10.30.2.0/24', $group->subnet);
    }
}
