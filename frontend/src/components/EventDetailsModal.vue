<template>
  <div v-if="open && event" class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-panel modal-panel--lg">
      <header class="modal-header">
        <h2>{{ resolvedLabels.title }}</h2>
        <button type="button" class="modal-close" @click="$emit('close')">×</button>
      </header>

      <div class="event-details">
        <p class="event-details-title">{{ event.title }}</p><p v-if="event.is_conference_event">{{ locale === 'es' ? (event.publish_to_feed ? 'Conferencia · Publicado' : 'Conferencia · Horario reservado · No publicado') : (event.publish_to_feed ? 'Conference · Published' : 'Conference · Reserved time · Unpublished') }}</p>
        <p v-if="event.description" class="event-details-text">
          <strong>{{ resolvedLabels.description }}:</strong> {{ event.description }}
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.department }}:</strong> {{ event.is_conference_event ? (locale === 'es' ? 'Conferencia' : 'Conference') : event.department?.name || '—' }}
        </p>
        <p v-if="!event.is_conference_event" class="event-details-text">
          <strong>{{ resolvedLabels.objective }}:</strong> {{ event.objective?.name || '—' }}
        </p>
        <p v-if="event.location" class="event-details-text">
          <strong>{{ resolvedLabels.location }}:</strong> {{ event.location || '—' }}
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.status }}:</strong>
          <span class="status-pill" :class="statusClass">{{ statusLabel }}</span>
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.start }}:</strong> {{ formatDate(event.start_at) }}
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.end }}:</strong> {{ formatDate(event.end_at) }}
        </p>
      </div>

      <section class="event-activity" aria-labelledby="event-activity-title">
        <div class="activity-heading">
          <h3 id="event-activity-title">{{ locale === 'es' ? 'Historial del evento' : 'Event activity' }}</h3>
          <span>{{ locale === 'es' ? 'Más reciente primero' : 'Newest first' }}</span>
        </div>
        <div v-if="activityEntries.length" class="activity-scroll" tabindex="0" :aria-label="locale === 'es' ? 'Notas y cambios de estado' : 'Notes and status changes'">
          <ol class="activity-timeline">
            <li v-for="entry in activityEntries" :key="entry.key" class="activity-entry" :class="`activity-entry--${entry.action}`">
              <div class="activity-card">
                <div class="activity-meta">
                  <span class="activity-badge">{{ entry.action === 'note' ? (locale === 'es' ? 'Nota' : 'Note') : eventActionLabel(entry.action, locale) }}</span>
                  <time :datetime="entry.createdAt">{{ formatDate(entry.createdAt) }}</time>
                </div>
                <div class="activity-author">{{ entry.author || '—' }}</div>
                <p v-if="entry.body" class="activity-body">{{ entry.body }}</p>
              </div>
            </li>
          </ol>
        </div>
        <p v-else class="activity-empty">{{ locale === 'es' ? 'Aún no hay actividad en este evento.' : 'No activity on this event yet.' }}</p>
        <div v-if="canReplyNotes" class="note-reply mt-3">
          <textarea
            v-model="replyDraft"
            class="form-control"
            rows="3"
            :aria-label="locale === 'es' ? 'Nota del evento' : 'Event note'"
            :placeholder="locale === 'es' ? 'Escribe una nota…' : 'Write a note…'"
          ></textarea>
          <button
            type="button"
            class="btn btn-outline-primary mt-2"
            :disabled="saving || !replyDraft.trim()"
            @click="submitReply"
          >
            {{ locale === 'es' ? 'Enviar nota' : 'Send note' }}
          </button>
        </div>
      </section>

      <div v-if="canReview && reviewOptions.length" class="event-details mt-3">
        <h3 class="history-title">{{ locale === 'es' ? 'Decisión de secretaría' : 'Clerk decision' }}</h3>
        <p>{{ locale === 'es' ? 'Las notas no cambian el estado. Usa este control para registrar una decisión.' : 'Notes do not change status. Use this control to record a decision.' }}</p>
        <label>{{ locale === 'es' ? 'Estado' : 'Status' }}
          <select v-model="reviewDecision" class="form-select">
            <option value="">{{ locale === 'es' ? 'Selecciona…' : 'Select…' }}</option>
            <option v-for="option in reviewOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </label>
        <p v-if="reviewDecision && reviewDecision !== 'approved'">{{ locale === 'es' ? 'Escribe el motivo en la nota antes de guardar el estado.' : 'Write the reason in the note before saving the status.' }}</p>
        <button type="button" :disabled="saving || !reviewDecision || (reviewDecision !== 'approved' && !replyDraft.trim())" @click="emit('review', { status: reviewDecision, note: replyDraft.trim() })">
          {{ locale === 'es' ? 'Guardar estado' : 'Save status' }}
        </button>
      </div>
      <p v-if="error" role="alert" class="event-error">{{ error }}</p>

      <div class="action-row">
        <button v-if="canRequestOpen" type="button" @click="$emit('request-open')">{{ requestOpenLabel }}</button>
        <span v-if="event.open_requested_at">{{ locale === 'es' ? 'Apertura solicitada' : 'Opening requested' }}</span>
        <button
          v-if="canEditEvent"
          type="button"
          class="btn btn-outline-secondary"
          @click="$emit('edit-event')"
        >
          {{ resolvedLabels.editEvent }}
        </button>
        <button type="button" @click="$emit('close')">{{ resolvedLabels.close }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { eventActionLabel } from '../utils/eventWorkflow'
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'
const emit = defineEmits(['close', 'reply-note', 'edit-event', 'request-open', 'review'])

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  event: {
    type: Object,
    default: null,
  },
  notes: {
    type: Array,
    default: () => [],
  },
  histories: {
    type: Array,
    default: () => [],
  },
  canReplyNotes: {
    type: Boolean,
    default: false,
  },
  canReview: Boolean,
  saving: Boolean,
  error: String,
  canRequestOpen: Boolean,
  requestOpenLabel: String,
  canEditEvent: {
    type: Boolean,
    default: false,
  },
  labels: {
    type: Object,
    default: null,
  },
})

const replyDraft = ref('')
const reviewDecision = ref('')
watch(() => [props.open, props.event?.id, props.notes.length, props.event?.review_status], () => {
  replyDraft.value = ''
  reviewDecision.value = ''
})
const reviewOptions = computed(() => {
  if (props.event?.status !== 'locked') return []
  const es = locale.value === 'es'
  if (props.event.review_status === 'approved') return [{ value: 'changes_requested', label: es ? 'Reabrir para editar' : 'Reopen for editing' }]
  if (!['pending', 'resubmitted'].includes(props.event.review_status)) return []
  return [
    { value: 'approved', label: es ? 'Aprobar' : 'Accept' },
    { value: 'changes_requested', label: es ? 'Solicitar cambios' : 'Request changes' },
    { value: 'denied', label: es ? 'Rechazar' : 'Reject' },
  ]
})
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const fallbackLabels = computed(() => translations[locale.value].calendar.eventDetails)
const resolvedLabels = computed(() => props.labels || fallbackLabels.value)

const activityEntries = computed(() => {
  const notesByHistory = new Map(props.notes.filter(note => note.event_history_id != null)
    .map(note => [String(note.event_history_id), note]))
  const representedNotes = new Set()
  const entries = props.histories.map(history => {
    const note = notesByHistory.get(String(history.id)) || history.event_note
    if (note?.id != null) representedNotes.add(String(note.id))
    return {
      key: `history-${history.id}`, action: history.action,
      createdAt: history.created_at, author: history.user?.name || note?.author?.name,
      body: note?.note || history.note || '',
    }
  })
  for (const note of props.notes) {
    if (representedNotes.has(String(note.id))) continue
    entries.push({
      key: `note-${note.id}`, action: note.kind && note.kind !== 'general' ? note.kind : 'note',
      createdAt: note.created_at, author: note.author?.name, body: note.note,
    })
  }
  return entries.sort((a, b) => (Date.parse(b.createdAt) || 0) - (Date.parse(a.createdAt) || 0)
    || b.key.localeCompare(a.key, undefined, { numeric: true }))
})

const statusLabel = computed(() => {
  const es = locale.value === 'es'
  if (props.event?.status === 'cancelled') return es ? 'Cancelado' : 'Cancelled'
  if (props.event?.status === 'hold') return es ? 'Reserva temporal' : 'Temporary reservation'
  const labels = {
    pending: es ? 'Pendiente de aprobación' : 'Awaiting approval',
    resubmitted: es ? 'Reenviado para aprobación' : 'Resubmitted for approval',
    approved: es ? 'Aprobado' : 'Accepted',
    denied: es ? 'Rechazado' : 'Rejected',
    changes_requested: es ? 'Cambios solicitados' : 'Changes requested',
  }
  return labels[props.event?.review_status] || labels.pending
})

const statusClass = computed(() => {
  if (props.event?.status === 'cancelled' || props.event?.review_status === 'denied') return 'status-rejected'
  if (props.event?.review_status === 'approved') return 'status-accepted'
  return 'status-pending'
})

const submitReply = () => {
  if (!replyDraft.value || !replyDraft.value.trim()) {
    return
  }
  emit('reply-note', { reply: replyDraft.value.trim() })
}

const formatDate = (value) => {
  if (!value) {
    return '—'
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return value
  }
  return date.toLocaleString()
}

</script>

<style scoped>
.event-activity { margin: 1.5rem 0; padding-top: 1.25rem; border-top: 1px solid #dce4e0; }
.activity-heading { display: flex; align-items: baseline; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-bottom: 1rem; }
.activity-heading h3 { font-size: 1rem; font-weight: 500; margin: 0; }
.activity-heading > span, .activity-meta time, .activity-empty { color: #5c6b65; font-size: .8rem; }
.activity-scroll { max-height: 25rem; overflow-y: auto; padding: .25rem .5rem .25rem .25rem; }
.activity-timeline { list-style: none; margin: 0; padding: 0 0 0 1rem; }
.activity-entry { position: relative; padding: 0 0 1rem 1.25rem; border-left: 2px solid #dce4e0; }
.activity-entry:last-child { padding-bottom: 0; border-color: transparent; }
.activity-entry::before { content: ''; position: absolute; left: -.38rem; top: .85rem; width: .65rem; height: .65rem; border-radius: 50%; background: #477263; box-shadow: 0 0 0 4px #fff; }
.activity-card { background: #f7f9f8; border: 1px solid #e1e7e4; border-radius: .65rem; padding: .85rem 1rem; }
.activity-meta { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .4rem .75rem; }
.activity-badge { font-size: .75rem; font-weight: 500; color: #285547; background: #e5eee9; border-radius: 1rem; padding: .2rem .6rem; }
.activity-entry--note .activity-badge { background: #e8edf4; color: #3e5578; }
.activity-entry--rejected .activity-badge { background: #f8e7e5; color: #853c35; }
.activity-entry--changes_requested .activity-badge, .activity-entry--reopened .activity-badge { background: #fff0cf; color: #745411; }
.activity-author { margin-top: .5rem; font-size: .8125rem; font-weight: 600; color: #35473e; }
.activity-body { margin: .35rem 0 0; font-size: .8125rem; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
.note-reply { border-top: 1px solid #e1e7e4; padding-top: 1rem; }
@media (max-width: 600px) {
  .activity-card { padding: .7rem; }
  .activity-timeline { padding-left: .4rem; }
  .activity-entry { padding-left: .8rem; }
  .activity-meta { align-items: flex-start; flex-direction: column; }
}
</style>
