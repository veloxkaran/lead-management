<?php

use App\Http\Controllers\AccountRequestController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityFeedController;
use App\Http\Controllers\ActivityFeedSettingsController;
use App\Http\Controllers\CommonReportController;
use App\Http\Controllers\DailySummaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailAccountController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\ImplementationRequestController;
use App\Http\Controllers\IndividualJdController;
use App\Http\Controllers\KnowledgeBaseCategoryController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadNoteAttachmentController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\LeadWhatsappUserController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MyPolicyDocumentsController;
use App\Http\Controllers\OrgTreeController;
use App\Http\Controllers\PolicyDocumentAcknowledgmentController;
use App\Http\Controllers\PolicyDocumentReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReleaseNoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SopController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaskChecklistItemController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsappChatController;
use App\Http\Controllers\WhatsappSettingsController;
use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

require __DIR__.'/auth.php';

// Public — called directly by Meta, not by a logged-in browser session.
Route::get('whatsapp/webhook', [WhatsappWebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
Route::post('whatsapp/webhook', [WhatsappWebhookController::class, 'handle'])->name('whatsapp.webhook.handle');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Personal email account configuration — each user manages only their own.
    Route::resource('email-accounts', EmailAccountController::class)->except('show');
    Route::post('email-accounts/{email_account}/test-connection', [EmailAccountController::class, 'testConnection'])->name('email-accounts.test-connection');
    Route::post('email-accounts/{email_account}/set-default', [EmailAccountController::class, 'setDefault'])->name('email-accounts.set-default');
    Route::patch('email-accounts/{email_account}/toggle-active', [EmailAccountController::class, 'toggleActive'])->name('email-accounts.toggle-active');

    // Leads
    Route::resource('leads', LeadController::class);
    Route::post('leads/{lead}/archive', [LeadController::class, 'archive'])->name('leads.archive');
    Route::post('leads/{lead}/restore', [LeadController::class, 'restore'])->name('leads.restore');
    Route::post('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update');
    Route::post('leads/{lead}/close', [LeadController::class, 'close'])->name('leads.close');
    Route::get('leads/{lead}/walkthrough', [LeadController::class, 'walkthrough'])->name('leads.walkthrough');

    Route::post('leads/{lead}/activities', [ActivityController::class, 'store'])->name('leads.activities.store');
    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');

    Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store'])->name('leads.notes.store');
    Route::delete('leads/{lead}/notes/{note}', [LeadNoteController::class, 'destroy'])->name('leads.notes.destroy');
    Route::get('lead-note-attachments/{attachment}/download', [LeadNoteAttachmentController::class, 'download'])->name('lead-note-attachments.download');

    Route::resource('follow-ups', FollowUpController::class)->except('show');
    Route::post('leads/{lead}/follow-ups', [FollowUpController::class, 'storeForLead'])->name('leads.follow-ups.store');

    Route::resource('requirements', RequirementController::class)->except('show');
    Route::post('leads/{lead}/requirements', [RequirementController::class, 'storeForLead'])->name('leads.requirements.store');

    Route::resource('goals', GoalController::class)->except('show');

    // Organization-wide task management, hierarchy-scoped.
    Route::resource('tasks', TaskController::class);
    Route::post('tasks/{task}/checklist-items', [TaskChecklistItemController::class, 'store'])->name('tasks.checklist-items.store');
    Route::patch('tasks/{task}/checklist-items/{checklistItem}', [TaskChecklistItemController::class, 'update'])->name('tasks.checklist-items.update');
    Route::delete('tasks/{task}/checklist-items/{checklistItem}', [TaskChecklistItemController::class, 'destroy'])->name('tasks.checklist-items.destroy');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    // Customer Success handoff — raised by Business Development, worked by Customer Success.
    Route::resource('implementation-requests', ImplementationRequestController::class)->except('show');

    // Lead progress tracking — managed by Customer Success/Management.
    Route::resource('trainings', TrainingController::class)->except('show');
    Route::get('leads/{lead}/trainings', [TrainingController::class, 'forLead'])->name('leads.trainings.index');

    Route::resource('subscriptions', SubscriptionController::class)->except('show');
    Route::get('leads/{lead}/subscriptions', [SubscriptionController::class, 'forLead'])->name('leads.subscriptions.index');

    // Raised by Managers, worked by Customer Success.
    Route::resource('support-tickets', SupportTicketController::class)->except('show');

    // Finance handoff — raised by Business Development, processed by Finance.
    Route::resource('account-requests', AccountRequestController::class)->except('show');

    Route::resource('daily-summaries', DailySummaryController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('release-notes', ReleaseNoteController::class);

    Route::resource('knowledge-base', KnowledgeBaseController::class);
    Route::get('knowledge-base/{knowledge_base}/download', [KnowledgeBaseController::class, 'download'])->name('knowledge-base.download');

    Route::resource('meetings', MeetingController::class)->except('show');

    Route::get('common-reports/goal-vs-achievement', [CommonReportController::class, 'goalVsAchievement'])->name('common-reports.goal-vs-achievement');
    Route::get('common-reports/personal-achievement', [CommonReportController::class, 'personalAchievement'])->name('common-reports.personal-achievement');

    // Available while impersonating (the active session is a regular user at this point).
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    // Collaborative Activity Feed dashboard widget — one JSON endpoint reused
    // by the widget instance embedded in every role's dashboard.
    Route::get('activity-feed', [ActivityFeedController::class, 'index'])->name('activity-feed.index');

    // WhatsApp inbox — visibility is enforced per-lead by LeadPolicy::chatWhatsapp,
    // not by a route middleware, since access is per-assignment rather than per-role.
    Route::get('whatsapp', [WhatsappChatController::class, 'index'])->name('whatsapp.index');
    Route::get('whatsapp-templates', [WhatsappChatController::class, 'templates'])->name('whatsapp.templates.index');
    Route::get('whatsapp/{lead}', [WhatsappChatController::class, 'show'])->name('whatsapp.show');
    Route::get('whatsapp/{lead}/messages', [WhatsappChatController::class, 'messages'])->name('whatsapp.messages');
    Route::post('whatsapp/{lead}/messages', [WhatsappChatController::class, 'sendMessage'])->name('whatsapp.messages.store');
    Route::post('whatsapp/{lead}/templates', [WhatsappChatController::class, 'sendTemplate'])->name('whatsapp.templates.store');

    // "My SOPs & Job Descriptions" — any employee reviews what's assigned to them,
    // and reopens/re-reads a document any time outside the forced onboarding flow.
    Route::get('my-policy-documents', [MyPolicyDocumentsController::class, 'index'])->name('my-policy-documents.index');
    Route::get('my-policy-documents/{policy_document_version}', [MyPolicyDocumentsController::class, 'show'])->name('my-policy-documents.show');

    // Backing endpoints for both the forced onboarding modal and the reopen/review page.
    Route::post('policy-documents/{policy_document_version}/view', [PolicyDocumentAcknowledgmentController::class, 'view'])->name('policy-documents.view');
    Route::post('policy-documents/{policy_document_version}/acknowledge', [PolicyDocumentAcknowledgmentController::class, 'acknowledge'])->name('policy-documents.acknowledge');

    // Team & Org Hierarchy — visibility derived from reporting_manager_id,
    // available to any authenticated user regardless of role (an IC with no
    // direct reports just sees an empty state, per OrganizationHierarchyPolicy).
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::get('org-tree', [OrgTreeController::class, 'index'])->name('org-tree.index');

    // Manager and Super Admin — full reporting suite, company-wide.
    Route::middleware('overseer')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/daily', [ReportController::class, 'daily'])->name('reports.daily');
        Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('reports/quarterly', [ReportController::class, 'quarterly'])->name('reports.quarterly');
        Route::get('reports/master', [ReportController::class, 'master'])->name('reports.master');
        Route::get('reports/time', [ReportController::class, 'time'])->name('reports.time');
        Route::get('reports/opportunity', [ReportController::class, 'opportunity'])->name('reports.opportunity');
        Route::get('reports/failure', [ReportController::class, 'failure'])->name('reports.failure');
        Route::get('reports/deal', [ReportController::class, 'deal'])->name('reports.deal');
        Route::get('reports/requirement', [ReportController::class, 'requirement'])->name('reports.requirement');
        Route::get('reports/conversion', [ReportController::class, 'conversion'])->name('reports.conversion');
        Route::get('reports/{type}/export/{format}', [ReportController::class, 'export'])->name('reports.export');
    });

    // Super Admin only — configuration, not just visibility.
    Route::middleware('super_admin')->group(function () {
        Route::resource('users', UserController::class);
        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');

        Route::resource('lead-statuses', LeadStatusController::class)->except('show');
        Route::post('lead-statuses/reorder', [LeadStatusController::class, 'reorder'])->name('lead-statuses.reorder');

        Route::resource('knowledge-base-categories', KnowledgeBaseCategoryController::class)->except('show');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('whatsapp-settings', [WhatsappSettingsController::class, 'edit'])->name('whatsapp-settings.edit');
        Route::put('whatsapp-settings', [WhatsappSettingsController::class, 'update'])->name('whatsapp-settings.update');
        Route::post('whatsapp-settings/test', [WhatsappSettingsController::class, 'test'])->name('whatsapp-settings.test');

        Route::put('leads/{lead}/whatsapp-users', [LeadWhatsappUserController::class, 'update'])->name('leads.whatsapp-users.update');

        // Both share one route parameter name ("policy_document") so the
        // shared PolicyDocumentTypeController base class can use a single
        // consistent method signature for implicit route-model binding.
        Route::resource('sops', SopController::class)->except('show')->parameters(['sops' => 'policy_document']);
        Route::post('sops/{policy_document}/versions', [SopController::class, 'storeVersion'])->name('sops.versions.store');

        Route::resource('individual-jds', IndividualJdController::class)->except('show')->parameters(['individual-jds' => 'policy_document']);
        Route::post('individual-jds/{policy_document}/versions', [IndividualJdController::class, 'storeVersion'])->name('individual-jds.versions.store');

        Route::get('policy-documents/reports', [PolicyDocumentReportController::class, 'index'])->name('policy-documents.reports.index');
        Route::get('policy-documents/reports/{policy_document}', [PolicyDocumentReportController::class, 'show'])->name('policy-documents.reports.show');

        Route::get('activity-feed-settings', [ActivityFeedSettingsController::class, 'edit'])->name('activity-feed-settings.edit');
        Route::put('activity-feed-settings', [ActivityFeedSettingsController::class, 'update'])->name('activity-feed-settings.update');
    });
});
