<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EmailTemplateService
{
    /**
     * @return Collection<int, EmailTemplate>
     */
    public function list(): Collection
    {
        return EmailTemplate::orderBy('name')->get();
    }

    public function findByKey(string $key): ?EmailTemplate
    {
        return EmailTemplate::where('key', $key)->first();
    }

    public function update(EmailTemplate $template, array $attributes, User $actor): EmailTemplate
    {
        $template->update([
            ...$attributes,
            'updated_by' => $actor->id,
        ]);

        return $template;
    }

    /**
     * Replaces every {{variable}} token with its value — missing variables
     * are left blank rather than left as a literal "{{token}}" in what's
     * actually mailed to a client.
     *
     * @param  array<string, string|null>  $variables
     */
    public static function merge(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) ($value ?? ''), $text);
        }

        return preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $text);
    }

    /**
     * @param  array<string, string|null>  $variables
     * @return array{subject: string, body: string}
     */
    public function render(EmailTemplate $template, array $variables): array
    {
        return [
            'subject' => self::merge($template->subject, $variables),
            'body' => self::merge($template->body, $variables),
        ];
    }
}
