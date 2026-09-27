<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const impersonation = computed(() => page.props.auth?.impersonation ?? null);
</script>

<template>
    <div
        v-if="impersonation?.active"
        class="border-b border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-950"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p>
                Você está vendo como <strong>{{ impersonation.owner_name }}</strong>
                · {{ impersonation.organization_name }}
                <span v-if="impersonation.organization_suspended" class="font-semibold"> (organização suspensa)</span>
                <span v-else-if="impersonation.organization_inaccessible" class="font-semibold"> (acesso do tenant bloqueado)</span>
            </p>
            <Link
                :href="impersonation.stop_url"
                method="post"
                as="button"
                class="inline-flex h-8 items-center rounded-md border border-amber-400 bg-white px-3 text-xs font-semibold text-amber-900 hover:bg-amber-50"
            >
                Sair da impersonação
            </Link>
        </div>
    </div>
</template>
