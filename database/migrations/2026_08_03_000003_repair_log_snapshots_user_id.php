<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * log_snapshots.user_id shipped as a uuid column pointing at users.id, which is a bigint.
 * MySQL creates the table and then rejects the foreign key as incompatible, so the install
 * is left with the table present, the migration unrecorded, and every later deploy failing
 * on "table already exists". The migration beside this one is now correct for fresh
 * installs; this repairs the ones that already ran the broken version.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('log_snapshots') || ! Schema::hasColumn('log_snapshots', 'user_id')) {
            return;
        }

        $type = Schema::getColumnType('log_snapshots', 'user_id');

        // Already a bigint: this install either got the fixed migration or was repaired.
        if (in_array($type, ['bigint', 'integer'], true)) {
            return;
        }

        // The column never held anything a bigint cannot: the foreign key failed on every
        // install that has it, so the feature could not write here. Anything non-numeric
        // is therefore junk, and nulling it keeps the cast from silently turning it into 0.
        // Filtered in PHP rather than with a regex, which is not spelled the same on every
        // driver this platform runs against.
        $junk = DB::table('log_snapshots')->whereNotNull('user_id')->pluck('user_id', 'id')
            ->reject(fn ($value) => ctype_digit((string) $value))->keys();

        if ($junk->isNotEmpty()) {
            DB::table('log_snapshots')->whereIn('id', $junk)->update(['user_id' => null]);
        }

        Schema::table('log_snapshots', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('log_snapshots', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Deliberately not reversed: putting the incompatible uuid column back would only
        // recreate the failure this exists to clear.
    }
};
