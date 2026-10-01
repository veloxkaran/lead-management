<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('audience')->default('none');
            // The audience's filter (lead status ids or industry names) and
            // any hand-picked lead ids, kept for the record of who was targeted.
            $table->json('audience_filter')->nullable();
            $table->json('lead_ids')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            // Duplicates/invalid entries dropped while building the list:
            // [{value, reason}], shown on the campaign page.
            $table->json('skipped')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('company_name')->nullable();
            // Normalized email (lowercased) or phone (digits only) — unique
            // per campaign so a contact can never be messaged twice.
            $table->string('address');
            $table->string('status')->default('pending');
            $table->string('provider_message_id')->nullable()->index();
            $table->string('tracking_token', 64)->nullable()->unique();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'address']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
