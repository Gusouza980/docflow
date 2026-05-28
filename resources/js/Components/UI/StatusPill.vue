<script setup>
defineProps({
    status: { type: String, required: true },
});

const statuses = {
    pending: ['Pendente', 'bg-amber-50 text-amber-700 ring-amber-200'],
    open: ['Aberta', 'bg-amber-50 text-amber-700 ring-amber-200'],
    partial: ['Parcial', 'bg-blue-50 text-blue-700 ring-blue-200'],
    paid: ['Paga', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    requested: ['Solicitado', 'bg-amber-50 text-amber-700 ring-amber-200'],
    received: ['Recebido', 'bg-blue-50 text-blue-700 ring-blue-200'],
    under_review: ['Em análise', 'bg-indigo-50 text-indigo-700 ring-indigo-200'],
    in_progress: ['Em andamento', 'bg-blue-50 text-blue-700 ring-blue-200'],
    blocked: ['Bloqueada', 'bg-red-50 text-red-700 ring-red-200'],
    approved: ['Aprovado', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    completed: ['Concluído', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    rejected: ['Recusado', 'bg-red-50 text-red-700 ring-red-200'],
    expired: ['Expirado', 'bg-rose-50 text-rose-700 ring-rose-200'],
    replaced: ['Substituído', 'bg-slate-100 text-slate-600 ring-slate-200'],
    review_requested: ['Em revisão', 'bg-indigo-50 text-indigo-700 ring-indigo-200'],
    review_approved: ['Revisão aprovada', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    adjustment_requested: ['Ajuste solicitado', 'bg-amber-50 text-amber-700 ring-amber-200'],
    scheduled: ['Agendado', 'bg-blue-50 text-blue-700 ring-blue-200'],
    confirmed: ['Confirmado', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    done: ['Realizado', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    overdue: ['Atrasado', 'bg-rose-50 text-rose-700 ring-rose-200'],
    cancelled: ['Cancelado', 'bg-slate-100 text-slate-600 ring-slate-200'],
    critical: ['Crítico', 'bg-red-50 text-red-800 ring-red-200'],
    active: ['Ativo', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    sent: ['Enviado', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    registered: ['Registrado', 'bg-slate-100 text-slate-700 ring-slate-200'],
    granted: ['Concedido', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    revoked: ['Revogado', 'bg-red-50 text-red-700 ring-red-200'],
    new: ['Novo', 'bg-blue-50 text-blue-700 ring-blue-200'],
    triage: ['Em análise', 'bg-indigo-50 text-indigo-700 ring-indigo-200'],
    waiting_client: ['Aguardando cliente', 'bg-amber-50 text-amber-700 ring-amber-200'],
    waiting_third_party: ['Aguardando terceiro', 'bg-amber-50 text-amber-700 ring-amber-200'],
    resolved: ['Resolvido', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    suspended: ['Suspenso', 'bg-red-50 text-red-700 ring-red-200'],
    accepted: ['Aceito', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    inactive: ['Inativo', 'bg-slate-100 text-slate-600 ring-slate-200'],
    negotiation: ['Negociação', 'bg-blue-50 text-blue-700 ring-blue-200'],
    delinquent: ['Inadimplente', 'bg-amber-50 text-amber-700 ring-amber-200'],
    closed: ['Encerrado', 'bg-slate-100 text-slate-600 ring-slate-200'],
};
</script>

<template>
    <span
        :class="[
            'inline-flex h-6 items-center rounded-full px-2.5 text-xs font-medium ring-1 ring-inset',
            (statuses[status] ?? [status, 'bg-slate-100 text-slate-700 ring-slate-200'])[1],
        ]"
    >
        {{ (statuses[status] ?? [status])[0] }}
    </span>
</template>
