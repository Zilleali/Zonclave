<?php

declare(strict_types=1);

namespace App\Filament\Resources\PpskGroups\Tables;

use App\Enums\PpskStatus;
use App\Filament\Resources\PpskGroups\Schemas\PpskGroupForm;
use App\Filament\Support\PskRevealNotification;
use App\Models\PpskGroup;
use App\Services\PpskService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

// PPSK inventory list, per CLAUDE.md Section 16.2. Inventory only in
// Phase 1: no live connection status, no polling, on-demand loads only
// (Section 23.3). Every mutation goes through PpskService; there are no
// bulk actions on purpose, so nothing can bypass the Section 23.1 path.
// Edit is a modal row action, not a dedicated page (Sancover UX request
// 2026-07-17, see CLAUDE.md Section 16.3) - PpskGroupResource::getPages()
// registers no 'edit' route, so EditAction renders as a modal by default.
class PpskGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('radius_username')
                    ->label('RADIUS username')
                    ->copyable()
                    ->copyMessage('RADIUS username copied')
                    ->copyMessageDuration(2000),
                TextColumn::make('vlan_id')->label('VLAN')->sortable(),
                TextColumn::make('wireguard_interface')->label('WireGuard tunnel'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PpskStatus $state): string => $state->label())
                    ->color(fn (PpskStatus $state): string => match ($state) {
                        PpskStatus::Active => 'success',
                        PpskStatus::Disabled => 'gray',
                        PpskStatus::Provisioning => 'warning',
                        PpskStatus::Error => 'danger',
                    }),
                TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('vlan_id')
                    ->label('VLAN')
                    ->options(fn (): array => PpskGroup::query()->distinct()->orderBy('vlan_id')
                        ->pluck('vlan_id', 'vlan_id')->map(fn (int $v): string => (string) $v)->all()),
                SelectFilter::make('status')
                    ->options(collect(PpskStatus::cases())->mapWithKeys(
                        fn (PpskStatus $s): array => [$s->value => $s->label()],
                    )->all()),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema(PpskGroupForm::labelAndVlanFields())
                    ->using(function (PpskGroup $record, array $data): PpskGroup {
                        return app(PpskService::class)->update(
                            $record,
                            (string) $data['label'],
                            (int) $data['vlan_id'],
                            Filament::auth()->user()?->getAttribute('email'),
                        );
                    }),

                Action::make('toggleStatus')
                    ->label(fn (PpskGroup $record): string => $record->status === PpskStatus::Active ? 'Disable' : 'Enable')
                    ->icon(fn (PpskGroup $record): string => $record->status === PpskStatus::Active ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                    ->color(fn (PpskGroup $record): string => $record->status === PpskStatus::Active ? 'warning' : 'success')
                    ->visible(fn (PpskGroup $record): bool => in_array($record->status, [PpskStatus::Active, PpskStatus::Disabled], true))
                    ->action(function (PpskGroup $record): void {
                        $service = app(PpskService::class);
                        $admin = Filament::auth()->user()?->getAttribute('email');

                        if ($record->status === PpskStatus::Active) {
                            $service->disable($record, $admin);
                        } else {
                            $service->enable($record, $admin);
                        }
                    }),

                Action::make('regeneratePassword')
                    ->label('Regenerate password')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->schema(PpskGroupForm::passwordFields())
                    ->modalDescription('The current Wi-Fi password stops working immediately. The new one is shown once.')
                    ->action(function (PpskGroup $record, array $data): void {
                        $manualPsk = ($data['password_source'] ?? 'generate') === 'manual'
                            ? (string) ($data['manual_password'] ?? '')
                            : null;

                        $result = app(PpskService::class)->regeneratePassword(
                            $record,
                            Filament::auth()->user()?->getAttribute('email'),
                            $manualPsk,
                        );

                        PskRevealNotification::make(
                            'Password regenerated - credentials shown once',
                            'New Wi-Fi credentials',
                            $result['group'],
                            $result['psk'],
                        )->send();
                    }),

                DeleteAction::make()
                    ->using(function (PpskGroup $record): void {
                        app(PpskService::class)->delete(
                            $record,
                            Filament::auth()->user()?->getAttribute('email'),
                        );
                    }),
            ])
            ->toolbarActions([]);
    }
}
