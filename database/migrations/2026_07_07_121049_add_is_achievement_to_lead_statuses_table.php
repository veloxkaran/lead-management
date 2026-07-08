<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->boolean('is_achievement')->default(false)->after('is_closed_lost');
        });
    }

    public function down(): void
    {
        Schema::table('lead_statuses', function (Blueprint $table) {
            $table->dropColumn('is_achievement');
        });
    }
};
