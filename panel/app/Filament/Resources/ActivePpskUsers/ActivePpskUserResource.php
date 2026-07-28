<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivePpskUsers;

use App\Enums\SessionStatus;
use App\Filament\Resources\ActivePpskUsers\Pages\ListActivePpskUsers;
use App\Filament\Resources\SessionLogs\Tables\SessionLogsTable;
use App\Models\RadiusAccounting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

// "Active PPSK Users" - devices connected right now (CLAUDE.md Section 16.6,
// client request 2026-07-28). Not a separate data source: a
// RadiusAccounting::scopeWithStatus(Connected) slice of the exact same
// radacct-backed table SessionLogResource ("All Sessions") uses, reusing
// SessionLogsTable so the two views can never drift apart in columns or
// behavior.
class ActivePpskUserResource extends Resource
{
    protected static ?string $model = RadiusAccounting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $modelLabel = 'active session';

    protected static ?string $pluralModelLabel = 'Active PPSK Users';

    protected static ?string $navigationLabel = 'Active PPSK Users';

    protected static string|UnitEnum|null $navigationGroup = 'Sessions';

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return SessionLogsTable::configure($table, SessionStatus::Connected);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = RadiusAccounting::query()->withStatus(SessionStatus::Connected)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivePpskUsers::route('/'),
        ];
    }
}
