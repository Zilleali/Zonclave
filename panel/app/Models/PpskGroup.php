<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PpskStatus;
use Database\Factories\PpskGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// The authoritative registry row (CLAUDE.md Section 7). radcheck/radreply
// are a transactional projection of this model, derived through
// PpskService::projectToRadius() only.
/**
 * @property int $id
 * @property string $label
 * @property string $radius_username
 * @property string $password_hash
 * @property int $vlan_id
 * @property string $subnet
 * @property string $wireguard_interface
 * @property string $wireguard_gateway
 * @property string|null $opnsense_interface
 * @property PpskStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PpskGroup extends Model
{
    /** @use HasFactory<PpskGroupFactory> */
    use HasFactory;

    protected $table = 'ppsk_groups';

    protected $fillable = [
        'label',
        'radius_username',
        'password_hash',
        'vlan_id',
        'subnet',
        'wireguard_interface',
        'wireguard_gateway',
        'opnsense_interface',
        'status',
    ];

    // Never expose the encrypted PSK in serialized output (Section 23.2).
    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'vlan_id' => 'integer',
            'status' => PpskStatus::class,
        ];
    }
}
