<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityFeedController;
use App\Http\Controllers\ActivityFeedSettingsController;
use App\Http\Controllers\BulkUploadController;
use App\Http\Controllers\CommonReportController;
use App\Http\Controllers\DailySummaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailAccountController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\GoalLeaderboardController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\KnowledgeBaseCategoryController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LeadBulkUploadController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadNoteAttachmentController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\LeadWhatsappUserController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrgTreeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RawDataBulkUploadController;
use App\Http\Controllers\RawDataCommentController;
use App\Http\Controllers\RawDataController;
use App\Http\Controllers\ReleaseNoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequirementCommentController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupportTicketAttachmentController;
use App\Http\Controllers\SupportTicketCommentController;
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

    // Bulk Upload hub — links out to each resource's own bulk-upload flow below.
    Route::get('bulk-upload', [BulkUploadController::class, 'index'])->name('bulk-upload.index');

    // Leads
    // Registered before the resource so /leads/bulk-upload isn't swallowed by the {lead} wildcard.
    Route::get('leads/bulk-upload', [LeadBulkUploadController::class, 'create'])->name('leads.bulk-upload.create');
    Route::get('leads/bulk-upload/template', [LeadBulkUploadController::class, 'template'])->name('leads.bulk-upload.template');
    Route::post('leads/bulk-upload', [LeadBulkUploadController::class, 'store'])->name('leads.bulk-upload.store');
    Route::get('leads/check-duplicate', [LeadController::class, 'checkDuplicate'])->name('leads.check-duplicate');
    Route::resource('leads', LeadController::class);
    Route::post('leads/{lead}/archive', [LeadController::class, 'archive'])->name('leads.archive');
    Route::post('leads/{lead}/restore', [LeadController::class, 'restore'])->name('leads.restore');
    Route::post('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update');
    Route::post('leads/{lead}/close', [LeadController::class, 'close'])->name('leads.close');
    Route::get('leads/{lead}/walkthrough', [LeadController::class, 'walkthrough'])->name('leads.walkthrough');
    Route::get('leads/{lead}/export-pdf', [LeadController::class, 'exportPdf'])->name('leads.export-pdf');

    Route::post('leads/{lead}/activities', [ActivityController::class, 'store'])->name('leads.activities.store');
    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');

    // Raw Data — minimal contact records, later converted into full Leads.
    // Registered before the resource so /raw-data/bulk-upload isn't swallowed by the {raw_data} wildcard.
    Route::get('raw-data/bulk-upload', [RawDataBulkUploadController::class, 'create'])->name('raw-data.bulk-upload.create');
    Route::get('raw-data/bulk-upload/template', [RawDataBulkUploadController::class, 'template'])->name('raw-data.bulk-upload.template');
    Route::post('raw-data/bulk-upload', [RawDataBulkUploadController::class, 'store'])->name('raw-data.bulk-upload.store');
    Route::post('raw-data/bulk-upload/paste', [RawDataBulkUploadController::class, 'storePasted'])->name('raw-data.bulk-upload.store-paste');
    Route::get('raw-data/bulk-upload/batches/{batch}', [RawDataBulkUploadController::class, 'showBatch'])->name('raw-data.bulk-upload.batches.show');
    Route::get('raw-data/bulk-upload/batches/{batch}/download', [RawDataBulkUploadController::class, 'downloadBatchRejections'])->name('raw-data.bulk-upload.batches.download');
    Route::post('raw-data/delete-incomplete', [RawDataController::class, 'deleteIncomplete'])->name('raw-data.delete-incomplete');
    Route::resource('raw-data', RawDataController::class)->parameters(['raw-data' => 'raw_data'])->except('edit', 'update');
    Route::post('raw-data/{raw_data}/mark-not-valid', [RawDataController::class, 'markNotValid'])->name('raw-data.mark-not-valid');
    Route::post('raw-data/{raw_data}/mark-hold', [RawDataController::class, 'markHold'])->name('raw-data.mark-hold');
    Route::post('raw-data/{raw_data}/convert', [RawDataController::class, 'convert'])->name('raw-data.convert');
    Route::post('raw-data/{raw_data}/assign', [RawDataController::class, 'assign'])->name('raw-data.assign');
    Route::post('raw-data/{raw_data}/comments', [RawDataCommentController::class, 'store'])->name('raw-data.comments.store');

    Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store'])->name('leads.notes.store');
    Route::delete('leads/{lead}/notes/{note}', [LeadNoteController::class, 'destroy'])->name('leads.notes.destroy');
    Route::get('lead-note-attachments/{attachment}/download', [LeadNoteAttachmentController::class, 'download'])->name('lead-note-attachments.download');

    Route::resource('follow-ups', FollowUpController::class)->except('show');
    Route::post('leads/{lead}/follow-ups', [FollowUpController::class, 'storeForLead'])->name('leads.follow-ups.store');

    // Registered before the resource so /requirements/export-pdf and /requirements/company/{lead} aren't swallowed by the {requirement} wildcard.
    Route::get('requirements/export-pdf', [RequirementController::class, 'exportPdf'])->name('requirements.export-pdf');
    Route::get('requirements/company/{lead}', [RequirementController::class, 'company'])->name('requirements.company');
    Route::resource('requirements', RequirementController::class);
    Route::post('leads/{lead}/requirements', [RequirementController::class, 'storeForLead'])->name('leads.requirements.store');
    Route::post('requirements/{requirement}/comments', [RequirementCommentController::class, 'store'])->name('requirements.comments.store');

    // Registered before the resource so /goals/leaderboard isn't swallowed by the {goal} wildcard.
    Route::get('goals/leaderboard', [GoalLeaderboardController::class, 'index'])->name('goals.leaderboard');
    Route::resource('goals', GoalController::class);

    // Organization-wide task management, hierarchy-scoped.
    Route::resource('tasks', TaskController::class);
    Route::post('leads/{lead}/tasks', [TaskController::class, 'storeForLead'])->name('leads.tasks.store');
    Route::post('tasks/{task}/checklist-items', [TaskChecklistItemController::class, 'store'])->name('tasks.checklist-items.store');
    Route::patch('tasks/{task}/checklist-items/{checklistItem}', [TaskChecklistItemController::class, 'update'])->name('tasks.checklist-items.update');
    Route::delete('tasks/{task}/checklist-items/{checklistItem}', [TaskChecklistItemController::class, 'destroy'])->name('tasks.checklist-items.destroy');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    // Lead progress tracking — managed by Customer Success/Management.
    Route::resource('trainings', TrainingController::class)->except('show');
    Route::get('leads/{lead}/trainings', [TrainingController::class, 'forLead'])->name('leads.trainings.index');

    // Open to every role — see SupportTicketPolicy.
    Route::resource('support-tickets', SupportTicketController::class);
    Route::post('leads/{lead}/support-tickets', [SupportTicketController::class, 'storeForLead'])->name('leads.support-tickets.store');
    Route::post('support-tickets/{support_ticket}/comments', [SupportTicketCommentController::class, 'store'])->name('support-tickets.comments.store');
    Route::patch('support-tickets/{support_ticket}/comments/{comment}', [SupportTicketCommentController::class, 'update'])->name('support-tickets.comments.update');
    Route::get('support-ticket-attachments/{attachment}/download', [SupportTicketAttachmentController::class, 'download'])->name('support-ticket-attachments.download');
    Route::get('support-ticket-attachments/{attachment}/preview', [SupportTicketAttachmentController::class, 'preview'])->name('support-ticket-attachments.preview');

    Route::resource('daily-summaries', DailySummaryController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('release-notes', ReleaseNoteController::class);

    Route::resource('knowledge-base', KnowledgeBaseController::class);
    Route::get('knowledge-base/{knowledge_base}/download', [KnowledgeBaseController::class, 'download'])->name('knowledge-base.download');

    Route::resource('meetings', MeetingController::class)->except('show');

    Route::get('common-reports/goal-vs-achievement', [CommonReportController::class, 'goalVsAchievement'])->name('common-reports.goal-vs-achievement');
    Route::get('common-reports/my-contributions', [CommonReportController::class, 'myContributions'])->name('common-reports.my-contributions');

    // Available while impersonating (the active session is a regular user at this point).
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    // Collaborative Activity Feed dashboard widget — one JSON endpoint reused
    // by the widget instance embedded in every role's dashboard.
    Route::get('activity-feed', [ActivityFeedController::class, 'index'])->name('activity-feed.index');

    // Team Meeting Room — a single shared workspace, open to every user
    // (see AgendaPolicy). Selection/search/filter/sort all live in the
    // query string of one index route rather than a separate show route,
    // so switching the selected agenda never loses the current filters.
    Route::get('meeting-room', [MeetingRoomController::class, 'index'])->name('meeting-room.index');
    Route::post('meeting-room', [MeetingRoomController::class, 'store'])->name('meeting-room.store');
    Route::patch('meeting-room/{agenda}/status', [MeetingRoomController::class, 'updateStatus'])->name('meeting-room.status.update');
    Route::get('meeting-room/{agenda}/discussions', [MeetingRoomController::class, 'discussions'])->name('meeting-room.discussions');
    Route::post('meeting-room/{agenda}/discussions', [MeetingRoomController::class, 'storeComment'])->name('meeting-room.discussions.store');

    Route::get('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // WhatsApp inbox — visibility is enforced per-lead by LeadPolicy::chatWhatsapp,
    // not by a route middleware, since access is per-assignment rather than per-role.
    Route::get('whatsapp', [WhatsappChatController::class, 'index'])->name('whatsapp.index');
    Route::get('whatsapp-templates', [WhatsappChatController::class, 'templates'])->name('whatsapp.templates.index');
    Route::get('whatsapp/{lead}', [WhatsappChatController::class, 'show'])->name('whatsapp.show');
    Route::get('whatsapp/{lead}/messages', [WhatsappChatController::class, 'messages'])->name('whatsapp.messages');
    Route::post('whatsapp/{lead}/messages', [WhatsappChatController::class, 'sendMessage'])->name('whatsapp.messages.store');
    Route::post('whatsapp/{lead}/templates', [WhatsappChatController::class, 'sendTemplate'])->name('whatsapp.templates.store');

    // Team & Org Hierarchy — visibility derived from reporting_manager_id,
    // available to any authenticated user regardless of role (an IC with no
    // direct reports just sees an empty state, per OrganizationHierarchyPolicy).
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::get('team/activities', [TeamController::class, 'activities'])->name('team.activities');
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

        Route::get('activity-feed-settings', [ActivityFeedSettingsController::class, 'edit'])->name('activity-feed-settings.edit');
        Route::put('activity-feed-settings', [ActivityFeedSettingsController::class, 'update'])->name('activity-feed-settings.update');
    });
});
