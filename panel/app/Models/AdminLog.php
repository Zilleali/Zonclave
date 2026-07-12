<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Administrative audit log (CLAUDE.md Section 17). Append-only; written
// through AdminLogRepository. Secrets are never written here (Section 23.2).
class AdminLog extends Model
{
    protected $table = 'admin_log';

    public $timestamps = false;

    protected $fillable = [
        'ts',
        'admin_user',
        'action',
        'target_ppsk_id',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'ts' => 'datetime',
            'target_ppsk_id' => 'integer',
        ];
    }
}
