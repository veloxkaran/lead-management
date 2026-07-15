<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('policy_ack_last_prompted_at')->nullable();
            $table->string('policy_ack_last_prompted_fingerprint')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['policy_ack_last_prompted_at', 'policy_ack_last_prompted_fingerprint']);
        });
    }
};
