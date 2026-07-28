<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->string('source', 20)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('raw_data', function (Blueprint $table) {
            $table->dropColumn(['email', 'source']);
        });
    }
};
