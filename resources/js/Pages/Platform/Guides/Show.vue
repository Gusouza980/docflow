<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '../../../Layouts/PlatformLayout.vue';
import Card from '../../../Components/UI/Card.vue';
import Badge from '../../../Components/UI/Badge.vue';

defineProps({
    guide: { type: Object, required: true },
    guides: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="`Platform · ${guide.title}`" />
    <PlatformLayout
        :title="guide.title"
        active-nav="guides"
        :breadcrumbs="[{ label: 'Platform' }, { label: 'Guia de uso', href: '/platform/guides' }, { label: guide.title }]"
    >
        <div class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
            <aside class="self-start rounded-lg border border-slate-200 bg-white p-3 lg:sticky lg:top-4">
                <Link
                    href="/platform/guides"
                    class="mb-2 block rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 hover:bg-slate-50 hover:text-violet-700"
                >
                    ← Todos os guias
                </Link>
                <Link
                    v-for="item in guides"
                    :key="item.slug"
                    :href="`/platform/guides/${item.slug}`"
                    :class="[
                        'block rounded-md px-3 py-2 text-sm font-medium transition',
                        item.slug === guide.slug
                            ? 'bg-violet-50 text-violet-800'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                    ]"
                >
                    {{ item.title }}
                </Link>
            </aside>

            <div class="grid gap-6">
                <div class="rounded-lg border border-slate-200 bg-white p-5">
                    <Badge tone="secondary">{{ guide.audience }}</Badge>
                    <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ guide.title }}</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ guide.summary }}</p>
                </div>

                <section
                    v-for="(section, index) in guide.sections"
                    :key="`${guide.slug}-${index}`"
                    class="grid gap-3"
                >
                    <div>
                        <h3 class="text-lg font-semibold text-slate-950">{{ section.heading }}</h3>
                        <p v-if="section.body" class="mt-1 max-w-3xl text-sm leading-6 text-slate-600">{{ section.body }}</p>
                    </div>

                    <Card v-if="section.bullets?.length">
                        <ul class="grid gap-2 text-sm text-slate-700">
                            <li v-for="item in section.bullets" :key="item" class="flex gap-2">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-violet-500" />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </Card>

                    <Card v-if="section.rules?.length" title="Regras">
                        <ul class="grid gap-2 text-sm text-slate-700">
                            <li v-for="rule in section.rules" :key="rule" class="flex gap-2">
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                                <span>{{ rule }}</span>
                            </li>
                        </ul>
                    </Card>

                    <Card v-if="section.steps?.length" title="Passo a passo">
                        <ol class="grid gap-3">
                            <li v-for="(step, stepIndex) in section.steps" :key="step" class="flex gap-3 text-sm leading-6 text-slate-700">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-50 text-xs font-bold text-violet-700">
                                    {{ stepIndex + 1 }}
                                </span>
                                <span>{{ step }}</span>
                            </li>
                        </ol>
                    </Card>

                    <Card v-if="section.pages?.length" title="Páginas">
                        <div class="divide-y divide-slate-100">
                            <div
                                v-for="pageItem in section.pages"
                                :key="pageItem.path"
                                class="grid gap-1 py-3 sm:grid-cols-[200px_1fr_1.2fr] sm:items-start sm:gap-4"
                            >
                                <p class="text-sm font-semibold text-slate-950">{{ pageItem.name }}</p>
                                <code class="text-xs text-violet-700">{{ pageItem.path }}</code>
                                <p class="text-sm text-slate-600">{{ pageItem.notes }}</p>
                            </div>
                        </div>
                    </Card>

                    <div v-if="section.tables?.length" class="grid gap-4">
                        <Card v-for="table in section.tables" :key="table.title" :title="table.title">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                                            <th
                                                v-for="header in table.headers"
                                                :key="header"
                                                class="px-2 py-2 font-semibold"
                                            >
                                                {{ header }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(row, rowIndex) in table.rows"
                                            :key="`${table.title}-${rowIndex}`"
                                            class="border-b border-slate-100"
                                        >
                                            <td
                                                v-for="(cell, cellIndex) in row"
                                                :key="`${rowIndex}-${cellIndex}`"
                                                class="px-2 py-2 text-slate-700"
                                            >
                                                {{ cell }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </div>
                </section>
            </div>
        </div>
    </PlatformLayout>
</template>
