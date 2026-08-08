<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProvisionedVlans\Pages;

use App\Filament\Resources\ProvisionedVlans\ProvisionedVlanResource;
use App\Models\ProvisionedVlan;
use App\Services\ProvisionedVlanService;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListProvisionedVlans extends ListRecords
{
    protected static string $resource = ProvisionedVlanResource::class;

    // A modal action, not a dedicated Create page - same convention
    // PpskGroupsTable's CreateAction already uses. Subnet, tunnel, and
    // gateway are never entered here; they're derived from the VLAN ID the
    // moment it's picked anywhere else in the panel (App\Domain\VlanPlan).
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->schema([
                    TextInput::make('vlan_id')
                        ->label('VLAN ID')
                        ->numeric()
                        ->integer()
                        ->minValue((int) config('zonclave.vlan_base'))
                        ->maxValue(4094)
                        ->required()
                        ->unique(table: 'provisioned_vlans', column: 'vlan_id')
                        ->helperText(sprintf(
                            '%d to 4094 (802.1Q range). Subnet, WireGuard tunnel, and gateway names are derived automatically.',
                            (int) config('zonclave.vlan_base'),
                        )),
                    TextInput::make('name')
                        ->label('Friendly name')
                        ->maxLength(64)
                        ->helperText('Optional - a plain-language label so you can tell VLANs apart at a glance (e.g. "Office main", "France exits"). Purely a display label, no effect on subnet/tunnel/gateway naming.'),
                ])
                ->using(fn (array $data): ProvisionedVlan => app(ProvisionedVlanService::class)->provision(
                    (int) $data['vlan_id'],
                    $data['name'] ?: null,
                    Filament::auth()->user()?->getAttribute('email'),
                )),
        ];
    }
}
