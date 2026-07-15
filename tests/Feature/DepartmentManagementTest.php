<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_manage_departments(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->get(route('departments.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('departments.store'), ['name' => 'Sales'])->assertForbidden();
    }

    public function test_super_admin_can_create_a_department(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)
            ->post(route('departments.store'), ['name' => 'Engineering', 'description' => 'Builds the product.'])
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['name' => 'Engineering']);
    }

    public function test_department_with_users_cannot_be_deleted(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $department = Department::factory()->create();
        User::factory()->create(['department_id' => $department->id]);

        $this->actingAs($superAdmin)->delete(route('departments.destroy', $department))
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_department_with_documents_cannot_be_deleted(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $department = Department::factory()->create();
        PolicyDocument::factory()->create(['department_id' => $department->id]);

        $this->actingAs($superAdmin)->delete(route('departments.destroy', $department))
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_empty_department_can_be_deleted(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $department = Department::factory()->create();

        $this->actingAs($superAdmin)->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }
}
