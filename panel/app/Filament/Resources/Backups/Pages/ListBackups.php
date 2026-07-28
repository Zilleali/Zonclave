<?php

declare(strict_types=1);

namespace App\Filament\Resources\Backups\Pages;

use App\Filament\Resources\Backups\BackupResource;
use App\Services\BackupService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use RuntimeException;

class ListBackups extends ListRecords
{
    protected static string $resource = BackupResource::class;

    // A plain Action, not CreateAction - there's no form, "Backup now" just
    // triggers BackupService::create() directly (same pattern
    // PpskGroupsTable's toggleStatus action already uses for a no-form
    // action). A failure (most likely: this environment isn't Postgres)
    // shows the actual reason rather than a generic error.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupNow')
                ->label('Backup now')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->requiresConfirmation()
                ->modalDescription('Creates a full database backup immediately, in addition to the automatic daily one.')
                ->action(function (): void {
                    try {
                        app(BackupService::class)->create(
                            Filament::auth()->user()?->getAttribute('email'),
                        );

                        Notification::make()->success()->title('Backup created')->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()->title('Backup failed')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
