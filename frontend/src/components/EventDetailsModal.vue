<template>
  <div v-if="open && event" class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-panel modal-panel--lg">
      <header class="modal-header">
        <h2>{{ resolvedLabels.title }}</h2>
        <button type="button" class="modal-close" @click="$emit('close')">×</button>
      </header>

      <div class="event-details">
        <p class="event-details-title">{{ event.title }}</p>
        <p v-if="event.description" class="event-details-text">
          <strong>{{ resolvedLabels.description }}:</strong> {{ event.description }}
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.department }}:</strong> {{ event.department?.name || '—' }}
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.objective }}:</strong> {{ event.objective?.name || '—' }}
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.location }}:</strong> {{ event.location || '—' }}
        </p>
        <template v-if="showStatusDetails">
          <p class="event-details-text">
            <strong>{{ resolvedLabels.status }}:</strong> {{ statusLabel }}
          </p>
          <p class="event-details-text">
            <strong>{{ resolvedLabels.reviewStatus }}:</strong> {{ reviewStatusLabel }}
          </p>
          <p class="event-details-text">
            <strong>{{ resolvedLabels.finalOutcome }}:</strong>
            <span class="status-pill" :class="finalOutcomeClass">{{ finalOutcomeLabel }}</span>
          </p>
          <p v-if="event.review_note" class="event-details-text">
            <strong>{{ resolvedLabels.reviewNote }}:</strong> {{ event.review_note }}
          </p>
        </template>
        <p v-else class="event-details-text">
          <strong>{{ resolvedLabels.finalOutcome }}:</strong>
          <span class="status-pill" :class="finalOutcomeClass">{{ finalOutcomeLabel }}</span>
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.start }}:</strong> {{ formatDate(event.start_at) }}
        </p>
        <p class="event-details-text">
          <strong>{{ resolvedLabels.end }}:</strong> {{ formatDate(event.end_at) }}
        </p>
      </div>

      <div v-if="visibleNotes.length" class="event-details mt-3">
        <h3 class="history-title">{{ resolvedLabels.notesTitle }}</h3>
        <div class="event-details-scroll">
          <ul class="history-list">
            <li v-for="note in visibleNotes" :key="note.id" class="history-item note-item" :class="noteClass(note)">
              <div class="history-meta">
                {{ note.author?.name || '—' }} · {{ formatDate(note.created_at) }}
              </div>
              <div class="history-note">{{ note.note }}</div>
            </li>
          </ul>
        </div>
        <div v-if="showReplyBox" class="note-reply mt-3">
          <textarea
            v-model="replyDraft"
            class="form-control"
            rows="3"
            :placeholder="resolvedLabels.replyPlaceholder"
          ></textarea>
          <button
            type="button"
            class="btn btn-outline-primary mt-2"
            :disabled="!replyDraft || !replyDraft.trim()"
            @click="submitReply"
          >
            {{ resolvedLabels.replyButton }}
          </button>
        </div>
      </div>

      <div v-if="historyEntries.length" class="event-details mt-3">
        <h3 class="history-title">{{ resolvedLabels.historyTitle }}</h3>
        <div class="event-details-scroll">
          <ul class="history-list">
            <li v-for="entry in historyEntries" :key="entry.id" class="history-item">
              <div class="history-meta">{{ formatHistoryMeta(entry) }}</div>
              <ul v-if="Object.keys(entry.changes || {}).length" class="history-changes">
                <li v-for="[field, change] in Object.entries(entry.changes || {})" :key="field">
                  <strong>{{ field }}</strong>: {{ formatHistoryValue(change.from) }} → {{ formatHistoryValue(change.to) }}
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </div>

      <div class="action-row">
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
import { computed, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'
const emit = defineEmits(['close', 'reply-note', 'edit-event'])

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
  canEditEvent: {
    type: Boolean,
    default: false,
  },
  showStatusDetails: {
    type: Boolean,
    default: true,
  },
  labels: {
    type: Object,
    default: null,
  },
})

const replyDraft = ref('')
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const fallbackLabels = computed(() => translations[locale.value].calendar.eventDetails)
const resolvedLabels = computed(() => props.labels || fallbackLabels.value)

const visibleNotes = computed(() => props.notes || [])

const historyEntries = computed(() => {
  const list = props.histories || []
  return [...list].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

const statusLabel = computed(() => {
  const value = props.event?.status
  if (!value) {
    return '—'
  }
  const map = {
    hold: resolvedLabels.value.statusHold,
    locked: resolvedLabels.value.statusLocked,
    cancelled: resolvedLabels.value.statusCancelled,
  }
  return map[value] || value
})

const reviewStatusLabel = computed(() => {
  const value = props.event?.review_status
  if (!value) {
    return resolvedLabels.value.reviewPending
  }
  const map = {
    pending: resolvedLabels.value.reviewPending,
    approved: resolvedLabels.value.reviewApproved,
    denied: resolvedLabels.value.reviewDenied,
    changes_requested: resolvedLabels.value.reviewChanges,
  }
  return map[value] || value
})

const finalOutcomeLabel = computed(() => {
  const value = props.event?.final_validation
  if (!value) {
    return resolvedLabels.value.reviewPending
  }
  const map = {
    accepted: resolvedLabels.value.finalOutcomeAccepted,
    rejected: resolvedLabels.value.finalOutcomeRejected,
    update_requested: resolvedLabels.value.finalOutcomeUpdateRequested,
  }
  return map[value] || value
})

const finalOutcomeClass = computed(() => {
  const value = props.event?.final_validation
  if (value === 'accepted') {
    return 'status-accepted'
  }
  if (value === 'rejected') {
    return 'status-rejected'
  }
  return 'status-pending'
})

const isSecretaryNote = (note) => {
  const role = note?.author?.role
  return role === 'secretary' || role === 'superadmin'
}

const noteClass = (note) => (isSecretaryNote(note) ? 'note-item--incoming' : 'note-item--outgoing')

const hasSecretaryNotes = computed(() => visibleNotes.value.some((note) => isSecretaryNote(note)))
const hasDepartmentReply = computed(() =>
  visibleNotes.value.some((note) => !isSecretaryNote(note))
)
const showReplyBox = computed(
  () => props.canReplyNotes && hasSecretaryNotes.value && !hasDepartmentReply.value
)

const submitReply = () => {
  if (!replyDraft.value || !replyDraft.value.trim()) {
    return
  }
  emit('reply-note', { reply: replyDraft.value.trim() })
  replyDraft.value = ''
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

const formatHistoryMeta = (entry) => {
  const userName = entry.user?.name || '—'
  const date = entry.created_at ? formatDate(entry.created_at) : '—'
  return `${userName} · ${date}`
}

const formatHistoryValue = (value) => {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  if (typeof value === 'string' && !Number.isNaN(Date.parse(value))) {
    return formatDate(value)
  }
  return String(value)
}
</script>
