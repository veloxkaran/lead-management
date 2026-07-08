<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CommonReportController;
use App\Http\Controllers\DailySummaryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\KnowledgeBaseCategoryController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadNoteAttachmentController;
use App\Http\Controllers\LeadNoteController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReleaseNoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Leads
    Route::resource('leads', LeadController::class);
    Route::post('leads/{lead}/archive', [LeadController::class, 'archive'])->name('leads.archive');
    Route::post('leads/{lead}/restore', [LeadController::class, 'restore'])->name('leads.restore');
    Route::post('leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update');
    Route::post('leads/{lead}/close', [LeadController::class, 'close'])->name('leads.close');

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

    Route::resource('daily-summaries', DailySummaryController::class)->only(['index', 'create', 'store', 'edit', 'update']);

    Route::resource('release-notes', ReleaseNoteController::class);

    Route::resource('knowledge-base', KnowledgeBaseController::class);
    Route::get('knowledge-base/{knowledge_base}/download', [KnowledgeBaseController::class, 'download'])->name('knowledge-base.download');

    Route::resource('meetings', MeetingController::class)->except('show');

    Route::get('common-reports/goal-vs-achievement', [CommonReportController::class, 'goalVsAchievement'])->name('common-reports.goal-vs-achievement');
    Route::get('common-reports/team-achievement', [CommonReportController::class, 'teamAchievement'])->name('common-reports.team-achievement');
    Route::get('common-reports/personal-achievement', [CommonReportController::class, 'personalAchievement'])->name('common-reports.personal-achievement');

    // Available while impersonating (the active session is a regular user at this point).
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    // Super Admin only
    Route::middleware('super_admin')->group(function () {
        Route::resource('users', UserController::class);
        Route::post('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');

        Route::resource('lead-statuses', LeadStatusController::class)->except('show');
        Route::post('lead-statuses/reorder', [LeadStatusController::class, 'reorder'])->name('lead-statuses.reorder');

        Route::resource('knowledge-base-categories', KnowledgeBaseCategoryController::class)->except('show');

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

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
