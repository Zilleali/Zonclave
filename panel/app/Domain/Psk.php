<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

// The single place that owns "what is a legal PSK" (CLAUDE.md Section 14).
// Every PSK passes through this type before it is accepted anywhere in the
// system, so no code path can persist an out-of-spec key.
final readonly class Psk
{
    // WPA2 personal PSK constraint.
    public const MIN_LENGTH = 8;

    public const MAX_LENGTH = 63;

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $length = strlen($value);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'PSK must be %d to %d characters, got %d.',
                self::MIN_LENGTH,
                self::MAX_LENGTH,
                $length,
            ));
        }

        return new self($value);
    }
}
