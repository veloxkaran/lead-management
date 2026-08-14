<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support ID + hashed PIN a Super Admin issues per-lead so its client
     * contact can self-serve support tickets without a staff login (see
     * ClientSupportController). Deliberately NOT added to Lead::$fillable —
     * these must only ever be written by LeadService::generateSupportAccess()/
     * revokeSupportAccess(), never through the generic update() path, which
     * would sweep the hash into the lead's unmasked, everyone-can-view
     * change log.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('support_id')->nullable()->unique()->after('archived_at');
            $table->string('support_pin_hash')->nullable()->after('support_id');
            $table->timestamp('support_pin_generated_at')->nullable()->after('support_pin_hash');
            $table->foreignId('support_pin_generated_by')->nullable()->after('support_pin_generated_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('support_pin_generated_by');
            $table->dropColumn(['support_id', 'support_pin_hash', 'support_pin_generated_at']);
        });
    }
};
