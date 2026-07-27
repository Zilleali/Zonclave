<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Support\Collection;
use RuntimeException;

// Thrown by ProvisionedVlanService::deprovision() when a VLAN still has at
// least one PPSK assigned to it (active or disabled - CLAUDE.md Section
// 16.5's client-directed "block, don't silently orphan" rule). The message
// names the specific PPSK(s) so the admin knows exactly what to fix first.
class VlanInUseException extends RuntimeException
{
    /** @param Collection<int, string> $labels */
    public static function forVlan(int $vlanId, Collection $labels): self
    {
        return new self(sprintf(
            'VLAN %d is still used by: %s. Delete or reassign %s first.',
            $vlanId,
            $labels->implode(', '),
            $labels->count() === 1 ? 'this PPSK' : 'these PPSKs',
        ));
    }
}
