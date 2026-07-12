<?php

declare(strict_types=1);

namespace App\Domain;

// PSK generation standard, per CLAUDE.md Section 14: 24 characters,
// A-Z a-z 0-9, ambiguous characters (0 O 1 l I) excluded. PSKs are always
// generated, never admin-chosen. Must stay identical to gen_psk() in
// installer/install.sh and db/seed/seed_test_ppsk_groups.sh.
final class PskGenerator
{
    public const LENGTH = 24;

    public const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function generate(): Psk
    {
        $max = strlen(self::CHARSET) - 1;
        $out = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $out .= self::CHARSET[random_int(0, $max)];
        }

        return Psk::fromString($out);
    }
}
