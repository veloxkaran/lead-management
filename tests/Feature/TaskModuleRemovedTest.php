<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TaskModuleRemovedTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_30_110000_remove_task_concept.php');
    }

    public function test_task_routes_tables_and_menu_are_gone(): void
    {
        $this->assertFalse(Route::has('tasks.index'));
        $this->assertFalse(Route::has('leads.tasks.store'));
        $this->assertFalse(Schema::hasTable('tasks'));
        $this->assertFalse(Schema::hasTable('task_comments'));
        $this->assertFalse(Schema::hasTable('task_checklist_items'));

        $this->actingAs(User::factory()->create())->get('/tasks')->assertNotFound();

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('/tasks', false)
            ->assertDontSee('Open Tasks');
    }

    public function test_tasks_no_longer_appear_in_the_permission_matrix(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->get(route('users.permissions.edit', $member))
            ->assertOk()
            ->assertDontSee('permissions[tasks]', false);
    }

    public function test_migration_strips_stale_task_permissions(): void
    {
        $onlyTasks = User::factory()->create();
        $mixed = User::factory()->create();
        DB::table('users')->where('id', $onlyTasks->id)->update(['permissions' => json_encode(['tasks' => []])]);
        DB::table('users')->where('id', $mixed->id)->update(['permissions' => json_encode(['tasks' => ['view'], 'leads' => ['view']])]);

        $this->migration()->up();

        $this->assertNull($onlyTasks->refresh()->permissions);
        $this->assertSame(['leads' => ['view']], $mixed->refresh()->permissions);
    }

    public function test_migration_refuses_to_drop_tasks_that_still_hold_data(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });
        DB::table('tasks')->insert(['title' => 'Real work']);

        try {
            $this->migration()->up();
            $this->fail('Expected the migration to refuse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tasks', $e->getMessage());
        }

        $this->assertTrue(Schema::hasTable('tasks'));
        $this->assertSame(1, DB::table('tasks')->count());
    }
}
