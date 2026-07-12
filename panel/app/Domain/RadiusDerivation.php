<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\PpskStatus;

// Pure derivation of the RADIUS projection of one ppsk_groups row, per
// CLAUDE.md Section 8.2. No database access: this class answers "given a
// registry row, exactly which radcheck/radreply rows must exist" and is
// unit-tested in isolation. RadiusRepository is the only class that writes
// the result.
final class RadiusDerivation
{
    /**
     * @return list<array{username: string, attribute: string, op: string, value: string}>
     */
    public static function radcheckRows(string $username, Psk $psk, PpskStatus $status): array
    {
        // Disabling withholds the credential entirely (Section 8.2).
        if (! $status->canAuthenticate()) {
            return [];
        }

        return [
            ['username' => $username, 'attribute' => 'Cleartext-Password', 'op' => ':=', 'value' => $psk->value],
        ];
    }

    /**
     * The VLAN handed to the AP derives only from the stored vlan_id
     * (Section 23.1), never from anything client-supplied.
     *
     * @return list<array{username: string, attribute: string, op: string, value: string}>
     */
    public static function radreplyRows(string $username, int $vlanId, PpskStatus $status): array
    {
        if (! $status->canAuthenticate()) {
            return [];
        }

        return [
            ['username' => $username, 'attribute' => 'Tunnel-Private-Group-Id', 'op' => ':=', 'value' => (string) $vlanId],
            ['username' => $username, 'attribute' => 'Tunnel-Type', 'op' => ':=', 'value' => 'VLAN'],
            ['username' => $username, 'attribute' => 'Tunnel-Medium-Type', 'op' => ':=', 'value' => 'IEEE-802'],
        ];
    }
}
