<?php

declare(strict_types=1);

namespace App\Domain;

use App\Models\ProvisionedVlan;
use InvalidArgumentException;

// Derives the fixed 1:1 artifacts of a VLAN ID, per CLAUDE.md Sections 5
// and 6. A VLAN ID alone determines subnet, WireGuard interface, and
// gateway names; nothing here is ever chosen freely.
//
// Which VLANs are currently provisioned lives in the provisioned_vlans
// table (Section 16.5, pulled into Phase 1 2026-07-28 - previously a
// config-derived contiguous range, ZONCLAVE_VLAN_MIN/MAX). Reading directly
// via Eloquent here for a simple lookup matches the precedent already set
// by PpskGroupForm's label datalist. The set no longer has to be
// contiguous - a deleted VLAN just leaves a gap, which is the whole point
// (VLAN304 sat orphaned with no way to remove it before this).
final class VlanPlan
{
    /**
     * @return array{vlan_id: int, subnet: string, wireguard_interface: string, wireguard_gateway: string}
     */
    public static function forVlan(int $vlanId): array
    {
        if (! self::isProvisioned($vlanId)) {
            throw new InvalidArgumentException(sprintf(
                'VLAN %d is not provisioned. Add it from the VLANs page first.',
                $vlanId,
            ));
        }

        return [
            'vlan_id' => $vlanId,
            'subnet' => sprintf((string) config('zonclave.subnet_template'), $vlanId - (int) config('zonclave.vlan_base')),
            'wireguard_interface' => sprintf('WG_VLAN%d', $vlanId),
            'wireguard_gateway' => sprintf('GW_WG_VLAN%d', $vlanId),
        ];
    }

    public static function isProvisioned(int $vlanId): bool
    {
        return ProvisionedVlan::query()->where('vlan_id', $vlanId)->exists();
    }

    /** @return array<int, string> vlan_id => display label, for form dropdowns */
    public static function options(): array
    {
        $options = [];

        foreach (ProvisionedVlan::query()->orderBy('vlan_id')->pluck('vlan_id') as $vlanId) {
            $plan = self::forVlan((int) $vlanId);
            $options[(int) $vlanId] = sprintf('VLAN %d (%s via %s)', $vlanId, $plan['subnet'], $plan['wireguard_interface']);
        }

        return $options;
    }
}
