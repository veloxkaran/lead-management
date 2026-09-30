<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Task module entirely, per explicit user request — tasks,
 * their checklists and comments, the lead-page tab, and the Tasks entry in
 * the per-member permission matrix. Confirmed via a live-data check before
 * writing this: zero rows in all three tables and no task activity-log
 * entries, so nothing needed exporting. Unlike earlier removals this one
 * guards itself anyway — if a deployment does hold task data it refuses to
 * run rather than silently dropping it, so that data can be exported first.
 * Drop order respects FKs (children -> tasks); down() recreates today's
 * live shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'task_comments', 'task_checklist_items'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Refusing to drop `{$table}`: it still holds rows. Export or remove them deliberately first.");
            }
        }

        if (DB::table('activity_log_entries')->where('module', 'task')->exists()) {
            throw new RuntimeException('Refusing to remove the Task module: activity_log_entries still has module=task rows, which would no longer cast to App\Enums\ActivityModule.');
        }

        $this->forgetTaskPermissions();

        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('tasks');
    }

    /**
     * Drops the no-longer-existing `tasks` key from any member's stored
     * permissions (User::hasPermission()), collapsing to null — full access
     * — when that was their only restriction.
     */
    private function forgetTaskPermissions(): void
    {
        DB::table('users')->whereNotNull('permissions')->orderBy('id')->each(function ($user) {
            $permissions = json_decode($user->permissions, true);

            if (! is_array($permissions) || ! array_key_exists('tasks', $permissions)) {
                return;
            }

            unset($permissions['tasks']);

            DB::table('users')->where('id', $user->id)->update([
                'permissions' => $permissions ? json_encode($permissions) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module');
            $table->nullableMorphs('taskable');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->decimal('estimated_hours', 6, 2)->nullable();
            $table->decimal('actual_hours', 6, 2)->nullable();
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index('module');
            $table->index('due_date');
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
            $table->softDeletes();

            $table->index('task_id');
        });
    }
};
