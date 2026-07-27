<?php

declare(strict_types=1);

namespace App\Enums;

// Derived display status for a radacct row (CLAUDE.md Section 16.6). Not a
// stored column - computed from acctstoptime/acctupdatetime each time a
// session is read, since RADIUS accounting alone can't tell us a device
// walked away without sending Acct-Stop (Stale exists for exactly that gap).
enum SessionStatus: string
{
    case Connected = 'connected';
    case Stale = 'stale';
    case Disconnected = 'disconnected';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected',
            self::Stale => 'Stale',
            self::Disconnected => 'Disconnected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Connected => 'success',
            self::Stale => 'warning',
            self::Disconnected => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Connected => 'heroicon-o-signal',
            self::Stale => 'heroicon-o-exclamation-triangle',
            self::Disconnected => 'heroicon-o-signal-slash',
        };
    }
}
