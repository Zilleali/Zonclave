<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaleSessions\Pages;

use App\Filament\Resources\StaleSessions\StaleSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListStaleSessions extends ListRecords
{
    protected static string $resource = StaleSessionResource::class;

    // No header actions: sessions are read-only, written only by FreeRADIUS
    // (Section 16.6), so there is no "Create" button here.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
