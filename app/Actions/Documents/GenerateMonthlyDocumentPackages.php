<?php

namespace App\Actions\Documents;

use App\Models\ClientService;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\ServiceType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyDocumentPackages
{
    /**
     * @return list<DocumentRequest>
     */
    public function execute(?Carbon $month = null): array
    {
        $billingPeriod = ($month ?? now())->copy()->startOfMonth()->toDateString();
        $generated = [];

        ClientService::query()
            ->with('serviceType', 'client')
            ->where('status', ClientService::STATUS_ACTIVE)
            ->whereHas('serviceType', function ($query): void {
                $query->where('is_active', true)
                    ->whereNotNull('monthly_document_items')
                    ->whereJsonLength('monthly_document_items', '>', 0);
            })
            ->orderBy('id')
            ->each(function (ClientService $service) use ($billingPeriod, &$generated): void {
                $created = $this->generateForService($service, $billingPeriod);

                if ($created !== null) {
                    $generated[] = $created;
                }
            });

        return $generated;
    }

    private function generateForService(ClientService $service, string $billingPeriod): ?DocumentRequest
    {
        $items = $this->titles($service->serviceType);

        if ($items === []) {
            return null;
        }

        return DB::transaction(function () use ($service, $billingPeriod, $items): ?DocumentRequest {
            if (DocumentRequest::query()
                ->where('client_service_id', $service->id)
                ->whereDate('billing_period', $billingPeriod)
                ->exists()) {
                return null;
            }

            try {
                $request = DocumentRequest::query()->create([
                    'organization_id' => $service->organization_id,
                    'client_id' => $service->client_id,
                    'client_service_id' => $service->id,
                    'title' => 'Documentos de '.Carbon::parse($billingPeriod)->format('m/Y').' — '.$service->serviceType->name,
                    'instructions' => 'Pacote documental mensal do serviço.',
                    'due_at' => Carbon::parse($billingPeriod)->endOfMonth()->toDateString(),
                    'billing_period' => $billingPeriod,
                    'status' => DocumentRequest::STATUS_PENDING,
                ]);
            } catch (QueryException) {
                return null;
            }

            foreach ($items as $title) {
                $request->items()->create([
                    'organization_id' => $service->organization_id,
                    'title' => $title,
                    'status' => DocumentRequestItem::STATUS_REQUESTED,
                    'due_at' => $request->due_at,
                ]);
            }

            return $request;
        });
    }

    /**
     * @return list<string>
     */
    private function titles(?ServiceType $serviceType): array
    {
        $raw = $serviceType?->monthly_document_items ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}
