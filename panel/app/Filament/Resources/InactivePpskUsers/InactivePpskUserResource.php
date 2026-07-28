<?php

declare(strict_types=1);

namespace App\Filament\Resources\InactivePpskUsers;

use App\Enums\SessionStatus;
use App\Filament\Resources\InactivePpskUsers\Pages\ListInactivePpskUsers;
use App\Filament\Resources\SessionLogs\Tables\SessionLogsTable;
use App\Models\RadiusAccounting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

// "Inactive PPSK Users" - sessions with a clean recorded disconnect
// (CLAUDE.md Section 16.6, client request 2026-07-28). Not a separate data
// source: a RadiusAccounting::scopeWithStatus(Disconnected) slice of the
// same table SessionLogResource ("All Sessions") uses. No navigation badge
// here on purpose - unlike Active/Stale, this history is unbounded and a
// large historical count in the sidebar would be noise, not a signal.
class InactivePpskUserResource extends Resource
{
    protected static ?string $model = RadiusAccounting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignalSlash;

    protected static ?string $modelLabel = 'inactive session';

    protected static ?string $pluralModelLabel = 'Inactive PPSK Users';

    protected static ?string $navigationLabel = 'Inactive PPSK Users';

    protected static string|UnitEnum|null $navigationGroup = 'Sessions';

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?int $navigationSort = 8;

    public static function table(Table $table): Table
    {
        return SessionLogsTable::configure($table, SessionStatus::Disconnected);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInactivePpskUsers::route('/'),
        ];
    }
}
