<?php

declare(strict_types=1);

namespace App\Filament\Resources\InactivePpskUsers\Pages;

use App\Filament\Resources\InactivePpskUsers\InactivePpskUserResource;
use Filament\Resources\Pages\ListRecords;

class ListInactivePpskUsers extends ListRecords
{
    protected static string $resource = InactivePpskUserResource::class;

    // No header actions: sessions are read-only, written only by FreeRADIUS
    // (Section 16.6), so there is no "Create" button here.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
