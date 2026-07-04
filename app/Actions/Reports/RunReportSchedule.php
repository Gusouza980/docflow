<?php

namespace App\Actions\Reports;

use App\Models\Client;
use App\Models\GeneratedReport;
use App\Models\OrganizationMember;
use App\Models\ReportSchedule;
use App\Reports\ReportMetrics;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RunReportSchedule
{
    public function __construct(
        private ReportMetrics $metrics,
    ) {}

    public function execute(ReportSchedule $schedule, bool $manual = false): ?GeneratedReport
    {
        if (! $schedule->is_active && ! $manual) {
            return null;
        }

        if ($schedule->report_type === 'client_monthly' && ! $schedule->client_id) {
            throw new RuntimeException('Agendamento mensal exige client_id.');
        }

        $membership = $this->membershipForSchedule($schedule);

        if ($schedule->report_type === 'finance' && ! $this->metrics->canAccessFinance($membership)) {
            throw new RuntimeException('Agendamento financeiro sem membro com permissão financeira.');
        }

        $period = $this->resolvePeriod($schedule);
        $filters = array_merge($schedule->filters ?? [], $period);

        $existing = GeneratedReport::query()
            ->where('report_schedule_id', $schedule->id)
            ->whereDate('period_start', $period['start_date'])
            ->whereDate('period_end', $period['end_date'])
            ->first();

        if ($existing) {
            $this->markScheduleSuccess($schedule);

            return $existing;
        }

        $report = DB::transaction(function () use ($schedule, $membership, $filters, $period): GeneratedReport {
            $payload = $this->buildPayload($schedule, $membership, $filters);
            $title = $this->buildTitle($schedule, $period);

            $report = GeneratedReport::create([
                'organization_id' => $schedule->organization_id,
                'client_id' => $schedule->client_id,
                'generated_by_user_id' => $schedule->created_by_user_id,
                'report_schedule_id' => $schedule->id,
                'type' => $schedule->report_type === 'client_monthly'
                    ? GeneratedReport::TYPE_CLIENT_MONTHLY
                    : $schedule->report_type,
                'title' => $title,
                'status' => GeneratedReport::STATUS_REVIEWED,
                'filters' => $filters,
                'period_start' => $period['start_date'],
                'period_end' => $period['end_date'],
                'payload' => $payload,
                'reviewed_at' => now(),
            ]);

            $this->markScheduleSuccess($schedule);

            return $report;
        });

        return $report;
    }

    public function markScheduleFailure(ReportSchedule $schedule, string $message): void
    {
        $schedule->update([
            'last_error' => $message,
            'consecutive_failures' => $schedule->consecutive_failures + 1,
        ]);
    }

    /**
     * Período fechado usado na execução automática:
     * - mensal: mês anterior completo ao `next_run_at`;
     * - semanal: últimos 7 dias até a data de referência;
     * - trimestral: trimestre anterior completo.
     *
     * @return array{start_date: string, end_date: string}
     */
    public function resolvePeriod(ReportSchedule $schedule): array
    {
        $reference = Carbon::parse($schedule->next_run_at ?? now())->startOfDay();

        return match ($schedule->frequency) {
            'weekly' => [
                'start_date' => $reference->copy()->subDays(6)->toDateString(),
                'end_date' => $reference->toDateString(),
            ],
            'quarterly' => $this->previousQuarterPeriod($reference),
            default => $this->previousMonthPeriod($reference),
        };
    }

    /**
     * @param  array{start_date: string, end_date: string}  $period
     */
    private function buildTitle(ReportSchedule $schedule, array $period): string
    {
        $periodLabel = Carbon::parse($period['start_date'])->format('d/m/Y')
            .' - '
            .Carbon::parse($period['end_date'])->format('d/m/Y');

        if ($schedule->client) {
            return "{$schedule->name} — {$schedule->client->display_name} ({$periodLabel})";
        }

        return "{$schedule->name} ({$periodLabel})";
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildPayload(ReportSchedule $schedule, OrganizationMember $membership, array $filters): array
    {
        if ($schedule->report_type === 'client_monthly') {
            $client = Client::query()
                ->whereBelongsTo($membership->organization)
                ->findOrFail($schedule->client_id);

            return $this->metrics->clientMonthly($client, $membership, $filters);
        }

        return match ($schedule->report_type) {
            'productivity' => $this->metrics->productivity($membership, $filters),
            'documents' => $this->metrics->documents($membership, $filters),
            'finance' => $this->metrics->finance($membership, $filters),
            default => $this->metrics->overview($membership, $filters),
        };
    }

    private function membershipForSchedule(ReportSchedule $schedule): OrganizationMember
    {
        $membership = OrganizationMember::query()
            ->where('organization_id', $schedule->organization_id)
            ->where('user_id', $schedule->created_by_user_id)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->first();

        if ($membership) {
            return $membership;
        }

        return OrganizationMember::query()
            ->where('organization_id', $schedule->organization_id)
            ->where('role', OrganizationMember::ROLE_ADMIN)
            ->where('status', OrganizationMember::STATUS_ACTIVE)
            ->firstOrFail();
    }

    private function markScheduleSuccess(ReportSchedule $schedule): void
    {
        $reference = Carbon::parse($schedule->next_run_at ?? now())->startOfDay();

        $schedule->update([
            'last_run_at' => now(),
            'last_error' => null,
            'consecutive_failures' => 0,
            'next_run_at' => $this->calculateNextRunAt($schedule, $reference)->toDateString(),
        ]);
    }

    private function calculateNextRunAt(ReportSchedule $schedule, Carbon $reference): Carbon
    {
        return match ($schedule->frequency) {
            'weekly' => $reference->copy()->addWeek(),
            'quarterly' => $reference->copy()->addMonthsNoOverflow(3),
            default => $reference->copy()->addMonthNoOverflow(),
        };
    }

    /**
     * @return array{start_date: string, end_date: string}
     */
    private function previousMonthPeriod(Carbon $reference): array
    {
        $start = $reference->copy()->subMonthNoOverflow()->startOfMonth();
        $end = $reference->copy()->subMonthNoOverflow()->endOfMonth();

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    /**
     * @return array{start_date: string, end_date: string}
     */
    private function previousQuarterPeriod(Carbon $reference): array
    {
        $previousQuarterStart = $reference->copy()->subQuarter()->startOfQuarter();
        $previousQuarterEnd = $reference->copy()->subQuarter()->endOfQuarter();

        return [
            'start_date' => $previousQuarterStart->toDateString(),
            'end_date' => $previousQuarterEnd->toDateString(),
        ];
    }
}
