<?php

namespace App\Filament\Resources\PpskGroups\Pages;

use App\Filament\Resources\PpskGroups\PpskGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPpskGroups extends ListRecords
{
    protected static string $resource = PpskGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
