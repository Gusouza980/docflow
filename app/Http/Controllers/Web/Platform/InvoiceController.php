<?php

namespace App\Http\Controllers\Web\Platform;

use App\Actions\Billing\MarkInvoicePaid;
use App\Actions\Platform\RecordPlatformAuditLog;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Support\DisplayFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $invoices = SubscriptionInvoice::query()
            ->with(['organization', 'subscription.plan'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($request->boolean('overdue'), fn ($query) => $query
                ->where('status', SubscriptionInvoice::STATUS_OPEN)
                ->where('due_at', '<', now()))
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (SubscriptionInvoice $invoice): array => [
                'id' => $invoice->id,
                'organization' => [
                    'id' => $invoice->organization->id,
                    'name' => $invoice->organization->name,
                ],
                'plan_name' => $invoice->subscription?->plan?->name,
                'amount_cents' => $invoice->amount_cents,
                'status' => $invoice->status,
                'due_at' => DisplayFormat::dateTime($invoice->due_at),
                'paid_at' => DisplayFormat::dateTime($invoice->paid_at),
                'is_overdue' => $invoice->isOverdue(),
            ]);

        return Inertia::render('Platform/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'status' => $status,
                'overdue' => $request->boolean('overdue'),
            ],
        ]);
    }

    public function markPaid(
        Request $request,
        SubscriptionInvoice $invoice,
        MarkInvoicePaid $markInvoicePaid,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        $markInvoicePaid->execute(
            invoice: $invoice,
            platformAdmin: $request->user(),
            request: $request,
            recordPlatformAuditLog: $recordPlatformAuditLog,
        );

        return redirect()
            ->route('platform.invoices.index')
            ->with('status', 'Fatura marcada como paga.');
    }

    public function void(
        Request $request,
        SubscriptionInvoice $invoice,
        RecordPlatformAuditLog $recordPlatformAuditLog,
    ): RedirectResponse {
        if ($invoice->status === SubscriptionInvoice::STATUS_PAID) {
            return back()->with('error', 'Faturas pagas não podem ser anuladas.');
        }

        $invoice->update(['status' => SubscriptionInvoice::STATUS_VOID]);

        $recordPlatformAuditLog->execute(
            action: 'platform.invoice.voided',
            platformAdmin: $request->user(),
            subject: $invoice,
            metadata: ['invoice_id' => $invoice->id],
            request: $request,
        );

        return redirect()
            ->route('platform.invoices.index')
            ->with('status', 'Fatura anulada.');
    }
}
