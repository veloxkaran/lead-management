<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Services\EmailTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    /**
     * Fixed keys documented on the edit form for a template with no
     * dedicated case in variablesFor() below.
     */
    private const ALL_VARIABLE_NAMES = [
        'company_name', 'contact_person', 'requirement', 'subject',
        'priority', 'old_status', 'new_status', 'due_date', 'app_name',
    ];

    public function __construct(protected EmailTemplateService $templates)
    {
    }

    public function index(): View
    {
        return view('email-templates.index', [
            'templates' => $this->templates->list(),
        ]);
    }

    public function edit(EmailTemplate $emailTemplate): View
    {
        return view('email-templates.edit', [
            'template' => $emailTemplate,
            // Formatted here, not in the Blade source — a literal "{{" / "}}"
            // pair written directly in a .blade.php file's echo expression
            // trips Blade's own (regex-based, quote-unaware) tag scanner.
            'variables' => array_map(fn (string $name) => '{{'.$name.'}}', $this->variablesFor($emailTemplate)),
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $this->templates->update($emailTemplate, $validated, $request->user());

        return redirect()->route('email-templates.index')->with('success', 'Email template updated.');
    }

    /**
     * Renders the template merged with fixed sample data — a live preview
     * of exactly what a client would receive, without needing a real
     * requirement/ticket to render against.
     */
    public function preview(EmailTemplate $emailTemplate): View
    {
        $rendered = $this->templates->render($emailTemplate, $this->sampleVariables());

        return view('emails.client-notification', ['body' => $rendered['body']]);
    }

    /**
     * @return array<string, string>
     */
    private function sampleVariables(): array
    {
        return [
            'company_name' => 'Acme Corp',
            'contact_person' => 'Jordan Smith',
            'requirement' => 'Set up SSO for the staging environment',
            'subject' => 'Cannot log in to the client portal',
            'priority' => 'High',
            'old_status' => 'Pending',
            'new_status' => 'In Progress',
            'due_date' => now()->addDays(7)->format('M d, Y'),
            'title' => 'New feature: scheduled reports',
            'content' => "We've just released scheduled reports — you can now have any report emailed to you daily or weekly.",
            'app_name' => config('app.name'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function variablesFor(EmailTemplate $template): array
    {
        return match ($template->key) {
            'requirement_created' => ['company_name', 'contact_person', 'requirement', 'priority', 'due_date', 'app_name'],
            'requirement_status_changed' => ['company_name', 'contact_person', 'requirement', 'old_status', 'new_status', 'app_name'],
            'support_ticket_created' => ['company_name', 'contact_person', 'subject', 'priority', 'app_name'],
            'support_ticket_status_changed' => ['company_name', 'contact_person', 'subject', 'old_status', 'new_status', 'app_name'],
            'announcement' => ['title', 'content', 'company_name', 'contact_person', 'app_name'],
            default => self::ALL_VARIABLE_NAMES,
        };
    }
}
