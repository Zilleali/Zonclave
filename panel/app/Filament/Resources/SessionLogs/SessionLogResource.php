<?php

declare(strict_types=1);

namespace App\Filament\Resources\SessionLogs;

use App\Filament\Resources\SessionLogs\Pages\ListSessionLogs;
use App\Filament\Resources\SessionLogs\Tables\SessionLogsTable;
use App\Models\RadiusAccounting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

// Read-only connected-device / session history (CLAUDE.md Section 16.6,
// pulled into Phase 1 2026-07-27). Sourced from FreeRADIUS's own radacct
// table, which the panel never writes to (App\Models\RadiusAccounting) - so,
// like AdminLogResource, this has no create or edit page and no route for
// either.
class SessionLogResource extends Resource
{
    protected static ?string $model = RadiusAccounting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWifi;

    protected static ?string $modelLabel = 'session';

    protected static ?string $pluralModelLabel = 'Sessions';

    // Grouped in the sidebar with Active PPSK Users / Stale Sessions /
    // Inactive PPSK Users (CLAUDE.md Section 16.6, client request
    // 2026-07-28) - this resource is the unfiltered "All Sessions" view,
    // the other three are RadiusAccounting::scopeWithStatus() slices of the
    // exact same table.
    protected static ?string $navigationLabel = 'All Sessions';

    protected static string|UnitEnum|null $navigationGroup = 'Sessions';

    protected static ?string $recordTitleAttribute = 'username';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return SessionLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSessionLogs::route('/'),
        ];
    }
}
