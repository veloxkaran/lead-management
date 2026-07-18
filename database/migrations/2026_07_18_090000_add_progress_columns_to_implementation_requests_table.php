<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('implementation_requests', function (Blueprint $table) {
            $table->date('planned_date')->nullable()->after('details');
            $table->unsignedTinyInteger('completion_percentage')->default(0)->after('status');
            $table->string('phase')->nullable()->after('completion_percentage');
            $table->text('notes')->nullable()->after('phase');
        });
    }

    public function down(): void
    {
        Schema::table('implementation_requests', function (Blueprint $table) {
            $table->dropColumn(['planned_date', 'completion_percentage', 'phase', 'notes']);
        });
    }
};
