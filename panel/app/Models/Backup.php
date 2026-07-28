<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Metadata for a full-database backup file (CLAUDE.md Section 16.8). The
// row's own fields are the only thing the panel reads to list/download/
// prune backups - App\Services\BackupService is the only writer.
/**
 * @property int $id
 * @property string $filename
 * @property string $disk_path
 * @property int $size_bytes
 */
class Backup extends Model
{
    protected $table = 'backups';

    protected $fillable = [
        'filename',
        'disk_path',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }
}
