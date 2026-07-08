<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->decimal('opportunity_cost', 14, 2)->nullable()->after('source');
            $table->decimal('achieved_cost', 14, 2)->nullable()->default(0)->after('opportunity_cost');
            $table->timestamp('achieved_at')->nullable()->after('achieved_cost');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['opportunity_cost', 'achieved_cost', 'achieved_at']);
        });
    }
};
