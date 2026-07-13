<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PpskStatus;
use App\Filament\Resources\PpskGroups\PpskGroupResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// Dashboard inventory counts, per CLAUDE.md Section 16.2 ("Phase 1
// dashboard is inventory only") and Section 23.3 ("no polling, on-demand
// loads only"). Polling is explicitly disabled below; this only reflects
// state as of the page load. Cards link through to the PPSK list,
// pre-filtered by status where relevant, rather than duplicating it.
class PpskStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        // One query for all three counts (was three separate COUNT(*)
        // calls). DB::table() bypasses the model's enum cast so the keys
        // here are the plain status strings, matching PpskStatus::value.
        $countsByStatus = DB::table('ppsk_groups')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (int) $countsByStatus->sum();
        $active = (int) ($countsByStatus[PpskStatus::Active->value] ?? 0);
        $disabled = (int) ($countsByStatus[PpskStatus::Disabled->value] ?? 0);

        // Percentages are of each other (Active + Disabled), not of $total.
        // PpskStatus also defines Provisioning and Error (Section 7, for
        // Phase 2 automation); those rows still count toward $total on the
        // card above but have no card of their own here, so dividing by
        // $total would make these two percentages silently stop summing to
        // 100% the moment such a row exists. Dividing by their own sum
        // keeps that guarantee regardless of what else is in the registry.
        $knownStatusTotal = $active + $disabled;

        return [
            Stat::make('Total PPSK groups', $total)
                ->chart($this->cumulativeTotalByDay())
                ->url(PpskGroupResource::getUrl()),

            Stat::make('Active', $active)
                ->color('success')
                ->description($this->percentOf($active, $knownStatusTotal).' of Active + Disabled')
                ->url($this->filteredByStatus(PpskStatus::Active)),

            Stat::make('Disabled', $disabled)
                ->color('gray')
                ->description($this->percentOf($disabled, $knownStatusTotal).' of Active + Disabled')
                ->url($this->filteredByStatus(PpskStatus::Disabled)),
        ];
    }

    // Registry growth over the last 7 days. Status counts have no
    // historical trend to show honestly in Phase 1 (no status-change
    // history is kept, see Section 13), so only the total gets a chart.
    // One query for both the window's daily counts and the pre-window
    // baseline (was two queries: a filtered day-group plus a separate
    // count() for everything before it).
    /** @return list<float> */
    private function cumulativeTotalByDay(): array
    {
        $since = Carbon::today()->subDays(6);

        $countsByDay = DB::table('ppsk_groups')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->pluck('count', 'day');

        $runningTotal = $countsByDay
            ->filter(fn (int $count, string $day): bool => $day < $since->toDateString())
            ->sum();

        return collect(range(0, 6))
            ->map(function (int $daysAgo) use ($since, $countsByDay, &$runningTotal): float {
                $day = $since->copy()->addDays($daysAgo)->toDateString();
                $runningTotal += (int) ($countsByDay[$day] ?? 0);

                return (float) $runningTotal;
            })
            ->all();
    }

    private function percentOf(int $part, int $total): string
    {
        return $total > 0 ? round(($part / $total) * 100).'%' : '0%';
    }

    private function filteredByStatus(PpskStatus $status): string
    {
        return PpskGroupResource::getUrl().'?'.http_build_query([
            'filters' => ['status' => ['value' => $status->value]],
        ]);
    }
}
