<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Metadata for each full-database backup (CLAUDE.md Section 16.8). The
// backup content itself lives on the private local disk
// (storage/app/private/backups/); this table is lightweight - just enough
// to list, download, and prune them through a normal Filament resource
// like every other feature in this app, rather than building a one-off
// filesystem-listing UI.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->string('filename');
            $table->string('disk_path');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
