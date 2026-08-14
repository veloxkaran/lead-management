<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flags a ticket raised through the public client self-service portal
     * (ClientSupportController) rather than by staff. `raised_by` still
     * points at the lead's assigned/creating staff member for FK integrity
     * (support_tickets.raised_by is NOT NULL and this app has no
     * system/bot-user account) — views use this flag to label such tickets
     * "Client (self-service portal)" instead of that staff member's name.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->boolean('is_client_submitted')->default(false)->after('raised_by');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('is_client_submitted');
        });
    }
};
