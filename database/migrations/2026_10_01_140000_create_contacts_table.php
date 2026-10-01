<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A shared address book. Every field is optional — a contact needs
        // at least one of them (enforced in ContactRequest, not here).
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('all_contacts')->default(false)->after('lead_ids');
            $table->json('contact_ids')->nullable()->after('all_contacts');
        });

        // A plain indexed column, not a foreign key: SQLite can only add a
        // foreign key by rebuilding the whole table, and contacts are
        // soft-deleted, so there's nothing to cascade anyway.
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->after('lead_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['contact_id']);
            $table->dropColumn('contact_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['all_contacts', 'contact_ids']);
        });

        Schema::dropIfExists('contacts');
    }
};
