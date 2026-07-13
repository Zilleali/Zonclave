<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Filament\Support\CopyToClipboardAction;
use Tests\TestCase;

// The click-to-copy notification action for the one-time PSK display
// (Section 14). Confirms the value is embedded safely (no raw
// interpolation) and the button carries the expected label.
class CopyToClipboardActionTest extends TestCase
{
    public function test_click_handler_writes_the_value_to_the_clipboard(): void
    {
        $action = CopyToClipboardAction::make('abcdefghjkmnpqrstuvwxyz2');

        $this->assertSame(
            "navigator.clipboard.writeText('abcdefghjkmnpqrstuvwxyz2')",
            $action->getCustomAlpineClickHandler(),
        );
    }

    public function test_value_containing_quotes_is_encoded_safely(): void
    {
        // PskGenerator output is always alphanumeric (Section 14), but the
        // handler must not be naively string-interpolated regardless.
        $action = CopyToClipboardAction::make('a"b\'c</script>');

        $handler = $action->getCustomAlpineClickHandler();

        $this->assertStringNotContainsString('</script>', $handler);
        $this->assertStringNotContainsString('writeText(a"b', $handler);
    }

    public function test_default_label(): void
    {
        $this->assertSame('Copy password', CopyToClipboardAction::make('x')->getLabel());
    }
}
