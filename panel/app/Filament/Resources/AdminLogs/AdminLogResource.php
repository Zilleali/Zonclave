<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminLogs;

use App\Filament\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Filament\Resources\AdminLogs\Tables\AdminLogsTable;
use App\Models\AdminLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

// Read-only audit trail (CLAUDE.md Section 17). admin_log is append-only,
// written only through AdminLogRepository (Section 23.1's logging
// counterpart); this resource has no create or edit page and no route for
// either, so the panel itself cannot alter history.
class AdminLogResource extends Resource
{
    protected static ?string $model = AdminLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'admin log entry';

    protected static ?string $pluralModelLabel = 'Admin Log';

    protected static ?string $recordTitleAttribute = 'action';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return AdminLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminLogs::route('/'),
        ];
    }
}
