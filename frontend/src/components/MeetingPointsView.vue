<template>
  <section class="container py-3 meeting-points">
    <div v-if="loading" class="text-muted small mb-2">{{ t.messages.loading }}</div>
    <p v-if="error" class="text-danger small">{{ error }}</p>

    <div v-if="meetings.length === 0 && !loading" class="text-muted">{{ t.messages.empty }}</div>

    <div v-else>
      <header class="meeting-header bg-white border rounded p-2 p-md-3 mb-3">
        <div class="meeting-header-grid">
          <div>
            <div class="meeting-title">{{ t.labels.meetingTitle }}</div>
            <div class="text-muted small">
              {{ selectedMeeting?.location || '—' }} · {{ formatDateTimeHuman(selectedMeeting?.planned_start_at) }}
            </div>
          </div>

          <div class="text-center">
            <div v-if="meetingState !== 'finalizada'" class="small text-muted">
              {{ t.labels.deadline }}: {{ formatDateTimeHuman(deadlineFor(selectedMeeting)) }}
            </div>
            <span v-if="meetingState !== 'finalizada'" class="chip" :class="meetingStateClass">{{ deadlineCountdownText }}</span>
          </div>

          <div class="d-flex flex-column align-items-end gap-2">
            <select v-model="selectedMeetingId" class="form-select form-select-sm" @change="loadMeetingData">
              <option value="">{{ t.labels.selectMeeting }}</option>
              <option v-for="meeting in meetings" :key="meeting.id" :value="String(meeting.id)">
                {{ formatDate(meeting.meeting_date) }} · {{ formatDateTimeHuman(meeting.planned_start_at) }}
              </option>
            </select>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
              <span class="status-pill" :class="meetingStateClass">{{ meetingStateLabel }}</span>
              <button
                v-if="meetingState !== 'finalizada'"
                class="btn btn-sm btn-primary"
                type="button"
                :disabled="!canSubmitNewPoint"
                @click="openCreateModal"
              >
                {{ t.actions.newPoint }}
              </button>
            </div>
          </div>
        </div>
      </header>

      <div class="d-lg-none mb-3">
        <div class="btn-group w-100" role="tablist">
          <button
            class="btn btn-outline-secondary btn-sm"
            :class="{ active: activeTab === 'agenda' }"
            type="button"
            @click="activeTab = 'agenda'"
          >
            {{ t.labels.agenda }}
          </button>
          <button
            class="btn btn-outline-secondary btn-sm"
            :class="{ active: activeTab === 'points' }"
            type="button"
            @click="activeTab = 'points'"
          >
            {{ t.labels.myPoints }}
          </button>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-8" v-show="activeTab === 'agenda' || isDesktop">
          <div class="section-card bg-white border rounded p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="section-title m-0">{{ t.labels.agenda }}</h2>
              <div class="d-flex align-items-center gap-2">
                <span v-if="meetingState === 'abierta' && agendaPoints.length === 0" class="text-muted small">
                  {{ t.messages.agendaPending }}
                </span>
                <button
                  v-if="selectedMeeting"
                  class="btn btn-sm btn-outline-secondary btn-icon"
                  type="button"
                  :aria-label="t.actions.refresh"
                  @click="reloadAgenda"
                >
                  ⟳
                </button>
              </div>
            </div>

            <div v-if="agendaPoints.length === 0" class="text-muted small">
              {{ t.messages.noAgenda }}
            </div>

            <ul v-else class="agenda-list">
              <li
                v-for="point in agendaPoints"
                :key="point.id"
                class="agenda-row"
                :class="{ active: selectedMeeting?.active_meeting_point_id === point.id }"
                @click="openPointDetails(point)"
              >
                <div class="agenda-order">{{ point.agenda_order }}</div>
                <div class="agenda-body">
                  <div class="agenda-title">{{ point.title }}</div>
                  <div class="agenda-meta">
                    {{ point.department?.name || '—' }}
                  </div>
                  <div v-if="point.final_note || point.review_note" class="agenda-note">
                    {{ point.final_note || point.review_note }}
                  </div>
                </div>
                <div class="agenda-status">
                  <span v-if="selectedMeeting?.active_meeting_point_id === point.id" class="badge bg-success">
                    {{ t.labels.activeBadge }}
                  </span>
                  <span class="badge bg-light text-dark">
                    {{ pointStatusLabel(point) }}
                  </span>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <div class="col-12 col-lg-4" v-show="activeTab === 'points' || isDesktop">
          <div class="section-card bg-white border rounded p-3 mb-3 meeting-info-card">
            <h2 class="section-title m-0">{{ t.labels.meetingInfo }}</h2>
            <div class="small text-muted mt-2">
              {{ t.labels.location }}: {{ selectedMeeting?.location || '—' }}
            </div>
            <div class="small text-muted">
              {{ t.labels.scheduled }}: {{ formatDateTimeHuman(selectedMeeting?.planned_start_at) }}
            </div>
            <div class="small text-muted">
              {{ t.labels.deadline }}: {{ formatDateTimeHuman(deadlineFor(selectedMeeting)) }}
            </div>
            <div class="small text-muted">
              {{ t.labels.status }}: {{ meetingStateLabel }}
            </div>
          </div>

          <div class="section-card bg-white border rounded p-3 mb-3 my-points-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="section-title m-0">{{ t.labels.myPoints }}</h2>
              <div class="filters">
                <button
                  v-for="filter in pointFilters"
                  :key="filter.value"
                  class="chip"
                  :class="{ active: pointFilter === filter.value }"
                  type="button"
                  @click="pointFilter = filter.value"
                >
                  {{ filter.label }}
                </button>
              </div>
            </div>

            <div v-if="filteredPoints.length === 0" class="text-muted small">
              {{ t.messages.noPoints }}
            </div>

            <ul v-else class="points-list">
              <li v-for="point in filteredPoints" :key="point.id" class="points-row">
                <div>
                  <div class="points-title">{{ point.title }}</div>
                  <div class="points-meta">
                    {{ formatDateTimeHuman(point.updated_at) }}
                  </div>
                  <div v-if="point.review_note" class="points-note">{{ point.review_note }}</div>
                </div>
                <div class="points-actions">
                  <span class="badge bg-light text-dark">{{ pointStatusLabel(point) }}</span>
                  <button class="btn btn-sm btn-outline-secondary" type="button" @click="openPointDetails(point)">
                    {{ t.actions.view }}
                  </button>
                  <button
                    v-if="canEditPoint(point)"
                    class="btn btn-sm btn-outline-primary"
                    type="button"
                    @click="openEditModal(point)"
                  >
                    {{ t.actions.edit }}
                  </button>
                </div>
              </li>
            </ul>
          </div>

          <div
            v-if="selectedMeeting && meetingState === 'finalizada'"
            class="section-card bg-white border rounded p-3 summary-card"
          >
            <h2 class="section-title m-0">{{ t.labels.summary }}</h2>
            <p v-if="selectedMeeting.summary_public" class="small mt-2">
              {{ summaryText || t.messages.summaryFallback }}
            </p>
            <p v-else class="small text-muted mt-2">
              {{ t.messages.summaryLocked }}
            </p>
            <button
              v-if="selectedMeeting.summary_public"
              class="btn btn-sm btn-outline-primary"
              type="button"
              @click="downloadSummary"
            >
              {{ t.actions.downloadPdf }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="pointDrawerOpen && detailPoint" class="modal-backdrop" @click.self="closePointDetails">
      <div class="drawer-panel">
        <header class="modal-header">
          <h2 class="h6 m-0">{{ t.labels.pointDetails }}</h2>
          <button type="button" class="modal-close" @click="closePointDetails">×</button>
        </header>
        <div class="modal-body">
          <h3 class="h6 mb-1">{{ detailPoint.title }}</h3>
          <div class="text-muted small mb-2">{{ detailPoint.department?.name || '—' }}</div>
          <p class="small mb-2">{{ detailPoint.description }}</p>
          <div class="d-flex gap-2 mb-2">
            <span class="badge bg-light text-dark">{{ pointStatusLabel(detailPoint) }}</span>
            <span v-if="detailPoint.final_status" class="badge bg-secondary">{{ formatFinal(detailPoint.final_status) }}</span>
          </div>
          <div v-if="detailPoint.review_note" class="small mb-2">
            <strong>{{ t.labels.reviewNote }}:</strong> {{ detailPoint.review_note }}
          </div>
          <div v-if="detailPoint.final_note" class="small mb-2">
            <strong>{{ t.labels.pointNote }}:</strong> {{ detailPoint.final_note }}
            <span v-if="detailPoint.finalized_at">· {{ formatDateTimeHuman(detailPoint.finalized_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="pointModalOpen" class="modal-backdrop" @click.self="closePointModal">
      <div class="modal-panel">
        <header class="modal-header">
          <h2 class="h6 m-0">{{ isEditing ? t.labels.editPoint : t.labels.newPoint }}</h2>
          <button type="button" class="modal-close" @click="closePointModal">×</button>
        </header>
        <div class="modal-body">
          <label class="form-label small">
            {{ t.fields.title }}
            <input v-model="form.title" class="form-control form-control-sm" type="text" required />
          </label>
          <label class="form-label small">
            {{ t.fields.description }}
            <textarea v-model="form.description" class="form-control form-control-sm" rows="3" required></textarea>
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="closePointModal">
            {{ common.cancel }}
          </button>
          <button type="button" class="btn btn-primary btn-sm" @click="submitPoint">
            {{ isEditing ? t.actions.submitUpdate : t.actions.submit }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useUiStore } from '../stores/uiStore'
import { storeToRefs } from 'pinia'
import { meetingApi } from '../services/meetingApi'
import { translations } from '../i18n/translations'

const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)

const t = computed(() => translations[locale.value].meetingPoints)
const common = computed(() => translations[locale.value].common)

const meetings = ref([])
const points = ref([])
const agendaPoints = ref([])
const selectedMeetingId = ref('')
const selectedMeeting = ref(null)
const loading = ref(false)
const error = ref('')
const summaryText = ref('')
const activeTab = ref('agenda')

const pointModalOpen = ref(false)
const isEditing = ref(false)
const form = ref({ title: '', description: '' })
const editingPointId = ref(null)

const pointDrawerOpen = ref(false)
const detailPoint = ref(null)

const pointFilter = ref('all')

const isDesktop = ref(window.innerWidth >= 992)

const meetingState = computed(() => {
  if (!selectedMeeting.value) return 'abierta'
  if (selectedMeeting.value.end_at) return 'finalizada'
  if (selectedMeeting.value.start_at) return 'en_vivo'
  return 'abierta'
})

const meetingStateLabel = computed(() => t.value.meetingStates[meetingState.value])

const meetingStateClass = computed(() => {
  if (meetingState.value === 'finalizada') return 'chip--neutral'
  if (meetingState.value === 'en_vivo') return 'chip--live'
  return 'chip--open'
})

const deadlineCountdownText = computed(() => {
  if (!selectedMeeting.value) return ''
  if (meetingState.value === 'finalizada') return t.value.countdown.finalized
  if (meetingState.value === 'en_vivo') return t.value.countdown.live
  const deadline = deadlineFor(selectedMeeting.value)
  const now = new Date()
  const diffMs = deadline - now
  if (diffMs <= 0) return t.value.countdown.closed
  const diffHours = Math.ceil(diffMs / (1000 * 60 * 60))
  if (diffHours < 48) return t.value.countdown.hours.replace('{count}', diffHours)
  const diffDays = Math.ceil(diffHours / 24)
  return t.value.countdown.days.replace('{count}', diffDays)
})

const canSubmitNewPoint = computed(() => {
  if (!selectedMeeting.value) return false
  if (meetingState.value !== 'abierta') return false
  if (selectedMeeting.value.agenda_closed_at) return false
  const deadline = deadlineFor(selectedMeeting.value)
  return new Date() <= deadline
})

const pointFilters = computed(() => [
  { value: 'all', label: t.value.filters.all },
  { value: 'submitted', label: t.value.filters.pending },
  { value: 'accepted', label: t.value.filters.accepted },
  { value: 'rejected', label: t.value.filters.rejected },
  { value: 'needs_update', label: t.value.filters.needsUpdate },
])

const filteredPoints = computed(() => {
  if (pointFilter.value === 'all') return points.value
  return points.value.filter((point) => point.status === pointFilter.value)
})

const reloadAgenda = async () => {
  if (!selectedMeeting.value) return
  agendaPoints.value = await meetingApi.listMeetingPoints(selectedMeeting.value.id, { scope: 'agenda' })
  selectedMeeting.value = await meetingApi.getMeeting(selectedMeeting.value.id)
}

const loadMeetings = async () => {
  loading.value = true
  error.value = ''
  try {
    meetings.value = await meetingApi.listMeetings()
    if (!selectedMeetingId.value) {
      selectedMeetingId.value = ''
      selectedMeeting.value = null
    }
  } catch (err) {
    error.value = err?.response?.data?.message || t.value.messages.loadError
  } finally {
    loading.value = false
  }
}

const loadMeetingData = async () => {
  if (!selectedMeetingId.value) {
    selectedMeeting.value = null
    summaryText.value = ''
    points.value = []
    agendaPoints.value = []
    return
  }
  selectedMeeting.value = meetings.value.find((meeting) => String(meeting.id) === selectedMeetingId.value) || null
  if (!selectedMeeting.value) return
  summaryText.value = selectedMeeting.value.summary_text || selectedMeeting.value.summary_generated || ''
  points.value = await meetingApi.listMeetingPoints(selectedMeeting.value.id)
  agendaPoints.value = await meetingApi.listMeetingPoints(selectedMeeting.value.id, { scope: 'agenda' })
}

const deadlineFor = (meeting) => {
  if (!meeting?.planned_start_at) return null
  const date = new Date(meeting.planned_start_at)
  date.setHours(date.getHours() - 24)
  return date
}

const formatDate = (value) => {
  if (!value) return '—'
  return new Date(value).toLocaleDateString(locale.value)
}

const formatDateTimeHuman = (value) => {
  if (!value) return '—'
  return new Date(value).toLocaleString(locale.value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  })
}

const pointStatusLabel = (point) => {
  if (point.final_status) return formatFinal(point.final_status)
  return formatStatus(point.status)
}

const formatStatus = (status) => t.value.statuses[status] || status
const formatFinal = (status) => t.value.finalStatuses[status] || status

const canEditPoint = (point) => {
  if (meetingState.value !== 'abierta') return false
  return point.status === 'needs_update'
}

const openCreateModal = () => {
  isEditing.value = false
  editingPointId.value = null
  form.value = { title: '', description: '' }
  pointModalOpen.value = true
}

const openEditModal = (point) => {
  isEditing.value = true
  editingPointId.value = point.id
  form.value = { title: point.title, description: point.description }
  pointModalOpen.value = true
}

const closePointModal = () => {
  pointModalOpen.value = false
}

const submitPoint = async () => {
  if (!selectedMeeting.value) return
  try {
    if (isEditing.value && editingPointId.value) {
      await meetingApi.updateMeetingPoint(editingPointId.value, form.value)
    } else {
      await meetingApi.createMeetingPoint(selectedMeeting.value.id, form.value)
    }
    closePointModal()
    await loadMeetingData()
  } catch (err) {
    error.value = err?.response?.data?.message || t.value.messages.createError
  }
}

const openPointDetails = (point) => {
  detailPoint.value = point
  pointDrawerOpen.value = true
}

const closePointDetails = () => {
  pointDrawerOpen.value = false
  detailPoint.value = null
}

const downloadSummary = async () => {
  if (!selectedMeeting.value) return
  const blob = await meetingApi.downloadMeetingSummary(selectedMeeting.value.id, locale.value)
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `meeting-summary-${selectedMeeting.value.id}.pdf`
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

onMounted(async () => {
  await loadMeetings()
  window.addEventListener('resize', () => {
    isDesktop.value = window.innerWidth >= 992
    if (isDesktop.value) {
      activeTab.value = 'agenda'
    }
  })
})
</script>

<style scoped>
.meeting-points {
  font-size: 14px;
}

.meeting-header {
  position: sticky;
  top: 0.5rem;
  z-index: 10;
}

.meeting-header-grid {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 12px;
  align-items: center;
}

.meeting-title {
  font-weight: 600;
  font-size: 16px;
}

.section-title {
  font-size: 16px;
  font-weight: 600;
}

.section-card {
  border-radius: 10px;
}

.meeting-info-card {
  border-color: #b6c2d1;
  background: #f8fbff;
}

.my-points-card {
  border-color: #9bb7da;
  background: #f0f6ff;
}

.summary-card {
  border-color: #c7b28a;
  background: #fff8e6;
}

.chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  border: 1px solid #dee2e6;
  background: #f8f9fa;
  color: #0b1f33;
  font-weight: 600;
}

.chip.active {
  background: #e9f2ff;
  border-color: #9ec5fe;
}

.chip--open {
  background: #dff4e8;
  border-color: #2f8f5b;
}

.chip--live {
  background: #ffe1dc;
  border-color: #c2453a;
}

.chip--neutral {
  background: #e6ebf1;
  border-color: #7b8a9a;
}

.status-pill {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  border: 1px solid transparent;
}

.agenda-list,
.points-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.agenda-row {
  display: grid;
  grid-template-columns: 36px 1fr auto;
  gap: 10px;
  align-items: center;
  padding: 10px 8px;
  border-bottom: 1px solid #eef0f2;
  cursor: pointer;
  position: relative;
}

.agenda-row.active {
  background: #e9f7ef;
  border-left: 4px solid #2e7d32;
}

.agenda-order {
  font-weight: 600;
  font-size: 12px;
  color: #6c757d;
}

.agenda-title {
  font-weight: 600;
  font-size: 14px;
}

.agenda-meta {
  font-size: 12px;
  color: #6c757d;
}

.agenda-note {
  font-size: 12px;
  color: #6c757d;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.agenda-status {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-end;
}

.points-row {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid #eef0f2;
}

.points-title {
  font-weight: 600;
  font-size: 14px;
}

.points-meta {
  font-size: 12px;
  color: #6c757d;
}

.points-note {
  font-size: 12px;
  color: #6c757d;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.points-actions {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-end;
}

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.drawer-panel {
  width: min(480px, 92vw);
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  margin-left: auto;
  height: 100%;
  overflow-y: auto;
}

@media (max-width: 991px) {
  .meeting-header-grid {
    grid-template-columns: 1fr;
    text-align: left;
  }

  .drawer-panel {
    width: 100%;
    height: 100%;
    border-radius: 0;
  }
}
</style>
