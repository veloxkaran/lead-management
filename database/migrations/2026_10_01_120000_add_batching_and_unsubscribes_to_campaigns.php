<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Snapshot of the Campaign Setup sending options at creation —
            // {batch_size, per_minute, pause_minutes, include_signature, track_opens}.
            $table->json('send_options')->nullable()->after('skipped');
            $table->unsignedInteger('batch_count')->default(0)->after('recipient_count');
            $table->unsignedInteger('current_batch')->default(0)->after('batch_count');
            // When the next batch is due to be queued; null when none is waiting.
            $table->timestamp('next_batch_at')->nullable()->after('scheduled_at');
            $table->timestamp('paused_at')->nullable()->after('started_at');
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->unsignedInteger('batch')->nullable()->after('campaign_id');
            $table->timestamp('queued_at')->nullable()->after('error');
            $table->timestamp('unsubscribed_at')->nullable()->after('delivered_at');

            $table->index(['campaign_id', 'batch']);
        });

        // Addresses that unsubscribed — never included in a campaign again.
        Schema::create('campaign_unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('address');
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_unsubscribes');

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'batch']);
            $table->dropColumn(['batch', 'queued_at', 'unsubscribed_at']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['send_options', 'batch_count', 'current_batch', 'next_batch_at', 'paused_at']);
        });
    }
};
