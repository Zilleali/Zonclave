<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// The registry of which VLANs a PPSK can currently be assigned to
// (CLAUDE.md Section 16.5). App\Domain\VlanPlan is the only reader outside
// this feature's own service/resource - the subnet/tunnel/gateway naming
// formula (Section 5/6) is still derived from the VLAN ID on the fly, never
// stored here, so provisioning a VLAN is just picking an ID.
class ProvisionedVlan extends Model
{
    protected $table = 'provisioned_vlans';

    protected $fillable = [
        'vlan_id',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'vlan_id' => 'integer',
        ];
    }
}
