<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivePpskUsers\Pages;

use App\Filament\Resources\ActivePpskUsers\ActivePpskUserResource;
use Filament\Resources\Pages\ListRecords;

class ListActivePpskUsers extends ListRecords
{
    protected static string $resource = ActivePpskUserResource::class;

    // No header actions: sessions are read-only, written only by FreeRADIUS
    // (Section 16.6), so there is no "Create" button here.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
