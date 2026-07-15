<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_document_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_document_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device')->nullable();
            $table->string('browser')->nullable();
            $table->timestamps();

            $table->unique(['policy_document_version_id', 'user_id'], 'policy_doc_version_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_document_acknowledgments');
    }
};
