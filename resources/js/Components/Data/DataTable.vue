<script setup>
import EmptyState from '../Feedback/EmptyState.vue';
import Skeleton from '../UI/Skeleton.vue';

defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'Nenhum registro encontrado' },
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <slot name="toolbar" />
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="px-4 py-3">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody v-if="loading" class="divide-y divide-slate-100">
                    <tr v-for="index in 3" :key="index">
                        <td v-for="column in columns" :key="column.key" class="px-4 py-3"><Skeleton class-name="h-5 w-full" /></td>
                    </tr>
                </tbody>
                <tbody v-else-if="rows.length" class="divide-y divide-slate-100">
                    <tr v-for="row in rows" :key="row.id ?? row[columns[0].key]" class="hover:bg-slate-50">
                        <td v-for="column in columns" :key="column.key" class="px-4 py-3 text-slate-600">
                            <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="!loading && !rows.length" class="p-8">
            <EmptyState :title="emptyTitle" description="Ajuste os filtros ou crie um novo registro para continuar." />
        </div>
    </div>
</template>
