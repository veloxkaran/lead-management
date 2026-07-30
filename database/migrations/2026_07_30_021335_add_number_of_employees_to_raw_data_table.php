<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->unsignedInteger('number_of_employees')->nullable()->after('company_name');
        });
    }

    public function down(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->dropColumn('number_of_employees');
        });
    }
};
