<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\AboutDeveloper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// In-panel counterpart to the public /about page (client request
// 2026-07-28) - static content, no model, just confirms it renders and
// carries the same social links every other placement uses.
class AboutDeveloperPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_is_reachable_and_shows_the_developer_bio(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(AboutDeveloper::getUrl())
            ->assertOk()
            ->assertSee('ZILL E ALI')
            ->assertSee('Developer &amp; Network Engineer', false);
    }

    public function test_page_shows_every_configured_social_link(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(AboutDeveloper::getUrl());

        foreach (config('socials') as $social) {
            $response->assertSee($social['url'], false);
        }
    }
}
