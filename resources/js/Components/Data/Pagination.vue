<script setup>
defineProps({
    currentPage: { type: Number, default: 1 },
    totalPages: { type: Number, default: 5 },
    perPage: { type: Number, default: 15 },
});
</script>

<template>
    <nav class="flex flex-col gap-3 border-t border-slate-200 bg-white px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between" aria-label="Paginação">
        <p class="text-slate-500">Página {{ currentPage }} de {{ totalPages }} · {{ perPage }} por página</p>
        <div class="flex gap-2">
            <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">Anterior</button>
            <button type="button" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">Próxima</button>
        </div>
    </nav>
</template>
