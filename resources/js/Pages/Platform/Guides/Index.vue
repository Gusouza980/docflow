<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Card from '../../../Components/UI/Card.vue';
import Badge from '../../../Components/UI/Badge.vue';

defineProps({
    guides: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Platform · Guia de uso" />
    <PlatformLayout
        title="Guia de uso do sistema"
        active-nav="guides"
        :breadcrumbs="[{ label: 'Platform' }, { label: 'Guia de uso' }]"
    >
        <div class="grid gap-6">
            <div class="rounded-lg border border-slate-200 bg-white p-5">
                <div class="flex flex-wrap gap-2">
                    <Badge tone="primary">Escritório</Badge>
                    <Badge tone="secondary">Portal do cliente</Badge>
                    <Badge tone="success">Platform</Badge>
                </div>
                <h2 class="mt-4 text-xl font-semibold text-slate-950">Documentação operacional completa</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Use estes guias para treinar clientes, suporte e a equipe interna. Cada página cobre fluxos, regras,
                    limites de plano e rotas relevantes — inclusive o portal do cliente final.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="guide in guides"
                    :key="guide.slug"
                    :href="`/platform/guides/${guide.slug}`"
                    class="block rounded-lg transition hover:ring-2 hover:ring-violet-300"
                >
                    <Card>
                        <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">{{ guide.audience }}</p>
                        <h3 class="mt-2 text-base font-semibold text-slate-950">{{ guide.title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ guide.summary }}</p>
                        <p class="mt-4 text-xs font-medium text-violet-700">Abrir guia →</p>
                    </Card>
                </Link>
            </div>
        </div>
    </PlatformLayout>
</template>
