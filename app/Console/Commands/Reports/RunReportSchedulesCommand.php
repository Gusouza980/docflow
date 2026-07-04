<?php

namespace App\Console\Commands\Reports;

use App\Actions\Organizations\RecordAuditLog;
use App\Actions\Reports\RunReportSchedule;
use App\Models\ReportSchedule;
use App\Support\SchedulerRunLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunReportSchedulesCommand extends Command
{
    protected $signature = 'reports:run-schedules';

    protected $description = 'Processa agendamentos de relatório com next_run_at vencido';

    public function handle(
        RunReportSchedule $runReportSchedule,
        RecordAuditLog $auditLog,
        SchedulerRunLogger $schedulerRunLogger,
    ): int {
        $meta = $schedulerRunLogger->run($this->signature, function () use ($runReportSchedule, $auditLog): array {
            $schedules = ReportSchedule::query()
                ->with(['client', 'organization'])
                ->where('is_active', true)
                ->whereNotNull('next_run_at')
                ->whereDate('next_run_at', '<=', now()->toDateString())
                ->orderBy('next_run_at')
                ->get();

            $processed = 0;
            $generated = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($schedules as $schedule) {
                $processed++;

                try {
                    $report = $runReportSchedule->execute($schedule);

                    if ($report && $report->wasRecentlyCreated) {
                        $generated++;
                    } else {
                        $skipped++;
                    }

                    $auditLog->execute(
                        'report.schedule.executed',
                        organization: $schedule->organization,
                        auditable: $schedule,
                        metadata: [
                            'schedule_id' => $schedule->id,
                            'report_id' => $report?->id,
                            'created' => $report?->wasRecentlyCreated ?? false,
                        ],
                    );
                } catch (Throwable $exception) {
                    $failed++;
                    $runReportSchedule->markScheduleFailure($schedule, $exception->getMessage());

                    Log::error('Report schedule execution failed', [
                        'schedule_id' => $schedule->id,
                        'message' => $exception->getMessage(),
                    ]);

                    $auditLog->execute(
                        'report.schedule.failed',
                        organization: $schedule->organization,
                        auditable: $schedule,
                        metadata: [
                            'schedule_id' => $schedule->id,
                            'error' => $exception->getMessage(),
                        ],
                    );
                }
            }

            return [
                'processed' => $processed,
                'generated' => $generated,
                'skipped' => $skipped,
                'failed' => $failed,
            ];
        });

        $this->info(sprintf(
            'Agendamentos processados: %d | gerados: %d | idempotentes: %d | falhas: %d',
            $meta['processed'] ?? 0,
            $meta['generated'] ?? 0,
            $meta['skipped'] ?? 0,
            $meta['failed'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
