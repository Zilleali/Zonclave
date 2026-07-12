<?php

declare(strict_types=1);

namespace App\Enums;

// ppsk_groups.status, per CLAUDE.md Section 7. Broader than a boolean so
// Phase 2 automation can track provisioning/error states.
enum PpskStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Provisioning = 'provisioning';
    case Error = 'error';

    // Only an active group materializes a radcheck row (Section 8.2:
    // disabling withholds the credential, it does not just hide a UI row).
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
