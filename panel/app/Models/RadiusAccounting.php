<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionStatus;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

// Read-only view of FreeRADIUS's radacct table (CLAUDE.md Section 16.6,
// pulled into Phase 1 2026-07-27). The panel never writes here - FreeRADIUS
// is the sole writer, same read-only relationship the panel already has with
// radcheck/radreply in the other direction (Section 23.1). Joined to
// ppsk_groups by username, not a real foreign key, since radacct is FreeRADIUS's
// own table and outlives any single ppsk_groups row.
/**
 * @property int $radacctid
 * @property string|null $username
 * @property string|null $nasipaddress
 * @property Carbon|null $acctstarttime
 * @property Carbon|null $acctupdatetime
 * @property Carbon|null $acctstoptime
 * @property int|null $acctsessiontime
 * @property int|null $acctinputoctets
 * @property int|null $acctoutputoctets
 * @property string|null $callingstationid
 * @property string|null $calledstationid
 * @property string|null $acctterminatecause
 * @property string|null $framedipaddress
 */
class RadiusAccounting extends Model
{
    protected $table = 'radacct';

    protected $primaryKey = 'radacctid';

    public $timestamps = false;

    protected $fillable = [
        'acctsessionid',
        'acctuniqueid',
        'username',
        'groupname',
        'realm',
        'nasipaddress',
        'nasportid',
        'nasporttype',
        'acctstarttime',
        'acctupdatetime',
        'acctstoptime',
        'acctinterval',
        'acctsessiontime',
        'acctauthentic',
        'connectinfo_start',
        'connectinfo_stop',
        'acctinputoctets',
        'acctoutputoctets',
        'calledstationid',
        'callingstationid',
        'acctterminatecause',
        'servicetype',
        'framedprotocol',
        'framedipaddress',
    ];

    // A session is considered stale once this long has passed since its last
    // known activity with no Acct-Stop recorded - UniFi doesn't always send a
    // clean stop for a device that just dies or loses power, so without this
    // a dead session would otherwise show as "Connected" forever.
    private const STALE_AFTER_MINUTES = 15;

    protected function casts(): array
    {
        return [
            'acctstarttime' => 'datetime',
            'acctupdatetime' => 'datetime',
            'acctstoptime' => 'datetime',
            'acctsessiontime' => 'integer',
            'acctinputoctets' => 'integer',
            'acctoutputoctets' => 'integer',
        ];
    }

    /**
     * @param  Builder<RadiusAccounting>  $query
     * @return Builder<RadiusAccounting>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('acctstoptime');
    }

    // Query-level mirror of effectiveStatus() below, so the Active/Stale/
    // Inactive sub-pages (CLAUDE.md Section 16.6) filter at the database
    // instead of loading every session and filtering in PHP. Must stay in
    // lockstep with effectiveStatus() - both encode the same "last activity
    // within 15 minutes, no stop recorded" rule.
    /**
     * @param  Builder<RadiusAccounting>  $query
     * @return Builder<RadiusAccounting>
     */
    public function scopeWithStatus(Builder $query, SessionStatus $status): Builder
    {
        return self::applyStatusFilter($query, $status);
    }

    // Plain static method, not a magic-resolved local scope, so it can be
    // called directly on the un-generic Builder Filament's own
    // modifyQueryUsing() closures receive (App\Filament\Resources\SessionLogs\
    // Tables\SessionLogsTable) - PHPStan/Larastan can only resolve a scope
    // like ->withStatus() through the "->" magic call when it knows the
    // Builder's model generic, which Filament's bare Closure type doesn't
    // carry.
    /**
     * @param  Builder<RadiusAccounting>  $query
     * @return Builder<RadiusAccounting>
     */
    public static function applyStatusFilter(Builder $query, SessionStatus $status): Builder
    {
        if ($status === SessionStatus::Disconnected) {
            return $query->whereNotNull('acctstoptime');
        }

        $cutoff = now()->subMinutes(self::STALE_AFTER_MINUTES);

        if ($status === SessionStatus::Connected) {
            return $query->whereNull('acctstoptime')->where(function (Builder $q) use ($cutoff): void {
                $q->where(function (Builder $q2): void {
                    $q2->whereNull('acctupdatetime')->whereNull('acctstarttime');
                })->orWhere(function (Builder $q2) use ($cutoff): void {
                    $q2->whereNotNull('acctupdatetime')->where('acctupdatetime', '>=', $cutoff);
                })->orWhere(function (Builder $q2) use ($cutoff): void {
                    $q2->whereNull('acctupdatetime')->whereNotNull('acctstarttime')->where('acctstarttime', '>=', $cutoff);
                });
            });
        }

        return $query->whereNull('acctstoptime')->where(function (Builder $q) use ($cutoff): void {
            $q->where(function (Builder $q2) use ($cutoff): void {
                $q2->whereNotNull('acctupdatetime')->where('acctupdatetime', '<', $cutoff);
            })->orWhere(function (Builder $q2) use ($cutoff): void {
                $q2->whereNull('acctupdatetime')->whereNotNull('acctstarttime')->where('acctstarttime', '<', $cutoff);
            });
        });
    }

    /** @return BelongsTo<PpskGroup, $this> */
    public function ppskGroup(): BelongsTo
    {
        return $this->belongsTo(PpskGroup::class, 'username', 'radius_username');
    }

    public function effectiveStatus(): SessionStatus
    {
        if ($this->acctstoptime !== null) {
            return SessionStatus::Disconnected;
        }

        $lastActivity = $this->acctupdatetime ?? $this->acctstarttime;

        if ($lastActivity !== null && $lastActivity->diffInMinutes(now()) > self::STALE_AFTER_MINUTES) {
            return SessionStatus::Stale;
        }

        return SessionStatus::Connected;
    }

    public function durationForHumans(): string
    {
        $seconds = $this->acctsessiontime
            ?? ($this->acctstarttime?->diffInSeconds($this->acctstoptime ?? now()));

        if ($seconds === null) {
            return '-';
        }

        return CarbonInterval::seconds($seconds)->cascade()->forHumans(short: true);
    }
}
