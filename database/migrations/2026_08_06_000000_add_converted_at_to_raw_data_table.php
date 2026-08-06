<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dashboard's "Converted to Lead" count was scoping by raw_data.created_at
     * (via a cloned "created in this period" query) instead of by when the
     * conversion actually happened, so an entry created weeks ago and
     * converted today never showed up in today's count. This adds a
     * dedicated timestamp for that moment, set in
     * RawDataService::convertToLead() going forward.
     *
     * Existing already-converted rows have no way to know the true
     * conversion moment retroactively, but updated_at was touched by that
     * same update() call at the time, so it's the closest available
     * approximation — backfilled here rather than left null (which would
     * make every historical conversion invisible to any period filter).
     */
    public function up(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->timestamp('converted_at')->nullable()->after('converted_lead_id');
        });

        DB::table('raw_data')
            ->where('status', 'converted_to_lead')
            ->whereNull('converted_at')
            ->update(['converted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->dropColumn('converted_at');
        });
    }
};
