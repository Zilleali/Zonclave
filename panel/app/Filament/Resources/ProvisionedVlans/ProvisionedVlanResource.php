<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProvisionedVlans;

use App\Filament\Resources\ProvisionedVlans\Pages\ListProvisionedVlans;
use App\Filament\Resources\ProvisionedVlans\Tables\ProvisionedVlansTable;
use App\Models\ProvisionedVlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

// Manages the provisioned VLAN registry (CLAUDE.md Section 16.5, pulled
// into Phase 1 2026-07-28). Adding a VLAN here is what makes it selectable
// on the PPSK create/edit form (App\Domain\VlanPlan::options()) - no more
// editing .env and running a CLI command to add one. Deleting one is
// blocked if any PPSK still references it (ProvisionedVlanService).
class ProvisionedVlanResource extends Resource
{
    protected static ?string $model = ProvisionedVlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $modelLabel = 'VLAN';

    protected static ?string $pluralModelLabel = 'VLANs';

    protected static ?string $recordTitleAttribute = 'vlan_id';

    // Grouped under "System" alongside Tunnel Egress IPs, Backups, and
    // Admin Log (client request 2026-07-28) - so the "Sessions" group can
    // sit between PPSK Groups and this one. Filament always renders all
    // ungrouped items as a single block before any named group, so this
    // block could not otherwise be reordered.
    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return ProvisionedVlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProvisionedVlans::route('/'),
        ];
    }
}
