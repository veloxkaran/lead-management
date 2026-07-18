<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_admin_cannot_create_a_sop(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)->post(route('sops.store'), [
            'title' => 'Onboarding SOP',
            'content' => '<p>Body</p>',
            'effective_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_a_sop_with_its_first_version(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->post(route('sops.store'), [
            'title' => 'Onboarding SOP',
            'content' => '<p>Body</p>',
            'effective_date' => now()->toDateString(),
            'version' => '1.0',
        ])->assertRedirect(route('sops.index'));

        $this->assertDatabaseHas('policy_documents', ['title' => 'Onboarding SOP', 'type' => 'sop']);
        $this->assertDatabaseHas('policy_document_versions', ['version' => '1.0']);
    }

    public function test_a_sop_applies_to_every_active_user_in_the_company(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $activeUser = User::factory()->create(['status' => UserStatus::Active]);
        $suspendedUser = User::factory()->create(['status' => UserStatus::Suspended]);

        $document = PolicyDocument::factory()->create();

        $assignedIds = $document->assignedUsers()->pluck('id');

        $this->assertTrue($assignedIds->contains($activeUser->id));
        $this->assertFalse($assignedIds->contains($suspendedUser->id));
    }

    public function test_creating_an_individual_jd_without_a_user_fails_validation(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($superAdmin)->post(route('individual-jds.store'), [
            'title' => 'Engineer JD',
            'content' => '<p>Body</p>',
            'effective_date' => now()->toDateString(),
        ])->assertSessionHasErrors('user_id');
    }

    public function test_super_admin_can_create_an_individual_jd_assigned_to_a_user(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $employee = User::factory()->create();

        $this->actingAs($superAdmin)->post(route('individual-jds.store'), [
            'title' => 'Engineer JD',
            'user_id' => $employee->id,
            'content' => '<p>Body</p>',
            'effective_date' => now()->toDateString(),
        ])->assertRedirect(route('individual-jds.index'));

        $this->assertDatabaseHas('policy_documents', [
            'title' => 'Engineer JD',
            'type' => 'individual_jd',
            'user_id' => $employee->id,
        ]);
    }

    public function test_updating_a_document_only_touches_metadata(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $document = PolicyDocument::factory()->create(['title' => 'Old title']);

        $this->actingAs($superAdmin)->put(route('sops.update', $document), [
            'title' => 'New title',
        ])->assertRedirect(route('sops.index'));

        $this->assertDatabaseHas('policy_documents', [
            'id' => $document->id,
            'title' => 'New title',
        ]);
    }

    public function test_super_admin_can_delete_a_document(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $document = PolicyDocument::factory()->create();

        $this->actingAs($superAdmin)->delete(route('sops.destroy', $document))
            ->assertRedirect(route('sops.index'));

        $this->assertSoftDeleted('policy_documents', ['id' => $document->id]);
    }
}
