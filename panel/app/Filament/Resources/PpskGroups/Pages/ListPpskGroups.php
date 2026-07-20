<?php

declare(strict_types=1);

namespace App\Filament\Resources\PpskGroups\Pages;

use App\Filament\Resources\PpskGroups\PpskGroupResource;
use App\Filament\Resources\PpskGroups\Schemas\PpskGroupForm;
use App\Filament\Support\PskRevealNotification;
use App\Models\PpskGroup;
use App\Services\PpskService;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListPpskGroups extends ListRecords
{
    protected static string $resource = PpskGroupResource::class;

    // A modal action, not a dedicated Create page (Sancover UX request
    // 2026-07-17, see CLAUDE.md Section 16.3) - PpskGroupResource::getPages()
    // registers no 'create' route, so this renders as a modal by default.
    // Creation still goes through PpskService (Section 23.1), never the
    // default Eloquent create; the generated-or-manual PSK is shown once via
    // PskRevealNotification and is not retrievable afterwards.
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->schema([
                    ...PpskGroupForm::labelAndVlanFields(),
                    ...PpskGroupForm::enabledField(),
                    ...PpskGroupForm::passwordFields(),
                    ...PpskGroupForm::usernameFields(),
                ])
                ->using(function (array $data): PpskGroup {
                    $manualPsk = ($data['password_source'] ?? 'generate') === 'manual'
                        ? (string) ($data['manual_password'] ?? '')
                        : null;
                    $manualUsername = ($data['username_source'] ?? 'generate') === 'manual'
                        ? (string) ($data['manual_username'] ?? '')
                        : null;

                    $result = app(PpskService::class)->create(
                        (string) $data['label'],
                        (int) $data['vlan_id'],
                        (bool) ($data['enabled'] ?? true),
                        Filament::auth()->user()?->getAttribute('email'),
                        $manualPsk,
                        $manualUsername,
                    );

                    PskRevealNotification::make(
                        'PPSK created - credentials shown once',
                        'Wi-Fi credentials',
                        $result['group'],
                        $result['psk'],
                    )->send();

                    return $result['group'];
                })
                ->successNotification(null),
        ];
    }
}
