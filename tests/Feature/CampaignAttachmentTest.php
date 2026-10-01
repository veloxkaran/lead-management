<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function payload(array $files, array $overrides = []): array
    {
        return [
            'name' => 'Brochure',
            'channel' => 'email',
            'subject' => 'Our new brochure',
            'message' => "Hi {{name}},\nHave a look.",
            'audience' => 'none',
            'extra_contacts' => 'Ram, ram@acme.test',
            'attachments' => $files,
            ...$overrides,
        ];
    }

    public function test_images_go_inside_the_email_and_pdfs_are_attached(): void
    {
        $admin = $this->admin();
        $png = UploadedFile::fake()->image('banner.png', 600, 200);
        $pdf = UploadedFile::fake()->createWithContent('brochure.pdf', '%PDF-1.4'.str_repeat(' ', 120 * 1024));

        // The required preview shows both, with the email's size.
        $preview = $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload([$png, $pdf]))->assertOk();
        $this->assertStringContainsString('src="data:image/png;base64,', $preview->json('html'));
        $this->assertStringContainsString('Attached: brochure.pdf', $preview->json('html'));
        $this->assertSame(['banner.png', 'brochure.pdf'], array_column($preview->json('attachments'), 'name'));
        $this->assertGreaterThan(120 * 1024, $preview->json('email_bytes'));

        // Sent through the real (array) mailer, so the image is really embedded.
        $this->actingAs($admin)->post(route('campaigns.store'), [...$this->payload([$png, $pdf]), 'preview_token' => $preview->json('token')])
            ->assertSessionHasNoErrors();

        $campaign = Campaign::firstOrFail();
        $this->assertSame(['image', 'document'], $campaign->attachments->pluck('kind')->all());
        $campaign->attachments->each(fn (CampaignAttachment $a) => Storage::disk('local')->assertExists($a->disk_path));

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertMatchesRegularExpression('#<img src="cid:[^"]+" width="536"#', $sent->getHtmlBody());
        $inline = collect($sent->getAttachments())->first(fn ($part) => $part->getFilename() === 'banner.png');
        $this->assertSame('image/png', $inline->getContentType(), 'embedded with its real type, not octet-stream');
        $this->assertStringContainsString('Attached: brochure.pdf', $sent->getTextBody());
        $names = array_map(fn ($part) => $part->getFilename(), $sent->getAttachments());
        $this->assertContains('brochure.pdf', $names);
        $this->assertCount(2, $sent->getAttachments(), 'the inline image and the PDF');
    }

    public function test_big_photos_are_shrunk_before_they_go_to_every_recipient(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $photo = UploadedFile::fake()->image('photo.jpg', 3000, 2000);

        $this->actingAs($admin)->postCampaign($this->payload([$photo]))->assertSessionHasNoErrors();

        $stored = Campaign::firstOrFail()->attachments->first();
        $this->assertSame('image/jpeg', $stored->mime);
        $this->assertSame(1072, getimagesize($stored->path())[0]);
        Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => count($mail->images) === 1 && $mail->documents === []);
    }

    public function test_the_preview_covers_the_files_too(): void
    {
        $admin = $this->admin();
        $preview = $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload([UploadedFile::fake()->image('a.png', 50, 50)]))->assertOk();

        // A different file after previewing → preview again.
        $this->actingAs($admin)->post(route('campaigns.store'), [...$this->payload([UploadedFile::fake()->image('a.png', 60, 60)]), 'preview_token' => $preview->json('token')])
            ->assertSessionHasErrors('preview_token');
        // Dropping the file → preview again.
        $this->actingAs($admin)->post(route('campaigns.store'), [...$this->payload([]), 'preview_token' => $preview->json('token')])
            ->assertSessionHasErrors('preview_token');
        $this->assertSame(0, Campaign::count());
    }

    public function test_only_png_jpg_and_pdf_within_the_limits_are_accepted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload([UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]))
            ->assertStatus(422)->assertJsonValidationErrors('attachments.0');
        $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload([UploadedFile::fake()->image('anim.gif')]))
            ->assertStatus(422)->assertJsonValidationErrors('attachments.0');
        $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload([UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')]))
            ->assertStatus(422)->assertJsonValidationErrors('attachments.0');
        $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload(array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.png", 10, 10), range(1, 6))))
            ->assertStatus(422)->assertJsonValidationErrors('attachments');
        $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload(array_map(fn ($i) => UploadedFile::fake()->create("d{$i}.pdf", 4000, 'application/pdf'), range(1, 3))))
            ->assertStatus(422)->assertJsonValidationErrors(['attachments' => 'The files add up to 11.7 MB']);
    }

    public function test_sms_campaigns_ignore_files(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postCampaign([
            'name' => 'SMS', 'channel' => 'sms', 'message' => 'Hi', 'audience' => 'none', 'extra_contacts' => '9800000001',
            'attachments' => [UploadedFile::fake()->image('a.png', 10, 10)],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, CampaignAttachment::count());
    }

    public function test_files_can_be_opened_by_anyone_who_can_view_the_campaign(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postCampaign($this->payload([UploadedFile::fake()->create('brochure.pdf', 20, 'application/pdf')]));
        $campaign = Campaign::firstOrFail();
        $file = $campaign->attachments->first();

        $this->actingAs(User::factory()->create(['role' => UserRole::Finance]))->get(route('campaigns.attachment', [$campaign, $file]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->get(route('campaigns.show', $campaign))->assertSee('brochure.pdf')->assertSee('attached');

        // Another campaign's id in the URL doesn't reach this file.
        $other = Campaign::factory()->create();
        $this->actingAs($admin)->get(route('campaigns.attachment', [$other, $file]))->assertNotFound();

        $noView = User::factory()->create();
        $noView->forceFill(['permissions' => ['campaigns' => []]])->save();
        $this->actingAs($noView)->get(route('campaigns.attachment', [$campaign, $file]))->assertForbidden();
    }

    public function test_sending_reuses_the_images_the_preview_already_shrank(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $photo = UploadedFile::fake()->image('photo.jpg', 2400, 1600);
        $hash = sha1_file($photo->getRealPath());

        $preview = $this->actingAs($admin)->postJson(route('campaigns.compose-preview'), $this->payload([$photo]))->assertOk();
        Storage::disk('local')->assertExists("campaign-drafts/{$hash}.jpg");

        // Mark the draft, so the send can be seen using it instead of shrinking again.
        Storage::disk('local')->put("campaign-drafts/{$hash}.jpg", $marker = (string) Storage::disk('local')->get("campaign-drafts/{$hash}.jpg").'reused');

        $this->actingAs($admin)->post(route('campaigns.store'), [...$this->payload([$photo]), 'preview_token' => $preview->json('token')])
            ->assertSessionHasNoErrors();

        $stored = Campaign::firstOrFail()->attachments->first();
        $this->assertSame($marker, Storage::disk('local')->get($stored->disk_path));
        Storage::disk('local')->assertMissing("campaign-drafts/{$hash}.jpg");
    }

    public function test_unsent_drafts_are_cleared_after_a_day(): void
    {
        Storage::disk('local')->put('campaign-drafts/old.jpg', 'x');
        touch(Storage::disk('local')->path('campaign-drafts/old.jpg'), now()->subDays(2)->getTimestamp());

        $this->actingAs($this->admin())->postJson(route('campaigns.compose-preview'), $this->payload([UploadedFile::fake()->image('new.png', 20, 20)]))->assertOk();

        Storage::disk('local')->assertMissing('campaign-drafts/old.jpg');
    }
}
