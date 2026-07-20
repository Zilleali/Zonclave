<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\VlanPlan;
use App\Enums\PpskStatus;
use App\Filament\Resources\PpskGroups\PpskGroupResource;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

// A static architecture diagram, not a live UniFi-controller-style
// topology (client asked for the latter, scoped down to this on
// 2026-07-18 - a real one needs OPNsense/UniFi API integration, which is
// Section 19/22 Phase 2 territory). Every node here is derived from
// ppsk_groups (Section 7) and VlanPlan (Sections 5-6), so it reflects what
// the registry actually says is provisioned, not live device/tunnel
// health. No polling (Section 23.3): reflects state as of page load only.
class NetworkTopologyWidget extends Widget
{
    protected string $view = 'filament.widgets.network-topology';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int, array{vlan_id: int, subnet: string, wireguard_interface: string, active: int, disabled: int, url: string}>
     */
    public function getVlanNodes(): array
    {
        $countsByVlanAndStatus = DB::table('ppsk_groups')
            ->selectRaw('vlan_id, status, COUNT(*) as count')
            ->groupBy('vlan_id', 'status')
            ->get()
            ->groupBy('vlan_id');

        $nodes = [];

        foreach (array_keys(VlanPlan::options()) as $vlanId) {
            $plan = VlanPlan::forVlan($vlanId);
            $counts = $countsByVlanAndStatus->get($vlanId, collect());

            $nodes[] = [
                'vlan_id' => $vlanId,
                'subnet' => $plan['subnet'],
                'wireguard_interface' => $plan['wireguard_interface'],
                'active' => (int) $counts->firstWhere('status', PpskStatus::Active->value)?->count,
                'disabled' => (int) $counts->firstWhere('status', PpskStatus::Disabled->value)?->count,
                'url' => PpskGroupResource::getUrl().'?'.http_build_query([
                    'filters' => ['vlan_id' => ['value' => $vlanId]],
                ]),
            ];
        }

        return $nodes;
    }
}
