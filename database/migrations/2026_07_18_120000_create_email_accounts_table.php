<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('email_address');
            $table->string('display_name')->nullable();
            $table->string('smtp_host');
            $table->unsignedInteger('smtp_port');
            $table->string('smtp_encryption');
            $table->string('imap_host')->nullable();
            $table->unsignedInteger('imap_port')->nullable();
            $table->string('imap_encryption')->nullable();
            $table->string('username');
            $table->text('password');
            $table->string('connection_status')->default('not_tested');
            $table->text('connection_error')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('signature')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
