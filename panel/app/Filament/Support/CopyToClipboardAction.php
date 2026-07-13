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
final class CopyToClipboardAction
{
    public static function make(string $value, string $label = 'Copy password'): Action
    {
        return Action::make('copyToClipboard')
            ->label($label)
            ->icon('heroicon-o-clipboard')
            ->color('gray')
            // Js::from() safely encodes $value for embedding in a JS string
            // literal; $value is server-generated (PskGenerator), never
            // raw user input, but this stays safe regardless.
            ->alpineClickHandler('navigator.clipboard.writeText('.Js::from($value).')');
    }
}
