<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\PpskGroup;
use Filament\Notifications\Notification;

// The persistent, one-time credential display notification (Section 14),
// shared between the PPSK create action and the regenerate-password action
// - both build an identical Notification block differing only in
// title/wording. Shows and offers separate copy buttons for both the
// RADIUS username and the PSK (Sancover UX request 2026-07-17) - the
// username alone is not a secret, but showing it alongside the password
// here saves a trip back to the list to look it up before the first
// connection test.
final class PskRevealNotification
{
    public static function make(string $title, string $bodyIntro, PpskGroup $group, string $psk): Notification
    {
        return Notification::make()
            ->title($title)
            ->body(sprintf(
                "%s for %s:\n\nUsername: %s\nPassword: %s\n\nCopy them now. The password cannot be displayed again.",
                $bodyIntro,
                $group->label,
                $group->radius_username,
                $psk,
            ))
            ->success()
            ->persistent()
            ->actions([
                CopyToClipboardAction::make($group->radius_username, 'Copy username'),
                CopyToClipboardAction::make($psk, 'Copy password'),
            ]);
    }
}
