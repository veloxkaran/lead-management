<?php

use App\Enums\GoalCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the goal category (a fixed set the achievement engine can dispatch
 * on — see GoalCategory) and a free-text description, ahead of the
 * Individual Goal removal in the next migration. Existing goals predate the
 * category concept, so they're backfilled to `other` here. Left nullable at
 * the database level (not upgraded to NOT NULL via a column ->change(),
 * which needs doctrine/dbal — not installed in this project) — every write
 * path (StoreGoalRequest/UpdateGoalRequest) already requires it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('category')->nullable()->after('title');
            $table->text('description')->nullable()->after('category');
        });

        DB::table('goals')->whereNull('category')->update(['category' => GoalCategory::Other->value]);
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropColumn(['category', 'description']);
        });
    }
};
