<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\NetworkTopologyWidget;
use App\Models\PpskGroup;
use App\Models\RadiusAccounting;
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
            ->assertSee('No PPSKs');
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

    // radacct has no vlan_id of its own (Section 16.6) - the live count is
    // derived through the joined ppsk_groups row, same as the Sessions
    // page's own VLAN filter/column.
    public function test_shows_a_live_connected_count_per_vlan(): void
    {
        PpskGroup::factory()->create(['radius_username' => 'ppsk_group001', 'vlan_id' => 300]);
        RadiusAccounting::query()->create([
            'acctsessionid' => 'sess-1',
            'acctuniqueid' => 'uniq-1',
            'username' => 'ppsk_group001',
            'acctstarttime' => now()->subMinutes(5),
            'acctupdatetime' => now(),
        ]);

        Livewire::test(NetworkTopologyWidget::class)
            ->assertSee('1 connected now');
    }

    public function test_does_not_show_a_connected_badge_for_closed_sessions(): void
    {
        PpskGroup::factory()->create(['radius_username' => 'ppsk_group001', 'vlan_id' => 300]);
        RadiusAccounting::query()->create([
            'acctsessionid' => 'sess-1',
            'acctuniqueid' => 'uniq-1',
            'username' => 'ppsk_group001',
            'acctstarttime' => now()->subHour(),
            'acctstoptime' => now(),
        ]);

        Livewire::test(NetworkTopologyWidget::class)
            ->assertDontSee('connected now');
    }
}
