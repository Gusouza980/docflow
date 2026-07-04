<?php

namespace Tests\Unit;

use App\Reports\ReportSpreadsheetRenderer;
use Tests\TestCase;

class ReportSpreadsheetRendererTest extends TestCase
{
    public function test_overview_render_uses_portuguese_labels_and_branded_header(): void
    {
        $html = (new ReportSpreadsheetRenderer)->render('overview', [
            'period' => ['start' => '2026-06-01', 'end' => '2026-06-30'],
            'clients' => ['active' => 3, 'high_risk' => 1, 'delinquent' => 0, 'without_primary_contact' => 2],
            'tasks' => ['open' => 4, 'overdue' => 1, 'completed' => 6],
            'documents' => ['pending' => 2, 'overdue' => 1, 'due_soon' => 3],
            'communication' => ['messages' => 8, 'open_tickets' => 2],
            'alerts' => [
                ['label' => '1 tarefa(s) atrasada(s)', 'count' => 1, 'severity' => 'danger'],
            ],
        ], 'Escritório Demo', []);

        $this->assertStringContainsString('Visão geral operacional', $html);
        $this->assertStringContainsString('Clientes ativos', $html);
        $this->assertStringContainsString('Data de geração', $html);
        $this->assertStringContainsString('Escritório Demo', $html);
        $this->assertStringContainsString('01/06/2026 a 30/06/2026', $html);
        $this->assertStringContainsString('alt="Docflow"', $html);
        $this->assertStringNotContainsString('clients.active', $html);
        $this->assertStringNotContainsString('report_type', $html);
    }

    public function test_documents_render_translates_status_and_formats_table_headers(): void
    {
        $html = (new ReportSpreadsheetRenderer)->render('documents', [
            'period' => ['start' => '2026-06-01', 'end' => '2026-06-30'],
            'summary' => ['pending' => 1, 'overdue' => 1, 'due_soon' => 0],
            'items' => [[
                'title' => 'Contrato social',
                'client' => 'Empresa Alfa',
                'category' => 'Societário',
                'request' => 'Documentação inicial',
                'status' => 'requested',
                'due_at' => '2026-06-15',
            ]],
        ], 'Escritório Demo', []);

        $this->assertStringContainsString('Documentos e solicitações', $html);
        $this->assertStringContainsString('Detalhamento dos itens', $html);
        $this->assertStringContainsString('Contrato social', $html);
        $this->assertStringContainsString('Solicitado', $html);
        $this->assertStringContainsString('15/06/2026', $html);
        $this->assertStringNotContainsString('summary.pending', $html);
    }
}
