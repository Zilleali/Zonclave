<?php

declare(strict_types=1);

namespace App\Filament\Resources\SessionLogs\Pages;

use App\Filament\Resources\SessionLogs\SessionLogResource;
use Filament\Resources\Pages\ListRecords;

class ListSessionLogs extends ListRecords
{
    protected static string $resource = SessionLogResource::class;

    // No header actions: sessions are read-only, written only by FreeRADIUS
    // (Section 16.6), so there is no "Create" button here.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
