<?php

declare(strict_types=1);

namespace App\Filament\Resources\SessionLogs\Tables;

use App\Domain\VlanPlan;
use App\Enums\SessionStatus;
use App\Models\PpskGroup;
use App\Models\RadiusAccounting;
use App\Models\TunnelEgressIp;
use Carbon\CarbonInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

// Connected-device / session list (CLAUDE.md Section 16.6). Newest first, no
// row or bulk actions - this is a read-only view of FreeRADIUS's own radacct
// table (Section 23.1's read-only counterpart). No polling (Section 23.3);
// on-demand loads only, same as the PPSK list and the admin log.
class SessionLogsTable
{
    public static function configure(Table $table): Table
    {
        // Computed once per page load, not per row - avoids an N+1 query
        // across every session for what is, at most, a few dozen VLANs.
        $egressIps = TunnelEgressIp::query()->pluck('egress_ip', 'vlan_id');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('ppskGroup'))
            ->defaultSort('acctstarttime', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (RadiusAccounting $record): string => $record->effectiveStatus()->value)
                    ->badge()
                    ->icon(fn (string $state): string => SessionStatus::from($state)->icon())
                    ->formatStateUsing(fn (string $state): string => SessionStatus::from($state)->label())
                    ->color(fn (string $state): string => SessionStatus::from($state)->color()),
                TextColumn::make('ppskGroup.label')
                    ->label('PPSK')
                    ->placeholder('(unknown)')
                    ->searchable(),
                TextColumn::make('username')
                    ->label('RADIUS username')
                    ->searchable(),
                TextColumn::make('vlan')
                    ->label('VLAN')
                    // radacct has no vlan_id of its own (that's a
                    // ppsk_groups/RADIUS-reply concept, not an accounting
                    // one) - derived from the joined PPSK group instead.
                    ->state(fn (RadiusAccounting $record): ?int => $record->ppskGroup?->vlan_id),
                TextColumn::make('callingstationid')
                    ->label('Device (MAC)')
                    ->placeholder('-'),
                TextColumn::make('framedipaddress')
                    ->label('Connected from IP')
                    ->placeholder('-'),
                TextColumn::make('egress_ip')
                    ->label('Known egress IP')
                    ->state(fn (RadiusAccounting $record) => $egressIps[$record->ppskGroup?->vlan_id] ?? null)
                    ->placeholder('Not set')
                    ->tooltip('Last confirmed manually - not a live measurement. Update from the Tunnel Egress IPs page.'),
                TextColumn::make('acctstarttime')
                    ->label('Connected at')
                    ->dateTime('M j, Y H:i')
                    ->description(fn (?CarbonInterface $state): ?string => $state?->diffForHumans())
                    ->sortable(),
                TextColumn::make('acctstoptime')
                    ->label('Disconnected at')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('-')
                    ->sortable(),
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
                    ->tooltip('Reported by the access point via RADIUS accounting, not interpreted by Zonclave.'),
                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(fn (RadiusAccounting $record): string => $record->durationForHumans()),
                TextColumn::make('data_used')
                    ->label('Data used')
                    ->state(function (RadiusAccounting $record): ?string {
                        if ($record->acctinputoctets === null && $record->acctoutputoctets === null) {
                            return null;
                        }

                        return Number::fileSize(($record->acctinputoctets ?? 0) + ($record->acctoutputoctets ?? 0));
                    })
                    ->placeholder('-'),
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
                Filter::make('open')
                    ->label('Currently connected only')
                    ->query(fn (Builder $query): Builder => $query->whereNull('acctstoptime'))
                    ->toggle(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
