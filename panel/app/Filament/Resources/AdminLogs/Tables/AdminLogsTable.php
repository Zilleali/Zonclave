<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminLogs\Tables;

use App\Enums\AdminLogAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

// Audit trail list (CLAUDE.md Section 17). Newest first, no row or bulk
// actions: this is a read-only history, not something the panel edits or
// deletes (Section 23.1's logging counterpart). No polling (Section 23.3);
// on-demand loads only, same as the PPSK list. Labels/colors come from
// AdminLogAction, the same enum PpskService and LogAuthenticationEvents
// write with, so this list can never drift out of sync with what's
// actually being logged.
class AdminLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('ts', 'desc')
            ->columns([
                TextColumn::make('ts')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AdminLogAction::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => AdminLogAction::tryFrom($state)?->color() ?? 'gray'),
                TextColumn::make('admin_user')
                    ->label('Admin')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('detail')
                    ->label('Detail')
                    ->placeholder('-')
                    ->searchable()
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')->options(
                    collect(AdminLogAction::cases())->mapWithKeys(
                        fn (AdminLogAction $action): array => [$action->value => $action->label()],
                    )->all(),
                ),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
