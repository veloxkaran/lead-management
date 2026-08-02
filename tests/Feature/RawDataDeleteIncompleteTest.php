<?php

namespace Tests\Feature;

use App\Models\RawData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawDataDeleteIncompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_delete_entries_with_no_phone_and_no_email(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $incomplete = RawData::factory()->create(['contact_person' => null, 'phone' => null, 'email' => null]);
        $onlyContactPerson = RawData::factory()->create(['contact_person' => 'Jane Doe', 'phone' => null, 'email' => null]);
        $hasPhone = RawData::factory()->create(['contact_person' => null, 'phone' => '9800000000', 'email' => null]);
        $hasEmail = RawData::factory()->create(['contact_person' => null, 'phone' => null, 'email' => 'jane@example.test']);

        $response = $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $response->assertRedirect(route('raw-data.index'));

        $this->assertModelMissing($incomplete);
        $this->assertModelMissing($onlyContactPerson);
        $this->assertModelExists($hasPhone);
        $this->assertModelExists($hasEmail);
    }

    public function test_entries_with_blank_string_phone_and_email_are_also_treated_as_incomplete(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $incomplete = RawData::factory()->create(['contact_person' => 'Jane Doe', 'phone' => '', 'email' => '']);

        $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $this->assertModelMissing($incomplete);
    }

    public function test_deleting_incomplete_entries_reports_how_many_were_removed(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        RawData::factory()->count(2)->create(['contact_person' => null, 'phone' => null, 'email' => null]);
        RawData::factory()->create(['contact_person' => 'Jane Doe', 'phone' => '9800000000']);

        $response = $this->actingAs($superAdmin)->post(route('raw-data.delete-incomplete'));

        $response->assertSessionHas('success', fn ($message) => str_contains($message, '2'));
        $this->assertSame(1, RawData::count());
    }

    public function test_a_non_super_admin_cannot_delete_incomplete_entries(): void
    {
        $user = User::factory()->create();
        $incomplete = RawData::factory()->create(['contact_person' => null, 'phone' => null, 'email' => null]);

        $this->actingAs($user)->post(route('raw-data.delete-incomplete'))->assertForbidden();

        $this->assertModelExists($incomplete);
    }

    public function test_delete_incomplete_button_is_only_visible_to_super_admins(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        $this->actingAs($superAdmin)->get(route('raw-data.index'))->assertSee('Delete Incomplete Entries');
        $this->actingAs($user)->get(route('raw-data.index'))->assertDontSee('Delete Incomplete Entries');
    }
}
