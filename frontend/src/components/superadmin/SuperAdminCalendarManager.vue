<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">{{ t.title }}</h1>
      <button class="btn btn-outline-secondary" type="button" :disabled="!selectedChurchId" @click="loadEvents">
        {{ t.refresh }}
      </button>
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

    <div v-if="!loading && selectedChurchId && events.length === 0" class="text-muted">
      {{ t.empty }}
    </div>

    <div v-else-if="selectedChurchId" class="bg-white border rounded">
      <div class="table-responsive d-none d-md-block">
        <table class="table mb-0" data-dt="off">
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
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { superAdminApi } from '../../services/superAdminApi'
import { useUiStore } from '../../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../../i18n/translations'

const churches = ref([])
const selectedChurchId = ref('')
const events = ref([])
const loading = ref(false)
const error = ref('')
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

watch(selectedChurchId, () => {
  loadEvents()
})

onMounted(loadChurches)
</script>
