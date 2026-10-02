<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Open tracking for client notification emails, like campaigns have:
        // plain SMTP gives no delivery receipt, so an opened email is the
        // confirmation that it arrived.
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('tracking_token', 64)->nullable()->unique()->after('error');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropColumn(['tracking_token', 'delivered_at']);
        });
    }
};
