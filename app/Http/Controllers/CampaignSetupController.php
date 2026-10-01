<?php

namespace App\Http\Controllers;

use App\Enums\CampaignChannel;
use App\Enums\MailEncryption;
use App\Http\Requests\Campaign\UpdateCampaignEmailSetupRequest;
use App\Http\Requests\Campaign\UpdateCampaignSetupRequest;
use App\Mail\CampaignMail;
use App\Models\CampaignUnsubscribe;
use App\Models\EmailAccount;
use App\Services\CampaignMailer;
use App\Services\SmsGateway;
use App\Support\CampaignSettings;
use App\Support\CampaignSignature;
use App\Support\ContactNormalizer;
use App\Support\SenderDomainCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Administration → Campaign Setup. Email tab: who campaign email comes
 * from (system settings, an Email Account, or a dedicated SMTP login),
 * reply-to, signature, footer and sending speed. SMS tab: the gateway's
 * endpoint, key and parameter names. Super Admin only (route middleware).
 */
class CampaignSetupController extends Controller
{
    public function __construct(protected CampaignSettings $settings)
    {
    }

    public function edit(Request $request, CampaignMailer $mailer): View
    {
        $email = collect(CampaignSettings::EMAIL_KEYS)->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)]);
        $email['campaign_email_mode'] = $this->settings->emailMode();
        $email['campaign_track_opens'] = $this->settings->tracksOpens() ? '1' : '0';

        // Blank settings show the config defaults they fall back to.
        $speed = $this->settings->sendOptions(CampaignChannel::Email);
        $email['campaign_batch_size'] = (string) $speed['batch_size'];
        $email['campaign_emails_per_minute'] = (string) $speed['per_minute'];
        $email['campaign_batch_pause_minutes'] = (string) $speed['pause_minutes'];

        return view('campaign-setup.edit', [
            'tab' => $request->query('tab') === 'sms' ? 'sms' : 'email',
            'values' => collect([...CampaignSettings::SMS_KEYS])
                ->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)]),
            'email' => $email,
            'signature' => CampaignSignature::forEditor($this->settings->get('campaign_signature')),
            'signatureImageSizes' => CampaignSignature::imageSizes($this->settings->get('campaign_signature')),
            'signatureMissingImages' => CampaignSignature::missingImages($this->settings->get('campaign_signature')),
            'hasSmtpPassword' => $this->settings->hasSmtpPassword(),
            'emailModes' => CampaignSettings::EMAIL_MODES,
            'encryptions' => MailEncryption::cases(),
            'hasApiKey' => $this->settings->hasSmsApiKey(),
            'presets' => CampaignSettings::SMS_PRESETS,
            'emailAccounts' => EmailAccount::withoutGlobalScopes()->where('is_active', true)->with('user:id,name')->orderBy('email_address')->get(),
            'sender' => $mailer->senderDescription(),
            'fromAddress' => $mailer->from()[0],
            'unsubscribeCount' => CampaignUnsubscribe::where('channel', CampaignChannel::Email)->count(),
            'dlrUrl' => route('webhooks.sms-delivery', $this->settings->dlrSecret()),
            'appUrlIsLocal' => in_array(parse_url((string) config('app.url'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true),
        ]);
    }

    public function updateEmail(UpdateCampaignEmailSetupRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach (CampaignSettings::EMAIL_KEYS as $key) {
            $value = $data[$key] ?? null;
            $this->settings->set($key, $value === null ? null : trim((string) $value));
        }

        $this->settings->set('campaign_track_opens', $request->boolean('campaign_track_opens') ? '1' : '0');

        $this->settings->set('campaign_signature', CampaignSignature::save($data['campaign_signature'] ?? null, missing: $missing));

        // A blank password field keeps the saved one; only "Remove saved password" clears it.
        if ($request->boolean('clear_campaign_smtp_password')) {
            $this->settings->setSmtpPassword(null);
        } elseif (filled($data['campaign_smtp_password'] ?? null)) {
            $this->settings->setSmtpPassword($data['campaign_smtp_password']);
        }

        return redirect()->route('campaign-setup.edit')->with('success', 'Email setup saved.'.($missing
            ? " {$missing} signature image(s) whose file had gone missing were removed — insert the image again and save."
            : ''));
    }

    public function update(UpdateCampaignSetupRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach (CampaignSettings::SMS_KEYS as $key) {
            $this->settings->set($key, isset($data[$key]) ? trim((string) $data[$key]) : null);
        }

        // A blank key field keeps the saved one; only "Remove saved key" clears it.
        if ($request->boolean('clear_sms_api_key')) {
            $this->settings->setSmsApiKey(null);
        } elseif (filled($data['sms_api_key'] ?? null)) {
            $this->settings->setSmsApiKey(trim($data['sms_api_key']));
        }

        return redirect()->route('campaign-setup.edit', ['tab' => 'sms'])->with('success', 'SMS setup saved.');
    }

    /**
     * Sends exactly what a recipient would get — signature, footer and an
     * unsubscribe link (to a "test email" page) — with sample merge values.
     */
    public function testEmail(Request $request, CampaignMailer $mailer): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email']]);

        $mail = new CampaignMail(
            'Test email from '.config('app.name'),
            "Hi {$request->user()->name},\n\nThis is a test email from Campaign Setup. It's laid out exactly like a campaign email — your signature and footer are below.\n\nIf it arrived in your inbox (not spam), campaign email is working.",
            unsubscribeUrl: route('campaigns.unsubscribe', 'test-email'),
            signatureHtml: $this->settings->signature(),
            footer: $this->settings->get('campaign_footer'),
        );

        try {
            $mailer->send($data['test_email'], $mail);
        } catch (Throwable $e) {
            // 535 = the server reached fine but refused the login — by far the most common setup mistake.
            $hint = str_contains($e->getMessage(), '535')
                ? ' — The mail server refused the username/password. Check the password by logging into webmail with it, then type it again in "Password" and save. (Your browser may have auto-filled a different saved password.)'
                : '';

            return back()->withInput()->with('error', 'Test email failed: '.$e->getMessage().$hint);
        }

        return back()->withInput()->with('success', "Test email sent to {$data['test_email']}. Check the inbox (and spam folder).");
    }

    public function testSms(Request $request, SmsGateway $sms): RedirectResponse
    {
        $data = $request->validate([
            'test_phone' => ['required', 'string', 'max:30'],
            'test_message' => ['nullable', 'string', 'max:160'],
        ]);

        $number = ContactNormalizer::phone($data['test_phone']);

        if ($number === null) {
            return back()->withInput()->withErrors(['test_phone' => 'Enter a valid phone number.']);
        }

        try {
            $result = $sms->send($number, ($data['test_message'] ?? null) ?: 'Test SMS from '.config('app.name').'.');
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Test SMS failed: '.$e->getMessage());
        }

        return back()->withInput()->with('success', "Gateway accepted the test SMS to {$number}. Response: {$result['response']}");
    }

    /**
     * SPF / DKIM / DMARC / MX for the saved From address's domain.
     */
    public function domainCheck(CampaignMailer $mailer, SenderDomainCheck $check): JsonResponse
    {
        return response()->json($check->check($mailer->from()[0]));
    }
}
