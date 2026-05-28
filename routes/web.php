<?php

use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Web\Auth\NewPasswordController;
use App\Http\Controllers\Web\Auth\PasswordResetLinkController;
use App\Http\Controllers\Web\CalendarEventController;
use App\Http\Controllers\Web\ClientContactController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\ClientPortalController;
use App\Http\Controllers\Web\ClientTagController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeadlineController;
use App\Http\Controllers\Web\DocumentCategoryController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\DocumentRequestController;
use App\Http\Controllers\Web\DocumentRequestItemController;
use App\Http\Controllers\Web\FinanceController;
use App\Http\Controllers\Web\OrganizationController;
use App\Http\Controllers\Web\OrganizationInvitationController;
use App\Http\Controllers\Web\OrganizationMemberController;
use App\Http\Controllers\Web\PortalController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\TaskChecklistItemController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\TaskTemplateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/style-guide', function () {
    return Inertia::render('StyleGuide/Index');
})->name('style-guide');

Route::get('/docs', function () {
    return Inertia::render('Docs/Index');
})->name('docs');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:login')->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::get('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'show'])->name('web.invitations.accept.show');
Route::get('/client-portal/{token}', [ClientPortalController::class, 'show'])->name('client-portal.show');
Route::post('/client-portal/{token}/consent', [ClientPortalController::class, 'storeConsent'])->name('client-portal.consent.store');
Route::post('/client-portal/{token}/messages', [ClientPortalController::class, 'storeMessage'])->name('client-portal.messages.store');
Route::post('/client-portal/{token}/tickets', [ClientPortalController::class, 'storeTicket'])->name('client-portal.tickets.store');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    Route::get('/team', [OrganizationMemberController::class, 'index'])->name('team.index');
    Route::post('/organization-invitations', [OrganizationInvitationController::class, 'store'])->name('organization-invitations.store');
    Route::delete('/organization-invitations/{organizationInvitation}', [OrganizationInvitationController::class, 'destroy'])->name('organization-invitations.destroy');
    Route::patch('/organization-members/{organizationMember}/suspend', [OrganizationMemberController::class, 'suspend'])->name('organization-members.suspend');
    Route::patch('/organization-members/{organizationMember}/reactivate', [OrganizationMemberController::class, 'reactivate'])->name('organization-members.reactivate');

    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::patch('/clients/{client}/status', [ClientController::class, 'updateStatus'])->name('clients.status.update');
    Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store'])->name('clients.contacts.store');
    Route::delete('/client-contacts/{contact}', [ClientContactController::class, 'destroy'])->name('client-contacts.destroy');
    Route::post('/client-tags', [ClientTagController::class, 'store'])->name('client-tags.store');
    Route::post('/clients/{client}/tags/{tag}', [ClientTagController::class, 'attach'])->name('clients.tags.attach');
    Route::delete('/clients/{client}/tags/{tag}', [ClientTagController::class, 'detach'])->name('clients.tags.detach');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::patch('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'storeVersion'])->name('documents.versions.store');
    Route::get('/documents/{document}/view', [DocumentController::class, 'view'])->name('documents.view');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/document-categories', [DocumentCategoryController::class, 'store'])->name('document-categories.store');
    Route::patch('/document-categories/{category}', [DocumentCategoryController::class, 'update'])->name('document-categories.update');
    Route::delete('/document-categories/{category}', [DocumentCategoryController::class, 'destroy'])->name('document-categories.destroy');

    Route::get('/document-requests', [DocumentRequestController::class, 'index'])->name('document-requests.index');
    Route::post('/document-requests', [DocumentRequestController::class, 'store'])->name('document-requests.store');
    Route::get('/document-requests/{documentRequest}', [DocumentRequestController::class, 'show'])->name('document-requests.show');
    Route::patch('/document-requests/{documentRequest}/cancel', [DocumentRequestController::class, 'cancel'])->name('document-requests.cancel');
    Route::post('/document-request-items/{item}/upload', [DocumentRequestItemController::class, 'upload'])->name('document-request-items.upload');
    Route::patch('/document-request-items/{item}/approve', [DocumentRequestItemController::class, 'approve'])->name('document-request-items.approve');
    Route::patch('/document-request-items/{item}/reject', [DocumentRequestItemController::class, 'reject'])->name('document-request-items.reject');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status.update');
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('/tasks/{task}/checklist-items', [TaskChecklistItemController::class, 'store'])->name('tasks.checklist-items.store');
    Route::patch('/task-checklist-items/{item}', [TaskChecklistItemController::class, 'update'])->name('task-checklist-items.update');
    Route::delete('/task-checklist-items/{item}', [TaskChecklistItemController::class, 'destroy'])->name('task-checklist-items.destroy');

    Route::get('/task-templates', [TaskTemplateController::class, 'index'])->name('task-templates.index');
    Route::post('/task-templates', [TaskTemplateController::class, 'store'])->name('task-templates.store');
    Route::patch('/task-templates/{template}', [TaskTemplateController::class, 'update'])->name('task-templates.update');
    Route::post('/task-templates/{template}/create-tasks', [TaskTemplateController::class, 'createTasks'])->name('task-templates.create-tasks');

    Route::get('/deadlines', [DeadlineController::class, 'index'])->name('deadlines.index');
    Route::post('/deadlines', [DeadlineController::class, 'store'])->name('deadlines.store');
    Route::patch('/deadlines/{deadline}', [DeadlineController::class, 'update'])->name('deadlines.update');
    Route::patch('/deadlines/{deadline}/complete', [DeadlineController::class, 'complete'])->name('deadlines.complete');
    Route::patch('/deadlines/{deadline}/request-review', [DeadlineController::class, 'requestReview'])->name('deadlines.request-review');
    Route::patch('/deadlines/{deadline}/approve-review', [DeadlineController::class, 'approveReview'])->name('deadlines.approve-review');

    Route::get('/calendar', [CalendarEventController::class, 'index'])->name('calendar.index');
    Route::post('/calendar-events', [CalendarEventController::class, 'store'])->name('calendar-events.store');
    Route::patch('/calendar-events/{event}', [CalendarEventController::class, 'update'])->name('calendar-events.update');
    Route::post('/calendar-events/{event}/notes', [CalendarEventController::class, 'notes'])->name('calendar-events.notes');

    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/finance/categories', [FinanceController::class, 'storeCategory'])->name('finance.categories.store');
    Route::post('/finance/receivables', [FinanceController::class, 'storeReceivable'])->name('finance.receivables.store');
    Route::post('/finance/receivables/{receivable}/payments', [FinanceController::class, 'payReceivable'])->name('finance.receivables.payments.store');
    Route::patch('/finance/receivables/{receivable}/cancel', [FinanceController::class, 'cancelReceivable'])->name('finance.receivables.cancel');
    Route::post('/finance/payables', [FinanceController::class, 'storePayable'])->name('finance.payables.store');
    Route::post('/finance/payables/{payable}/payments', [FinanceController::class, 'payPayable'])->name('finance.payables.payments.store');

    Route::get('/portal', [PortalController::class, 'index'])->name('portal.index');
    Route::post('/portal/accesses', [PortalController::class, 'storeAccess'])->name('portal.accesses.store');
    Route::patch('/portal/accesses/{access}/revoke', [PortalController::class, 'revokeAccess'])->name('portal.accesses.revoke');
    Route::post('/portal/messages', [PortalController::class, 'storeMessage'])->name('portal.messages.store');
    Route::post('/portal/tickets', [PortalController::class, 'storeTicket'])->name('portal.tickets.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/filters', [ReportController::class, 'storeFilter'])->name('reports.filters.store');
    Route::post('/reports/schedules', [ReportController::class, 'storeSchedule'])->name('reports.schedules.store');
    Route::post('/reports/monthly', [ReportController::class, 'generateMonthly'])->name('reports.monthly.store');
    Route::patch('/reports/{report}/release', [ReportController::class, 'release'])->name('reports.release');

    Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'store'])->name('web.invitations.accept');
});
