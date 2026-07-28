<?php

declare(strict_types=1);

namespace App\Filament\Resources\TunnelEgressIps;

use App\Domain\VlanPlan;
use App\Filament\Resources\TunnelEgressIps\Pages\ListTunnelEgressIps;
use App\Filament\Resources\TunnelEgressIps\Tables\TunnelEgressIpsTable;
use App\Models\TunnelEgressIp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

// Manually-maintained per-VLAN residential egress IP reference (CLAUDE.md
// Section 16.6). There is no OPNsense API integration yet (Section 19 is
// Phase 2), so this is a hand-confirmed value, not a live lookup - an admin
// updates it here whenever they check a tunnel's actual public IP, and the
// session log (SessionLogResource) displays it as "last known", not live.
//
// One row always exists per currently-provisioned VLAN (getEloquentQuery()
// lazily creates any missing ones), so the list never needs a migration or
// seeder re-run when ZONCLAVE_VLAN_MAX grows (Section 26.11's own history).
// There is no create or delete action: the row set is derived entirely from
// VlanPlan's provisioned range, never chosen freely.
class TunnelEgressIpResource extends Resource
{
    protected static ?string $model = TunnelEgressIp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $modelLabel = 'tunnel egress IP';

    protected static ?string $pluralModelLabel = 'Tunnel Egress IPs';

    protected static ?string $recordTitleAttribute = 'vlan_id';

    // Grouped under "System" (client request 2026-07-28) - see
    // ProvisionedVlanResource's own comment for why.
    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 6;

    public static function getEloquentQuery(): Builder
    {
        foreach (array_keys(VlanPlan::options()) as $vlanId) {
            TunnelEgressIp::query()->firstOrCreate(['vlan_id' => $vlanId]);
        }

        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return TunnelEgressIpsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTunnelEgressIps::route('/'),
        ];
    }
}
