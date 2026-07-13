<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\PpskGroup;
use Filament\Notifications\Notification;

// The persistent, one-time PSK display notification (Section 14), shared
// between CreatePpskGroup and EditPpskGroup's regenerate action - both
// built an identical Notification block differing only in title/wording.
final class PskRevealNotification
{
    public static function make(string $title, string $bodyIntro, PpskGroup $group, string $psk): Notification
    {
        return Notification::make()
            ->title($title)
            ->body(sprintf(
                "%s for %s:\n\n%s\n\nCopy it now. It cannot be displayed again.",
                $bodyIntro,
                $group->label,
                $psk,
            ))
            ->success()
            ->persistent()
            ->actions([CopyToClipboardAction::make($psk)]);
    }
}
