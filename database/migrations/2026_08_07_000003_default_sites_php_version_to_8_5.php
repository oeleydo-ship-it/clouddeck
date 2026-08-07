<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Align the column default with config('clouddeck.default_php_version') for rows
        // created without an explicit php_version. Existing sites keep their stored value.
        Schema::table('sites', function ($table) {
            $table->string('php_version')->default('8.5')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function ($table) {
            $table->string('php_version')->default('8.4')->change();
        });
    }
};
