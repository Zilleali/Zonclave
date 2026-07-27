<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// A manually-maintained reference of each VLAN's last-confirmed residential
// egress IP (CLAUDE.md Section 16.6). There is no OPNsense API integration
// yet (Section 19 is Phase 2), so this is deliberately not a live value -
// an admin updates it by hand via the TunnelEgressIps page whenever they
// confirm a tunnel's public IP, and the session log displays it as
// "last known", never as a live per-session measurement.
/**
 * @property int $vlan_id
 * @property string|null $egress_ip
 * @property Carbon|null $checked_at
 * @property string|null $updated_by
 */
class TunnelEgressIp extends Model
{
    protected $table = 'tunnel_egress_ips';

    protected $fillable = [
        'vlan_id',
        'egress_ip',
        'checked_at',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'vlan_id' => 'integer',
            'checked_at' => 'datetime',
        ];
    }
}
