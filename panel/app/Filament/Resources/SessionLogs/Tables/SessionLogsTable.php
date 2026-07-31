<?php

declare(strict_types=1);

namespace App\Filament\Resources\SessionLogs\Tables;

use App\Domain\VlanPlan;
use App\Enums\SessionStatus;
use App\Models\PpskGroup;
use App\Models\RadiusAccounting;
use App\Models\TunnelEgressIp;
use App\Services\RadiusAccountingService;
use Carbon\CarbonInterface;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

// Connected-device / session list (CLAUDE.md Section 16.6). Newest first.
// Still a read-only view of FreeRADIUS's own radacct table in the sense
// that matters (Section 23.1's boundary): FreeRADIUS remains the sole
// writer of session data itself, the panel never creates or edits a row.
// Two deliberate, narrow exceptions added 2026-07-31 (client request):
// - Live polling (10s) - a scoped exception to Section 23.3's no-polling
//   rule, limited to these four Sessions pages only, not the rest of the
//   panel. Filament's poll() re-fetches the table's data via AJAX only -
//   no page reload, no navigation - so "live" here never means a
//   disruptive full-page refresh.
// - A DeleteAction - lets an admin clear out a stale/irrelevant row
//   (App\Services\RadiusAccountingService, the only path that deletes a
//   radacct row, logged to admin_log same as every other admin action).
class SessionLogsTable
{
    // $scope narrows this to one of the Sessions sub-pages (Active PPSK
    // Users / Inactive PPSK Users / Stale Sessions) - null means the
    // unfiltered "All Sessions" view. Same table definition either way, so
    // the four pages can never drift out of sync with each other; only the
    // underlying query differs (RadiusAccounting::scopeWithStatus()).
    public static function configure(Table $table, ?SessionStatus $scope = null): Table
    {
        // Computed once per page load, not per row - avoids an N+1 query
        // across every session for what is, at most, a few dozen VLANs.
        $egressIps = TunnelEgressIp::query()->pluck('egress_ip', 'vlan_id');

        return $table
            // Filament's modifyQueryUsing() hands back a bare, un-generic
            // Builder, so the withStatus() local scope (which needs to know
            // the model to magically resolve) can't be called on it directly
            // here - RadiusAccounting::applyStatusFilter() is the same logic
            // exposed as a plain static method instead, callable regardless.
            ->modifyQueryUsing(fn (Builder $query): Builder => $scope === null
                ? $query->with('ppskGroup')
                : RadiusAccounting::applyStatusFilter($query->with('ppskGroup'), $scope))
            ->poll('10s')
            ->defaultSort('acctstarttime', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (RadiusAccounting $record): string => $record->effectiveStatus()->value)
                    ->badge()
                    ->icon(fn (string $state): string => SessionStatus::from($state)->icon())
                    ->formatStateUsing(fn (string $state): string => SessionStatus::from($state)->label())
                    ->color(fn (string $state): string => SessionStatus::from($state)->color())
                    ->toggleable(),
                TextColumn::make('ppskGroup.label')
                    ->label('PPSK')
                    ->placeholder('(unknown)')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('RADIUS username')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('vlan')
                    ->label('VLAN')
                    // radacct has no vlan_id of its own (that's a
                    // ppsk_groups/RADIUS-reply concept, not an accounting
                    // one) - derived from the joined PPSK group instead.
                    ->state(fn (RadiusAccounting $record): ?int => $record->ppskGroup?->vlan_id)
                    ->toggleable(),
                TextColumn::make('callingstationid')
                    ->label('Device (MAC)')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('framedipaddress')
                    ->label('Connected from IP')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('egress_ip')
                    ->label('Known egress IP')
                    ->state(fn (RadiusAccounting $record) => $egressIps[$record->ppskGroup?->vlan_id] ?? null)
                    ->placeholder('Not set')
                    ->tooltip('Last confirmed manually - not a live measurement. Update from the Tunnel Egress IPs page.')
                    ->toggleable(),
                TextColumn::make('acctstarttime')
                    ->label('Connected at')
                    ->dateTime('M j, Y H:i')
                    ->description(fn (?CarbonInterface $state): ?string => $state?->diffForHumans())
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('acctstoptime')
                    ->label('Disconnected at')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('acctterminatecause')
                    ->label('Disconnect reason')
                    // Raw RADIUS Acct-Terminate-Cause, whatever the AP/
                    // FreeRADIUS reported (e.g. User-Request, Lost-Carrier,
                    // Idle-Timeout, NAS-Reboot) - not normalized/translated,
                    // since the exact vocabulary depends on the AP vendor
                    // and there's no safe way to guess every value in
                    // advance. Blank on a still-open session (nothing to
                    // report yet) and on a stale one (no Acct-Stop was ever
                    // received - that absence is itself the signal, see the
                    // Stale status).
                    ->placeholder('-')
                    ->tooltip('Reported by the access point via RADIUS accounting, not interpreted by Zonclave.')
                    ->toggleable(),
                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(fn (RadiusAccounting $record): string => $record->durationForHumans())
                    ->toggleable(),
                TextColumn::make('data_used')
                    ->label('Data used')
                    ->state(function (RadiusAccounting $record): ?string {
                        if ($record->acctinputoctets === null && $record->acctoutputoctets === null) {
                            return null;
                        }

                        return Number::fileSize(($record->acctinputoctets ?? 0) + ($record->acctoutputoctets ?? 0));
                    })
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('vlan_id')
                    ->label('VLAN')
                    ->options(VlanPlan::options())
                    // No vlan_id column on radacct itself (see the 'vlan'
                    // column above) - narrow by the usernames belonging to
                    // that VLAN's PPSK groups instead.
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereIn('username', PpskGroup::query()
                            ->where('vlan_id', $data['value'])
                            ->pluck('radius_username'));
                    }),
                // Redundant (and potentially self-contradicting) on a
                // status-scoped sub-page - e.g. "currently connected only"
                // has nothing left to do on the Inactive PPSK Users page,
                // which is already scoped to disconnected sessions. Only
                // shown on the unscoped "All Sessions" view.
                ...($scope === null ? [
                    Filter::make('open')
                        ->label('Currently connected only')
                        ->query(fn (Builder $query): Builder => $query->whereNull('acctstoptime'))
                        ->toggle(),
                ] : []),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Delete')
                    ->modalDescription('Removes this row from the session history permanently - it does not disconnect the device or affect its ability to reconnect.')
                    ->using(function (RadiusAccounting $record): void {
                        app(RadiusAccountingService::class)->delete(
                            $record,
                            Filament::auth()->user()?->getAttribute('email'),
                        );
                    }),
            ])
            ->toolbarActions([]);
    }
}
