<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaleSessions;

use App\Enums\SessionStatus;
use App\Filament\Resources\SessionLogs\Tables\SessionLogsTable;
use App\Filament\Resources\StaleSessions\Pages\ListStaleSessions;
use App\Models\RadiusAccounting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

// "Stale Sessions" - no clean Acct-Stop was ever received, but the device
// has gone quiet for 15+ minutes (CLAUDE.md Section 16.6, client request
// 2026-07-28): a distinct, actionable state (usually a dead or
// out-of-range device) that shouldn't be silently folded into either
// Active or Inactive. A RadiusAccounting::scopeWithStatus(Stale) slice of
// the same table SessionLogResource ("All Sessions") uses.
class StaleSessionResource extends Resource
{
    protected static ?string $model = RadiusAccounting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $modelLabel = 'stale session';

    protected static ?string $pluralModelLabel = 'Stale Sessions';

    protected static ?string $navigationLabel = 'Stale Sessions';

    protected static string|UnitEnum|null $navigationGroup = 'Sessions';

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?int $navigationSort = 7;

    public static function table(Table $table): Table
    {
        return SessionLogsTable::configure($table, SessionStatus::Stale);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = RadiusAccounting::query()->withStatus(SessionStatus::Stale)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaleSessions::route('/'),
        ];
    }
}
