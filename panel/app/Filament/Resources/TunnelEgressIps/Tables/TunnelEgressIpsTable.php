<?php

declare(strict_types=1);

namespace App\Filament\Resources\TunnelEgressIps\Tables;

use App\Domain\VlanPlan;
use App\Models\TunnelEgressIp;
use App\Services\TunnelEgressIpService;
use Carbon\CarbonInterface;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// Per-VLAN egress IP reference (CLAUDE.md Section 16.6). One row per
// currently-provisioned VLAN, edited one at a time via the modal Edit action
// - same "modal, not a dedicated page" convention PpskGroupsTable already
// uses. No create/delete: rows are derived from VlanPlan, not chosen freely.
class TunnelEgressIpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('vlan_id', 'asc')
            ->columns([
                TextColumn::make('vlan_id')->label('VLAN')->sortable(),
                TextColumn::make('wireguard_interface')
                    ->label('WireGuard tunnel')
                    ->state(fn (TunnelEgressIp $record): string => VlanPlan::forVlan($record->vlan_id)['wireguard_interface'])
                    ->toggleable(),
                TextColumn::make('egress_ip')
                    ->label('Known egress IP')
                    ->placeholder('Not set')
                    ->toggleable(),
                TextColumn::make('checked_at')
                    ->label('Last confirmed')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Never')
                    ->description(fn (?CarbonInterface $state): ?string => $state?->diffForHumans())
                    ->toggleable(),
                TextColumn::make('updated_by')
                    ->label('Confirmed by')
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        TextInput::make('egress_ip')
                            ->label('Egress IP')
                            ->maxLength(45)
                            ->helperText('Manually confirmed value, not a live measurement. Update it whenever you check this tunnel\'s actual public IP.'),
                    ])
                    ->using(function (TunnelEgressIp $record, array $data): TunnelEgressIp {
                        $admin = Filament::auth()->user()?->getAttribute('email');

                        return app(TunnelEgressIpService::class)->update(
                            $record->vlan_id,
                            filled($data['egress_ip'] ?? null) ? (string) $data['egress_ip'] : null,
                            $admin,
                        );
                    }),
            ])
            ->toolbarActions([]);
    }
}
