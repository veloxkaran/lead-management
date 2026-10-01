<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\CampaignSettings;
use App\Support\CampaignSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /** A PNG of random pixels — noise doesn't compress, so size tracks pixel count. */
    private function noisePng(int $width, int $height): string
    {
        mt_srand($width * 1000 + $height);
        $image = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function logoPng(): string
    {
        $image = imagecreatetruecolor(120, 40);
        imagefill($image, 0, 0, imagecolorallocate($image, 36, 86, 166));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function dataUri(string $png): string
    {
        return 'data:image/png;base64,'.base64_encode($png);
    }

    private function save(string $signature)
    {
        return $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->put(route('campaign-setup.update-email'), [
                'campaign_email_mode' => 'system',
                'campaign_signature' => $signature,
                'campaign_batch_size' => 100,
                'campaign_emails_per_minute' => 20,
                'campaign_batch_pause_minutes' => 15,
            ]);
    }

    private function assertSignatureError($response, string $needle): void
    {
        $response->assertSessionHasErrors('campaign_signature');
        $this->assertStringContainsString($needle, session('errors')->first('campaign_signature'));
    }

    public function test_an_inserted_image_is_stored_as_a_file_and_counted_in_the_size(): void
    {
        $logo = $this->logoPng();

        $this->save('<p><img src="'.$this->dataUri($logo).'" alt="Acme"></p><p><strong>Ram Sharma</strong></p>')
            ->assertSessionHasNoErrors();

        $signature = app(CampaignSettings::class)->signature();
        $file = sha1($logo).'.png';

        $this->assertStringNotContainsString('data:image', $signature);
        $this->assertStringContainsString('/storage/campaign-signature/'.$file, $signature);
        Storage::disk('public')->assertExists('campaign-signature/'.$file);
        $this->assertSame(strlen($signature) + strlen($logo), CampaignSignature::bytes($signature));
        $this->assertSame([$file => strlen($logo)], CampaignSignature::imageSizes($signature));

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))->get(route('campaign-setup.edit'))
            ->assertOk()->assertSee('data-rich-text-max-bytes="51200"', false)->assertSee($file);
    }

    public function test_the_signature_is_limited_to_50_kb_including_images(): void
    {
        // 150×150 noise ≈ 68 KB and under the 400px shrink width, so it can't be made to fit.
        $this->assertSignatureError($this->save('<p><img src="'.$this->dataUri($this->noisePng(150, 150)).'"></p>'), 'A signature image is');

        // Two images that each fit but not together (≈ 27 KB each).
        $this->assertSignatureError($this->save('<p><img src="'.$this->dataUri($this->noisePng(95, 95)).'"><img src="'.$this->dataUri($this->noisePng(96, 95)).'"></p>'), 'the limit is 50 KB including images');

        $this->assertNull(app(CampaignSettings::class)->signature());
    }

    public function test_a_wide_image_is_scaled_down_to_fit(): void
    {
        $wide = $this->noisePng(1600, 60); // ≈ 290 KB; at 400px wide ≈ 18 KB

        $this->save('<p><img src="'.$this->dataUri($wide).'"></p>')->assertSessionHasNoErrors();

        $signature = app(CampaignSettings::class)->signature();
        $stored = Storage::disk('public')->files('campaign-signature');
        $this->assertCount(1, $stored);
        $this->assertSame(400, getimagesize(Storage::disk('public')->path($stored[0]))[0]);
        $this->assertLessThanOrEqual(CampaignSignature::MAX_BYTES, CampaignSignature::bytes($signature));
    }

    public function test_images_linked_from_other_sites_are_rejected(): void
    {
        $this->assertSignatureError($this->save('<p><img src="https://cdn.example.com/logo.png"> Ram</p>'), 'not linked from another site');
    }

    public function test_sent_emails_embed_signature_images_inline(): void
    {
        $this->save('<p><img src="'.$this->dataUri($this->logoPng()).'" alt="Acme"> Ram Sharma</p>')->assertSessionHasNoErrors();

        // Through the real (array) mailer, so the Blade view gets the $message that embeds.
        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))
            ->post(route('campaign-setup.test-email'), ['test_email' => 'me@example.com'])
            ->assertSessionHas('success');

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $html = $sent->getHtmlBody();

        $this->assertMatchesRegularExpression('#<img src="cid:[^"]+" width="120" alt="Acme"#', $html);
        $this->assertStringNotContainsString('/storage/campaign-signature/', $html);
        $this->assertCount(1, $sent->getAttachments());
    }

    public function test_replaced_and_removed_images_are_deleted(): void
    {
        $this->save('<p><img src="'.$this->dataUri($this->logoPng()).'"></p>');
        $first = Storage::disk('public')->files('campaign-signature');
        $this->assertCount(1, $first);

        $this->save('<p><img src="'.$this->dataUri($this->noisePng(40, 40)).'"></p>');
        $second = Storage::disk('public')->files('campaign-signature');
        $this->assertCount(1, $second);
        $this->assertNotSame($first, $second);

        // Re-saving the stored signature as-is keeps its image.
        $this->save((string) app(CampaignSettings::class)->signature())->assertSessionHasNoErrors();
        $this->assertSame($second, Storage::disk('public')->files('campaign-signature'));

        $this->save('');
        $this->assertSame([], Storage::disk('public')->files('campaign-signature'));
        $this->assertNull(app(CampaignSettings::class)->signature());
    }

    public function test_a_signature_whose_image_file_went_missing_still_works(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->save('<p><img src="'.$this->dataUri($this->logoPng()).'"> Ram Sharma</p>')->assertSessionHasNoErrors();
        $saved = app(CampaignSettings::class)->signature();
        Storage::disk('public')->deleteDirectory('campaign-signature');

        // The setup page loads and says what's wrong instead of crashing.
        $this->actingAs($admin)->get(route('campaign-setup.edit'))->assertOk()->assertSee("can't be found on the server", false);
        $this->assertSame(strlen($saved), CampaignSignature::bytes($saved));

        // Emails go out without the broken image.
        $this->assertStringNotContainsString('<img', CampaignSignature::forEmail($saved));

        // Saving drops it and says so.
        $this->save($saved)->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, '1 signature image(s) whose file had gone missing were removed'));
        $this->assertStringNotContainsString('<img', app(CampaignSettings::class)->signature());
        $this->assertStringContainsString('Ram Sharma', app(CampaignSettings::class)->signature());
    }

    public function test_signature_images_use_relative_paths_so_the_editor_preview_works_on_any_host(): void
    {
        $logo = $this->logoPng();
        $file = sha1($logo).'.png';

        $this->save('<p><img src="'.$this->dataUri($logo).'"> Ram</p>')->assertSessionHasNoErrors();
        $saved = app(CampaignSettings::class)->signature();
        $this->assertStringContainsString('src="/storage/campaign-signature/'.$file.'"', $saved);
        $this->assertStringNotContainsString('http', $saved);
        // HTMLPurifier's made-up alt (the file name) isn't kept.
        $this->assertStringContainsString('alt=""', $saved);

        // A signature saved earlier with an absolute APP_URL link and a hash alt still previews.
        $old = '<p><img src="http://localhost/storage/campaign-signature/'.$file.'" alt="'.$file.'"> Ram</p>';
        app(CampaignSettings::class)->set('campaign_signature', $old);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))->get(route('campaign-setup.edit'))
            ->assertOk()
            ->assertSee('&lt;img src=&quot;/storage/campaign-signature/'.$file.'&quot; alt=&quot;&quot;&gt;', false)
            ->assertDontSee('http://localhost/storage/campaign-signature', false);

        $this->assertStringContainsString('src="/storage/campaign-signature/'.$file.'"', CampaignSignature::forEmail($old));
        $this->assertStringContainsString('alt=""', CampaignSignature::forEmail($old));
    }

    public function test_an_image_only_signature_counts_as_a_signature(): void
    {
        $this->save('<p><img src="'.$this->dataUri($this->logoPng()).'"></p>')->assertSessionHasNoErrors();

        $settings = app(CampaignSettings::class);
        $this->assertNotNull($settings->signature());
        $this->assertTrue($settings->sendOptions(\App\Enums\CampaignChannel::Email)['include_signature']);

        $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))->get(route('campaigns.create'))
            ->assertSee('Add the signature from Campaign Setup')->assertDontSee('No signature set up yet');
    }
}
