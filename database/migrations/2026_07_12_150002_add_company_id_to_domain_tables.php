<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every tenant-owned table in the app, scoped by a nullable company_id.
     * Nullable for now: rows are backfilled to a single default company in
     * the migration that follows this one, then app code (BelongsToCompany)
     * keeps new rows populated going forward.
     */
    private array $tables = [
        'teams', 'users', 'lead_statuses', 'leads', 'lead_activities', 'lead_notes',
        'follow_ups', 'requirements', 'goals', 'deal_closures', 'daily_summaries',
        'release_notes', 'knowledge_base_categories', 'knowledge_base_tags',
        'knowledge_base_items', 'meetings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('company_id')->nullable()->after('id')
                    ->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('company_id');
            });
        }
    }
};
