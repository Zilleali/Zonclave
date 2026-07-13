<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Filament\Support\CopyToClipboardAction;
use Tests\TestCase;

// The click-to-copy notification action for the one-time PSK display
// (Section 14). Confirms the value is embedded safely (no raw
// interpolation), the button carries the expected label, and it renders
// as a solid button rather than a plain link.
class CopyToClipboardActionTest extends TestCase
{
    public function test_click_handler_calls_the_global_copy_helper(): void
    {
        $action = CopyToClipboardAction::make('abcdefghjkmnpqrstuvwxyz2');

        $this->assertSame(
            "window.zonclaveCopyToClipboard('abcdefghjkmnpqrstuvwxyz2')",
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

    public function test_renders_as_a_button_not_a_plain_link(): void
    {
        $this->assertTrue(CopyToClipboardAction::make('x')->isButton());
    }

    public function test_has_a_tooltip(): void
    {
        $this->assertNotNull(CopyToClipboardAction::make('x')->getTooltip());
    }
}
