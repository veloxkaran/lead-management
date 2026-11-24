<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Cancelled" status (RequirementStatus::Cancelled) needs a reason, plus who
 * cancelled and when. Additive and nullable — existing rows are untouched;
 * status itself is a plain string column so the new value needs no change.
 */
return new class extends Migration
{
    private const TABLES = ['requirements', 'support_tickets'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->text('cancellation_reason')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('cancelled_by');
                $table->dropColumn(['cancellation_reason', 'cancelled_at']);
            });
        }
    }
};
