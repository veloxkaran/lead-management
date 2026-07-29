<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('contact_person');
            $table->text('notes')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'notes']);
        });
    }
};
