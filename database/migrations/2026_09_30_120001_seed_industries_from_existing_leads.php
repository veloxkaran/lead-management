<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Leads keep storing their industry as text in leads.industry — the
     * admin-managed list only constrains what can be picked. Seeding it
     * with every value already in use means existing leads stay valid on
     * their next edit instead of being forced onto a different industry.
     * Only inserts into the new table; no lead rows are touched.
     *
     * Leads created by users without a company have a null company_id; the
     * seeded industry then goes to the default company (as the rest of the
     * admin lists did in backfill_default_company), since a null-company
     * row would be hidden from company-scoped users by BelongsToCompany.
     */
    public function up(): void
    {
        $defaultCompanyId = DB::table('companies')->where('slug', 'default')->value('id')
            ?? DB::table('companies')->orderBy('id')->value('id');

        $existing = DB::table('leads')
            ->whereNotNull('industry')
            ->where('industry', '!=', '')
            ->selectRaw('trim(industry) as name, min(company_id) as company_id')
            ->groupByRaw('trim(industry)')
            ->get();

        foreach ($existing as $row) {
            if ($row->name === '' || DB::table('industries')->where('name', $row->name)->exists()) {
                continue;
            }

            DB::table('industries')->insert([
                'company_id' => $row->company_id ?? $defaultCompanyId,
                'name' => $row->name,
                'slug' => Str::slug($row->name),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Nothing to undo: dropping the industries table (previous migration's
     * down()) removes the seeded rows.
     */
    public function down(): void {}
};
