<?php

namespace App\Support;

class ReportLabels
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'pending' => 'Pendente',
        'open' => 'Em aberto',
        'partial' => 'Parcialmente pago',
        'paid' => 'Pago',
        'requested' => 'Solicitado',
        'received' => 'Recebido',
        'under_review' => 'Em análise',
        'in_progress' => 'Em andamento',
        'blocked' => 'Bloqueada',
        'approved' => 'Aprovado',
        'completed' => 'Concluído',
        'rejected' => 'Recusado',
        'expired' => 'Expirado',
        'replaced' => 'Substituído',
        'cancelled' => 'Cancelado',
        'renegotiated' => 'Renegociada',
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'negotiation' => 'Em negociação',
        'delinquent' => 'Inadimplente',
        'closed' => 'Encerrado',
        'new' => 'Novo',
        'triage' => 'Em triagem',
        'waiting_client' => 'Aguardando cliente',
        'waiting_third_party' => 'Aguardando terceiro',
        'resolved' => 'Resolvido',
        'draft' => 'Rascunho',
        'reviewed' => 'Revisado',
        'released' => 'Liberado',
    ];

    /**
     * @var array<string, string>
     */
    private const SEVERITIES = [
        'danger' => 'Alta',
        'warning' => 'Média',
        'info' => 'Informativa',
    ];

    /**
     * @var array<string, string>
     */
    private const REPORT_TYPES = [
        'overview' => 'Visão geral operacional',
        'productivity' => 'Produtividade da equipe',
        'documents' => 'Documentos e solicitações',
        'finance' => 'Relatório financeiro',
        'client_monthly' => 'Relatório mensal do cliente',
    ];

    public static function reportType(string $type): string
    {
        return self::REPORT_TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public static function status(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }

        return self::STATUSES[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function severity(?string $severity): string
    {
        if ($severity === null || $severity === '') {
            return '—';
        }

        return self::SEVERITIES[$severity] ?? ucfirst($severity);
    }

    public static function money(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }

        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    public static function number(int|float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return number_format((float) $value, 0, ',', '.');
    }

    public static function text(?string $value, string $fallback = '—'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        return $value;
    }

    public static function period(?string $start, ?string $end): string
    {
        $startLabel = DisplayFormat::date($start) ?? '—';
        $endLabel = DisplayFormat::date($end) ?? '—';

        return "{$startLabel} a {$endLabel}";
    }
}
