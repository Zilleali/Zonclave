<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PpskStatus;
use App\Models\PpskGroup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// Dashboard inventory counts, per CLAUDE.md Section 16.2 ("Phase 1
// dashboard is inventory only") and Section 23.3 ("no polling, on-demand
// loads only"). Polling is explicitly disabled below; this only reflects
// state as of the page load.
class PpskStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $total = PpskGroup::query()->count();
        $active = PpskGroup::query()->where('status', PpskStatus::Active)->count();
        $disabled = PpskGroup::query()->where('status', PpskStatus::Disabled)->count();

        return [
            Stat::make('Total PPSK groups', $total),
            Stat::make('Active', $active)->color('success'),
            Stat::make('Disabled', $disabled)->color('gray'),
        ];
    }
}
