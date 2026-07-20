<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

// The single place that owns "what is a legal RADIUS username" for a
// manually-entered value (CLAUDE.md Section 6/14, manual username entry is
// an opt-in alongside auto-generate as of 2026-07-18, same pattern as the
// PSK manual-entry reversal). Auto-generated usernames (ppsk_group###)
// never pass through here - they are already known-good by construction.
final readonly class RadiusUsername
{
    public const MIN_LENGTH = 3;

    // Matches the ppsk_groups.radius_username column width (Section 7).
    public const MAX_LENGTH = 64;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $length = strlen($value);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'RADIUS username must be %d to %d characters, got %d.',
                self::MIN_LENGTH,
                self::MAX_LENGTH,
                $length,
            ));
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            throw new InvalidArgumentException(
                'RADIUS username may only contain letters, numbers, underscores, and hyphens.'
            );
        }

        return new self($value);
    }
}
