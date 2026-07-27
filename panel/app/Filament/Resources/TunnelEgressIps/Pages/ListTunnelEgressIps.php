<?php

declare(strict_types=1);

namespace App\Filament\Resources\TunnelEgressIps\Pages;

use App\Filament\Resources\TunnelEgressIps\TunnelEgressIpResource;
use Filament\Resources\Pages\ListRecords;

class ListTunnelEgressIps extends ListRecords
{
    protected static string $resource = TunnelEgressIpResource::class;

    // No header actions: the row set is derived from VlanPlan's provisioned
    // range (see TunnelEgressIpResource::getEloquentQuery()), not something
    // an admin creates freely.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
