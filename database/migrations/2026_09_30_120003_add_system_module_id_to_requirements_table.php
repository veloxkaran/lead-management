<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable because requirements logged before modules existed have
     * none; new and edited requirements must pick one (enforced in the
     * form requests). SystemModuleController refuses to delete a module
     * that's still in use, so nullOnDelete is only a safety net.
     */
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->foreignId('system_module_id')->nullable()->after('lead_id')
                ->constrained('system_modules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('system_module_id');
        });
    }
};
