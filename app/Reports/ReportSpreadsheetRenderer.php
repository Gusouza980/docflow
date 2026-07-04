<?php

namespace App\Reports;

use App\Support\DisplayFormat;
use App\Support\ReportLabels;

class ReportSpreadsheetRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $filters
     */
    public function render(string $type, array $data, string $organizationName, array $filters = []): string
    {
        $title = ReportLabels::reportType($type);
        $period = $data['period'] ?? [
            'start' => $filters['start_date'] ?? null,
            'end' => $filters['end_date'] ?? null,
        ];

        $body = match ($type) {
            'productivity' => $this->productivityBody($data),
            'documents' => $this->documentsBody($data),
            'finance' => $this->financeBody($data),
            default => $this->overviewBody($data),
        };

        return $this->wrapDocument($title, $organizationName, $period, $body);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function overviewBody(array $data): string
    {
        $clients = $data['clients'] ?? [];
        $tasks = $data['tasks'] ?? [];
        $documents = $data['documents'] ?? [];
        $communication = $data['communication'] ?? [];
        $alerts = $data['alerts'] ?? [];

        $sections = $this->section('Clientes', [
            ['Clientes ativos', ReportLabels::number($clients['active'] ?? 0)],
            ['Clientes de alto risco', ReportLabels::number($clients['high_risk'] ?? 0)],
            ['Clientes inadimplentes', ReportLabels::number($clients['delinquent'] ?? 0)],
            ['Sem contato principal', ReportLabels::number($clients['without_primary_contact'] ?? 0)],
        ]);

        $sections .= $this->section('Tarefas', [
            ['Tarefas em aberto', ReportLabels::number($tasks['open'] ?? 0)],
            ['Tarefas atrasadas', ReportLabels::number($tasks['overdue'] ?? 0)],
            ['Tarefas concluídas no período', ReportLabels::number($tasks['completed'] ?? 0)],
        ]);

        $sections .= $this->section('Documentos', [
            ['Itens pendentes', ReportLabels::number($documents['pending'] ?? 0)],
            ['Itens vencidos', ReportLabels::number($documents['overdue'] ?? 0)],
            ['Itens a vencer em 7 dias', ReportLabels::number($documents['due_soon'] ?? 0)],
        ]);

        $sections .= $this->section('Comunicação', [
            ['Mensagens no período', ReportLabels::number($communication['messages'] ?? 0)],
            ['Chamados em aberto', ReportLabels::number($communication['open_tickets'] ?? 0)],
        ]);

        if ($alerts !== []) {
            $rows = array_map(
                fn (array $alert): array => [
                    ReportLabels::text($alert['label'] ?? 'Alerta'),
                    ReportLabels::number($alert['count'] ?? 0),
                    ReportLabels::severity($alert['severity'] ?? null),
                ],
                $alerts,
            );

            $sections .= $this->tableSection('Alertas operacionais', ['Alerta', 'Quantidade', 'Prioridade'], $rows);
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function productivityBody(array $data): string
    {
        $members = $data['members'] ?? [];

        $rows = array_map(
            fn (array $member): array => [
                ReportLabels::text($member['name'] ?? null, 'Colaborador sem nome'),
                ReportLabels::number($member['open_tasks'] ?? 0),
                ReportLabels::number($member['completed_tasks'] ?? 0),
                ReportLabels::number($member['overdue_tasks'] ?? 0),
                ReportLabels::number($member['open_tickets'] ?? 0),
            ],
            $members,
        );

        return $this->tableSection(
            'Desempenho por colaborador',
            ['Colaborador', 'Tarefas abertas', 'Concluídas no período', 'Atrasadas', 'Chamados abertos'],
            $rows,
            'Nenhum colaborador encontrado para o período selecionado.',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function documentsBody(array $data): string
    {
        $summary = $data['summary'] ?? [];
        $items = $data['items'] ?? [];

        $sections = $this->section('Resumo', [
            ['Itens pendentes', ReportLabels::number($summary['pending'] ?? 0)],
            ['Itens vencidos', ReportLabels::number($summary['overdue'] ?? 0)],
            ['Itens a vencer em 7 dias', ReportLabels::number($summary['due_soon'] ?? 0)],
        ]);

        $rows = array_map(
            fn (array $item): array => [
                ReportLabels::text($item['title'] ?? null),
                ReportLabels::text($item['client'] ?? null),
                ReportLabels::text($item['category'] ?? null, 'Sem categoria'),
                ReportLabels::text($item['request'] ?? null),
                ReportLabels::status($item['status'] ?? null),
                DisplayFormat::date($item['due_at'] ?? null) ?? 'Sem prazo',
            ],
            $items,
        );

        $sections .= $this->tableSection(
            'Detalhamento dos itens',
            ['Documento', 'Cliente', 'Categoria', 'Solicitação', 'Status', 'Prazo'],
            $rows,
            'Nenhum item documental encontrado para os filtros selecionados.',
        );

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function financeBody(array $data): string
    {
        $summary = $data['summary'] ?? [];
        $delinquentClients = $data['delinquent_clients'] ?? [];

        $sections = $this->section('Resumo financeiro', [
            ['Valores a receber', ReportLabels::money($summary['open_receivables_cents'] ?? 0)],
            ['Valores vencidos', ReportLabels::money($summary['overdue_receivables_cents'] ?? 0)],
            ['Valores recebidos no período', ReportLabels::money($summary['received_cents'] ?? 0)],
            ['Despesas em aberto', ReportLabels::money($summary['open_payables_cents'] ?? 0)],
            ['Despesas pagas no período', ReportLabels::money($summary['paid_payables_cents'] ?? 0)],
        ]);

        $rows = array_map(
            fn (array $client): array => [
                ReportLabels::text($client['client'] ?? null),
                ReportLabels::money($client['balance_cents'] ?? 0),
            ],
            $delinquentClients,
        );

        $sections .= $this->tableSection(
            'Clientes com saldo em atraso',
            ['Cliente', 'Saldo em aberto'],
            $rows,
            'Nenhum cliente inadimplente no momento.',
        );

        return $sections;
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     */
    private function section(string $title, array $rows): string
    {
        $tableRows = '';

        foreach ($rows as [$label, $value]) {
            $tableRows .= '<tr>'
                .'<td class="metric-label">'.e($label).'</td>'
                .'<td class="metric-value">'.e($value).'</td>'
                .'</tr>';
        }

        return <<<HTML
            <div class="section">
                <h2 class="section-title">{$this->escape($title)}</h2>
                <table class="data-table metrics-table">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>{$tableRows}</tbody>
                </table>
            </div>
        HTML;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function tableSection(string $title, array $headers, array $rows, ?string $emptyMessage = null): string
    {
        $headerCells = implode('', array_map(
            fn (string $header): string => '<th>'.e($header).'</th>',
            $headers,
        ));

        if ($rows === []) {
            return <<<HTML
                <div class="section">
                    <h2 class="section-title">{$this->escape($title)}</h2>
                    <p class="empty-message">{$this->escape($emptyMessage ?? 'Nenhum registro encontrado.')}</p>
                </div>
            HTML;
        }

        $bodyRows = '';

        foreach ($rows as $row) {
            $cells = implode('', array_map(
                fn (string $cell): string => '<td>'.e($cell).'</td>',
                $row,
            ));
            $bodyRows .= "<tr>{$cells}</tr>";
        }

        return <<<HTML
            <div class="section">
                <h2 class="section-title">{$this->escape($title)}</h2>
                <table class="data-table">
                    <thead><tr>{$headerCells}</tr></thead>
                    <tbody>{$bodyRows}</tbody>
                </table>
            </div>
        HTML;
    }

    /**
     * @param  array<string, mixed>|null  $period
     */
    private function wrapDocument(string $title, string $organizationName, ?array $period, string $body): string
    {
        $generatedAt = DisplayFormat::dateTime(now());
        $periodLabel = ReportLabels::period($period['start'] ?? null, $period['end'] ?? null);
        $logo = $this->logoDataUri();

        return <<<HTML
            <!DOCTYPE html>
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <title>{$this->escape($title)}</title>
                <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Relatório</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
                <style>
                    body { font-family: Arial, Helvetica, sans-serif; color: #0f172a; margin: 24px; }
                    .report-header { width: 100%; border-collapse: collapse; margin-bottom: 24px; border-bottom: 3px solid #2563eb; }
                    .report-header td { vertical-align: middle; padding-bottom: 16px; }
                    .report-header .generated-at { text-align: right; font-size: 13px; color: #475569; }
                    .report-header .generated-at strong { display: block; font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px; }
                    .report-title { font-size: 24px; margin: 0 0 8px; color: #0f172a; }
                    .report-meta { font-size: 13px; color: #475569; margin: 0 0 24px; }
                    .section { margin-bottom: 28px; }
                    .section-title { font-size: 16px; margin: 0 0 12px; color: #1e293b; background: #f8fafc; border-left: 4px solid #2563eb; padding: 10px 12px; }
                    .data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
                    .data-table th { background: #2563eb; color: #ffffff; text-align: left; padding: 10px 12px; border: 1px solid #1d4ed8; }
                    .data-table td { padding: 9px 12px; border: 1px solid #cbd5e1; vertical-align: top; }
                    .data-table tbody tr:nth-child(even) { background: #f8fafc; }
                    .metrics-table .metric-label { width: 55%; font-weight: 600; color: #334155; }
                    .metrics-table .metric-value { width: 45%; }
                    .empty-message { font-size: 13px; color: #64748b; margin: 0; padding: 12px; background: #f8fafc; border: 1px dashed #cbd5e1; }
                </style>
            </head>
            <body>
                <table class="report-header">
                    <tr>
                        <td><img src="{$logo}" alt="Docflow" height="52"></td>
                        <td class="generated-at">
                            <strong>Data de geração</strong>
                            {$this->escape($generatedAt)}
                        </td>
                    </tr>
                </table>
                <h1 class="report-title">{$this->escape($title)}</h1>
                <p class="report-meta">
                    <strong>Organização:</strong> {$this->escape($organizationName)}<br>
                    <strong>Período analisado:</strong> {$this->escape($periodLabel)}
                </p>
                {$body}
            </body>
            </html>
        HTML;
    }

    private function logoDataUri(): string
    {
        $path = resource_path('reports/docflow-logo.svg');
        $contents = is_readable($path) ? file_get_contents($path) : '';

        if ($contents === false || $contents === '') {
            return '';
        }

        return 'data:image/svg+xml;base64,'.base64_encode($contents);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
