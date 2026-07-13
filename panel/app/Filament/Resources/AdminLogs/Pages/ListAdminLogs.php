<?php

declare(strict_types=1);

namespace App\Filament\Resources\AdminLogs\Pages;

use App\Filament\Resources\AdminLogs\AdminLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminLogs extends ListRecords
{
    protected static string $resource = AdminLogResource::class;

    // No header actions: the audit trail is read-only (Section 17), so
    // there is no "Create" button here.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
