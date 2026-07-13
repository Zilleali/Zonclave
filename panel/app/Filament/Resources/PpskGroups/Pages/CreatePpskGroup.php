<?php

declare(strict_types=1);

namespace App\Filament\Resources\PpskGroups\Pages;

use App\Filament\Resources\PpskGroups\PpskGroupResource;
use App\Filament\Support\CopyToClipboardAction;
use App\Services\PpskService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePpskGroup extends CreateRecord
{
    protected static string $resource = PpskGroupResource::class;

    // Creation goes through PpskService (Section 23.1), never through the
    // default Eloquent create. The generated PSK is shown once here and is
    // not retrievable afterwards; only regeneration issues a new one.
    protected function handleRecordCreation(array $data): Model
    {
        $result = app(PpskService::class)->create(
            (string) $data['label'],
            (int) $data['vlan_id'],
            (bool) ($data['enabled'] ?? true),
            Filament::auth()->user()?->getAttribute('email'),
        );

        Notification::make()
            ->title('PPSK created - password shown once')
            ->body(sprintf(
                "Wi-Fi password for %s:\n\n%s\n\nCopy it now. It cannot be displayed again.",
                $result['group']->label,
                $result['psk'],
            ))
            ->success()
            ->persistent()
            ->actions([CopyToClipboardAction::make($result['psk'])])
            ->send();

        return $result['group'];
    }

    protected function getCreatedNotification(): ?Notification
    {
        // The persistent PSK notification above replaces the default toast.
        return null;
    }
}
