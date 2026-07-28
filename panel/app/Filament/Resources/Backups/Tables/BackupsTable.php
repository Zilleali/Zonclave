<?php

declare(strict_types=1);

namespace App\Filament\Resources\Backups\Tables;

use App\Models\Backup;
use App\Services\BackupService;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

// Backup list (CLAUDE.md Section 16.8). Newest first. Download opens a real
// route (Storage::download(), panel/routes/web.php) rather than a Filament
// action, since a Livewire AJAX call can't trigger a browser file download.
class BackupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('filename')->searchable(),
                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->state(fn (Backup $record): string => Number::fileSize($record->size_bytes))
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->description(fn (CarbonInterface $state): string => $state->diffForHumans())
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Backup $record): string => route('backups.download', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->using(function (Backup $record): void {
                        app(BackupService::class)->delete(
                            $record,
                            Filament::auth()->user()?->getAttribute('email'),
                        );
                    }),
            ])
            ->toolbarActions([]);
    }
}
