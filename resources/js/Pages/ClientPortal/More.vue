<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ClientPortalLayout from '../../Layouts/ClientPortalLayout.vue';

defineProps({
    announcements: { type: Array, default: () => [] },
    reports: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Mais · Portal" />
    <ClientPortalLayout title="Mais opções" active-nav="more">
        <div class="grid gap-6">
            <div class="grid gap-3 sm:grid-cols-2">
                <Link href="/client-portal/finance" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-md">
                    <h2 class="text-base font-semibold text-slate-950">Cobranças</h2>
                    <p class="mt-2 text-sm text-slate-600">Consulte valores e vencimentos.</p>
                </Link>
                <Link href="/client-portal/meetings" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-md">
                    <h2 class="text-base font-semibold text-slate-950">Reuniões</h2>
                    <p class="mt-2 text-sm text-slate-600">Confirme ou solicite remarcação de compromissos.</p>
                </Link>
                <Link href="/client-portal/profile" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-200 hover:shadow-md">
                    <h2 class="text-base font-semibold text-slate-950">Meus dados</h2>
                    <p class="mt-2 text-sm text-slate-600">Atualize nome, e-mail e telefones.</p>
                </Link>
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-950">Comunicados</h2>
                <div class="mt-4 grid gap-4">
                    <article v-for="announcement in announcements" :key="announcement.id" class="border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                        <h3 class="font-medium text-slate-950">{{ announcement.title }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ announcement.published_at }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ announcement.body }}</p>
                    </article>
                    <p v-if="!announcements.length" class="text-sm text-slate-500">Nenhum comunicado publicado.</p>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-950">Relatórios liberados</h2>
                <div class="mt-4 grid gap-4">
                    <article v-for="report in reports" :key="report.id" class="border-b border-slate-100 pb-4 last:border-0 last:pb-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-medium text-slate-950">{{ report.title }}</h3>
                                <p class="mt-1 text-xs text-slate-500">Liberado em {{ report.released_at }}</p>
                                <p class="mt-2 text-sm text-slate-600">
                                    Tarefas concluídas: {{ report.summary?.tasks_completed ?? 0 }} · Chamados abertos: {{ report.summary?.tickets_open ?? 0 }}
                                </p>
                            </div>
                            <a
                                :href="report.download_url"
                                class="inline-flex h-9 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"
                            >
                                Baixar
                            </a>
                        </div>
                    </article>
                    <p v-if="!reports.length" class="text-sm text-slate-500">Nenhum relatório liberado.</p>
                </div>
            </section>
        </div>
    </ClientPortalLayout>
</template>
