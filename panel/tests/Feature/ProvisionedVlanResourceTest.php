<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ProvisionedVlans\Pages\ListProvisionedVlans;
use App\Models\PpskGroup;
use App\Models\ProvisionedVlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// VLAN management UI (CLAUDE.md Section 16.5). Confirms the seeded range
// shows up, adding a VLAN works end to end through ProvisionedVlanService,
// and deleting one in use is blocked with a notification rather than
// silently orphaning the PPSK that still references it.
class ProvisionedVlanResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_shows_the_seeded_range(): void
    {
        Livewire::test(ListProvisionedVlans::class)
            ->assertCanSeeTableRecords(ProvisionedVlan::query()->get());

        $this->assertSame([300, 301, 302, 303, 304], ProvisionedVlan::query()->orderBy('vlan_id')->pluck('vlan_id')->all());
    }

    public function test_create_action_provisions_a_new_vlan(): void
    {
        Livewire::test(ListProvisionedVlans::class)
            ->callAction('create', data: ['vlan_id' => 305]);

        $this->assertSame(1, ProvisionedVlan::query()->where('vlan_id', 305)->count());
    }

    public function test_create_action_rejects_a_vlan_below_the_reserved_floor(): void
    {
        Livewire::test(ListProvisionedVlans::class)
            ->callAction('create', data: ['vlan_id' => 100])
            ->assertHasActionErrors(['vlan_id']);

        $this->assertSame(0, ProvisionedVlan::query()->where('vlan_id', 100)->count());
    }

    public function test_delete_action_removes_an_unused_vlan(): void
    {
        $vlan = ProvisionedVlan::query()->where('vlan_id', 304)->firstOrFail();

        Livewire::test(ListProvisionedVlans::class)
            ->callTableAction('delete', $vlan);

        $this->assertSame(0, ProvisionedVlan::query()->where('vlan_id', 304)->count());
    }

    public function test_delete_action_is_blocked_when_a_ppsk_uses_the_vlan(): void
    {
        PpskGroup::factory()->create(['label' => 'VLAN300_INUSE', 'radius_username' => 'ppsk_group903', 'vlan_id' => 300]);
        $vlan = ProvisionedVlan::query()->where('vlan_id', 300)->firstOrFail();

        Livewire::test(ListProvisionedVlans::class)
            ->callTableAction('delete', $vlan);

        $this->assertSame(1, ProvisionedVlan::query()->where('vlan_id', 300)->count());
    }
}
