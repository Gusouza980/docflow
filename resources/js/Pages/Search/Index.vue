<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    query: { type: String, default: '' },
    clients: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Busca" />
    <AppLayout title="Busca" :breadcrumbs="[{ label: 'Busca' }]">
        <div class="grid gap-6">
            <p class="text-sm text-slate-600">Resultados para “{{ query }}”.</p>

            <section class="rounded-lg border border-slate-200 bg-white">
                <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-950">Clientes</h2>
                <p v-if="!clients.length" class="px-4 py-6 text-sm text-slate-500">Nenhum cliente encontrado.</p>
                <ul v-else class="divide-y divide-slate-200">
                    <li v-for="client in clients" :key="client.id">
                        <Link :href="client.href" class="block px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-slate-50">{{ client.title }}</Link>
                    </li>
                </ul>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white">
                <h2 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-950">Tarefas</h2>
                <p v-if="!tasks.length" class="px-4 py-6 text-sm text-slate-500">Nenhuma tarefa encontrada.</p>
                <ul v-else class="divide-y divide-slate-200">
                    <li v-for="task in tasks" :key="task.id">
                        <Link :href="task.href" class="block px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-slate-50">{{ task.title }}</Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
