<?php

declare(strict_types=1);

namespace App\Filament\Resources\PpskGroups;

use App\Filament\Resources\PpskGroups\Pages\ListPpskGroups;
use App\Filament\Resources\PpskGroups\Schemas\PpskGroupForm;
use App\Filament\Resources\PpskGroups\Tables\PpskGroupsTable;
use App\Models\PpskGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PpskGroupResource extends Resource
{
    protected static ?string $model = PpskGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $modelLabel = 'PPSK';

    protected static ?string $pluralModelLabel = 'PPSK groups';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return PpskGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PpskGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    // Create and Edit are modal actions (ListPpskGroups header, PpskGroupsTable
    // row action), not dedicated pages - Sancover UX request 2026-07-17,
    // see CLAUDE.md Section 16.3. No 'create'/'edit' routes here on purpose.
    public static function getPages(): array
    {
        return [
            'index' => ListPpskGroups::route('/'),
        ];
    }
}
