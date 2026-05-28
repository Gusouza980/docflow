<script setup>
defineProps({
    title: { type: String, default: 'Acesse sua conta' },
    subtitle: { type: String, default: 'Entre para organizar clientes, documentos, prazos e finanças.' },
});
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">
        <section class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                <p class="text-sm font-bold text-blue-700">Docflow</p>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">{{ title }}</h1>
                <p class="mt-2 text-sm leading-6 text-slate-600">{{ subtitle }}</p>
            </div>
            <slot />
        </section>
    </main>
</template>
