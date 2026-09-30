<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Completed requirements saved before completed_at was tracked on every
     * path have no solved time. Use the moment the activity log recorded the
     * status change to "completed"; if none was logged (created already
     * completed), fall back to the row's last update.
     */
    public function up(): void
    {
        $missing = DB::table('requirements')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->get(['id', 'created_at', 'updated_at']);

        foreach ($missing as $requirement) {
            $completedAt = DB::table('activity_log_entries')
                ->where('subject_type', 'App\Models\Requirement')
                ->where('subject_id', $requirement->id)
                ->where('new_values', 'like', '%"status":"completed"%')
                ->latest('id')
                ->value('created_at');

            DB::table('requirements')
                ->where('id', $requirement->id)
                ->update(['completed_at' => $completedAt ?? $requirement->updated_at ?? $requirement->created_at]);
        }
    }

    /**
     * Not reversible: there's no record of which rows were backfilled.
     */
    public function down(): void {}
};
