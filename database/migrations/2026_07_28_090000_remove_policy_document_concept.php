<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the SOP / Individual Job Description (PolicyDocument) feature
 * entirely, per explicit user request — the acknowledgment/versioning
 * system, its forced-onboarding modal, and every settings/report screen
 * for it. Confirmed via a live-data check before writing this migration:
 * zero rows existed in any of the three tables, so no backfill/export was
 * needed — a fresh db:backup was still taken beforehand per this project's
 * CLAUDE.md. Drop order respects FKs (acknowledgments -> versions ->
 * documents); down() recreates today's live shape (no department_id —
 * that was already dropped from policy_documents by
 * 2026_07_18_130001_remove_department_concept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('policy_document_acknowledgments');
        Schema::dropIfExists('policy_document_versions');
        Schema::dropIfExists('policy_documents');

        if (Schema::hasColumn('users', 'policy_ack_last_prompted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['policy_ack_last_prompted_at', 'policy_ack_last_prompted_fingerprint']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('policy_ack_last_prompted_at')->nullable();
            $table->string('policy_ack_last_prompted_fingerprint')->nullable();
        });

        Schema::create('policy_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('allow_skip')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'user_id']);
        });

        Schema::create('policy_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_document_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->longText('content');
            $table->date('effective_date');
            $table->timestamp('published_at');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['policy_document_id', 'effective_date']);
        });

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
};
