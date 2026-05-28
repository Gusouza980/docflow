<?php

namespace App\Http\Controllers\Web;

use App\Actions\Organizations\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreFinancialCategoryRequest;
use App\Http\Requests\Web\StorePayablePaymentRequest;
use App\Http\Requests\Web\StorePayableRequest;
use App\Http\Requests\Web\StoreReceivablePaymentRequest;
use App\Http\Requests\Web\StoreReceivableRequest;
use App\Models\Client;
use App\Models\FinancialCategory;
use App\Models\OrganizationMember;
use App\Models\Payable;
use App\Models\Receivable;
use App\Support\WebOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FinanceController extends Controller
{
    public function index(Request $request, WebOrganizationContext $webOrganizationContext): Response|RedirectResponse
    {
        $membership = $webOrganizationContext->membership($request);

        if (! $membership) {
            return redirect()->route('organizations.index')->with('error', 'Selecione uma organização para acessar o financeiro.');
        }

        abort_unless($this->canAccessFinance($membership), HttpResponse::HTTP_FORBIDDEN);

        $receivablesQuery = $this->receivableQuery($request, $membership);
        $payablesQuery = $this->payableQuery($request, $membership);

        $receivables = (clone $receivablesQuery)
            ->with(['client', 'category'])
            ->orderBy('due_at')
            ->paginate(12, ['*'], 'receivables_page')
            ->withQueryString();

        $payables = (clone $payablesQuery)
            ->with(['client', 'category'])
            ->orderBy('due_at')
            ->paginate(12, ['*'], 'payables_page')
            ->withQueryString();

        return Inertia::render('Finance/Index', [
            'metrics' => [
                'open_receivables_cents' => (clone $receivablesQuery)->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])->sum(DB::raw('amount_cents - paid_amount_cents')),
                'overdue_receivables_cents' => (clone $receivablesQuery)->whereDate('due_at', '<', now()->toDateString())->whereIn('status', [Receivable::STATUS_OPEN, Receivable::STATUS_PARTIAL])->sum(DB::raw('amount_cents - paid_amount_cents')),
                'open_payables_cents' => (clone $payablesQuery)->whereIn('status', [Payable::STATUS_OPEN, Payable::STATUS_PARTIAL])->sum(DB::raw('amount_cents - paid_amount_cents')),
                'cash_balance_cents' => (clone $receivablesQuery)->where('status', Receivable::STATUS_PAID)->sum('paid_amount_cents') - (clone $payablesQuery)->where('status', Payable::STATUS_PAID)->sum('paid_amount_cents'),
            ],
            'receivables' => [
                'data' => $receivables->getCollection()->map(fn (Receivable $receivable): array => $this->receivableSummary($receivable)),
                'meta' => ['current_page' => $receivables->currentPage(), 'last_page' => $receivables->lastPage(), 'per_page' => $receivables->perPage(), 'total' => $receivables->total()],
            ],
            'payables' => [
                'data' => $payables->getCollection()->map(fn (Payable $payable): array => $this->payableSummary($payable)),
                'meta' => ['current_page' => $payables->currentPage(), 'last_page' => $payables->lastPage(), 'per_page' => $payables->perPage(), 'total' => $payables->total()],
            ],
            'categories' => FinancialCategory::query()
                ->whereBelongsTo($membership->organization)
                ->orderBy('name')
                ->get()
                ->map(fn (FinancialCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'is_active' => $category->is_active,
                ]),
            'filters' => [
                'status' => $request->string('status')->toString(),
                'client_id' => $request->string('client_id')->toString(),
            ],
            'options' => $this->options($membership),
        ]);
    }

    public function storeCategory(StoreFinancialCategoryRequest $request, WebOrganizationContext $webOrganizationContext): RedirectResponse
    {
        $membership = $this->financeMembership($request, $webOrganizationContext);

        FinancialCategory::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
        ]);

        return redirect()->route('finance.index')->with('status', 'Categoria criada.');
    }

    public function storeReceivable(StoreReceivableRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $this->financeMembership($request, $webOrganizationContext);
        $receivable = Receivable::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
        ]);

        $auditLog->execute('web.finance.receivable.created', $request->user(), $membership->organization, $receivable, request: $request);

        return redirect()->route('finance.index')->with('status', 'Conta a receber criada.');
    }

    public function payReceivable(StoreReceivablePaymentRequest $request, Receivable $receivable, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $this->financeMembership($request, $webOrganizationContext);
        abort_if($receivable->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_if($receivable->status === Receivable::STATUS_CANCELLED || $receivable->status === Receivable::STATUS_PAID, HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

        $data = $request->validated();
        abort_if($data['amount_cents'] > $receivable->balanceCents(), HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

        DB::transaction(function () use ($request, $receivable, $data): void {
            $receivable->payments()->create([
                ...$data,
                'organization_id' => $receivable->organization_id,
                'received_by_user_id' => $request->user()->id,
            ]);

            $paid = $receivable->paid_amount_cents + $data['amount_cents'];
            $receivable->update([
                'paid_amount_cents' => $paid,
                'status' => $paid >= $receivable->amount_cents ? Receivable::STATUS_PAID : Receivable::STATUS_PARTIAL,
                'paid_at' => $paid >= $receivable->amount_cents ? $data['paid_at'] : null,
            ]);
        });

        $auditLog->execute('web.finance.receivable.paid', $request->user(), $receivable->organization, $receivable, request: $request);

        return redirect()->route('finance.index')->with('status', 'Pagamento registrado.');
    }

    public function cancelReceivable(Request $request, Receivable $receivable, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $this->financeMembership($request, $webOrganizationContext);
        abort_if($receivable->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_if($receivable->status === Receivable::STATUS_PAID, HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

        $data = $request->validate(['cancellation_reason' => ['required', 'string', 'max:255']]);
        $receivable->update([
            'status' => Receivable::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $data['cancellation_reason'],
        ]);

        $auditLog->execute('web.finance.receivable.cancelled', $request->user(), $receivable->organization, $receivable, request: $request);

        return redirect()->route('finance.index')->with('status', 'Cobrança cancelada.');
    }

    public function storePayable(StorePayableRequest $request, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $this->financeMembership($request, $webOrganizationContext);
        $payable = Payable::create([
            ...$request->validated(),
            'organization_id' => $membership->organization_id,
            'created_by_user_id' => $request->user()->id,
        ]);

        $auditLog->execute('web.finance.payable.created', $request->user(), $membership->organization, $payable, request: $request);

        return redirect()->route('finance.index')->with('status', 'Conta a pagar criada.');
    }

    public function payPayable(StorePayablePaymentRequest $request, Payable $payable, WebOrganizationContext $webOrganizationContext, RecordAuditLog $auditLog): RedirectResponse
    {
        $membership = $this->financeMembership($request, $webOrganizationContext);
        abort_if($payable->organization_id !== $membership->organization_id, HttpResponse::HTTP_NOT_FOUND);
        abort_if($payable->status === Payable::STATUS_PAID, HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

        $data = $request->validated();
        abort_if($data['amount_cents'] > $payable->balanceCents(), HttpResponse::HTTP_UNPROCESSABLE_ENTITY);

        DB::transaction(function () use ($request, $payable, $data): void {
            $payable->payments()->create([
                ...$data,
                'organization_id' => $payable->organization_id,
                'paid_by_user_id' => $request->user()->id,
            ]);

            $paid = $payable->paid_amount_cents + $data['amount_cents'];
            $payable->update([
                'paid_amount_cents' => $paid,
                'status' => $paid >= $payable->amount_cents ? Payable::STATUS_PAID : Payable::STATUS_PARTIAL,
                'paid_at' => $paid >= $payable->amount_cents ? $data['paid_at'] : null,
            ]);
        });

        $auditLog->execute('web.finance.payable.paid', $request->user(), $payable->organization, $payable, request: $request);

        return redirect()->route('finance.index')->with('status', 'Pagamento de despesa registrado.');
    }

    private function receivableQuery(Request $request, OrganizationMember $membership): Builder
    {
        return Receivable::query()
            ->whereBelongsTo($membership->organization)
            ->when($request->integer('client_id'), fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status));
    }

    private function payableQuery(Request $request, OrganizationMember $membership): Builder
    {
        return Payable::query()
            ->whereBelongsTo($membership->organization)
            ->when($request->integer('client_id'), fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status));
    }

    private function receivableSummary(Receivable $receivable): array
    {
        return [
            'id' => $receivable->id,
            'description' => $receivable->description,
            'amount_cents' => $receivable->amount_cents,
            'paid_amount_cents' => $receivable->paid_amount_cents,
            'balance_cents' => $receivable->balanceCents(),
            'status' => $receivable->status,
            'due_at' => $receivable->due_at?->toDateString(),
            'is_overdue' => $receivable->isOverdue(),
            'client' => ['id' => $receivable->client->id, 'name' => $receivable->client->display_name],
            'category' => $receivable->category ? ['id' => $receivable->category->id, 'name' => $receivable->category->name] : null,
        ];
    }

    private function payableSummary(Payable $payable): array
    {
        return [
            'id' => $payable->id,
            'description' => $payable->description,
            'vendor_name' => $payable->vendor_name,
            'amount_cents' => $payable->amount_cents,
            'paid_amount_cents' => $payable->paid_amount_cents,
            'balance_cents' => $payable->balanceCents(),
            'status' => $payable->status,
            'due_at' => $payable->due_at?->toDateString(),
            'is_reimbursable' => $payable->is_reimbursable,
            'client' => $payable->client ? ['id' => $payable->client->id, 'name' => $payable->client->display_name] : null,
            'category' => $payable->category ? ['id' => $payable->category->id, 'name' => $payable->category->name] : null,
        ];
    }

    private function options(OrganizationMember $membership): array
    {
        return [
            'clients' => Client::query()->whereBelongsTo($membership->organization)->orderBy('display_name')->get(['id', 'display_name'])->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->display_name])->values(),
            'categories' => FinancialCategory::query()->whereBelongsTo($membership->organization)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type'])->map(fn (FinancialCategory $category): array => ['value' => $category->id, 'label' => "{$category->name} ({$category->type})", 'type' => $category->type])->values(),
        ];
    }

    private function financeMembership(Request $request, WebOrganizationContext $webOrganizationContext): OrganizationMember
    {
        $membership = $webOrganizationContext->membership($request);
        abort_unless($membership && $this->canAccessFinance($membership), HttpResponse::HTTP_FORBIDDEN);

        return $membership;
    }

    private function canAccessFinance(OrganizationMember $membership): bool
    {
        return in_array($membership->role, [OrganizationMember::ROLE_ADMIN, OrganizationMember::ROLE_MANAGER, OrganizationMember::ROLE_FINANCE], true);
    }
}
