<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// radcheck/radreply, the projection targets of ppsk_groups (CLAUDE.md
// Section 8.2). On the production node the full FreeRADIUS rlm_sql schema
// is loaded by the installer from the freeradius-postgresql package, so
// this migration is guarded and becomes a no-op there. It creates only the
// two tables the panel projects into, for dev and test databases.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('radcheck')) {
            Schema::create('radcheck', function (Blueprint $table): void {
                $table->id();
                $table->text('username')->default('');
                $table->text('attribute')->default('');
                $table->string('op', 2)->default('==');
                $table->text('value')->default('');
            });
        }

        if (! Schema::hasTable('radreply')) {
            Schema::create('radreply', function (Blueprint $table): void {
                $table->id();
                $table->text('username')->default('');
                $table->text('attribute')->default('');
                $table->string('op', 2)->default('=');
                $table->text('value')->default('');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('radreply');
        Schema::dropIfExists('radcheck');
    }
};
