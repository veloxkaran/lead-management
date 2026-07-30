<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adopted_by');
            $table->dropColumn('adopted_at');
            $table->string('sprint')->nullable()->after('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn('sprint');
            $table->foreignId('adopted_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('adopted_at')->nullable()->after('client_acknowledged_at');
        });
    }
};
