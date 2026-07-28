<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Department concept entirely, per explicit user request —
 * both the department_id FK and the legacy free-text `department` string
 * column on users. Confirmed via a live-data check before writing this
 * migration: 6 departments existed with 6 linked users, but zero policy
 * documents or trainings actually referenced one — a fresh db:backup was
 * taken beforehand per this project's CLAUDE.md.
 *
 * SOPs moved to a company-wide assignment model (every active user) instead
 * of per-department, and the "Department JD" policy-document type was
 * removed outright (Individual JD already covered "assign to one person").
 * The whole PolicyDocument/SOP feature was later removed entirely — see
 * 2026_07_28_090000_remove_policy_document_concept.
 *
 * Each step is guarded with hasColumn/hasTable so this migration is safe to
 * re-run after a partial failure (the first attempt failed on
 * policy_documents because its composite [type, department_id] index had
 * to be dropped before the column could be).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        if (Schema::hasColumn('users', 'department')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('department');
            });
        }

        if (Schema::hasColumn('policy_documents', 'department_id')) {
            Schema::table('policy_documents', function (Blueprint $table) {
                $table->dropIndex(['type', 'department_id']);
                $table->dropConstrainedForeignId('department_id');
            });
        }

        if (Schema::hasColumn('trainings', 'department_id')) {
            Schema::table('trainings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('department_id');
            });
        }

        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('phone');
            $table->foreignId('department_id')->nullable()->after('department')->constrained()->nullOnDelete();
        });

        Schema::table('policy_documents', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('title')->constrained()->nullOnDelete();
            $table->index(['type', 'department_id']);
        });

        Schema::table('trainings', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('attendees_count')->constrained()->nullOnDelete();
        });
    }
};
