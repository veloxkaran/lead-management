<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by');

            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('assigned_at');
        });
    }
};
