<?php

declare(strict_types=1);

namespace App\Filament\Resources\PpskGroups\Pages;

use App\Filament\Resources\PpskGroups\PpskGroupResource;
use App\Filament\Support\CopyToClipboardAction;
use App\Models\PpskGroup;
use App\Services\PpskService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPpskGroup extends EditRecord
{
    protected static string $resource = PpskGroupResource::class;

    // Updates go through PpskService (Section 23.1): a VLAN change
    // re-derives subnet/tunnel/gateway and reprojects the RADIUS rows.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof PpskGroup);

        return app(PpskService::class)->update(
            $record,
            (string) $data['label'],
            (int) $data['vlan_id'],
            Filament::auth()->user()?->getAttribute('email'),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regeneratePassword')
                ->label('Regenerate password')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('The current Wi-Fi password stops working immediately. The new one is shown once.')
                ->action(function (): void {
                    $record = $this->getRecord();
                    assert($record instanceof PpskGroup);

                    $result = app(PpskService::class)->regeneratePassword(
                        $record,
                        Filament::auth()->user()?->getAttribute('email'),
                    );

                    Notification::make()
                        ->title('Password regenerated - shown once')
                        ->body(sprintf(
                            "New Wi-Fi password for %s:\n\n%s\n\nCopy it now. It cannot be displayed again.",
                            $result['group']->label,
                            $result['psk'],
                        ))
                        ->success()
                        ->persistent()
                        ->actions([CopyToClipboardAction::make($result['psk'])])
                        ->send();
                }),

            DeleteAction::make()
                ->using(function (PpskGroup $record): void {
                    app(PpskService::class)->delete(
                        $record,
                        Filament::auth()->user()?->getAttribute('email'),
                    );
                }),
        ];
    }
}
