<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

// Audit trail list (CLAUDE.md Section 17). Newest first, no row or bulk
// actions: this is a read-only history, not something the panel edits or
// deletes (Section 23.1's logging counterpart). No polling (Section 23.3);
// on-demand loads only, same as the PPSK list.
class AdminLogsTable
{
    private const ACTIONS = [
        'admin_login_success' => 'Admin login success',
        'admin_login_failed' => 'Admin login failed',
        'ppsk_created' => 'PPSK created',
        'ppsk_updated' => 'PPSK updated',
        'ppsk_enabled' => 'PPSK enabled',
        'ppsk_disabled' => 'PPSK disabled',
        'ppsk_deleted' => 'PPSK deleted',
        'ppsk_password_regenerated' => 'PPSK password regenerated',
    ];

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
                    ->formatStateUsing(fn (string $state): string => self::ACTIONS[$state] ?? $state)
                    ->color(fn (string $state): string => match (true) {
                        str_contains($state, 'failed') => 'danger',
                        str_contains($state, 'deleted') => 'danger',
                        str_contains($state, 'disabled') => 'warning',
                        default => 'success',
                    }),
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
                SelectFilter::make('action')->options(self::ACTIONS),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
