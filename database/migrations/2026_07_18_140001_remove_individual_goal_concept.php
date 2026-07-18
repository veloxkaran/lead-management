<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Individual Goal concept entirely, per explicit user request —
 * Organizational Goals are now the only kind. Confirmed via a live-data
 * check before writing this migration: the real database has 2 goals, both
 * already `goal_type = 'organization'`, zero `individual` rows — nothing of
 * value is lost by dropping the column outright. A fresh db:backup was
 * taken beforehand per this project's CLAUDE.md.
 *
 * Achievement for Organization goals no longer reads from Lead at all (see
 * GoalContributionService) — `goal_type` and `user_id` (the per-assignee
 * pointer) are both dead once Individual goals are gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('goals', 'user_id')) {
            Schema::table('goals', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasColumn('goals', 'goal_type')) {
            Schema::table('goals', function (Blueprint $table) {
                // The original goals table indexes (goal_type, bs_year, bs_month)
                // together — SQLite refuses to drop a column still referenced by
                // an index, so the index goes first.
                $table->dropIndex(['goal_type', 'bs_year', 'bs_month']);
                $table->dropColumn('goal_type');
            });
        }
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('goal_type')->default('organization')->after('description');
            $table->foreignId('user_id')->nullable()->after('goal_type')->constrained()->cascadeOnDelete();
            $table->index(['goal_type', 'bs_year', 'bs_month']);
        });
    }
};
