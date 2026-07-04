<?php

namespace App\Actions\Reports;

use App\Models\OrganizationMember;
use App\Reports\ReportMetrics;
use App\Reports\ReportSpreadsheetRenderer;
use App\Support\ReportLabels;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportReportSpreadsheet
{
    public function __construct(
        private ReportMetrics $metrics,
        private ReportSpreadsheetRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function data(OrganizationMember $membership, string $type, array $filters = []): array
    {
        return match ($type) {
            'productivity' => $this->metrics->productivity($membership, $filters),
            'documents' => $this->metrics->documents($membership, $filters),
            'finance' => $this->metrics->finance($membership, $filters),
            default => $this->metrics->overview($membership, $filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function canExport(OrganizationMember $membership, string $type): bool
    {
        if ($type === 'finance') {
            return $this->metrics->canAccessFinance($membership);
        }

        return in_array($type, ['overview', 'productivity', 'documents'], true);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $filters
     */
    public function streamDownload(
        string $type,
        array $data,
        OrganizationMember $membership,
        array $filters = [],
    ): StreamedResponse {
        $html = $this->renderer->render(
            $type,
            $data,
            $membership->organization->name,
            $filters,
        );

        $filename = $this->filename($type);

        return response()->streamDownload(function () use ($html): void {
            echo "\xEF\xBB\xBF";
            echo $html;
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function filename(string $type): string
    {
        $slug = match ($type) {
            'overview' => 'visao-geral',
            'productivity' => 'produtividade',
            'documents' => 'documentos',
            'finance' => 'financeiro',
            default => $type,
        };

        return sprintf('docflow-relatorio-%s-%s.xls', $slug, now()->format('Y-m-d'));
    }

    public function reportTypeLabel(string $type): string
    {
        return ReportLabels::reportType($type);
    }
}
