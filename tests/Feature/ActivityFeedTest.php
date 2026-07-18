<?php

namespace Tests\Feature;

use App\Enums\ActivityModule;
use App\Enums\UserRole;
use App\Models\ActivityLogEntry;
use App\Models\Company;
use App\Models\DealClosure;
use App\Models\Lead;
use App\Models\PolicyDocument;
use App\Models\PolicyDocumentVersion;
use App\Models\Requirement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_orders_newest_first(): void
    {
        $user = User::factory()->create();

        $older = ActivityLogEntry::factory()->create(['user_id' => $user->id, 'description' => 'older event']);
        $older->forceFill(['created_at' => now()->subHour()])->save();

        $newer = ActivityLogEntry::factory()->create(['user_id' => $user->id, 'description' => 'newer event']);

        $response = $this->actingAs($user)->getJson(route('activity-feed.index'))->assertOk();

        $descriptions = collect($response->json('data'))->pluck('description');
        $this->assertSame(['newer event', 'older event'], $descriptions->all());
    }

    public function test_feed_respects_the_configured_per_page_setting(): void
    {
        $user = User::factory()->create();
        Setting::set('activity_feed_per_page', '2');
        ActivityLogEntry::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson(route('activity-feed.index'))->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('last_page'));
    }

    public function test_feed_excludes_a_module_disabled_in_settings(): void
    {
        $user = User::factory()->create();
        Setting::set('activity_feed_enabled_modules', 'lead,requirement'); // whatsapp excluded

        ActivityLogEntry::factory()->create(['user_id' => $user->id, 'module' => ActivityModule::Lead]);
        ActivityLogEntry::factory()->create(['user_id' => $user->id, 'module' => ActivityModule::Whatsapp]);

        $response = $this->actingAs($user)->getJson(route('activity-feed.index'))->assertOk();

        $modules = collect($response->json('data'))->pluck('module');
        $this->assertTrue($modules->contains('lead'));
        $this->assertFalse($modules->contains('whatsapp'));
    }

    public function test_feed_is_scoped_to_the_viewers_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userB = User::factory()->create(['company_id' => $companyB->id]);

        ActivityLogEntry::factory()->create(['user_id' => $userA->id, 'company_id' => $companyA->id, 'description' => 'company A event']);
        ActivityLogEntry::factory()->create(['user_id' => $userB->id, 'company_id' => $companyB->id, 'description' => 'company B event']);

        $response = $this->actingAs($userA)->getJson(route('activity-feed.index'))->assertOk();

        $descriptions = collect($response->json('data'))->pluck('description');
        $this->assertTrue($descriptions->contains('company A event'));
        $this->assertFalse($descriptions->contains('company B event'));
    }

    public function test_a_permitted_viewer_gets_a_clickable_url_and_a_denied_viewer_does_not(): void
    {
        $creator = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $stranger = User::factory()->create(['role' => UserRole::BusinessDevelopment]);
        $requirement = Requirement::factory()->create(['created_by' => $creator->id]);

        $entry = ActivityLogEntry::factory()->create([
            'user_id' => $creator->id,
            'module' => ActivityModule::Requirement,
            'subject_type' => Requirement::class,
            'subject_id' => $requirement->id,
        ]);

        $asCreator = $this->actingAs($creator)->getJson(route('activity-feed.index'))->assertOk();
        $creatorRow = collect($asCreator->json('data'))->firstWhere('id', $entry->id);
        $this->assertTrue($creatorRow['can_view']);
        $this->assertNotNull($creatorRow['url']);

        $asStranger = $this->actingAs($stranger)->getJson(route('activity-feed.index'))->assertOk();
        $strangerRow = collect($asStranger->json('data'))->firstWhere('id', $entry->id);
        $this->assertFalse($strangerRow['can_view']);
        $this->assertNull($strangerRow['url']);
    }

    /**
     * Regression test for the N+1 fixed in ActivityLogRepository::feedForViewer
     * via morphWith(): ActivityLinkResolver accesses $subject->lead (for
     * DealClosure/Whatsapp-style entries) and $subject->policyDocument (for
     * PolicyDocument entries). Without eager-loading those nested relations
     * per subject type, each row triggers its own lazy query — so the total
     * query count would grow with the number of rows. It must not.
     */
    public function test_feed_query_count_does_not_grow_with_the_number_of_mixed_module_rows(): void
    {
        Setting::set('activity_feed_per_page', '20');
        $user = User::factory()->create();
        $policyDocument = PolicyDocument::factory()->create();

        $seedMixedEntries = function (int $count) use ($user, $policyDocument) {
            foreach (range(1, $count) as $i) {
                // deal_closures.lead_id is unique, so each deal needs its own
                // lead — but assigned_user_id/created_by/closed_by are
                // overridden to $user->id to avoid cascading fresh
                // User::factory() calls per iteration.
                $lead = Lead::factory()->create(['assigned_user_id' => $user->id, 'created_by' => $user->id]);
                $deal = DealClosure::factory()->create(['lead_id' => $lead->id, 'closed_by' => $user->id]);
                ActivityLogEntry::factory()->create([
                    'user_id' => $user->id,
                    'module' => ActivityModule::Lead,
                    'subject_type' => DealClosure::class,
                    'subject_id' => $deal->id,
                ]);

                $version = $policyDocument->versions()->create([
                    'version' => "1.{$i}", 'content' => '<p>Body</p>', 'effective_date' => now()->toDateString(),
                    'published_at' => now(), 'created_by' => $user->id,
                ]);
                ActivityLogEntry::factory()->create([
                    'user_id' => $user->id,
                    'module' => ActivityModule::PolicyDocument,
                    'subject_type' => PolicyDocumentVersion::class,
                    'subject_id' => $version->id,
                ]);
            }
        };

        $seedMixedEntries(2);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($user)->getJson(route('activity-feed.index'))->assertOk();
        $queryCountForFour = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        $seedMixedEntries(4);
        Cache::flush(); // defeat the feed's short-TTL cache so the second request re-queries
        DB::enableQueryLog();
        $this->actingAs($user)->getJson(route('activity-feed.index'))->assertOk();
        $queryCountForTwelve = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queryCountForFour,
            $queryCountForTwelve,
            'Activity Feed query count grew with the number of rows — the N+1 fix has regressed.'
        );
    }
}
