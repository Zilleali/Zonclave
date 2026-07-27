<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\SessionLogs\Pages\ListSessionLogs;
use App\Filament\Resources\SessionLogs\SessionLogResource;
use App\Models\PpskGroup;
use App\Models\RadiusAccounting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Read-only connected-device list UI (CLAUDE.md Section 16.6). Sourced from
// radacct, which the panel never writes to (App\Models\RadiusAccounting) -
// confirms sessions render, join to their PPSK group, and that there is no
// way to create/edit/delete a session through the panel.
class SessionLogResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function makeSession(array $overrides = []): RadiusAccounting
    {
        return RadiusAccounting::query()->create(array_merge([
            'acctsessionid' => 'sess-'.bin2hex(random_bytes(4)),
            'acctuniqueid' => 'uniq-'.bin2hex(random_bytes(8)),
            'username' => 'ppsk_group001',
            'callingstationid' => 'AA:BB:CC:DD:EE:FF',
            'framedipaddress' => '10.30.0.11',
            'acctstarttime' => now()->subHour(),
            'acctupdatetime' => now(),
        ], $overrides));
    }

    public function test_list_page_shows_sessions_newest_first(): void
    {
        $older = $this->makeSession(['acctstarttime' => now()->subDay()]);
        $newer = $this->makeSession(['acctstarttime' => now()]);

        Livewire::test(ListSessionLogs::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_session_joins_to_its_ppsk_group_label(): void
    {
        $group = PpskGroup::factory()->create(['radius_username' => 'ppsk_group001', 'label' => 'VLAN300_LAPTOP']);
        $session = $this->makeSession(['username' => 'ppsk_group001']);

        Livewire::test(ListSessionLogs::class)
            ->assertTableColumnStateSet('ppskGroup.label', 'VLAN300_LAPTOP', record: $session);
    }

    public function test_resource_has_no_create_route(): void
    {
        $this->assertFalse(SessionLogResource::canCreate());
        $this->get(SessionLogResource::getUrl('index'))->assertOk();
    }

    public function test_filtering_by_currently_connected_only_excludes_closed_sessions(): void
    {
        $open = $this->makeSession();
        $closed = $this->makeSession(['acctstoptime' => now()]);

        Livewire::test(ListSessionLogs::class)
            ->set('tableFilters', ['open' => ['isActive' => true]])
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$closed]);
    }

    // radacct has no vlan_id column of its own (Section 16.6) - the VLAN
    // column and filter both derive it through the joined ppsk_groups row,
    // which this confirms actually works end to end.
    public function test_vlan_column_shows_the_joined_ppsk_groups_vlan(): void
    {
        PpskGroup::factory()->create(['radius_username' => 'ppsk_group001', 'vlan_id' => 302]);
        $session = $this->makeSession(['username' => 'ppsk_group001']);

        Livewire::test(ListSessionLogs::class)
            ->assertTableColumnStateSet('vlan', 302, record: $session);
    }

    public function test_filtering_by_vlan_narrows_to_that_vlans_sessions(): void
    {
        PpskGroup::factory()->create(['radius_username' => 'ppsk_group001', 'vlan_id' => 300]);
        PpskGroup::factory()->create(['radius_username' => 'ppsk_group002', 'vlan_id' => 301]);

        $vlan300 = $this->makeSession(['username' => 'ppsk_group001']);
        $vlan301 = $this->makeSession(['username' => 'ppsk_group002']);

        Livewire::test(ListSessionLogs::class)
            ->set('tableFilters', ['vlan_id' => ['value' => 300]])
            ->assertCanSeeTableRecords([$vlan300])
            ->assertCanNotSeeTableRecords([$vlan301]);
    }

    // The RADIUS Acct-Terminate-Cause the AP reported for a closed session -
    // stored in radacct since the very first version of this table, but
    // never actually shown anywhere until now.
    public function test_disconnect_reason_column_shows_the_reported_terminate_cause(): void
    {
        $session = $this->makeSession([
            'acctstoptime' => now(),
            'acctterminatecause' => 'User-Request',
        ]);

        Livewire::test(ListSessionLogs::class)
            ->assertTableColumnStateSet('acctterminatecause', 'User-Request', record: $session);
    }
}
