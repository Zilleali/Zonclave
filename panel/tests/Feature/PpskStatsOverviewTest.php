<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PpskStatus;
use App\Filament\Resources\PpskGroups\Pages\ListPpskGroups;
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

    public function test_cards_link_to_the_ppsk_list_pre_filtered_by_status(): void
    {
        $widget = new PpskStatsOverview;
        $ref = new \ReflectionMethod($widget, 'getStats');
        $ref->setAccessible(true);

        [$total, $active, $disabled] = $ref->invoke($widget);

        $this->assertSame(url('/admin/ppsk-groups'), $total->getUrl());
        $this->assertSame(
            url('/admin/ppsk-groups').'?filters%5Bstatus%5D%5Bvalue%5D=active',
            $active->getUrl(),
        );
        $this->assertSame(
            url('/admin/ppsk-groups').'?filters%5Bstatus%5D%5Bvalue%5D=disabled',
            $disabled->getUrl(),
        );
    }

    public function test_total_card_has_a_growth_chart_and_status_cards_do_not(): void
    {
        $widget = new PpskStatsOverview;
        $ref = new \ReflectionMethod($widget, 'getStats');
        $ref->setAccessible(true);

        [$total, $active, $disabled] = $ref->invoke($widget);

        $this->assertCount(7, $total->getChart());
        $this->assertNull($active->getChart());
        $this->assertNull($disabled->getChart());
    }

    public function test_filtering_by_the_active_card_url_shows_only_active_groups(): void
    {
        $this->actingAs(User::factory()->create());

        $activeGroup = PpskGroup::factory()->create(['status' => PpskStatus::Active]);
        $disabledGroup = PpskGroup::factory()->create(['status' => PpskStatus::Disabled]);

        Livewire::test(ListPpskGroups::class)
            ->set('tableFilters', ['status' => ['value' => PpskStatus::Active->value]])
            ->assertCanSeeTableRecords([$activeGroup])
            ->assertCanNotSeeTableRecords([$disabledGroup]);
    }
}
