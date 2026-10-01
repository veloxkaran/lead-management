<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BulkUploadController;
use App\Http\Controllers\ClientSupportController;
use App\Http\Controllers\CommonReportController;
use App\Http\Controllers\DailySummaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailAccountController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AnnouncementDocumentController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignSetupController;
use App\Http\Controllers\CampaignTrackingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\GoalLeaderboardController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\IndustryController;
use App\Http\Controllers\KnowledgeBaseCategoryController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LeadBulkUploadController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadNoteAttachmentController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\LeadStatusController;
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
use App\Http\Controllers\RequirementAttachmentController;
use App\Http\Controllers\RequirementCommentController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\RequirementStatusController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SupportTicketAttachmentController;
use App\Http\Controllers\SupportTicketCommentController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\SystemModuleController;
use App\Http\Controllers\TaskChecklistItemController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ThemePreferenceController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

require __DIR__.'/auth.php';

// Public — client self-service support ticket portal. No login; gated by a
// Support ID + PIN a Super Admin issues from the lead's details page (see
// LeadController::generateSupportAccess()).
Route::prefix('support-access')->name('client-support.')->group(function () {
    Route::get('/', [ClientSupportController::class, 'showVerify'])->name('show');
    Route::post('/', [ClientSupportController::class, 'verify'])->name('verify');
    Route::get('/ticket', [ClientSupportController::class, 'showTicketForm'])->name('ticket.create');
    Route::post('/ticket', [ClientSupportController::class, 'storeTicket'])->name('ticket.store');
    Route::get('/ticket/submitted', [ClientSupportController::class, 'submitted'])->name('ticket.submitted');
    Route::post('/logout', [ClientSupportController::class, 'logout'])->name('logout');
});

// Public — campaign delivery confirmations: the tracking image in campaign
// emails, and the SMS gateway's delivery-report callback (its URL carries a
// secret; see Campaign Setup). No session or CSRF: mail apps and gateways
// have neither, and a session row per email open would bloat the table.
// Same for unsubscribe: mail apps' one-click unsubscribe POSTs with no
// session — the recipient's random token is what authorizes it.
Route::withoutMiddleware([
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    \App\Http\Middleware\EnsureUserIsActive::class,
])->group(function () {
    Route::get('track/email/{token}', [CampaignTrackingController::class, 'open'])->name('campaigns.track-open');
    Route::match(['get', 'post'], 'webhooks/sms/delivery/{secret}', [CampaignTrackingController::class, 'smsDelivery'])->name('webhooks.sms-delivery');
    Route::get('unsubscribe/{token}', [CampaignTrackingController::class, 'unsubscribeForm'])->name('campaigns.unsubscribe');
    Route::post('unsubscribe/{token}', [CampaignTrackingController::class, 'unsubscribe'])->middleware('throttle:30,1')->name('campaigns.unsubscribe.confirm');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/performance-snapshot', [DashboardController::class, 'performanceSnapshotJson'])->name('dashboard.performance-snapshot');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/preferences/theme', [ThemePreferenceController::class, 'update'])->name('preferences.theme.update');

    // Personal email account configuration — each user manages only their own.
    Route::resource('email-accounts', EmailAccountController::class)->except('show');
    Route::post('email-accounts/{email_account}/test-connection', [EmailAccountController::class, 'testConnection'])->name('email-accounts.test-connection');
    Route::post('email-accounts/{email_account}/set-default', [EmailAccountController::class, 'setDefault'])->name('email-accounts.set-default');
    Route::patch('email-accounts/{email_account}/toggle-active', [EmailAccountController::class, 'toggleActive'])->name('email-accounts.toggle-active');

    // Bulk Upload hub — links out to each resource's own bulk-upload flow below.
    Route::get('bulk-upload', [BulkUploadController::class, 'index'])->name('bulk-upload.index');

    // Component routes below are gated per member by `permission:{module}`
    // (see EnsureUserHasPermission / User::hasPermission()) — default is full
    // access. A route-level `permission:{module},{action}` overrides the
    // action the group would infer, e.g. comments/notes count as an edit.

    // Leads
    Route::middleware('permission:leads')->group(function () {
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
        Route::post('leads/{lead}/support-access', [LeadController::class, 'generateSupportAccess'])->name('leads.support-access.generate');
        Route::delete('leads/{lead}/support-access', [LeadController::class, 'revokeSupportAccess'])->name('leads.support-access.revoke');

        Route::post('leads/{lead}/activities', [ActivityController::class, 'store'])->middleware('permission:leads,update')->name('leads.activities.store');
        Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');

        Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store'])->middleware('permission:leads,update')->name('leads.notes.store');
        Route::delete('leads/{lead}/notes/{note}', [LeadNoteController::class, 'destroy'])->middleware('permission:leads,update')->name('leads.notes.destroy');
        Route::get('lead-note-attachments/{attachment}/download', [LeadNoteAttachmentController::class, 'download'])->name('lead-note-attachments.download');
    });

    // Raw Data — minimal contact records, later converted into full Leads.
    Route::middleware('permission:raw_data')->group(function () {
        // Registered before the resource so /raw-data/similar-leads and /raw-data/bulk-upload aren't swallowed by the {raw_data} wildcard.
        Route::get('raw-data/similar-leads', [RawDataController::class, 'similarLeads'])->name('raw-data.similar-leads');
        Route::get('raw-data/bulk-upload', [RawDataBulkUploadController::class, 'create'])->name('raw-data.bulk-upload.create');
        Route::get('raw-data/bulk-upload/template', [RawDataBulkUploadController::class, 'template'])->name('raw-data.bulk-upload.template');
        Route::post('raw-data/bulk-upload', [RawDataBulkUploadController::class, 'store'])->name('raw-data.bulk-upload.store');
        Route::post('raw-data/bulk-upload/paste', [RawDataBulkUploadController::class, 'storePasted'])->name('raw-data.bulk-upload.store-paste');
        Route::get('raw-data/bulk-upload/batches/{batch}', [RawDataBulkUploadController::class, 'showBatch'])->name('raw-data.bulk-upload.batches.show');
        Route::get('raw-data/bulk-upload/batches/{batch}/download', [RawDataBulkUploadController::class, 'downloadBatchRejections'])->name('raw-data.bulk-upload.batches.download');
        Route::post('raw-data/delete-incomplete', [RawDataController::class, 'deleteIncomplete'])->middleware('permission:raw_data,delete')->name('raw-data.delete-incomplete');
        Route::resource('raw-data', RawDataController::class)->parameters(['raw-data' => 'raw_data'])->except('edit', 'update');
        Route::post('raw-data/{raw_data}/mark-not-valid', [RawDataController::class, 'markNotValid'])->name('raw-data.mark-not-valid');
        Route::post('raw-data/{raw_data}/mark-hold', [RawDataController::class, 'markHold'])->name('raw-data.mark-hold');
        Route::post('raw-data/{raw_data}/convert', [RawDataController::class, 'convert'])->name('raw-data.convert');
        Route::post('raw-data/{raw_data}/assign', [RawDataController::class, 'assign'])->name('raw-data.assign');
        Route::post('raw-data/{raw_data}/comments', [RawDataCommentController::class, 'store'])->middleware('permission:raw_data,update')->name('raw-data.comments.store');
    });

    Route::middleware('permission:follow_ups')->group(function () {
        Route::resource('follow-ups', FollowUpController::class)->except('show');
        Route::post('leads/{lead}/follow-ups', [FollowUpController::class, 'storeForLead'])->name('leads.follow-ups.store');
    });

    Route::middleware('permission:requirements')->group(function () {
        // Registered before the resource so /requirements/export-pdf and /requirements/company/{lead} aren't swallowed by the {requirement} wildcard.
        Route::get('requirements/export-pdf', [RequirementController::class, 'exportPdf'])->name('requirements.export-pdf');
        Route::get('requirements/company/{lead}', [RequirementController::class, 'company'])->name('requirements.company');
        Route::resource('requirements', RequirementController::class);
        Route::post('leads/{lead}/requirements', [RequirementController::class, 'storeForLead'])->name('leads.requirements.store');
        Route::post('requirements/{requirement}/comments', [RequirementCommentController::class, 'store'])->middleware('permission:requirements,update')->name('requirements.comments.store');
        Route::patch('requirements/{requirement}/status', [RequirementStatusController::class, 'update'])->name('requirements.status.update');
        Route::post('requirements/{requirement}/assign-to-me', [RequirementController::class, 'assignToMe'])->name('requirements.assign-to-me');
        Route::get('requirement-attachments/{attachment}/download', [RequirementAttachmentController::class, 'download'])->name('requirement-attachments.download');
        Route::get('requirement-attachments/{attachment}/preview', [RequirementAttachmentController::class, 'preview'])->name('requirement-attachments.preview');
    });

    Route::middleware('permission:goals')->group(function () {
        // Registered before the resource so /goals/leaderboard isn't swallowed by the {goal} wildcard.
        Route::get('goals/leaderboard', [GoalLeaderboardController::class, 'index'])->name('goals.leaderboard');
        Route::resource('goals', GoalController::class);
    });

    // Organization-wide task management, hierarchy-scoped.
    Route::middleware('permission:tasks')->group(function () {
        Route::resource('tasks', TaskController::class);
        Route::post('leads/{lead}/tasks', [TaskController::class, 'storeForLead'])->name('leads.tasks.store');
        Route::post('tasks/{task}/checklist-items', [TaskChecklistItemController::class, 'store'])->middleware('permission:tasks,update')->name('tasks.checklist-items.store');
        Route::patch('tasks/{task}/checklist-items/{checklistItem}', [TaskChecklistItemController::class, 'update'])->name('tasks.checklist-items.update');
        Route::delete('tasks/{task}/checklist-items/{checklistItem}', [TaskChecklistItemController::class, 'destroy'])->middleware('permission:tasks,update')->name('tasks.checklist-items.destroy');
        Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->middleware('permission:tasks,update')->name('tasks.comments.store');
        Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->middleware('permission:tasks,update')->name('tasks.comments.destroy');
    });

    // Lead progress tracking — managed by Customer Success/Management.
    Route::middleware('permission:trainings')->group(function () {
        Route::resource('trainings', TrainingController::class)->except('show');
        Route::get('leads/{lead}/trainings', [TrainingController::class, 'forLead'])->name('leads.trainings.index');
    });

    // Open to every role — see SupportTicketPolicy.
    Route::middleware('permission:support_tickets')->group(function () {
        Route::resource('support-tickets', SupportTicketController::class);
        Route::post('leads/{lead}/support-tickets', [SupportTicketController::class, 'storeForLead'])->name('leads.support-tickets.store');
        Route::post('support-tickets/{support_ticket}/comments', [SupportTicketCommentController::class, 'store'])->middleware('permission:support_tickets,update')->name('support-tickets.comments.store');
        Route::patch('support-tickets/{support_ticket}/comments/{comment}', [SupportTicketCommentController::class, 'update'])->name('support-tickets.comments.update');
        Route::post('support-tickets/{support_ticket}/assign-to-me', [SupportTicketController::class, 'assignToMe'])->name('support-tickets.assign-to-me');
        Route::get('support-ticket-attachments/{attachment}/download', [SupportTicketAttachmentController::class, 'download'])->name('support-ticket-attachments.download');
        Route::get('support-ticket-attachments/{attachment}/preview', [SupportTicketAttachmentController::class, 'preview'])->name('support-ticket-attachments.preview');
    });

    Route::resource('daily-summaries', DailySummaryController::class)->only(['index', 'create', 'store', 'edit', 'update'])->middleware('permission:daily_summaries');

    Route::resource('release-notes', ReleaseNoteController::class)->middleware('permission:release_notes');

    // Everyone reads; only Super Admin creates — see AnnouncementPolicy.
    Route::middleware('permission:announcements')->group(function () {
        Route::resource('announcements', AnnouncementController::class)->only(['index', 'create', 'store', 'show']);
        Route::get('announcement-documents/{document}/download', [AnnouncementDocumentController::class, 'download'])->name('announcement-documents.download');
        Route::get('announcement-documents/{document}/preview', [AnnouncementDocumentController::class, 'preview'])->name('announcement-documents.preview');
    });

    // Super Admin, Manager and Business Development — see ContactPolicy.
    Route::middleware('permission:contacts')->group(function () {
        Route::get('contacts/import', [ContactController::class, 'importForm'])->middleware('permission:contacts,create')->name('contacts.import');
        Route::post('contacts/import', [ContactController::class, 'import'])->middleware('permission:contacts,create')->name('contacts.import.store');
        Route::get('contacts/import/template', [ContactController::class, 'template'])->middleware('permission:contacts,create')->name('contacts.import.template');
        Route::get('contacts/export', [ContactController::class, 'export'])->name('contacts.export');
        Route::post('contacts/bulk-delete', [ContactController::class, 'bulkDestroy'])->middleware('permission:contacts,delete')->name('contacts.bulk-destroy');
        Route::resource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // Super Admin, Manager and Business Development — see CampaignPolicy.
    Route::middleware('permission:campaigns')->group(function () {
        Route::post('campaigns/preview', [CampaignController::class, 'preview'])->middleware('permission:campaigns,create')->name('campaigns.preview');
        Route::post('campaigns/compose-preview', [CampaignController::class, 'composePreview'])->middleware('permission:campaigns,create')->name('campaigns.compose-preview');
        Route::resource('campaigns', CampaignController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
        Route::post('campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
        Route::post('campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
        Route::post('campaigns/{campaign}/retry-failed', [CampaignController::class, 'retryFailed'])->name('campaigns.retry-failed');
        Route::get('campaigns/{campaign}/export', [CampaignController::class, 'export'])->name('campaigns.export');
        Route::get('campaigns/{campaign}/email-preview', [CampaignController::class, 'emailPreview'])->name('campaigns.email-preview');
        Route::get('campaigns/{campaign}/attachments/{attachment}', [CampaignController::class, 'attachment'])->scopeBindings()->name('campaigns.attachment');
        Route::post('campaigns/{campaign}/approve', [CampaignController::class, 'approve'])->name('campaigns.approve');
        Route::post('campaigns/{campaign}/reject', [CampaignController::class, 'reject'])->name('campaigns.reject');
    });

    Route::middleware('permission:knowledge_base')->group(function () {
        Route::resource('knowledge-base', KnowledgeBaseController::class);
        Route::get('knowledge-base/{knowledge_base}/download', [KnowledgeBaseController::class, 'download'])->name('knowledge-base.download');
    });

    Route::resource('meetings', MeetingController::class)->except('show')->middleware('permission:meetings');

    Route::middleware('permission:reports')->group(function () {
        Route::get('common-reports/goal-vs-achievement', [CommonReportController::class, 'goalVsAchievement'])->name('common-reports.goal-vs-achievement');
        Route::get('common-reports/my-contributions', [CommonReportController::class, 'myContributions'])->name('common-reports.my-contributions');
    });

    // Available while impersonating (the active session is a regular user at this point).
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    // Team Meeting Room — a single shared workspace, open to every user
    // (see AgendaPolicy). Selection/search/filter/sort all live in the
    // query string of one index route rather than a separate show route,
    // so switching the selected agenda never loses the current filters.
    Route::middleware('permission:meeting_room')->group(function () {
        Route::get('meeting-room', [MeetingRoomController::class, 'index'])->name('meeting-room.index');
        Route::post('meeting-room', [MeetingRoomController::class, 'store'])->name('meeting-room.store');
        Route::patch('meeting-room/{agenda}/status', [MeetingRoomController::class, 'updateStatus'])->name('meeting-room.status.update');
        Route::get('meeting-room/{agenda}/discussions', [MeetingRoomController::class, 'discussions'])->name('meeting-room.discussions');
        Route::post('meeting-room/{agenda}/discussions', [MeetingRoomController::class, 'storeComment'])->middleware('permission:meeting_room,update')->name('meeting-room.discussions.store');
    });

    Route::get('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Team & Org Hierarchy — visibility derived from reporting_manager_id,
    // available to any authenticated user regardless of role (an IC with no
    // direct reports just sees an empty state, per OrganizationHierarchyPolicy).
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::get('team/activities', [TeamController::class, 'activities'])->name('team.activities');
    Route::get('org-tree', [OrgTreeController::class, 'index'])->name('org-tree.index');

    // Manager and Super Admin — full reporting suite, company-wide.
    Route::middleware(['overseer', 'permission:reports'])->group(function () {
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
        Route::get('users/{user}/permissions', [UserPermissionController::class, 'edit'])->name('users.permissions.edit');
        Route::put('users/{user}/permissions', [UserPermissionController::class, 'update'])->name('users.permissions.update');
        Route::delete('users/{user}/permissions', [UserPermissionController::class, 'destroy'])->name('users.permissions.destroy');

        Route::resource('lead-statuses', LeadStatusController::class)->except('show');
        Route::post('lead-statuses/reorder', [LeadStatusController::class, 'reorder'])->name('lead-statuses.reorder');

        Route::resource('knowledge-base-categories', KnowledgeBaseCategoryController::class)->except('show');

        Route::resource('industries', IndustryController::class)->except('show');
        Route::resource('system-modules', SystemModuleController::class)->except('show');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('email-templates', [EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::get('email-templates/{emailTemplate}/preview', [EmailTemplateController::class, 'preview'])->name('email-templates.preview');

        Route::get('campaign-setup', [CampaignSetupController::class, 'edit'])->name('campaign-setup.edit');
        Route::put('campaign-setup', [CampaignSetupController::class, 'update'])->name('campaign-setup.update');
        Route::put('campaign-setup/email', [CampaignSetupController::class, 'updateEmail'])->name('campaign-setup.update-email');
        Route::post('campaign-setup/test-email', [CampaignSetupController::class, 'testEmail'])->name('campaign-setup.test-email');
        Route::post('campaign-setup/test-sms', [CampaignSetupController::class, 'testSms'])->name('campaign-setup.test-sms');
        Route::get('campaign-setup/domain-check', [CampaignSetupController::class, 'domainCheck'])->name('campaign-setup.domain-check');

        Route::get('email-logs', [EmailLogController::class, 'index'])->name('email-logs.index');
        Route::get('email-logs/{emailLog}', [EmailLogController::class, 'show'])->name('email-logs.show');
    });
});
