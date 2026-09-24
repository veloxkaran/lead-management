<?php

namespace App\Services;

use App\Enums\AnnouncementAudience;
use App\Enums\EmailLogStatus;
use App\Jobs\SendClientNotificationEmail;
use App\Models\Announcement;
use App\Models\EmailLog;
use App\Models\Lead;
use App\Models\User;
use App\Support\EmailImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public const TEMPLATE_KEY = 'announcement';

    /**
     * Combined cap on everything attached (images after optimizing +
     * documents). Attachments are base64-encoded (~1.37× larger), so 7MB
     * becomes ~9.6MB on the wire — under the 10MB many mail servers enforce.
     */
    public const MAX_TOTAL_ATTACHMENT_KB = 7168;

    public function __construct(protected EmailTemplateService $templates)
    {
    }

    /**
     * Leads an announcement goes to: active (not archived) leads in the
     * chosen audience with a valid email address, one per address — two
     * leads sharing an inbox get it once, not twice.
     *
     * @param  array<int, int|string>  $leadStatusIds
     * @return Collection<int, Lead>
     */
    public function recipients(AnnouncementAudience $audience, array $leadStatusIds = []): Collection
    {
        $query = Lead::active()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id');

        match ($audience) {
            AnnouncementAudience::AllLeads => null,
            AnnouncementAudience::Customers => $query->whereHas('status', fn ($q) => $q->where('is_closed_won', true)),
            AnnouncementAudience::LeadStatuses => $query->whereIn('lead_status_id', $leadStatusIds),
        };

        return $query->get(['id', 'company_name', 'contact_person', 'email'])
            ->filter(fn (Lead $lead) => filter_var(trim($lead->email), FILTER_VALIDATE_EMAIL) !== false)
            ->unique(fn (Lead $lead) => strtolower(trim($lead->email)))
            ->values();
    }

    /**
     * Saves the announcement, records a "pending" Email Log row per
     * recipient, and queues the sends — staggered so no more than
     * mail.announcement_per_minute leave per minute.
     *
     * @param  array{title: string, content: string, audience: string, lead_status_ids?: array<int, int|string>}  $attributes
     * @param  array<int, UploadedFile|null>  $images
     * @param  array<int, UploadedFile|null>  $documents
     */
    public function send(array $attributes, array $images, User $actor, array $documents = []): Announcement
    {
        $audience = AnnouncementAudience::from($attributes['audience']);
        $statusIds = $audience === AnnouncementAudience::LeadStatuses
            ? array_map('intval', $attributes['lead_status_ids'] ?? [])
            : null;

        $recipients = $this->recipients($audience, $statusIds ?? []);

        // Optimized before anything is saved — the size cap applies to what
        // is actually emailed, and a rejection leaves no orphaned files.
        $prepared = $this->prepareImages($images);
        $documents = array_values(array_filter($documents, fn ($file) => $file instanceof UploadedFile));
        $this->assertTotalSize($prepared, $documents);

        [$announcement, $logs] = DB::transaction(function () use ($attributes, $audience, $statusIds, $recipients, $prepared, $documents, $actor) {
            $announcement = Announcement::create([
                'title' => $attributes['title'],
                'content' => $attributes['content'],
                'audience' => $audience,
                'lead_status_ids' => $statusIds,
                'recipient_count' => $recipients->count(),
                'created_by' => $actor->id,
            ]);

            $this->storeImages($announcement, $prepared);
            $this->storeDocuments($announcement, $documents);

            $logs = $recipients->map(fn (Lead $lead) => $this->pendingLog($announcement, $lead));

            return [$announcement, $logs];
        });

        // Dispatched after commit so the worker never picks up a job whose
        // log row isn't visible yet.
        $perMinute = config('mail.announcement_per_minute');

        foreach ($logs->values() as $i => $log) {
            SendClientNotificationEmail::dispatch($log)
                ->delay(now()->addMinutes(intdiv($i, $perMinute)));
        }

        return $announcement;
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function render(Announcement $announcement, ?Lead $lead = null): array
    {
        $variables = [
            'title' => $announcement->title,
            'content' => $announcement->content,
            'company_name' => $lead?->company_name,
            'contact_person' => $lead?->contact_person,
            'app_name' => config('app.name'),
        ];

        $template = $this->templates->findByKey(self::TEMPLATE_KEY);

        // Template deleted/renamed — still send something sensible rather
        // than failing the whole announcement.
        if (! $template) {
            return [
                'subject' => $announcement->title,
                'body' => EmailTemplateService::merge("Hi {{contact_person}},\n\n{{content}}\n\nThanks,\n{{app_name}}", $variables),
            ];
        }

        return $this->templates->render($template, $variables);
    }

    private function pendingLog(Announcement $announcement, Lead $lead): EmailLog
    {
        $rendered = $this->render($announcement, $lead);

        $log = new EmailLog([
            'to_email' => trim($lead->email),
            'subject' => $rendered['subject'],
            'body' => $rendered['body'],
            'template_key' => self::TEMPLATE_KEY,
            'status' => EmailLogStatus::Pending,
        ]);
        $log->related()->associate($announcement);
        $log->save();

        return $log;
    }

    /**
     * @param  array<int, UploadedFile|null>  $images
     * @return array<int, array{contents: string, extension: string, name: string}>
     */
    private function prepareImages(array $images): array
    {
        return collect($images)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file) => [
                ...EmailImage::prepare($file),
                'name' => $file->getClientOriginalName(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{contents: string}>  $images
     * @param  array<int, UploadedFile>  $documents
     *
     * @throws ValidationException
     */
    private function assertTotalSize(array $images, array $documents): void
    {
        $bytes = array_sum(array_map(fn (array $image) => strlen($image['contents']), $images))
            + array_sum(array_map(fn (UploadedFile $file) => (int) $file->getSize(), $documents));

        if ($bytes / 1024 > self::MAX_TOTAL_ATTACHMENT_KB) {
            throw ValidationException::withMessages([
                'images' => 'Images and documents total '.round($bytes / 1048576, 1).'MB even after optimizing images — keep them under 7MB so mail servers don\'t reject the email.',
            ]);
        }
    }

    /**
     * @param  array<int, array{contents: string, extension: string, name: string}>  $images
     */
    private function storeImages(Announcement $announcement, array $images): void
    {
        foreach ($images as $image) {
            $path = "announcements/{$announcement->id}/".Str::random(40).'.'.$image['extension'];

            Storage::disk('public')->put($path, $image['contents']);

            $announcement->images()->create([
                'disk_path' => $path,
                'original_name' => $image['name'],
            ]);
        }
    }

    /**
     * @param  array<int, UploadedFile>  $documents
     */
    private function storeDocuments(Announcement $announcement, array $documents): void
    {
        foreach ($documents as $file) {
            $announcement->documents()->create([
                'disk_path' => $file->store("announcements/{$announcement->id}/documents", 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ]);
        }
    }
}
