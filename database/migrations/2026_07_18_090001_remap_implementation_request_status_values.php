<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ImplementationRequest's status cast is switching from the shared
 * RequirementStatus enum (Pending/InProgress/Completed/OnHold) to a
 * dedicated ImplementationStatus enum with two additional states
 * (Scheduled, Cancelled). Only the 'pending' value's spelling changes
 * ('pending' -> 'not_started'); in_progress/on_hold/completed are
 * identical strings in both enums and need no remapping.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('implementation_requests')
            ->where('status', 'pending')
            ->update(['status' => 'not_started']);
    }

    public function down(): void
    {
        DB::table('implementation_requests')
            ->where('status', 'not_started')
            ->update(['status' => 'pending']);
    }
};
