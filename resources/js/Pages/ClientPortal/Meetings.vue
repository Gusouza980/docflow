<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import ClientPortalLayout from '../../Layouts/ClientPortalLayout.vue';
import Badge from '../../Components/UI/Badge.vue';
import Button from '../../Components/UI/Button.vue';
import TextareaInput from '../../Components/Forms/TextareaInput.vue';

defineProps({
    meetings: { type: Array, default: () => [] },
});

const confirmingId = ref(null);
const notesByMeeting = reactive({});
const confirmForm = useForm({ action: '', notes: '' });

function confirmationLabel(status) {
    return {
        pending: 'Aguardando confirmação',
        confirmed: 'Confirmada',
        declined: 'Recusada',
        reschedule_requested: 'Remarcação solicitada',
    }[status] ?? status;
}

function confirmationTone(status) {
    return {
        pending: 'warning',
        confirmed: 'success',
        declined: 'danger',
        reschedule_requested: 'secondary',
    }[status] ?? 'secondary';
}

function submitConfirmation(meetingId, action) {
    confirmingId.value = meetingId;
    confirmForm.action = action;
    confirmForm.notes = notesByMeeting[meetingId] ?? '';
    confirmForm.patch(`/client-portal/meetings/${meetingId}/confirm`, {
        preserveScroll: true,
        onFinish: () => {
            confirmingId.value = null;
        },
    });
}

function isSubmitting(meetingId, action) {
    return confirmingId.value === meetingId && confirmForm.processing && confirmForm.action === action;
}
</script>

<template>
    <Head title="Reuniões · Portal" />
    <ClientPortalLayout title="Reuniões" active-nav="more">
        <div class="grid gap-4">
            <p class="text-sm text-slate-600">Confirme, recuse ou solicite remarcação dos compromissos agendados pelo escritório.</p>

            <article
                v-for="meeting in meetings"
                :key="meeting.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950">{{ meeting.title }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ meeting.starts_at }}<span v-if="meeting.ends_at"> · até {{ meeting.ends_at }}</span></p>
                        <p v-if="meeting.location" class="mt-1 text-sm text-slate-500">{{ meeting.location }}</p>
                    </div>
                    <Badge :tone="confirmationTone(meeting.confirmation_status)">
                        {{ confirmationLabel(meeting.confirmation_status) }}
                    </Badge>
                </div>

                <p v-if="meeting.description" class="mt-3 text-sm leading-6 text-slate-600">{{ meeting.description }}</p>

                <p v-if="meeting.confirmation_deadline_at" class="mt-2 text-xs text-amber-700">
                    Confirme até {{ meeting.confirmation_deadline_at }}
                </p>

                <p v-if="meeting.confirmation_notes && !meeting.can_confirm" class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">
                    {{ meeting.confirmation_notes }}
                </p>

                <div v-if="meeting.can_confirm" class="mt-4 grid gap-3 border-t border-slate-100 pt-4">
                    <TextareaInput
                        :id="`meeting-notes-${meeting.id}`"
                        v-model="notesByMeeting[meeting.id]"
                        label="Observações (obrigatório para remarcação)"
                        :error="confirmingId === meeting.id ? confirmForm.errors.notes : null"
                    />
                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" :loading="isSubmitting(meeting.id, 'confirmed')" @click="submitConfirmation(meeting.id, 'confirmed')">
                            Confirmar
                        </Button>
                        <Button size="sm" variant="secondary" :loading="isSubmitting(meeting.id, 'declined')" @click="submitConfirmation(meeting.id, 'declined')">
                            Recusar
                        </Button>
                        <Button size="sm" variant="secondary" :loading="isSubmitting(meeting.id, 'reschedule_requested')" @click="submitConfirmation(meeting.id, 'reschedule_requested')">
                            Solicitar remarcação
                        </Button>
                    </div>
                </div>
            </article>

            <p v-if="!meetings.length" class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                Nenhuma reunião pendente de confirmação.
            </p>
        </div>
    </ClientPortalLayout>
</template>
