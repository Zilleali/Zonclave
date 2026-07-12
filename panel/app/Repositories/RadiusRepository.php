<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

// THE RADIUS WRITE BOUNDARY (CLAUDE.md Section 23.1).
//
// This class is the ONLY code in the panel allowed to touch radcheck and
// radreply, and its only caller is PpskService::projectToRadius(). No
// route, controller, Filament resource, seeder, or ad-hoc query may write
// these tables. If you are about to add a second caller, stop: extend the
// service path instead.
//
// No Eloquent models exist for these tables on purpose; the query builder
// keeps the surface minimal and greppable.
class RadiusRepository
{
    /**
     * Atomically replace a username's projection. Deletes whatever exists
     * and materializes exactly the derived rows. Callers must already hold
     * a transaction (PpskService opens it); this method assumes one.
     *
     * @param  list<array{username: string, attribute: string, op: string, value: string}>  $radcheckRows
     * @param  list<array{username: string, attribute: string, op: string, value: string}>  $radreplyRows
     */
    public function replaceFor(string $username, array $radcheckRows, array $radreplyRows): void
    {
        DB::table('radcheck')->where('username', $username)->delete();
        DB::table('radreply')->where('username', $username)->delete();

        if ($radcheckRows !== []) {
            DB::table('radcheck')->insert($radcheckRows);
        }

        if ($radreplyRows !== []) {
            DB::table('radreply')->insert($radreplyRows);
        }
    }

    public function purgeFor(string $username): void
    {
        DB::table('radcheck')->where('username', $username)->delete();
        DB::table('radreply')->where('username', $username)->delete();
    }
}
