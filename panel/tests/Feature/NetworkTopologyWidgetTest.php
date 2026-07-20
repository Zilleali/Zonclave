<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\NetworkTopologyWidget;
use App\Models\PpskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Static architecture diagram on the Dashboard (Section 16.2 - inventory
// only, no live device/tunnel status; that stays Section 13 Phase 2).
class NetworkTopologyWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_shows_every_provisioned_vlan_including_empty_ones(): void
    {
        Livewire::test(NetworkTopologyWidget::class)
            ->assertSee('VLAN 300')
            ->assertSee('VLAN 304')
            ->assertSee('unprovisioned');
    }

    public function test_reflects_active_and_disabled_counts_per_vlan(): void
    {
        PpskGroup::factory()->count(2)->create(['vlan_id' => 300]);
        PpskGroup::factory()->create(['vlan_id' => 300, 'status' => 'disabled']);

        Livewire::test(NetworkTopologyWidget::class)
            ->assertSee('2 active')
            ->assertSee('1 disabled');
    }

    public function test_vlan_node_links_to_the_ppsk_list_filtered_by_that_vlan(): void
    {
        $nodes = (new NetworkTopologyWidget)->getVlanNodes();
        $vlan300 = collect($nodes)->firstWhere('vlan_id', 300);

        $this->assertStringContainsString('vlan_id', (string) $vlan300['url']);
        $this->assertStringContainsString('300', (string) $vlan300['url']);
    }
}
