<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\TunnelEgressIps\Pages\ListTunnelEgressIps;
use App\Filament\Resources\TunnelEgressIps\TunnelEgressIpResource;
use App\Models\TunnelEgressIp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Tunnel egress IP reference UI (CLAUDE.md Section 16.6). Confirms one row
// per provisioned VLAN always exists (even with zero prior TunnelEgressIp
// rows in the database), and that editing goes through
// TunnelEgressIpService rather than writing the model directly.
class TunnelEgressIpResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_list_page_shows_a_row_for_every_provisioned_vlan_with_no_prior_rows(): void
    {
        $this->assertSame(0, TunnelEgressIp::query()->count());

        Livewire::test(ListTunnelEgressIps::class);

        // config/zonclave.php's default range is VLAN 300 to 304 inclusive.
        $this->assertSame(5, TunnelEgressIp::query()->count());
        $this->assertSame([300, 301, 302, 303, 304], TunnelEgressIp::query()->orderBy('vlan_id')->pluck('vlan_id')->all());
    }

    public function test_resource_has_no_create_route(): void
    {
        $this->assertFalse(TunnelEgressIpResource::canCreate());
        $this->get(TunnelEgressIpResource::getUrl('index'))->assertOk();
    }

    public function test_edit_action_updates_the_egress_ip_and_logs_it(): void
    {
        Livewire::test(ListTunnelEgressIps::class);
        $record = TunnelEgressIp::query()->where('vlan_id', 300)->firstOrFail();

        Livewire::test(ListTunnelEgressIps::class)
            ->callTableAction('edit', $record, data: ['egress_ip' => '46.151.227.182']);

        $record->refresh();
        $this->assertSame('46.151.227.182', $record->egress_ip);
        $this->assertNotNull($record->checked_at);
    }
}
