<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Illuminate\Support\Js;

// A notification action button that copies a value to the clipboard
// client-side, no server round trip. Used for the one-time PSK display
// (Section 14): the value never leaves this browser tab and is never
// logged, so a click-to-copy button is the only way to retrieve it after
// generation.
//
// Rendered as a solid button (not a plain link) so it reads clearly as
// the primary action on the notification. The actual copy logic lives in
// window.zonclaveCopyToClipboard (resources/views/filament/clipboard-
// script.blade.php, registered as a panel render hook), which also
// handles the insecure-HTTP fallback and fires the "Password copied" /
// failure toast - keeping this class a thin, testable wrapper around one
// JS call rather than duplicating that logic per call site.
final class CopyToClipboardAction
{
    public static function make(string $value, string $label = 'Copy password'): Action
    {
        return Action::make('copyToClipboard')
            ->label($label)
            ->icon('heroicon-o-clipboard-document')
            ->color('primary')
            ->button()
            ->tooltip('Copy the generated Wi-Fi password to your clipboard')
            // Js::from() safely encodes $value for embedding in a JS string
            // literal; $value is server-generated (PskGenerator), never
            // raw user input, but this stays safe regardless.
            ->alpineClickHandler('window.zonclaveCopyToClipboard('.Js::from($value).')');
    }
}
