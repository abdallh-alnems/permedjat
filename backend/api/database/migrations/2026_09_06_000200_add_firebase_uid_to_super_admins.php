<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the one column production never got.
 *
 * `2026_01_01_000810_create_super_admins_table` declares `firebase_uid`, but it
 * was never run: `permedjat:baseline` adopts a migration whose table already
 * exists, and `BaselineSchemaCommand::alreadyPresent()` decides that on
 * `Schema::hasTable()` alone. `super_admins` came over from the old backend
 * without the column — it was never there, in either backend — so the migration
 * was recorded as applied and the column silently skipped.
 *
 * A full diff of the migration-built schema against production found this and
 * nothing else: 1130 columns against 1129, no type, nullability or default
 * differing on any column present in both, 179 foreign keys either side, and
 * the only index missing the one on this column. It is the whole of the drift.
 *
 * Nothing is broken today. The Console app signs in with a username and
 * password, and `withPassword()` never reads this column; only `withFirebase()`
 * does, on the Google path no shipped build takes. This closes the gap before
 * something does take it, on a table holding no rows.
 *
 * On a database built from migrations this is a no-op the ledger skips, because
 * the create migration already made the column. It exists for production.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('super_admins', 'firebase_uid')) {
            return;
        }

        Schema::table('super_admins', function (Blueprint $table): void {
            $table->string('firebase_uid', 128)->nullable()->after('username');
            $table->unique(['firebase_uid'], 'super_admins_firebase_uid_unique');
        });
    }

    public function down(): void
    {
        // Left in place: the create migration declares it, so dropping it here
        // would leave a rolled-back database differing from a rebuilt one.
    }
};
