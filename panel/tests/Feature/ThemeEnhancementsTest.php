<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The UI polish render hook (box-shadow/transition/animation, sidebar
// collapse) must actually reach the rendered page.
class ThemeEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_theme_enhancements_style_block_is_registered_on_panel_pages(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/ppsk-groups')
            ->assertOk()
            ->assertSee('fi-fade-in', false);
    }
}
