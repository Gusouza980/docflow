<?php

use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\AuditController;
use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Web\Auth\NewPasswordController;
use App\Http\Controllers\Web\Auth\PasswordResetLinkController;
use App\Http\Controllers\Web\Auth\PortalAuthenticatedSessionController;
use App\Http\Controllers\Web\Auth\PortalNewPasswordController;
use App\Http\Controllers\Web\Auth\PortalPasswordResetLinkController;
use App\Http\Controllers\Web\AutomationRuleController;
use App\Http\Controllers\Web\CalendarEventController;
use App\Http\Controllers\Web\ClientContactController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\ClientHubController;
use App\Http\Controllers\Web\ClientPortalController;
use App\Http\Controllers\Web\ClientPortalInviteController;
use App\Http\Controllers\Web\ClientPortalOnboardingController;
use App\Http\Controllers\Web\ClientProfileUpdateController;
use App\Http\Controllers\Web\ClientServiceController;
use App\Http\Controllers\Web\ClientTagController;
use App\Http\Controllers\Web\ContractController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DeadlineController;
use App\Http\Controllers\Web\DocumentCategoryController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\DocumentRequestController;
use App\Http\Controllers\Web\DocumentRequestItemController;
use App\Http\Controllers\Web\FinanceController;
use App\Http\Controllers\Web\InternalNotificationController;
use App\Http\Controllers\Web\LeadController;
use App\Http\Controllers\Web\MessageBatchController;
use App\Http\Controllers\Web\MessageTemplateController;
use App\Http\Controllers\Web\OnboardingTemplateController;
use App\Http\Controllers\Web\OrganizationBillingController;
use App\Http\Controllers\Web\OrganizationController;
use App\Http\Controllers\Web\OrganizationInvitationController;
use App\Http\Controllers\Web\OrganizationMemberController;
use App\Http\Controllers\Web\OrganizationPaymentGatewayController;
use App\Http\Controllers\Web\OrganizationPlanController;
use App\Http\Controllers\Web\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Web\Platform\InvoiceController as PlatformInvoiceController;
use App\Http\Controllers\Web\Platform\OrganizationController as PlatformOrganizationController;
use App\Http\Controllers\Web\Platform\OrganizationPlanController as PlatformOrganizationPlanController;
use App\Http\Controllers\Web\Platform\PlanController as PlatformPlanController;
use App\Http\Controllers\Web\Platform\SubscriptionController as PlatformSubscriptionController;
use App\Http\Controllers\Web\Platform\UsageGuideController as PlatformUsageGuideController;
use App\Http\Controllers\Web\PortalClientNotificationController;
use App\Http\Controllers\Web\PortalController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ServiceTypeController;
use App\Http\Controllers\Web\SubscriptionRequiredController;
use App\Http\Controllers\Web\TaskChecklistItemController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\TaskTemplateController;
use App\Http\Controllers\Web\Webhooks\BillingWebhookController;
use App\Http\Controllers\Web\Webhooks\TenantAsaasWebhookController;
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

Route::post('/webhooks/billing/{provider}', [BillingWebhookController::class, 'store'])->name('webhooks.billing');
Route::post('/webhooks/tenant/asaas/{organization}', [TenantAsaasWebhookController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('webhooks.tenant.asaas');

Route::middleware(['auth', 'platform.admin'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/', [PlatformDashboardController::class, 'index'])->name('dashboard');
    Route::get('/plans', [PlatformPlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [PlatformPlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [PlatformPlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [PlatformPlanController::class, 'edit'])->name('plans.edit');
    Route::patch('/plans/{plan}', [PlatformPlanController::class, 'update'])->name('plans.update');
    Route::get('/organizations', [PlatformOrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organizations/{organization}', [PlatformOrganizationController::class, 'show'])->name('organizations.show');
    Route::patch('/organizations/{organization}/notes', [PlatformOrganizationController::class, 'updateNotes'])->name('organizations.notes.update');
    Route::patch('/organizations/{organization}/plan', [PlatformOrganizationPlanController::class, 'updatePlan'])->name('organizations.plan.update');
    Route::post('/organizations/{organization}/overrides', [PlatformOrganizationPlanController::class, 'storeOverride'])->name('organizations.overrides.store');
    Route::delete('/organizations/{organization}/overrides/{override}', [PlatformOrganizationPlanController::class, 'destroyOverride'])->name('organizations.overrides.destroy');
    Route::post('/organizations/{organization}/suspend', [PlatformOrganizationController::class, 'suspend'])->name('organizations.suspend');
    Route::post('/organizations/{organization}/reactivate', [PlatformOrganizationController::class, 'reactivate'])->name('organizations.reactivate');
    Route::post('/organizations/{organization}/subscription/change-plan', [PlatformSubscriptionController::class, 'changePlan'])->name('organizations.subscription.change-plan');
    Route::post('/organizations/{organization}/subscription/extend-trial', [PlatformSubscriptionController::class, 'extendTrial'])->name('organizations.subscription.extend-trial');
    Route::post('/organizations/{organization}/subscription/cancel', [PlatformSubscriptionController::class, 'cancel'])->name('organizations.subscription.cancel');
    Route::post('/organizations/{organization}/subscription/activate', [PlatformSubscriptionController::class, 'activate'])->name('organizations.subscription.activate');
    Route::post('/organizations/{organization}/subscription/pause', [PlatformSubscriptionController::class, 'pause'])->name('organizations.subscription.pause');
    Route::post('/organizations/{organization}/subscription/mark-past-due', [PlatformSubscriptionController::class, 'markPastDue'])->name('organizations.subscription.mark-past-due');
    Route::get('/invoices', [PlatformInvoiceController::class, 'index'])->name('invoices.index');
    Route::post('/invoices/{invoice}/mark-paid', [PlatformInvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/invoices/{invoice}/void', [PlatformInvoiceController::class, 'void'])->name('invoices.void');
    Route::get('/guides', [PlatformUsageGuideController::class, 'index'])->name('guides.index');
    Route::get('/guides/{guide}', [PlatformUsageGuideController::class, 'show'])->name('guides.show');
});

Route::middleware('portal.guest')->group(function (): void {
    Route::get('/portal/login', [PortalAuthenticatedSessionController::class, 'create'])->name('portal.login');
    Route::post('/portal/login', [PortalAuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/portal/forgot-password', [PortalPasswordResetLinkController::class, 'create'])->name('portal.password.request');
    Route::post('/portal/forgot-password', [PortalPasswordResetLinkController::class, 'store'])->middleware('throttle:login')->name('portal.password.email');
    Route::get('/portal/reset-password/{token}', [PortalNewPasswordController::class, 'create'])->name('portal.password.reset');
    Route::post('/portal/reset-password', [PortalNewPasswordController::class, 'store'])->middleware('throttle:login')->name('portal.password.store');
    Route::get('/client-portal/onboarding', [ClientPortalOnboardingController::class, 'create'])->name('client-portal.onboarding');
    Route::post('/client-portal/onboarding', [ClientPortalOnboardingController::class, 'store']);
});

Route::get('/client-portal/invite/{token}', [ClientPortalInviteController::class, 'show'])->name('client-portal.invite');

Route::middleware('portal.auth')->prefix('client-portal')->group(function (): void {
    Route::get('/', [ClientPortalController::class, 'dashboard'])->name('client-portal.dashboard');
    Route::get('/messages', [ClientPortalController::class, 'messages'])->name('client-portal.messages');
    Route::get('/messages/poll', [ClientPortalController::class, 'pollMessages'])->name('client-portal.messages.poll');
    Route::post('/consent', [ClientPortalController::class, 'storeConsent'])->name('client-portal.consent.store');
    Route::post('/messages', [ClientPortalController::class, 'storeMessage'])->name('client-portal.messages.store');
    Route::post('/messages/{message}/ticket', [ClientPortalController::class, 'storeTicketFromMessage'])->name('client-portal.messages.ticket.store');
    Route::get('/documents', [ClientPortalController::class, 'documents'])->name('client-portal.documents.index');
    Route::get('/documents/{documentRequest}', [ClientPortalController::class, 'showDocumentRequest'])->name('client-portal.documents.show');
    Route::post('/document-items/{item}/upload', [ClientPortalController::class, 'uploadDocumentItem'])->name('client-portal.documents.items.upload');
    Route::get('/tickets', [ClientPortalController::class, 'tickets'])->name('client-portal.tickets.index');
    Route::post('/tickets', [ClientPortalController::class, 'storeTicket'])->name('client-portal.tickets.store');
    Route::get('/tickets/{ticket}', [ClientPortalController::class, 'showTicket'])->name('client-portal.tickets.show');
    Route::get('/tickets/{ticket}/messages/poll', [ClientPortalController::class, 'ticketMessages'])->name('client-portal.tickets.messages.poll');
    Route::post('/tickets/{ticket}/messages', [ClientPortalController::class, 'storeTicketMessage'])->name('client-portal.tickets.messages.store');
    Route::get('/tickets/attachments/{attachment}/download', [ClientPortalController::class, 'downloadTicketAttachment'])->name('client-portal.tickets.attachments.download');
    Route::post('/tickets/{ticket}/rating', [ClientPortalController::class, 'storeTicketRating'])->name('client-portal.tickets.rating.store');
    Route::get('/finance', [ClientPortalController::class, 'finance'])->name('client-portal.finance');
    Route::get('/meetings', [ClientPortalController::class, 'meetings'])->name('client-portal.meetings');
    Route::patch('/meetings/{event}/confirm', [ClientPortalController::class, 'confirmMeeting'])->name('client-portal.meetings.confirm');
    Route::get('/profile', [ClientPortalController::class, 'profile'])->name('client-portal.profile');
    Route::patch('/profile', [ClientPortalController::class, 'updateProfile'])->name('client-portal.profile.update');
    Route::get('/reports/{report}/download', [ClientPortalController::class, 'downloadReport'])->name('client-portal.reports.download');
    Route::get('/more', [ClientPortalController::class, 'more'])->name('client-portal.more');
    Route::get('/notifications', [PortalClientNotificationController::class, 'index'])->name('client-portal.notifications.index');
    Route::get('/notifications/unread-count', [PortalClientNotificationController::class, 'unreadCount'])->name('client-portal.notifications.unread-count');
    Route::patch('/notifications/{portalClientAlert}/read', [PortalClientNotificationController::class, 'markRead'])->name('client-portal.notifications.read');
    Route::post('/notifications/read-all', [PortalClientNotificationController::class, 'markAllRead'])->name('client-portal.notifications.read-all');
    Route::post('/logout', [PortalAuthenticatedSessionController::class, 'destroy'])->name('portal.logout');
});

Route::get('/client-portal/{token}', [ClientPortalInviteController::class, 'legacy'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('client-portal.show');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/subscription/required', [SubscriptionRequiredController::class, 'show'])->name('subscription.required');
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/organizations/plan', [OrganizationPlanController::class, 'show'])->name('organizations.plan.show');
    Route::get('/organizations/billing', [OrganizationBillingController::class, 'show'])->name('organizations.billing.show');
    Route::post('/organizations/billing/change-plan', [OrganizationBillingController::class, 'changePlan'])->name('organizations.billing.change-plan');
    Route::post('/organizations/billing/cancel', [OrganizationBillingController::class, 'cancel'])->name('organizations.billing.cancel');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::put('/organizations/{organization}/payment-gateway', [OrganizationPaymentGatewayController::class, 'update'])->name('organizations.payment-gateway.update');
    Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');
    Route::post('/invitations/{token}/accept', [InvitationAcceptanceController::class, 'store'])->name('web.invitations.accept');

    Route::middleware('org.accessible')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/notifications', [InternalNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [InternalNotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::patch('/notifications/{reminder}/read', [InternalNotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [InternalNotificationController::class, 'markAllRead'])->name('notifications.read-all');

        Route::get('/team', [OrganizationMemberController::class, 'index'])->name('team.index');
        Route::post('/organization-invitations', [OrganizationInvitationController::class, 'store'])->name('organization-invitations.store');
        Route::delete('/organization-invitations/{organizationInvitation}', [OrganizationInvitationController::class, 'destroy'])->name('organization-invitations.destroy');
        Route::patch('/organization-members/{organizationMember}/suspend', [OrganizationMemberController::class, 'suspend'])->name('organization-members.suspend');
        Route::patch('/organization-members/{organizationMember}/reactivate', [OrganizationMemberController::class, 'reactivate'])->name('organization-members.reactivate');

        Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
        Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
        Route::get('/leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
        Route::patch('/leads/{lead}/stage', [LeadController::class, 'updateStage'])->name('leads.stage.update');
        Route::post('/leads/{lead}/activities', [LeadController::class, 'storeActivity'])->name('leads.activities.store');
        Route::post('/leads/{lead}/proposals', [LeadController::class, 'storeProposal'])->name('leads.proposals.store');
        Route::patch('/leads/{lead}/proposals/{proposal}/status', [LeadController::class, 'updateProposalStatus'])->name('leads.proposals.status.update');
        Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

        Route::get('/onboarding-templates', [OnboardingTemplateController::class, 'index'])->name('onboarding-templates.index');
        Route::post('/onboarding-templates', [OnboardingTemplateController::class, 'store'])->name('onboarding-templates.store');
        Route::post('/onboarding-templates/{template}/start', [OnboardingTemplateController::class, 'start'])->name('onboarding-templates.start');

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
        Route::get('/clients/{client}/messages/poll', [ClientHubController::class, 'messages'])->name('clients.messages.poll');
        Route::post('/clients/{client}/messages', [ClientHubController::class, 'storeMessage'])->name('clients.messages.store');
        Route::post('/clients/{client}/messages/{message}/whatsapp', [ClientHubController::class, 'openWhatsApp'])->name('clients.messages.whatsapp');
        Route::post('/clients/{client}/messages/{message}/ticket', [ClientHubController::class, 'storeTicketFromMessage'])->name('clients.messages.ticket.store');
        Route::get('/clients/{client}/tickets/{ticket}', [ClientHubController::class, 'showTicket'])->name('clients.tickets.show');
        Route::post('/clients/{client}/tickets', [ClientHubController::class, 'storeTicket'])->name('clients.tickets.store');
        Route::patch('/clients/{client}/tickets/{ticket}', [ClientHubController::class, 'updateTicket'])->name('clients.tickets.update');
        Route::post('/clients/{client}/tickets/{ticket}/messages', [ClientHubController::class, 'storeTicketMessage'])->name('clients.tickets.messages.store');
        Route::get('/clients/{client}/tickets/attachments/{attachment}/download', [ClientHubController::class, 'downloadTicketAttachment'])->name('clients.tickets.attachments.download');
        Route::post('/clients/{client}/portal-accesses', [ClientHubController::class, 'storePortalAccess'])->name('clients.portal-accesses.store');
        Route::patch('/clients/{client}/portal-accesses/{access}/revoke', [ClientHubController::class, 'revokePortalAccess'])->name('clients.portal-accesses.revoke');
        Route::post('/clients/{client}/services', [ClientServiceController::class, 'store'])->name('clients.services.store');
        Route::patch('/clients/{client}/services/{service}', [ClientServiceController::class, 'update'])->name('clients.services.update');

        Route::get('/service-types', [ServiceTypeController::class, 'index'])->name('service-types.index');
        Route::post('/service-types', [ServiceTypeController::class, 'store'])->name('service-types.store');
        Route::patch('/service-types/{serviceType}', [ServiceTypeController::class, 'update'])->name('service-types.update');

        Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
        Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
        Route::post('/contracts/{contract}/renew', [ContractController::class, 'renew'])->name('contracts.renew');
        Route::post('/contracts/{contract}/cancel', [ContractController::class, 'cancel'])->name('contracts.cancel');

        Route::get('/automations', [AutomationRuleController::class, 'index'])->name('automations.index');
        Route::post('/automations', [AutomationRuleController::class, 'store'])->name('automations.store');
        Route::get('/automations/{automationRule}', [AutomationRuleController::class, 'show'])->name('automations.show');
        Route::post('/automations/{automationRule}/pause', [AutomationRuleController::class, 'pause'])->name('automations.pause');
        Route::post('/automations/{automationRule}/resume', [AutomationRuleController::class, 'resume'])->name('automations.resume');

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
        Route::post('/finance/receivables/{receivable}/charge', [FinanceController::class, 'chargeReceivable'])->name('finance.receivables.charge');
        Route::patch('/finance/receivables/{receivable}/cancel', [FinanceController::class, 'cancelReceivable'])->name('finance.receivables.cancel');
        Route::patch('/finance/receivables/{receivable}/renegotiate', [FinanceController::class, 'renegotiateReceivable'])->name('finance.receivables.renegotiate');
        Route::post('/finance/receivables/{receivable}/reminders', [FinanceController::class, 'storeReceivableReminder'])->name('finance.receivables.reminders.store');
        Route::post('/finance/recurrences', [FinanceController::class, 'storeRecurrence'])->name('finance.recurrences.store');
        Route::post('/finance/recurrences/{recurrence}/generate', [FinanceController::class, 'generateRecurrence'])->name('finance.recurrences.generate');
        Route::post('/finance/payables', [FinanceController::class, 'storePayable'])->name('finance.payables.store');
        Route::post('/finance/payables/{payable}/payments', [FinanceController::class, 'payPayable'])->name('finance.payables.payments.store');

        Route::get('/portal', [PortalController::class, 'index'])->name('portal.index');
        Route::post('/portal/accesses', [PortalController::class, 'storeAccess'])->name('portal.accesses.store');
        Route::patch('/portal/accesses/{access}/revoke', [PortalController::class, 'revokeAccess'])->name('portal.accesses.revoke');
        Route::post('/portal/messages', [PortalController::class, 'storeMessage'])->name('portal.messages.store');
        Route::post('/portal/tickets', [PortalController::class, 'storeTicket'])->name('portal.tickets.store');
        Route::patch('/portal/profile-updates/{profileUpdate}/approve', [ClientProfileUpdateController::class, 'approve'])->name('portal.profile-updates.approve');
        Route::patch('/portal/profile-updates/{profileUpdate}/reject', [ClientProfileUpdateController::class, 'reject'])->name('portal.profile-updates.reject');

        Route::get('/messages/batch', [MessageBatchController::class, 'create'])->name('messages.batch.create');
        Route::post('/messages/batch', [MessageBatchController::class, 'store'])->name('messages.batch.store');
        Route::get('/messages/batches/{batch}', [MessageBatchController::class, 'show'])->name('messages.batches.show');

        Route::get('/message-templates', [MessageTemplateController::class, 'index'])->name('message-templates.index');
        Route::post('/message-templates', [MessageTemplateController::class, 'store'])->name('message-templates.store');
        Route::patch('/message-templates/{template}', [MessageTemplateController::class, 'update'])->name('message-templates.update');
        Route::delete('/message-templates/{template}', [MessageTemplateController::class, 'destroy'])->name('message-templates.destroy');

        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::patch('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::post('/reports/filters', [ReportController::class, 'storeFilter'])->name('reports.filters.store');
        Route::post('/reports/schedules', [ReportController::class, 'storeSchedule'])->name('reports.schedules.store');
        Route::post('/reports/schedules/{schedule}/run', [ReportController::class, 'runSchedule'])->name('reports.schedules.run');
        Route::post('/reports/monthly', [ReportController::class, 'generateMonthly'])->name('reports.monthly.store');
        Route::patch('/reports/{report}/release', [ReportController::class, 'release'])->name('reports.release');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });
});
