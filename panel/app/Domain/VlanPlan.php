<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

// Derives the fixed 1:1 artifacts of a VLAN ID, per CLAUDE.md Sections 5
// and 6. A VLAN ID alone determines subnet, WireGuard interface, and
// gateway names; nothing here is ever chosen freely.
final class VlanPlan
{
    /**
     * @return array{vlan_id: int, subnet: string, wireguard_interface: string, wireguard_gateway: string}
     */
    public static function forVlan(int $vlanId): array
    {
        if (! self::isProvisioned($vlanId)) {
            throw new InvalidArgumentException(sprintf(
                'VLAN %d is outside the provisioned block %d to %d.',
                $vlanId,
                self::min(),
                self::max(),
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
        return $vlanId >= self::min() && $vlanId <= self::max();
    }

    /** @return array<int, string> vlan_id => display label, for form dropdowns */
    public static function options(): array
    {
        $options = [];

        for ($vlan = self::min(); $vlan <= self::max(); $vlan++) {
            $plan = self::forVlan($vlan);
            $options[$vlan] = sprintf('VLAN %d (%s via %s)', $vlan, $plan['subnet'], $plan['wireguard_interface']);
        }

        return $options;
    }

    private static function min(): int
    {
        return (int) config('zonclave.vlan_min');
    }

    private static function max(): int
    {
        return (int) config('zonclave.vlan_max');
    }
}
