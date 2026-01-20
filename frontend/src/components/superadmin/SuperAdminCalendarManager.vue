<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">{{ t.title }}</h1>
      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" type="button" :disabled="!selectedChurchId" @click="loadEvents">
          {{ t.refresh }}
        </button>
        <button class="btn btn-outline-danger" type="button" :disabled="!selectedChurchId" @click="openDeleteConfirm">
          {{ t.deleteCalendar }}
        </button>
      </div>
    </div>

    <div class="bg-white border rounded p-3 mb-4">
      <label class="form-label">
        {{ t.church }}
        <select v-model="selectedChurchId" class="form-select">
          <option value="">{{ common.select }}</option>
          <option v-for="church in churches" :key="church.id" :value="church.id">
            {{ church.name }}
          </option>
        </select>
      </label>
    </div>

    <div v-if="loading">{{ t.loading }}</div>
    <p v-if="error" class="text-danger">{{ error }}</p>
    <p v-if="success" class="text-success">{{ success }}</p>

    <div v-if="!loading && selectedChurchId && events.length === 0" class="text-muted">
      {{ t.empty }}
    </div>

    <div v-else-if="selectedChurchId" class="bg-white border rounded">
      <div class="table-responsive d-none d-md-block">
        <table class="table mb-0" data-dt="off">
          <caption class="caption-top px-3 pt-3 text-muted">
            {{ t.rangeLabel }}: {{ rangeLabel }}
          </caption>
          <thead>
            <tr>
              <th>{{ t.columns.id }}</th>
              <th>{{ t.columns.department }}</th>
              <th>{{ t.columns.title }}</th>
              <th>{{ t.columns.start }}</th>
              <th>{{ t.columns.end }}</th>
              <th>{{ t.columns.status }}</th>
              <th>{{ t.columns.reviewStatus }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in events" :key="event.id">
              <td>{{ event.id }}</td>
              <td>{{ event.department?.name || '—' }}</td>
              <td>{{ event.title || '—' }}</td>
              <td>{{ formatDateTime(event.start_at) }}</td>
              <td>{{ formatDateTime(event.end_at) }}</td>
              <td>{{ event.status || '—' }}</td>
              <td>{{ event.review_status || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-md-none p-3">
        <div class="text-muted mb-3">
          <strong>{{ t.rangeLabel }}:</strong> {{ rangeLabel }}
        </div>
        <div v-for="event in events" :key="event.id" class="card mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h5 class="card-title mb-1">{{ event.title || '—' }}</h5>
                <p class="card-subtitle text-muted mb-0">#{{ event.id }}</p>
              </div>
            </div>
            <div class="mb-2">
              <strong>{{ t.columns.department }}:</strong> {{ event.department?.name || '—' }}
            </div>
            <div class="mb-2">
              <strong>{{ t.columns.start }}:</strong> {{ formatDateTime(event.start_at) }}
            </div>
            <div class="mb-2">
              <strong>{{ t.columns.end }}:</strong> {{ formatDateTime(event.end_at) }}
            </div>
            <div class="mb-2">
              <strong>{{ t.columns.status }}:</strong> {{ event.status || '—' }}
            </div>
            <div>
              <strong>{{ t.columns.reviewStatus }}:</strong> {{ event.review_status || '—' }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="confirmDeleteOpen" class="confirm-modal-backdrop" role="presentation">
      <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-delete-title">
        <h2 id="confirm-delete-title" class="confirm-modal-title">{{ t.deleteCalendar }}</h2>
        <p class="confirm-modal-text">{{ confirmDeleteMessage }}</p>
        <div class="confirm-modal-actions">
          <button class="btn btn-outline-secondary" type="button" @click="closeDeleteConfirm">
            {{ common.cancel }}
          </button>
          <button class="btn btn-outline-danger" type="button" @click="deleteCalendar">
            {{ t.deleteCalendar }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { superAdminApi } from '../../services/superAdminApi'
import { useUiStore } from '../../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../../i18n/translations'

const churches = ref([])
const selectedChurchId = ref('')
const events = ref([])
const loading = ref(false)
const error = ref('')
const success = ref('')
const successTimer = ref(null)
const confirmDeleteOpen = ref(false)
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].superadmin.calendarManager)
const common = computed(() => translations[locale.value].common)

const loadChurches = async () => {
  const response = await superAdminApi.listChurches()
  churches.value = response.data
}

const loadEvents = async () => {
  if (!selectedChurchId.value) {
    events.value = []
    return
  }
  loading.value = true
  error.value = ''
  success.value = ''
  try {
    events.value = await superAdminApi.listChurchEvents(selectedChurchId.value)
  } catch {
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const formatDateTime = (value) => {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return date.toLocaleString(locale.value)
}

const setSuccessMessage = (message) => {
  success.value = message
  if (successTimer.value) {
    clearTimeout(successTimer.value)
  }
  successTimer.value = setTimeout(() => {
    success.value = ''
    successTimer.value = null
  }, 3000)
}

const formatCountMessage = (template, count) => template.replace('{count}', count)

const confirmDeleteMessage = computed(() => {
  const selectedId = Number(selectedChurchId.value)
  const churchName = churches.value.find((church) => church.id === selectedId)?.name || ''
  return t.value.deleteCalendarConfirm.replace('{name}', churchName)
})

const openDeleteConfirm = () => {
  if (!selectedChurchId.value) {
    return
  }
  confirmDeleteOpen.value = true
}

const closeDeleteConfirm = () => {
  confirmDeleteOpen.value = false
}

const deleteCalendar = async () => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  success.value = ''
  try {
    const response = await superAdminApi.deleteChurchEvents(selectedChurchId.value)
    events.value = []
    const message = formatCountMessage(t.value.deleteCalendarSuccess, response.deleted ?? 0)
    setSuccessMessage(message)
    closeDeleteConfirm()
  } catch {
    error.value = t.value.deleteCalendarError
    closeDeleteConfirm()
  }
}

const rangeLabel = computed(() => {
  if (events.value.length === 0) {
    return t.value.rangeEmpty
  }
  const starts = events.value.map((event) => new Date(event.start_at)).filter((date) => !Number.isNaN(date.getTime()))
  const ends = events.value.map((event) => new Date(event.end_at)).filter((date) => !Number.isNaN(date.getTime()))
  if (starts.length === 0 || ends.length === 0) {
    return t.value.rangeEmpty
  }
  const earliest = new Date(Math.min(...starts.map((date) => date.getTime())))
  const latest = new Date(Math.max(...ends.map((date) => date.getTime())))
  return `${earliest.toLocaleDateString(locale.value)} - ${latest.toLocaleDateString(locale.value)}`
})

watch(selectedChurchId, () => {
  loadEvents()
})

watch(confirmDeleteOpen, (next) => {
  document.body.style.overflow = next ? 'hidden' : ''
})

onMounted(loadChurches)

onUnmounted(() => {
  if (successTimer.value) {
    clearTimeout(successTimer.value)
  }
  document.body.style.overflow = ''
})
</script>
