<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional friendly name per VLAN (client request 2026-08-08) so the admin
// can tell VLANs apart at a glance (e.g. "Office main", "France exits")
// instead of only the bare numeric ID. Purely a display label - the VLAN ID
// remains the row's real identity and the subnet/tunnel/gateway derivation
// (App\Domain\VlanPlan::forVlan()) is unaffected.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provisioned_vlans', function (Blueprint $table): void {
            $table->string('name', 64)->nullable()->after('vlan_id');
        });
    }

    public function down(): void
    {
        Schema::table('provisioned_vlans', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }
};
