<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// radacct: FreeRADIUS accounting (CLAUDE.md Section 16.6, pulled into Phase 1
// 2026-07-27, client request - was Section 22's Phase 2 exclusion). On the
// production node the full FreeRADIUS rlm_sql schema is already loaded by the
// installer (it ships this table from day one so shipped SQL queries don't
// error even before accounting was enabled), so this is a no-op there. It
// creates the table for dev and test databases, mirroring
// db/schema/01_radius.sql's column set. The panel only ever reads this table
// (App\Models\RadiusAccounting); FreeRADIUS is the only writer.
//
// tunnel_egress_ips: a small manually-maintained reference of each VLAN's
// last-confirmed residential egress IP (Section 16.6). There is no OPNsense
// API integration yet (Section 19 is Phase 2), so this is deliberately not a
// live lookup - an admin updates it by hand via the TunnelEgressIps page
// whenever they confirm a tunnel's IP.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('radacct')) {
            Schema::create('radacct', function (Blueprint $table): void {
                $table->id('radacctid');
                $table->text('acctsessionid');
                $table->text('acctuniqueid')->unique();
                $table->text('username')->nullable();
                $table->text('groupname')->nullable();
                $table->text('realm')->nullable();
                $table->ipAddress('nasipaddress')->nullable();
                $table->text('nasportid')->nullable();
                $table->text('nasporttype')->nullable();
                $table->timestampTz('acctstarttime')->nullable();
                $table->timestampTz('acctupdatetime')->nullable();
                $table->timestampTz('acctstoptime')->nullable();
                $table->bigInteger('acctinterval')->nullable();
                $table->bigInteger('acctsessiontime')->nullable();
                $table->text('acctauthentic')->nullable();
                $table->text('connectinfo_start')->nullable();
                $table->text('connectinfo_stop')->nullable();
                $table->unsignedBigInteger('acctinputoctets')->nullable();
                $table->unsignedBigInteger('acctoutputoctets')->nullable();
                $table->text('calledstationid')->nullable();
                $table->text('callingstationid')->nullable();
                $table->text('acctterminatecause')->nullable();
                $table->text('servicetype')->nullable();
                $table->text('framedprotocol')->nullable();
                $table->ipAddress('framedipaddress')->nullable();

                $table->index(['acctstarttime', 'username'], 'radacct_start_user_idx');
            });

            // Partial index (open sessions only), matching
            // db/schema/01_radius.sql - built with raw SQL since Postgres
            // partial indexes have no Blueprint helper. Skipped on SQLite
            // (the local dev/test driver), which doesn't support partial
            // indexes the same way; the plain composite index above is
            // enough for tests, and production loads the real package
            // schema.sql (with the partial index) directly, never this file.
            if (Schema::getConnection()->getDriverName() === 'pgsql') {
                DB::statement('CREATE INDEX radacct_active_session_idx ON radacct (acctuniqueid) WHERE acctstoptime IS NULL');
            }
        }

        if (! Schema::hasTable('tunnel_egress_ips')) {
            Schema::create('tunnel_egress_ips', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('vlan_id')->unique();
                $table->string('egress_ip', 45)->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->string('updated_by', 128)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tunnel_egress_ips');
        Schema::dropIfExists('radacct');
    }
};
