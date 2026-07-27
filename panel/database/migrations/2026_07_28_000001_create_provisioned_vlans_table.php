<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The provisioned VLAN registry (CLAUDE.md Section 16.5, pulled into Phase 1
// 2026-07-28, client request). Previously the set of VLANs a PPSK could be
// assigned to was a config-derived contiguous range
// (ZONCLAVE_VLAN_MIN/MAX) - adding one meant editing .env and running a CLI
// command, and there was no way to remove an unused one (VLAN304 sat
// orphaned, Section 26.10/26.11). This table is the new source of truth;
// App\Domain\VlanPlan reads it instead of the config range, but its public
// API (forVlan/isProvisioned/options) is unchanged, so no caller needed to
// change.
//
// Seeded from the existing config('zonclave.vlan_min')..vlan_max range so
// every current deployment (dev, and the live production node the next time
// its migration step runs) keeps exactly the VLANs it already has today -
// zero behavior change until an admin actually adds or deletes one via the
// new panel page.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provisioned_vlans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('vlan_id')->unique();
            $table->timestamps();
        });

        $min = (int) config('zonclave.vlan_min');
        $max = (int) config('zonclave.vlan_max');
        $now = now();

        $rows = [];
        for ($vlanId = $min; $vlanId <= $max; $vlanId++) {
            $rows[] = ['vlan_id' => $vlanId, 'created_at' => $now, 'updated_at' => $now];
        }

        if ($rows !== []) {
            DB::table('provisioned_vlans')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioned_vlans');
    }
};
