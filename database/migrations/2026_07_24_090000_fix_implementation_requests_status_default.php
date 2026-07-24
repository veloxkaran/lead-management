<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The status column's default was never updated when 'pending' was renamed
 * to 'not_started' (see 2026_07_18_090001_remap_implementation_request_status_values).
 * New rows created without an explicit status still fell back to the stale
 * 'pending' default, which isn't a valid ImplementationStatus case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementation_requests', function (Blueprint $table) {
            $table->string('status')->default('not_started')->change();
        });
    }

    public function down(): void
    {
        Schema::table('implementation_requests', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }
};
