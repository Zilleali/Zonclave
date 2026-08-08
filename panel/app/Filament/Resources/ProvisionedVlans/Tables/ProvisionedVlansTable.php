<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProvisionedVlans\Tables;

use App\Domain\VlanPlan;
use App\Exceptions\VlanInUseException;
use App\Models\PpskGroup;
use App\Models\ProvisionedVlan;
use App\Services\ProvisionedVlanService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// The provisioned VLAN list (CLAUDE.md Section 16.5). Subnet, tunnel, and
// gateway are always derived from the VLAN ID (VlanPlan::forVlan()), never
// stored or edited directly. Delete is blocked, not silently allowed, when
// a PPSK still references the VLAN (ProvisionedVlanService::deprovision()) -
// the row stays and a notification names what's in the way.
class ProvisionedVlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('vlan_id', 'asc')
            ->columns([
                TextColumn::make('vlan_id')->label('VLAN')->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('subnet')
                    ->state(fn (ProvisionedVlan $record): string => VlanPlan::forVlan($record->vlan_id)['subnet'])
                    ->toggleable(),
                TextColumn::make('wireguard_interface')
                    ->label('WireGuard tunnel')
                    ->state(fn (ProvisionedVlan $record): string => VlanPlan::forVlan($record->vlan_id)['wireguard_interface'])
                    ->toggleable(),
                TextColumn::make('wireguard_gateway')
                    ->label('Gateway')
                    ->state(fn (ProvisionedVlan $record): string => VlanPlan::forVlan($record->vlan_id)['wireguard_gateway'])
                    ->toggleable(),
                TextColumn::make('ppsk_count')
                    ->label('PPSKs using it')
                    ->state(fn (ProvisionedVlan $record): int => PpskGroup::query()->where('vlan_id', $record->vlan_id)->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->toggleable(),
                TextColumn::make('created_at')->label('Added')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Rename')
                    ->modalHeading(fn (ProvisionedVlan $record): string => sprintf('Rename VLAN %d', $record->vlan_id))
                    ->schema([
                        TextInput::make('name')
                            ->label('Friendly name')
                            ->maxLength(64)
                            ->helperText('Optional - a plain-language label so you can tell VLANs apart at a glance (e.g. "Office main", "France exits").'),
                    ])
                    ->using(function (ProvisionedVlan $record, array $data): ProvisionedVlan {
                        return app(ProvisionedVlanService::class)->rename(
                            $record,
                            $data['name'] ?: null,
                            Filament::auth()->user()?->getAttribute('email'),
                        );
                    }),
                DeleteAction::make()
                    ->successNotification(null)
                    ->using(function (ProvisionedVlan $record): void {
                        try {
                            app(ProvisionedVlanService::class)->deprovision(
                                $record,
                                Filament::auth()->user()?->getAttribute('email'),
                            );
                        } catch (VlanInUseException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete this VLAN')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }
}
