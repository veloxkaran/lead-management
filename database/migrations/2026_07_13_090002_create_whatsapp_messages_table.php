<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('wa_message_id')->nullable()->unique();
            $table->string('from_number', 30)->nullable();
            $table->string('to_number', 30)->nullable();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('template_name')->nullable();
            $table->json('template_payload')->nullable();
            $table->string('media_id')->nullable();
            $table->string('media_url')->nullable();
            $table->string('status')->default('queued');
            $table->text('status_error')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('wa_timestamp')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
