<?php

namespace Tests\Feature;

use App\Models\ActivityLogEntry;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadSupportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_generate_support_access(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create();

        $response = $this->actingAs($admin)->post(route('leads.support-access.generate', $lead));

        $response->assertSessionHas('new_support_pin');
        $pin = session('new_support_pin');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $pin);

        $lead->refresh();
        $this->assertNotNull($lead->support_id);
        $this->assertNotNull($lead->support_pin_hash);
        $this->assertTrue(Hash::check($pin, $lead->support_pin_hash));
        $this->assertTrue($lead->verifySupportPin($pin));
        $this->assertSame($admin->id, $lead->support_pin_generated_by);
    }

    public function test_non_super_admin_cannot_generate_support_access(): void
    {
        $user = User::factory()->create();
        $lead = Lead::factory()->create();

        $response = $this->actingAs($user)->post(route('leads.support-access.generate', $lead));

        $response->assertForbidden();
        $this->assertNull($lead->refresh()->support_id);
    }

    public function test_regenerating_keeps_the_support_id_stable_and_rotates_the_pin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($admin)->post(route('leads.support-access.generate', $lead));
        $lead->refresh();
        $firstSupportId = $lead->support_id;
        $firstHash = $lead->support_pin_hash;

        $this->actingAs($admin)->post(route('leads.support-access.generate', $lead));
        $lead->refresh();

        $this->assertSame($firstSupportId, $lead->support_id);
        $this->assertNotSame($firstHash, $lead->support_pin_hash);
    }

    public function test_revoking_clears_support_id_and_pin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create();
        $this->actingAs($admin)->post(route('leads.support-access.generate', $lead));

        $response = $this->actingAs($admin)->delete(route('leads.support-access.revoke', $lead));

        $response->assertRedirect();
        $lead->refresh();
        $this->assertNull($lead->support_id);
        $this->assertNull($lead->support_pin_hash);
        $this->assertFalse($lead->hasSupportAccess());
    }

    public function test_the_pin_never_appears_in_the_lead_change_log(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $lead = Lead::factory()->create();

        $this->actingAs($admin)->post(route('leads.support-access.generate', $lead));
        $this->actingAs($admin)->post(route('leads.support-access.generate', $lead));

        // Same query LeadController::show() feeds to leads/_change_log.blade.php
        // (whereNotNull('new_values') — the "lead created" entry from the
        // factory above has no new_values and is expected/irrelevant here).
        $entries = ActivityLogEntry::query()
            ->where('subject_type', $lead->getMorphClass())
            ->where('subject_id', $lead->id)
            ->whereNotNull('new_values')
            ->get();

        $this->assertTrue($entries->isEmpty(), 'Generating support access must not write to the generic diffed change log.');
    }
}
