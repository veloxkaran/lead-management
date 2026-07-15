<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $tables = [
        'teams', 'users', 'lead_statuses', 'leads', 'lead_activities', 'lead_notes',
        'follow_ups', 'requirements', 'goals', 'deal_closures', 'daily_summaries',
        'release_notes', 'knowledge_base_categories', 'knowledge_base_tags',
        'knowledge_base_items', 'meetings',
    ];

    /**
     * Every row created before multi-tenancy existed belongs to one real
     * company. This creates that company (if it doesn't already exist) and
     * points every existing row at it — an UPDATE of a new nullable column,
     * never a delete/truncate of real data.
     */
    public function up(): void
    {
        $companyId = DB::table('companies')->where('slug', 'default')->value('id');

        if (! $companyId) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => config('app.name', 'Default Company'),
                'slug' => 'default',
                'status' => 'active',
                'fiscal_calendar' => 'bikram_sambat',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('branches')->where('company_id', $companyId)->exists()) {
            DB::table('branches')->insert([
                'company_id' => $companyId,
                'name' => 'Head Office',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($this->tables as $table) {
            DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
        }
    }

    public function down(): void
    {
        $companyId = DB::table('companies')->where('slug', 'default')->value('id');

        foreach ($this->tables as $table) {
            DB::table($table)->where('company_id', $companyId)->update(['company_id' => null]);
        }

        DB::table('branches')->where('company_id', $companyId)->delete();
        DB::table('companies')->where('id', $companyId)->delete();
    }
};
