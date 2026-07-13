<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PpskStatus;
use App\Filament\Widgets\PpskStatsOverview;
use App\Models\PpskGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// Dashboard inventory counts, per CLAUDE.md Section 16.2.
class PpskStatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_polling_is_disabled(): void
    {
        // Section 23.3: never add polling or timed auto-refresh.
        $widget = new PpskStatsOverview;
        $ref = new \ReflectionMethod($widget, 'getPollingInterval');
        $ref->setAccessible(true);

        $this->assertNull($ref->invoke($widget));
    }

    public function test_stats_reflect_group_counts_by_status(): void
    {
        $this->actingAs(User::factory()->create());

        PpskGroup::factory()->count(2)->create(['status' => PpskStatus::Active]);
        PpskGroup::factory()->create(['status' => PpskStatus::Disabled]);

        Livewire::test(PpskStatsOverview::class)
            ->assertSee('Total PPSK groups')
            ->assertSee('3')
            ->assertSee('Active')
            ->assertSee('2')
            ->assertSee('Disabled')
            ->assertSee('1');
    }
}
