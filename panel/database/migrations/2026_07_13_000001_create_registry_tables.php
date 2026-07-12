<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ppsk_groups and admin_log, per CLAUDE.md Sections 7 and 17. On the
// production node these tables are created by installer/install.sh before
// the panel deploys, so this migration is guarded and becomes a no-op
// there. It exists for dev and test databases. Definitions must stay in
// lockstep with db/schema/02_registry.sql and the installer.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ppsk_groups')) {
            Schema::create('ppsk_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('label', 128);
                $table->string('radius_username', 64)->unique();
                $table->string('password_hash', 255);
                $table->integer('vlan_id');
                $table->string('subnet', 32);
                $table->string('wireguard_interface', 32);
                $table->string('wireguard_gateway', 32);
                $table->string('opnsense_interface', 64)->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();

                $table->index('vlan_id', 'idx_ppsk_groups_vlan');
                $table->index('status', 'idx_ppsk_groups_status');
                $table->index('label', 'idx_ppsk_groups_label');
            });
        }

        if (! Schema::hasTable('admin_log')) {
            Schema::create('admin_log', function (Blueprint $table): void {
                $table->id();
                $table->timestamp('ts')->useCurrent();
                $table->string('admin_user', 128)->nullable();
                $table->string('action', 64);
                $table->integer('target_ppsk_id')->nullable();
                $table->text('detail')->nullable();

                $table->index('ts', 'idx_admin_log_ts');
                $table->index('target_ppsk_id', 'idx_admin_log_target');
                $table->index('action', 'idx_admin_log_action');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_log');
        Schema::dropIfExists('ppsk_groups');
    }
};
