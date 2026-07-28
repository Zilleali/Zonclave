<?php

declare(strict_types=1);

namespace App\Filament\Resources\Backups;

use App\Filament\Resources\Backups\Pages\ListBackups;
use App\Filament\Resources\Backups\Tables\BackupsTable;
use App\Models\Backup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

// Full-database backups (CLAUDE.md Section 16.8). Created only through
// App\Services\BackupService - on demand (this resource's "Backup now"
// header action) or on the daily schedule (`zonclave:backup`). No create
// form; canCreate() stays false since a backup isn't something an admin
// fills fields in for.
class BackupResource extends Resource
{
    protected static ?string $model = Backup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $modelLabel = 'backup';

    protected static ?string $pluralModelLabel = 'Backups';

    protected static ?string $recordTitleAttribute = 'filename';

    // Grouped under "System" (client request 2026-07-28) - see
    // ProvisionedVlanResource's own comment for why.
    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 7;

    public static function table(Table $table): Table
    {
        return BackupsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBackups::route('/'),
        ];
    }
}
