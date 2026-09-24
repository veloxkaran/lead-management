<?php

namespace Tests\Feature;

use App\Enums\EmailLogStatus;
use App\Jobs\SendClientNotificationEmail;
use App\Mail\ClientNotificationMail;
use App\Models\Announcement;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'New feature launched',
            'content' => "We've shipped scheduled reports.\nTry them today.",
            'audience' => 'all_leads',
            ...$overrides,
        ];
    }

    public function test_non_super_admin_cannot_create_announcements(): void
    {
        $user = User::factory()->create();
        Lead::factory()->create(['email' => 'a@example.test']);

        $this->actingAs($user)->get(route('announcements.create'))->assertForbidden();
        $this->actingAs($user)->post(route('announcements.store'), $this->payload())->assertForbidden();

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_any_user_can_read_announcements_but_not_see_recipients(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);
        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload())->assertRedirect();
        $announcement = Announcement::sole();

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('New feature launched')
            ->assertDontSee('New Announcement')
            ->assertDontSee('Delivery');

        $this->actingAs($user)->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee('scheduled reports')
            ->assertDontSee('client@example.test')
            ->assertDontSee('Recipients')
            ->assertDontSee('Email as Sent');
    }

    public function test_dashboard_lists_latest_announcements_for_every_role(): void
    {
        $author = User::factory()->superAdmin()->create();
        foreach (range(1, 6) as $i) {
            Announcement::create([
                'title' => "Announcement number {$i}",
                'content' => 'Body',
                'audience' => 'all_leads',
                'created_by' => $author->id,
            ])->forceFill(['created_at' => now()->subMinutes(10 - $i)])->save();
        }

        foreach (\App\Enums\UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk()
                ->assertSee('Announcements')
                ->assertSee('Announcement number 6')
                ->assertDontSee('Announcement number 1'); // only the latest 5

            $role === \App\Enums\UserRole::SuperAdmin
                ? $response->assertSee(route('announcements.create'))
                : $response->assertDontSee(route('announcements.create'));
        }
    }

    public function test_super_admin_can_view_index_and_create_pages(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'a@example.test']);

        $this->actingAs($superAdmin)->get(route('announcements.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('announcements.create'))->assertOk()->assertSee('Preview &amp; Send', false)->assertSee('Email Preview');
    }

    public function test_sends_only_to_active_leads_with_a_valid_unique_email(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->superAdmin()->create();

        Lead::factory()->create(['email' => 'one@example.test', 'contact_person' => 'Jordan']);
        Lead::factory()->create(['email' => 'ONE@example.test ']); // duplicate address
        Lead::factory()->create(['email' => 'two@example.test']);
        Lead::factory()->create(['email' => 'not-an-email']);
        Lead::factory()->create(['email' => null]);
        Lead::factory()->create(['email' => 'archived@example.test', 'archived_at' => now()]);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload())
            ->assertRedirect();

        $announcement = Announcement::sole();
        $this->assertSame(2, $announcement->recipient_count);

        Mail::assertSent(ClientNotificationMail::class, 2);
        Mail::assertSent(ClientNotificationMail::class, fn (ClientNotificationMail $mail) => $mail->hasTo('one@example.test')
            && $mail->renderedSubject === 'New feature launched'
            && str_contains($mail->renderedBody, 'Hi Jordan')
            && str_contains($mail->renderedBody, 'scheduled reports'));

        $this->assertSame(2, EmailLog::where('status', EmailLogStatus::Sent)->count());
        $this->assertTrue(EmailLog::first()->related->is($announcement));
    }

    public function test_customers_audience_only_reaches_closed_won_leads(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        $won = LeadStatus::factory()->create(['is_closed_won' => true]);
        $open = LeadStatus::factory()->create();

        Lead::factory()->create(['email' => 'customer@example.test', 'lead_status_id' => $won->id]);
        Lead::factory()->create(['email' => 'prospect@example.test', 'lead_status_id' => $open->id]);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload(['audience' => 'customers']))
            ->assertRedirect();

        Mail::assertSent(ClientNotificationMail::class, 1);
        Mail::assertSent(ClientNotificationMail::class, fn ($mail) => $mail->hasTo('customer@example.test'));
    }

    public function test_lead_status_audience_requires_and_respects_selected_statuses(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        $demo = LeadStatus::factory()->create();
        $other = LeadStatus::factory()->create();

        Lead::factory()->create(['email' => 'demo@example.test', 'lead_status_id' => $demo->id]);
        Lead::factory()->create(['email' => 'other@example.test', 'lead_status_id' => $other->id]);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload(['audience' => 'lead_statuses']))
            ->assertSessionHasErrors('lead_status_ids');

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'audience' => 'lead_statuses',
            'lead_status_ids' => [$demo->id],
        ]))->assertRedirect();

        Mail::assertSent(ClientNotificationMail::class, 1);
        Mail::assertSent(ClientNotificationMail::class, fn ($mail) => $mail->hasTo('demo@example.test'));
        $this->assertSame([$demo->id], Announcement::sole()->lead_status_ids);
    }

    public function test_audience_with_no_valid_emails_is_rejected(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => null]);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload())
            ->assertSessionHasErrors('audience');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_images_are_stored_and_embedded_in_the_email(): void
    {
        Mail::fake();
        Storage::fake('public');
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('banner.png', 600, 200)],
        ]))->assertRedirect();

        $image = Announcement::sole()->images()->sole();
        Storage::disk('public')->assertExists($image->disk_path);

        Mail::assertSent(ClientNotificationMail::class, fn (ClientNotificationMail $mail) => $mail->imagePaths === [$image->absolutePath()]);
    }

    public function test_images_are_limited_to_email_friendly_count_type_and_size(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'images' => array_fill(0, 6, UploadedFile::fake()->image('a.png')),
        ]))->assertSessionHasErrors('images');

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'images' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
        ]))->assertSessionHasErrors('images.0');

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('huge.png')->size(9000)],
        ]))->assertSessionHasErrors('images.0');

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('big.gif')->size(3000)],
        ]))->assertSessionHasErrors('images.0');

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('panorama.png', 6001, 10)],
        ]))->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_edited_announcement_template_is_used(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test', 'company_name' => 'Acme']);

        EmailTemplate::where('key', 'announcement')->update([
            'subject' => '[News] {{title}}',
            'body' => "Dear {{company_name}} team,\n{{content}}",
        ]);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload())->assertRedirect();

        Mail::assertSent(ClientNotificationMail::class, fn ($mail) => $mail->renderedSubject === '[News] New feature launched'
            && str_starts_with($mail->renderedBody, 'Dear Acme team,'));
    }

    public function test_sends_are_queued_pending_and_staggered_per_minute(): void
    {
        Queue::fake();
        config(['mail.announcement_per_minute' => 2]);
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->count(5)->sequence(fn ($s) => ['email' => "lead{$s->index}@example.test"])->create();

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload())->assertRedirect();

        $this->assertSame(5, EmailLog::where('status', EmailLogStatus::Pending)->count());

        $delays = collect(Queue::pushed(SendClientNotificationEmail::class))
            ->map(fn ($job) => (int) round(now()->diffInMinutes($job->delay)))
            ->all();

        $this->assertSame([0, 0, 1, 1, 2], $delays);
        Queue::assertPushedOn('emails', SendClientNotificationEmail::class);
    }

    public function test_show_page_lists_delivery_status(): void
    {
        Mail::fake();
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload())->assertRedirect();

        $this->actingAs($superAdmin)->get(route('announcements.show', Announcement::sole()))
            ->assertOk()
            ->assertSee('client@example.test')
            ->assertSee('New feature launched');

        $this->actingAs($superAdmin)->get(route('email-logs.index'))
            ->assertSee('Announcement — New feature launched');
    }

    public function test_real_message_embeds_images_inline_by_content_id(): void
    {
        config(['mail.default' => 'array']);
        Storage::fake('public');
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'images' => [UploadedFile::fake()->image('banner.png', 300, 100)],
        ]))->assertRedirect();

        $sent = app('mailer')->getSymfonyTransport()->messages()->sole();
        $raw = $sent->toString();

        // HTML part is quoted-printable, so "=" is encoded as "=3D".
        $this->assertStringContainsString('src=3D"cid:', $raw);
        $this->assertStringContainsString('multipart/related', $raw);
        $this->assertStringContainsString('Content-Disposition: inline', $raw);
        $this->assertStringContainsString('image/png', $raw);
    }

    /**
     * A real image file (not a fake with only a reported size).
     */
    private function realImage(string $name, int $width, int $height, string $type = 'jpeg', bool $noise = false, bool $transparent = false): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);

        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagefilledrectangle($image, (int) ($width / 4), (int) ($height / 4), (int) ($width * 3 / 4), (int) ($height * 3 / 4), imagecolorallocate($image, 200, 30, 30));
        } else {
            imagefill($image, 0, 0, imagecolorallocate($image, 36, 86, 166));
        }

        if ($noise) {
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'img');
        match ($type) {
            'png' => imagepng($image, $path),
            'gif' => imagegif($image, $path),
            default => imagejpeg($image, $path, 90),
        };

        return new UploadedFile($path, $name, null, null, true);
    }

    /** Inserts a minimal EXIF block carrying the given Orientation tag. */
    private function withExifOrientation(UploadedFile $jpeg, int $orientation): UploadedFile
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"
            ."\x00\x01"
            ."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00"
            ."\x00\x00\x00\x00";
        $app1 = "Exif\x00\x00".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        $bytes = file_get_contents($jpeg->getRealPath());
        file_put_contents($jpeg->getRealPath(), substr($bytes, 0, 2).$segment.substr($bytes, 2));

        return $jpeg;
    }

    private function storeWithImages(array $images): Announcement
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload(['images' => $images]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Announcement::sole()->load('images');
    }

    public function test_wide_jpeg_is_scaled_down_to_email_width(): void
    {
        Mail::fake();
        Storage::fake('public');

        $announcement = $this->storeWithImages([$this->realImage('wide.jpg', 2400, 1200)]);

        [$width, $height] = getimagesize($announcement->images->sole()->absolutePath());
        $this->assertSame([1072, 536], [$width, $height]);
    }

    public function test_small_image_keeps_its_size(): void
    {
        Mail::fake();
        Storage::fake('public');

        $announcement = $this->storeWithImages([$this->realImage('logo.png', 300, 120, 'png')]);

        [$width, $height] = getimagesize($announcement->images->sole()->absolutePath());
        $this->assertSame([300, 120], [$width, $height]);
    }

    public function test_jpeg_exif_rotation_is_applied(): void
    {
        Mail::fake();
        Storage::fake('public');

        // Orientation 6 = "rotate 90° clockwise to display" — typical portrait phone photo.
        $announcement = $this->storeWithImages([$this->withExifOrientation($this->realImage('phone.jpg', 400, 200), 6)]);

        $path = $announcement->images->sole()->absolutePath();
        [$width, $height] = getimagesize($path);
        $this->assertSame([200, 400], [$width, $height]);
        $this->assertArrayNotHasKey('Orientation', @exif_read_data($path) ?: []);
    }

    public function test_wide_png_keeps_transparency_when_scaled(): void
    {
        Mail::fake();
        Storage::fake('public');

        $announcement = $this->storeWithImages([$this->realImage('badge.png', 2000, 1000, 'png', transparent: true)]);

        $image = imagecreatefrompng($announcement->images->sole()->absolutePath());
        $this->assertSame(1072, imagesx($image));
        $this->assertSame(127, (imagecolorat($image, 2, 2) >> 24) & 0x7F, 'corner should stay fully transparent');
    }

    public function test_gif_is_stored_untouched_to_keep_animation(): void
    {
        Mail::fake();
        Storage::fake('public');
        $gif = $this->realImage('anim.gif', 1500, 100, 'gif');
        $originalBytes = file_get_contents($gif->getRealPath());

        $announcement = $this->storeWithImages([$gif]);

        $this->assertSame($originalBytes, file_get_contents($announcement->images->sole()->absolutePath()));
    }

    public function test_email_sets_outlook_width_without_upscaling(): void
    {
        config(['mail.default' => 'array']);
        Storage::fake('public');

        $this->storeWithImages([
            $this->realImage('wide.jpg', 2400, 1200),
            $this->realImage('logo.png', 300, 120, 'png'),
        ]);

        $raw = app('mailer')->getSymfonyTransport()->messages()->sole()->toString();
        $html = quoted_printable_decode($raw);

        $this->assertStringContainsString('width="536"', $html);
        $this->assertStringContainsString('width="300"', $html);
    }

    public function test_images_over_the_total_limit_after_optimizing_are_rejected_without_saving(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        // Random-noise PNGs don't compress and are under the resize width,
        // so they stay ~3MB each after optimizing — 3 of them exceed 7MB.
        $images = [
            $this->realImage('noise1.png', 1000, 1000, 'png', noise: true),
            $this->realImage('noise2.png', 1000, 1000, 'png', noise: true),
            $this->realImage('noise3.png', 1000, 1000, 'png', noise: true),
        ];

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload(['images' => $images]))
            ->assertSessionHasErrors('images');

        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('email_logs', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_documents_are_stored_and_sent_as_email_attachments(): void
    {
        config(['mail.default' => 'array']);
        Storage::fake('public');
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'documents' => [UploadedFile::fake()->create('Price List.pdf', 120, 'application/pdf')],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $document = Announcement::sole()->documents()->sole();
        $this->assertSame('Price List.pdf', $document->original_name);
        Storage::disk('public')->assertExists($document->disk_path);

        $raw = app('mailer')->getSymfonyTransport()->messages()->sole()->toString();
        $this->assertStringContainsString('Content-Disposition: attachment; name="Price List.pdf"', $raw);
    }

    public function test_documents_are_limited_by_type_count_size_and_combined_total(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'documents' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
        ]))->assertSessionHasErrors('documents.0');

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'documents' => array_fill(0, 4, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')),
        ]))->assertSessionHasErrors('documents');

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'documents' => [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')],
        ]))->assertSessionHasErrors('documents.0');

        // Each document is within limits, but together they exceed 7MB.
        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'documents' => array_map(fn ($i) => UploadedFile::fake()->create("part{$i}.pdf", 2600, 'application/pdf'), [1, 2, 3]),
        ]))->assertSessionHasErrors('images');

        $this->assertDatabaseCount('announcements', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_any_user_can_preview_and_download_documents(): void
    {
        Mail::fake();
        Storage::fake('public');
        $superAdmin = User::factory()->superAdmin()->create();
        Lead::factory()->create(['email' => 'client@example.test']);

        $this->actingAs($superAdmin)->post(route('announcements.store'), $this->payload([
            'documents' => [UploadedFile::fake()->createWithContent('notes.txt', 'Hello team')],
        ]))->assertRedirect();

        $document = Announcement::sole()->documents()->sole();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('announcements.show', $document->announcement))
            ->assertOk()
            ->assertSee('notes.txt')
            ->assertSee(route('announcement-documents.download', $document));

        $preview = $this->actingAs($user)->get(route('announcement-documents.preview', $document));
        $preview->assertOk();
        $this->assertStringStartsWith('inline', $preview->headers->get('content-disposition'));

        $download = $this->actingAs($user)->get(route('announcement-documents.download', $document));
        $download->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('content-disposition'));

        auth()->logout();
        $this->get(route('announcement-documents.preview', $document))->assertRedirect(route('login'));
    }
}
