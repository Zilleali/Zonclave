<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The global window.zonclaveCopyToClipboard helper (Section 14) must
// actually reach the rendered page for CopyToClipboardAction's click
// handler to have anything to call.
class ClipboardScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_clipboard_helper_script_is_registered_on_panel_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/ppsk-groups')
            ->assertOk()
            ->assertSee('window.zonclaveCopyToClipboard', false);
    }
}
