<template>
  <section class="container py-3 meetings-admin">
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
            <div v-if="selectedMeeting" class="text-muted small mt-1">
              {{ t.labels.status }}: {{ meetingStateLabel }}
            </div>
            <div class="text-muted small mt-1">
              {{ t.labels.deadline }}: {{ formatDateTimeHuman(deadlineFor(selectedMeeting)) }}
            </div>
            <span class="chip" :class="meetingStateClass">{{ deadlineCountdownText }}</span>
          </div>

          <div class="d-flex flex-column align-items-end gap-2">
            <select v-model="selectedMeetingId" class="form-select form-select-sm" @change="loadMeetingData">
              <option value="">{{ t.labels.selectMeeting }}</option>
              <option v-for="meeting in meetings" :key="meeting.id" :value="String(meeting.id)">
                {{ formatDate(meeting.meeting_date) }} · {{ formatDateTimeHuman(meeting.planned_start_at) }} · {{ meetingStatusLabel(meeting) }}
              </option>
            </select>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
              <button class="btn btn-sm btn-outline-secondary" type="button" @click="openCreateMeeting">
                {{ t.actions.create }}
              </button>
              <button
                v-if="meetingState === 'abierta'"
                class="btn btn-sm btn-outline-success"
                type="button"
                @click="startMeeting(selectedMeeting)"
              >
                {{ t.actions.start }}
              </button>
              <button
                v-if="meetingState === 'en_vivo'"
                class="btn btn-sm btn-outline-danger"
                type="button"
                @click="adjournMeeting(selectedMeeting)"
              >
                {{ t.actions.adjourn }}
              </button>
              <button class="btn btn-sm btn-primary" type="button" @click="openSummaryModal">
                {{ t.actions.summary }}
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
            :class="{ active: activeTab === 'controls' }"
            type="button"
            @click="activeTab = 'controls'"
          >
            {{ t.labels.controls }}
          </button>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12 col-lg-8" v-show="activeTab === 'agenda' || isDesktop">
          <div class="section-card bg-white border rounded p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h2 class="section-title m-0">{{ t.labels.agenda }}</h2>
              <button
                v-if="meetingState !== 'finalizada'"
                class="btn btn-sm btn-outline-secondary btn-icon"
                type="button"
                :aria-label="t.actions.refresh"
                @click="reloadPoints"
              >
                ⟳
              </button>
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
                  <div class="agenda-meta">{{ point.department?.name || '—' }}</div>
                  <div v-if="point.review_note || point.final_note" class="agenda-note">
                    {{ point.review_note || point.final_note }}
                  </div>
                </div>
                <div class="agenda-status">
                  <span v-if="selectedMeeting?.active_meeting_point_id === point.id" class="badge bg-success">
                    {{ t.labels.activeBadge }}
                  </span>
                  <span v-if="meetingState !== 'en_vivo'" class="badge bg-light text-dark">
                    {{ pointStatusLabel(point) }}
                  </span>
                  <button
                    v-if="meetingState === 'en_vivo' && selectedMeeting?.active_meeting_point_id !== point.id && !point.final_status"
                    class="btn btn-sm btn-outline-primary btn-icon"
                    type="button"
                    :aria-label="t.actions.setActive"
                    @click.stop="activatePoint(point)"
                  >
                    ▶
                  </button>
                  <div v-if="meetingState === 'abierta'" class="d-flex gap-1">
                    <button
                      class="btn btn-sm btn-outline-secondary btn-icon"
                      type="button"
                      :aria-label="t.actions.moveUp"
                      @click.stop="movePoint(point, -1)"
                    >
                      ▲
                    </button>
                    <button
                      class="btn btn-sm btn-outline-secondary btn-icon"
                      type="button"
                      :aria-label="t.actions.moveDown"
                      @click.stop="movePoint(point, 1)"
                    >
                      ▼
                    </button>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <div class="col-12 col-lg-4" v-show="activeTab === 'controls' || isDesktop">
          <div class="section-card bg-white border rounded p-3 mb-3">
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

          <div v-if="meetingState === 'en_vivo'" class="section-card bg-white border rounded p-3 mb-3">
            <h2 class="section-title m-0">{{ t.labels.openingNotes }}</h2>
            <div class="small text-muted mt-2">
              {{ t.labels.openedBy }}: {{ openerName || '—' }}
            </div>
            <div v-if="showOpeningFields">
              <label class="form-label small mt-2">
                {{ t.labels.openingPrayer }}
                <textarea v-model="openingPrayer" class="form-control form-control-sm" rows="2"></textarea>
              </label>
              <label class="form-label small">
                {{ t.labels.openingRemarks }}
                <textarea v-model="openingRemarks" class="form-control form-control-sm" rows="3"></textarea>
              </label>
              <button class="btn btn-sm btn-outline-primary" type="button" @click="saveOpeningNotes">
                {{ t.actions.saveOpening }}
              </button>
            </div>
            <div v-else class="text-muted small mt-2">
              {{ t.messages.openingSaved }}
            </div>

            <div class="mt-3">
              <label class="form-label small">
                {{ t.labels.generalNote }}
                <textarea v-model="generalNote" class="form-control form-control-sm" rows="2"></textarea>
              </label>
              <button class="btn btn-sm btn-outline-secondary" type="button" @click="saveGeneralNote">
                {{ t.actions.saveGeneralNote }}
              </button>
            </div>
            <div v-if="meetingNotes.length" class="small mt-2">
              <strong>{{ t.labels.generalNotes }}</strong>
              <ul class="ps-3 mt-1">
                <li v-for="note in meetingNotes" :key="note.id">
                  {{ formatDateTimeHuman(note.created_at) }} · {{ note.note }}
                </li>
              </ul>
            </div>
          </div>
          <div class="section-card bg-white border rounded p-3 mb-3">
            <h2 class="section-title m-0">{{ t.labels.controls }}</h2>
            <div class="d-flex flex-column gap-2 mt-2">
              <button
                v-if="meetingState === 'abierta'"
                class="btn btn-sm btn-outline-primary"
                type="button"
                :disabled="Boolean(selectedMeeting?.agenda_closed_at)"
                @click="closeAgenda(selectedMeeting)"
              >
                {{ t.actions.closeAgenda }}
              </button>
              <button
                v-if="meetingState === 'abierta'"
                class="btn btn-sm btn-outline-success"
                type="button"
                @click="startMeeting(selectedMeeting)"
              >
                {{ t.actions.start }}
              </button>
              <button
                v-if="meetingState === 'en_vivo'"
                class="btn btn-sm btn-outline-danger"
                type="button"
                @click="adjournMeeting(selectedMeeting)"
              >
                {{ t.actions.adjourn }}
              </button>
              <button class="btn btn-sm btn-outline-secondary" type="button" @click="openSummaryModal">
                {{ t.actions.editSummary }}
              </button>
              <label v-if="meetingState === 'finalizada'" class="form-check-label small">
                <input v-model="summaryPublic" class="form-check-input me-2" type="checkbox" @change="persistSummary" />
                {{ t.actions.publishSummary }}
              </label>
              <button
                v-if="meetingState === 'finalizada'"
                class="btn btn-sm btn-outline-primary"
                type="button"
                @click="downloadSummary"
              >
                {{ t.actions.downloadPdf }}
              </button>
            </div>
          </div>

          <div v-if="meetingState === 'finalizada'" class="section-card bg-white border rounded p-3">
            <h2 class="section-title m-0">{{ t.labels.summary }}</h2>
            <p class="small mt-2">{{ summaryText || t.messages.summaryFallback }}</p>
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

          <div v-if="meetingState === 'abierta'" class="mt-3">
            <h4 class="small fw-semibold">{{ t.labels.review }}</h4>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-success" type="button" @click="setReviewStatus('accepted')">
                {{ t.actions.accept }}
              </button>
              <button class="btn btn-sm btn-outline-warning" type="button" @click="setReviewStatus('needs_update')">
                {{ t.actions.needsUpdate }}
              </button>
              <button class="btn btn-sm btn-outline-danger" type="button" @click="setReviewStatus('rejected')">
                {{ t.actions.reject }}
              </button>
            </div>
            <label class="form-label small mt-2">
              {{ t.labels.reviewNote }}
              <textarea v-model="reviewNote" class="form-control form-control-sm" rows="2"></textarea>
            </label>
            <button class="btn btn-sm btn-outline-primary" type="button" @click="submitReview">
              {{ t.actions.saveReview }}
            </button>
          </div>

          <div v-if="meetingState === 'en_vivo'" class="mt-3">
            <h4 class="small fw-semibold">{{ t.labels.liveControls }}</h4>
            <div class="d-flex flex-wrap gap-2">
            </div>
            <label class="form-label small mt-2">
              {{ t.labels.finalStatus }}
              <select v-model="finalForm.final_status" class="form-select form-select-sm">
                <option value="approved">{{ t.labels.approved }}</option>
                <option value="denied">{{ t.labels.denied }}</option>
                <option value="informative">{{ t.labels.informative }}</option>
                <option value="postponed">{{ t.labels.postponed }}</option>
                <option value="needs_update">{{ t.labels.needsUpdate }}</option>
              </select>
            </label>
            <label class="form-label small">
              {{ t.labels.pointNote }}
              <input v-model="finalForm.final_note" class="form-control form-control-sm" type="text" />
            </label>
            <button class="btn btn-sm btn-outline-dark" type="button" @click="closePoint(detailPoint)">
              {{ t.actions.closePoint }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="createMeetingOpen" class="modal-backdrop" @click.self="closeCreateMeeting">
      <div class="modal-panel">
        <header class="modal-header">
          <h2 class="h6 m-0">{{ t.actions.create }}</h2>
          <button type="button" class="modal-close" @click="closeCreateMeeting">×</button>
        </header>
        <div class="modal-body">
          <div v-if="isSuperAdmin" class="mb-2">
            <label class="form-label small">
              {{ t.fields.church }}
              <select v-model="createForm.church_id" class="form-select form-select-sm" required>
                <option value="">{{ common.select }}</option>
                <option v-for="church in churches" :key="church.id" :value="String(church.id)">
                  {{ church.name }}
                </option>
              </select>
            </label>
          </div>
          <label class="form-label small">
            {{ t.fields.meetingDate }}
            <input v-model="createForm.meeting_date" class="form-control form-control-sm" type="date" required />
          </label>
          <label class="form-label small">
            {{ t.fields.plannedStart }}
            <input v-model="createForm.planned_start_time" class="form-control form-control-sm" type="time" required />
          </label>
          <label class="form-label small">
            {{ t.fields.location }}
            <input v-model="createForm.location" class="form-control form-control-sm" type="text" />
          </label>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="closeCreateMeeting">
            {{ common.cancel }}
          </button>
          <button type="button" class="btn btn-primary btn-sm" @click="createMeeting">
            {{ t.actions.create }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="summaryModalOpen" class="modal-backdrop" @click.self="closeSummaryModal">
      <div class="modal-panel modal-panel--lg">
        <header class="modal-header">
          <h2 class="h6 m-0">{{ t.labels.summary }}</h2>
          <button type="button" class="modal-close" @click="closeSummaryModal">×</button>
        </header>
        <div class="modal-body">
          <label class="form-label small">
            {{ t.fields.summaryText }}
            <textarea v-model="summaryText" class="form-control form-control-sm" rows="4"></textarea>
          </label>
          <div class="form-check mt-2">
            <input v-model="summaryPublic" class="form-check-input" type="checkbox" id="summary-public" />
            <label class="form-check-label small" for="summary-public">
              {{ t.actions.publishSummary }}
            </label>
          </div>
          <div v-if="selectedMeeting?.summary_generated" class="mt-3">
            <label class="form-label small">{{ t.fields.summaryGenerated }}</label>
            <pre class="p-2 bg-light border rounded small">{{ selectedMeeting.summary_generated }}</pre>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-sm" @click="closeSummaryModal">
            {{ common.cancel }}
          </button>
          <button type="button" class="btn btn-primary btn-sm" @click="saveSummaryAndClose">
            {{ t.actions.saveSummary }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '../../stores/authStore'
import { useUiStore } from '../../stores/uiStore'
import { storeToRefs } from 'pinia'
import { meetingApi } from '../../services/meetingApi'
import { superAdminApi } from '../../services/superAdminApi'
import { translations } from '../../i18n/translations'

const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)

const t = computed(() => translations[locale.value].meetings)
const common = computed(() => translations[locale.value].common)

const isSuperAdmin = computed(() => authStore.user?.role === 'superadmin')

const meetings = ref([])
const agendaPoints = ref([])
const churches = ref([])
const selectedMeeting = ref(null)
const selectedMeetingId = ref('')
const loading = ref(false)
const error = ref('')

const createMeetingOpen = ref(false)
const summaryModalOpen = ref(false)
const activeTab = ref('agenda')

const summaryText = ref('')
const summaryPublic = ref(false)
const reviewNote = ref('')
const reviewStatus = ref('submitted')
const finalForm = ref({ final_status: 'approved', final_note: '' })
const openingPrayer = ref('')
const openingRemarks = ref('')
const generalNote = ref('')
const meetingNotes = ref([])

const pointDrawerOpen = ref(false)
const detailPoint = ref(null)

const createForm = ref({
  church_id: '',
  meeting_date: '',
  planned_start_time: '',
  location: '',
})

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

const loadMeetings = async () => {
  loading.value = true
  error.value = ''
  try {
    const params = {}
    meetings.value = await meetingApi.listMeetings(params)
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
    summaryPublic.value = false
    openingPrayer.value = ''
    openingRemarks.value = ''
    agendaPoints.value = []
    return
  }
  selectedMeeting.value = meetings.value.find((meeting) => String(meeting.id) === selectedMeetingId.value) || null
  if (!selectedMeeting.value) return
  summaryText.value = selectedMeeting.value.summary_text || ''
  summaryPublic.value = Boolean(selectedMeeting.value.summary_public)
  openingPrayer.value = selectedMeeting.value.opening_prayer || ''
  openingRemarks.value = selectedMeeting.value.opening_remarks || ''
  meetingNotes.value = (selectedMeeting.value.meeting_notes || []).slice().sort((a, b) => {
    return new Date(a.created_at) - new Date(b.created_at)
  })
  await reloadPoints()
}

const reloadPoints = async () => {
  if (!selectedMeeting.value) return
  agendaPoints.value = await meetingApi.listMeetingPoints(selectedMeeting.value.id)
}

const loadChurches = async () => {
  if (!isSuperAdmin.value) return
  const response = await superAdminApi.listChurches()
  churches.value = response.data || []
}

const openCreateMeeting = () => {
  createMeetingOpen.value = true
}

const closeCreateMeeting = () => {
  createMeetingOpen.value = false
}

const createMeeting = async () => {
  error.value = ''
  const payload = {
    meeting_date: createForm.value.meeting_date,
    planned_start_at: combineDateTime(createForm.value.meeting_date, createForm.value.planned_start_time),
    location: createForm.value.location || null,
  }
  if (isSuperAdmin.value) {
    payload.church_id = createForm.value.church_id
  }
  try {
    await meetingApi.createMeeting(payload)
    createForm.value = { church_id: '', meeting_date: '', planned_start_time: '', location: '' }
    closeCreateMeeting()
    await loadMeetings()
  } catch (err) {
    error.value = err?.response?.data?.message || t.value.messages.createError
  }
}

const openSummaryModal = () => {
  summaryModalOpen.value = true
}

const closeSummaryModal = () => {
  summaryModalOpen.value = false
}

const persistSummary = async () => {
  if (!selectedMeeting.value) return
  await meetingApi.updateMeeting(selectedMeeting.value.id, {
    summary_text: summaryText.value || null,
    summary_public: summaryPublic.value,
  })
  selectedMeeting.value = await meetingApi.getMeeting(selectedMeeting.value.id)
}

const saveSummaryAndClose = async () => {
  await persistSummary()
  closeSummaryModal()
}

const saveOpeningNotes = async () => {
  if (!selectedMeeting.value) return
  try {
    await meetingApi.updateMeeting(selectedMeeting.value.id, {
      opening_prayer: openingPrayer.value || null,
      opening_remarks: openingRemarks.value || null,
    })
    selectedMeeting.value = await meetingApi.getMeeting(selectedMeeting.value.id)
    uiStore.showToast(t.value.messages.openingSaved, 'success')
  } catch {
    uiStore.showToast(t.value.messages.openingSaveFailed, 'error')
  }
}

const saveGeneralNote = async () => {
  if (!selectedMeeting.value || !generalNote.value.trim()) return
  try {
    await meetingApi.addMeetingNote(selectedMeeting.value.id, { note: generalNote.value.trim() })
    generalNote.value = ''
    selectedMeeting.value = await meetingApi.getMeeting(selectedMeeting.value.id)
    meetingNotes.value = (selectedMeeting.value.meeting_notes || []).slice().sort((a, b) => {
      return new Date(a.created_at) - new Date(b.created_at)
    })
    uiStore.showToast(t.value.messages.generalNoteSaved, 'success')
  } catch {
    uiStore.showToast(t.value.messages.generalNoteSaveFailed, 'error')
  }
}

const closeAgenda = async (meeting) => {
  await meetingApi.closeAgenda(meeting.id)
  await loadMeetings()
  await loadMeetingData()
}

const startMeeting = async (meeting) => {
  await meetingApi.startMeeting(meeting.id)
  await loadMeetings()
  await loadMeetingData()
}

const adjournMeeting = async (meeting) => {
  await meetingApi.adjournMeeting(meeting.id)
  await loadMeetings()
  await loadMeetingData()
}

const setReviewStatus = (status) => {
  reviewStatus.value = status
}

const submitReview = async () => {
  if (!detailPoint.value) return
  await meetingApi.reviewMeetingPoint(detailPoint.value.id, {
    status: reviewStatus.value,
    review_note: reviewNote.value || null,
  })
  reviewNote.value = ''
  await reloadPoints()
}

const activatePoint = async (point) => {
  await meetingApi.activateMeetingPoint(point.id)
  selectedMeeting.value = await meetingApi.getMeeting(point.meeting_id)
  uiStore.showToast(t.value.messages.activeSet, 'success')
}

const closePoint = async (point) => {
  await meetingApi.finalizeMeetingPoint(point.id, finalForm.value)
  if (selectedMeeting.value) {
    selectedMeeting.value = await meetingApi.getMeeting(selectedMeeting.value.id)
  }
  await reloadPoints()
  closePointDetails()
}


const movePoint = async (point, direction) => {
  const index = agendaPoints.value.findIndex((item) => item.id === point.id)
  const nextIndex = index + direction
  if (index < 0 || nextIndex < 0 || nextIndex >= agendaPoints.value.length) return
  const list = [...agendaPoints.value]
  const [moved] = list.splice(index, 1)
  list.splice(nextIndex, 0, moved)
  agendaPoints.value = list
  const ids = agendaPoints.value.map((item) => item.id)
  await meetingApi.reorderMeetingPoints(selectedMeeting.value.id, ids)
}

const openPointDetails = (point) => {
  detailPoint.value = point
  finalForm.value = {
    final_status: point.final_status || 'approved',
    final_note: point.final_note || '',
  }
  reviewNote.value = point.review_note || ''
  reviewStatus.value = point.status || 'submitted'
  pointDrawerOpen.value = true
}

const closePointDetails = () => {
  pointDrawerOpen.value = false
  detailPoint.value = null
  reviewNote.value = ''
  reviewStatus.value = 'submitted'
}

const pointStatusLabel = (point) => {
  if (point.final_status) return formatFinal(point.final_status)
  return formatStatus(point.status)
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

const combineDateTime = (dateValue, timeValue) => {
  if (!dateValue || !timeValue) return ''
  return `${dateValue}T${timeValue}`
}

const formatStatus = (status) => t.value.statuses[status] || status
const formatFinal = (status) => t.value.finalStatuses[status] || status

const meetingStatusLabel = (meeting) => {
  if (!meeting) return ''
  if (meeting.end_at) return t.value.meetingStates.finalizada
  if (meeting.start_at) return t.value.meetingStates.en_vivo
  return t.value.meetingStates.abierta
}

const openerName = computed(() => selectedMeeting.value?.opener?.name)
const showOpeningFields = computed(() => !(openingPrayer.value || openingRemarks.value))

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
  await loadChurches()
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
.meetings-admin {
  font-size: 14px;
}

.meeting-header {
  position: sticky;
  top: 0.5rem;
  z-index: 10;
}

.meeting-header-grid {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 16px;
  align-items: start;
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

.chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
  border: 1px solid #dee2e6;
  background: #f8f9fa;
}

.chip--open {
  background: #e9f7ef;
  border-color: #a3e4c1;
}

.chip--live {
  background: #ffe8e6;
  border-color: #f5b5b0;
}

.chip--neutral {
  background: #f1f3f5;
  border-color: #d0d4d9;
}

.status-pill {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  border: 1px solid transparent;
}

.agenda-list {
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

.btn-icon {
  width: 32px;
  height: 32px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.drawer-panel {
  width: min(520px, 92vw);
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
