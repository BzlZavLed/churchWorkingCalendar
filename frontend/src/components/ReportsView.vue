<template>
  <section class="reports-page">
    <header class="reports-header">
      <div>
        <p class="reports-eyebrow">{{ authStore.user?.church?.name || (locale === 'es' ? 'Administración de iglesia' : 'Church administration') }}</p>
        <h1>{{ t.title }}</h1>
        <p class="reports-intro">{{ locale === 'es' ? 'Consulta el avance de los eventos, sus objetivos y las conversaciones de tu equipo.' : 'Review event progress, objectives, and your team’s conversations.' }}</p>
      </div>
      <div class="reports-scope">
        <span>{{ locale === 'es' ? 'Eventos del año' : 'Event year' }}</span>
        <strong>{{ reportYear }}</strong>
        <span>{{ canChooseDepartment ? (locale === 'es' ? 'Todos los departamentos disponibles' : 'All available departments') : authStore.user?.department?.name }}</span>
      </div>
    </header>

    <div class="reports-tabs" :aria-label="locale === 'es' ? 'Tipo de reporte' : 'Report type'">
      <button
        v-if="!showNotesTab"
        type="button"
        class="report-tab" :aria-pressed="activeTab === 'objectives'"
        :class="{ active: activeTab === 'objectives' }"
        @click="activeTab = 'objectives'"
      >
        {{ t.tabs.objectives }}
      </button>
      <button
        v-if="showNotesTab"
        type="button"
        class="report-tab" :aria-pressed="activeTab === 'notes'"
        :class="{ active: activeTab === 'notes' }"
        @click="activeTab = 'notes'"
      >
        {{ t.tabs.notes }}
      </button>
      <button
        type="button"
        class="report-tab" :aria-pressed="activeTab === 'events'"
        :class="{ active: activeTab === 'events' }"
        @click="activeTab = 'events'"
      >
        {{ t.tabs.events }}
      </button>
    </div>

    <div class="report-summary" aria-live="polite">
      <div v-for="stat in reportStats" :key="stat.label" class="report-stat">
        <span>{{ stat.label }}</span><strong>{{ reportLoading ? '…' : reportReady ? stat.value : '—' }}</strong>
      </div>
    </div>

    <div v-if="activeTab === 'objectives' && !showNotesTab" class="report-surface">
      <div class="report-filters row g-3">
        <div class="col-12 col-md-6">
          <label v-if="canChooseDepartment" class="form-label">
            {{ t.labels.department }}
            <select v-model="objectiveDepartmentFilter" class="form-select">
              <option value="all">{{ t.labels.all }}</option>
              <option v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                {{ dept.name }}
              </option>
            </select>
          </label>
          <p v-else class="form-label mb-0">
            {{ t.labels.department }}: {{ authStore.user?.department?.name || '—' }}
          </p>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            {{ t.labels.objective }}
            <select v-model="objectiveFilter" class="form-select">
              <option value="all">{{ t.labels.all }}</option>
              <option v-for="objective in objectiveOptions" :key="objective.id" :value="String(objective.id)">
                {{ objective.name }}
              </option>
            </select>
          </label>
          <label class="export-toggle">
            <input v-model="includeObjectiveEvents" type="checkbox" />
            <span>{{ t.labels.includeEvents }}</span>
          </label>
        </div>
        <div class="report-toolbar col-12 d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary w-auto" :disabled="objectivesLoading" @click="searchObjectives">
            {{ t.labels.search }}
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary w-auto"
            :disabled="sortedObjectives.length === 0"
            @click="exportObjectives"
          >
            {{ t.labels.exportCsv }}
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary w-auto"
            :disabled="sortedObjectives.length === 0"
            @click="exportObjectivesPdf"
          >
            {{ t.labels.exportPdf }}
          </button>
        </div>
      </div>

      <div v-if="objectivesLoading" class="report-empty" role="status">{{ t.messages.loadingObjectives }}</div>
      <p v-if="objectivesError" class="report-error" role="alert">{{ objectivesError }}</p>

      <div v-if="!objectivesLoaded && !objectivesLoading" class="report-empty">{{ t.messages.searchToLoad }}</div>
      <div v-else-if="filteredObjectives.length === 0 && !objectivesLoading" class="report-empty">{{ t.messages.noObjectives }}</div>
      <div v-else class="table-responsive">
        <table class="table mb-0 main-table" data-dt="off">
          <thead>
            <tr>
              <th>{{ t.columns.id }}</th>
              <th>
                <button type="button" class="table-sort" @click="setObjectiveSort('name')">
                  {{ t.columns.name }}
                  <span class="sort-indicator">{{ sortIndicator(objectiveSort, 'name') }}</span>
                </button>
              </th>
              <th>
                <button type="button" class="table-sort" @click="setObjectiveSort('department')">
                  {{ t.columns.department }}
                  <span class="sort-indicator">{{ sortIndicator(objectiveSort, 'department') }}</span>
                </button>
              </th>
              <th>
                <button type="button" class="table-sort" @click="setObjectiveSort('description')">
                  {{ t.columns.description }}
                  <span class="sort-indicator">{{ sortIndicator(objectiveSort, 'description') }}</span>
                </button>
              </th>
              <th>
                <button type="button" class="table-sort" @click="setObjectiveSort('metrics')">
                  {{ t.columns.metrics }}
                  <span class="sort-indicator">{{ sortIndicator(objectiveSort, 'metrics') }}</span>
                </button>
              </th>
              <th v-if="includeObjectiveEvents">{{ t.columns.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="objective in sortedObjectives" :key="objective.id">
              <tr>
                <td>{{ objective.id }}</td>
                <td>{{ objective.name }}</td>
                <td class="dept-cell">
                  <span
                    class="dept-swatch"
                    :style="{ backgroundColor: objective.department?.color || '#ccc' }"
                  ></span>
                  {{ objective.department?.name || '—' }}
                </td>
                <td>{{ objective.description }}</td>
                <td>{{ objective.evaluation_metrics }}</td>
                <td v-if="includeObjectiveEvents">
                  <button type="button" class="table-toggle" @click="toggleObjectiveEvents(objective.id)">
                    {{ isObjectiveExpanded(objective.id) ? t.labels.hide : t.labels.show }}
                  </button>
                </td>
              </tr>
              <tr v-if="isObjectiveExpanded(objective.id)" class="child-row">
                <td colspan="6">
                  <div class="child-title">{{ t.labels.events }}</div>
                  <table class="table table-sm mb-0 child-table" data-dt="off">
                    <thead>
                      <tr>
                        <th>{{ t.columns.date }}</th>
                        <th>{{ t.columns.time }}</th>
                        <th>{{ t.columns.place }}</th>
                        <th>{{ t.columns.event }}</th>
                        <th>{{ t.columns.status }}</th>
                        <th>{{ t.columns.acceptedAt }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="event in eventsByObjective.get(objective.id) || []" :key="event.id" class="child-row">
                        <td>{{ formatDate(event.start_at) }}</td>
                        <td>{{ formatTime(event) }}</td>
                        <td>{{ event.location || '—' }}</td>
                        <td><button type="button" class="report-event-link" @click="openNoteEvent(event)">{{ event.title }}</button></td>
                        <td>{{ statusLabel(event.review_status) }}</td>
                        <td>{{ formatDate(event.accepted_at) }}</td>
                      </tr>
                      <tr v-if="(eventsByObjective.get(objective.id) || []).length === 0" class="child-row">
                        <td colspan="6" class="text-muted">{{ t.labels.noEvents }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else-if="activeTab === 'notes' && showNotesTab" class="report-surface">
      <div class="notes-report-heading">
        <div><h2>{{ t.tabs.notes }}</h2><p>{{ locale === 'es' ? 'Una conversación por evento. Abre el historial para consultar notas anteriores.' : 'One conversation per event. Expand the history to read earlier notes.' }}</p></div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-secondary w-auto" :disabled="notesLoading" @click="fetchNotes">{{ locale === 'es' ? 'Actualizar' : 'Refresh' }}</button>
          <button type="button" class="btn btn-outline-secondary w-auto" :disabled="notesLoading || !!notesError || !notesByEvent.length" @click="exportNotes">{{ t.labels.exportCsv }}</button>
          <button type="button" class="btn btn-outline-secondary w-auto" :disabled="notesLoading || !!notesError || !notesByEvent.length" @click="exportNotesPdf">{{ t.labels.exportPdf }}</button>
        </div>
      </div>
      <div v-if="notesLoading" class="report-empty" role="status">{{ t.messages.loadingNotes }}</div>
      <p v-if="notesError" class="report-error" role="alert">{{ notesError }}</p>
      <div v-if="notesLoaded && !notesLoading && notesByEvent.length === 0" class="report-empty">{{ t.messages.noNotes }}</div>
      <div v-else-if="!notesLoading" class="notes-report-list">
        <article v-for="group in notesByEvent" :key="group.event.id" class="note-report-card">
          <div class="note-report-top">
            <div><h3>{{ group.event.title || '—' }}</h3><p>{{ formatDate(group.event.start_at) }} · {{ formatTime(group.event) }}</p></div>
            <span class="report-status" :class="statusClass(group.event.review_status)">{{ statusLabel(group.event.review_status) }}</span>
          </div>
          <div class="note-preview">
            <span>{{ locale === 'es' ? 'Última nota' : 'Latest note' }} · {{ group.notes[group.notes.length - 1].author?.name || '—' }} · {{ formatDate(group.notes[group.notes.length - 1].created_at) }}</span>
            <p>{{ group.notes[group.notes.length - 1].note }}</p>
          </div>
          <div class="note-report-actions">
            <button type="button" class="btn btn-outline-secondary w-auto" :aria-expanded="isNotesExpanded(group.event.id)" @click="toggleNotesForEvent(group.event.id)">
              {{ isNotesExpanded(group.event.id) ? t.labels.hideNotes : t.labels.viewNotes }} <span class="note-total">{{ group.notes.length }}</span>
            </button>
            <button type="button" class="report-detail-link" @click="openNoteEvent(group.event)">{{ t.labels.viewDetails }} →</button>
          </div>
          <ol v-if="isNotesExpanded(group.event.id)" class="report-conversation">
            <li v-for="note in group.notes" :key="note.id">
              <div><strong>{{ note.author?.name || '—' }}</strong><time :datetime="note.created_at">{{ formatDate(note.created_at) }} · {{ formatNoteTime(note.created_at) }}</time></div>
              <p>{{ note.note }}</p>
            </li>
          </ol>
        </article>
      </div>
    </div>

    <div v-else class="report-surface">
      <div class="report-filters row g-3">
        <div class="col-12 col-md-6">
          <label v-if="canChooseDepartment" class="form-label">
            {{ t.labels.department }}
            <select v-model="eventDepartmentFilter" class="form-select">
              <option value="all">{{ t.labels.all }}</option>
              <option v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                {{ dept.name }}
              </option>
            </select>
          </label>
          <p v-else class="form-label mb-0">
            {{ t.labels.department }}: {{ authStore.user?.department?.name || '—' }}
          </p>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            {{ t.labels.status }}
            <select v-model="eventStatusFilter" class="form-select">
              <option value="all">{{ t.labels.all }}</option>
              <option value="pending">{{ t.status.pending }}</option>
              <option value="resubmitted">{{ locale === 'es' ? 'Reenviado' : 'Resubmitted' }}</option>
              <option value="approved">{{ t.status.approved }}</option>
              <option value="denied">{{ t.status.denied }}</option>
              <option value="changes_requested">{{ t.status.changes }}</option>
            </select>
          </label>
        </div>
        <div class="report-toolbar col-12 d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary w-auto" :disabled="eventsLoading" @click="searchEvents">
            {{ t.labels.search }}
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary w-auto"
            :disabled="sortedEvents.length === 0"
            @click="exportEvents"
          >
            {{ t.labels.exportCsv }}
          </button>
          <button
            type="button"
            class="btn btn-outline-secondary w-auto"
            :disabled="sortedEvents.length === 0"
            @click="exportEventsPdf"
          >
            {{ t.labels.exportPdf }}
          </button>
        </div>
      </div>

      <div v-if="eventsLoading" class="report-empty" role="status">{{ t.messages.loadingEvents }}</div>
      <p v-if="eventsError" class="report-error" role="alert">{{ eventsError }}</p>

      <div v-if="!eventsLoaded && !eventsLoading" class="report-empty">{{ t.messages.searchToLoad }}</div>
      <div v-else-if="events.length === 0 && !eventsLoading" class="report-empty">{{ t.messages.noEvents }}</div>
      <div v-else class="table-responsive">
        <table class="table mb-0 main-table" data-dt="off">
          <thead>
            <tr>
              <th>
                <button type="button" class="table-sort" @click="setEventSort('date')">
                  {{ t.columns.date }}
                  <span class="sort-indicator">{{ sortIndicator(eventSort, 'date') }}</span>
                </button>
              </th>
              <th>{{ t.columns.time }}</th>
              <th>
                <button type="button" class="table-sort" @click="setEventSort('place')">
                  {{ t.columns.place }}
                  <span class="sort-indicator">{{ sortIndicator(eventSort, 'place') }}</span>
                </button>
              </th>
              <th>
                <button type="button" class="table-sort" @click="setEventSort('department')">
                  {{ t.columns.department }}
                  <span class="sort-indicator">{{ sortIndicator(eventSort, 'department') }}</span>
                </button>
              </th>
              <th>
                <button type="button" class="table-sort" @click="setEventSort('event')">
                  {{ t.columns.event }}
                  <span class="sort-indicator">{{ sortIndicator(eventSort, 'event') }}</span>
                </button>
              </th>
              <th>
                <button type="button" class="table-sort" @click="setEventSort('objective')">
                  {{ t.columns.objective }}
                  <span class="sort-indicator">{{ sortIndicator(eventSort, 'objective') }}</span>
                </button>
              </th>
              <th>
                <button type="button" class="table-sort" @click="setEventSort('status')">
                  {{ t.columns.status }}
                  <span class="sort-indicator">{{ sortIndicator(eventSort, 'status') }}</span>
                </button>
              </th>
              <th>{{ t.columns.acceptedAt }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in sortedEvents" :key="event.id">
              <td>{{ formatDate(event.start_at) }}</td>
              <td>{{ formatTime(event) }}</td>
              <td>{{ event.location || '—' }}</td>
              <td class="dept-cell">
                <span
                  class="dept-swatch"
                  :style="{ backgroundColor: event.department?.color || '#ccc' }"
                ></span>
                {{ event.department?.name || '—' }}
              </td>
              <td><button type="button" class="report-event-link" @click="openNoteEvent(event)">{{ event.title }}</button></td>
              <td>{{ event.objective?.name || '—' }}</td>
              <td class="status-cell">
                <span class="report-status" :class="statusClass(event.review_status)">{{ statusLabel(event.review_status) }}</span>
              </td>
              <td>{{ formatDate(event.accepted_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <EventDetailsModal
      :open="isEventDetailsOpen"
      :event="selectedEvent"
      :notes="selectedEvent?.notes || []"
      :histories="selectedEvent?.histories || []"
      :can-reply-notes="canReplyEventNotes"
      :saving="replySaving"
      :error="replyError"
      :labels="t.eventDetails"
      @close="closeEventDetails"
      @reply-note="handleDetailsReply"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'
import { objectiveApi } from '../services/objectiveApi'
import { apiClient } from '../services/authApi'
import { calendarApi } from '../services/calendarApi'
import { publicApi } from '../services/publicApi'
import EventDetailsModal from './EventDetailsModal.vue'
import jsPDF from 'jspdf'
import 'jspdf-autotable'

const authStore = useAuthStore()

const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].reports)
const reportYear = new Date().getFullYear()
const replySaving = ref(false)
const replyError = ref('')


const isMember = computed(() => authStore.user?.role === 'member')
const isAdmin = computed(() => authStore.user?.role === 'admin')
const showNotesTab = computed(() => isMember.value || isAdmin.value)
const activeTab = ref(showNotesTab.value ? 'notes' : 'objectives')
const departments = ref([])
const canChooseDepartment = computed(() =>
  !authStore.user?.department_id || ['superadmin', 'secretary'].includes(authStore.user?.role || '')
)
const departmentId = computed(() => authStore.user?.department_id || null)

const objectives = ref([])
const objectivesLoading = ref(false)
const objectivesError = ref('')
const objectiveDepartmentFilter = ref('all')
const objectiveFilter = ref('all')
const expandedObjectiveIds = ref(new Set())
const objectiveEvents = ref([])
const objectivesLoaded = ref(false)
const eventsLoaded = ref(false)
const objectiveSort = ref({ key: 'name', dir: 'asc' })
const includeObjectiveEvents = ref(false)

const events = ref([])
const eventsLoading = ref(false)
const eventsError = ref('')
const eventDepartmentFilter = ref('all')
const eventStatusFilter = ref('all')
const eventSort = ref({ key: 'date', dir: 'asc' })

const notesEvents = ref([])
const notesLoading = ref(false)
const notesError = ref('')
const notesLoaded = ref(false)
const selectedEvent = ref(null)
const isEventDetailsOpen = ref(false)
const expandedNoteEventIds = ref(new Set())

const canReplyEventNotes = computed(() =>
  authStore.canEdit('reports') && ['member', 'admin'].includes(authStore.user?.role) && Boolean(authStore.user?.department_id) && selectedEvent.value?.status === 'locked'
)

const objectiveOptions = computed(() => {
  if (!canChooseDepartment.value && authStore.user?.department_id) {
    const deptId = Number(authStore.user.department_id)
    return objectives.value.filter((objective) => objective.department_id === deptId)
  }
  if (objectiveDepartmentFilter.value === 'all') {
    return objectives.value
  }
  const deptId = Number(objectiveDepartmentFilter.value)
  return objectives.value.filter((objective) => objective.department_id === deptId)
})

const filteredObjectives = computed(() => {
  let list = objectives.value
  if (!canChooseDepartment.value && authStore.user?.department_id) {
    const deptId = Number(authStore.user.department_id)
    list = list.filter((objective) => objective.department_id === deptId)
  } else if (objectiveDepartmentFilter.value !== 'all') {
    const deptId = Number(objectiveDepartmentFilter.value)
    list = list.filter((objective) => objective.department_id === deptId)
  }
  if (objectiveFilter.value !== 'all') {
    const objectiveId = Number(objectiveFilter.value)
    list = list.filter((objective) => objective.id === objectiveId)
  }
  return list
})

const eventsByObjective = computed(() => {
  const map = new Map()
  objectiveEvents.value.forEach((event) => {
    if (!map.has(event.objective_id)) {
      map.set(event.objective_id, [])
    }
    map.get(event.objective_id).push(event)
  })
  return map
})

const sortedObjectives = computed(() => {
  const list = [...filteredObjectives.value]
  const { key, dir } = objectiveSort.value
  list.sort((a, b) => compareObjectives(a, b, key, dir))
  return list
})

const sortedEvents = computed(() => {
  const list = [...events.value]
  const { key, dir } = eventSort.value
  list.sort((a, b) => compareEvents(a, b, key, dir))
  return list
})

const notesByEvent = computed(() => {
  const groups = []
  notesEvents.value.forEach((event) => {
    const notes = [...(event.notes || [])].sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    if (notes.length) {
      groups.push({ event, notes })
    }
  })
  return groups.sort((a, b) => new Date(b.event.start_at) - new Date(a.event.start_at))
})

const reportLoading = computed(() => activeTab.value === 'notes' ? notesLoading.value : activeTab.value === 'events' ? eventsLoading.value : objectivesLoading.value)
const reportReady = computed(() => activeTab.value === 'notes' ? notesLoaded.value : activeTab.value === 'events' ? eventsLoaded.value : objectivesLoaded.value)
const reportStats = computed(() => {
  const es = locale.value === 'es'
  if (activeTab.value === 'notes') return [
    { label: es ? 'Eventos con notas' : 'Events with notes', value: notesByEvent.value.length },
    { label: es ? 'Notas en total' : 'Total notes', value: notesByEvent.value.reduce((sum, group) => sum + group.notes.length, 0) },
    { label: es ? 'Con cambios solicitados' : 'Changes requested', value: notesByEvent.value.filter(group => group.event.review_status === 'changes_requested').length },
  ]
  if (activeTab.value === 'objectives') return [
    { label: es ? 'Objetivos en el reporte' : 'Objectives in report', value: sortedObjectives.value.length },
    { label: es ? 'Departamentos' : 'Departments', value: new Set(sortedObjectives.value.map(item => item.department_id)).size },
  ]
  return [
    { label: es ? 'Eventos en el reporte' : 'Events in report', value: sortedEvents.value.length },
    { label: es ? 'Aprobados' : 'Accepted', value: sortedEvents.value.filter(item => item.review_status === 'approved').length },
    { label: es ? 'Pendientes de decisión' : 'Awaiting decision', value: sortedEvents.value.filter(item => ['pending', 'resubmitted'].includes(item.review_status)).length },
  ]
})

const toggleNotesForEvent = (eventId) => {
  const next = new Set(expandedNoteEventIds.value)
  if (next.has(eventId)) {
    next.delete(eventId)
  } else {
    next.add(eventId)
  }
  expandedNoteEventIds.value = next
}

const isNotesExpanded = (eventId) => expandedNoteEventIds.value.has(eventId)

const loadDepartments = async () => {
  if (!authStore.user?.church_id) {
    departments.value = []
    return
  }
  departments.value = await publicApi.listDepartments(authStore.user.church_id)
}

const loadObjectives = async () => {
  objectivesLoading.value = true
  objectivesError.value = ''
  try {
    objectives.value = await apiClient.get('/reports/objectives').then(r=>r.data)
    objectivesLoaded.value = true
  } catch {
    objectivesError.value = 'Unable to load objectives.'
  } finally {
    objectivesLoading.value = false
  }
}

watch(
  departmentId,
  (next) => {
    if (!canChooseDepartment.value && next) {
      objectiveDepartmentFilter.value = String(next)
      eventDepartmentFilter.value = String(next)
    }
  },
  { immediate: true }
)

const fetchEvents = async () => {
  eventsLoading.value = true
  eventsError.value = ''
  try {
    const now = new Date()
    const start = new Date(now.getFullYear(), 0, 1)
    const end = new Date(now.getFullYear(), 11, 31, 23, 59, 59, 999)
    const params = {
      start: start.toISOString(),
      end: end.toISOString(),
    }
    if (!canChooseDepartment.value && authStore.user?.department_id) {
      params.department_id = authStore.user.department_id
    } else if (eventDepartmentFilter.value !== 'all') {
      params.department_id = eventDepartmentFilter.value
    }
    if (eventStatusFilter.value !== 'all') {
      params.review_status = eventStatusFilter.value
    }
    events.value = await apiClient.get('/reports/events',{params}).then(r=>r.data)
    eventsLoaded.value = true
  } catch {
    eventsError.value = 'Unable to load events.'
  } finally {
    eventsLoading.value = false
  }
}

const fetchNotes = async () => {
  notesLoading.value = true
  notesError.value = ''
  try {
    const now = new Date()
    const start = new Date(now.getFullYear(), 0, 1)
    const end = new Date(now.getFullYear(), 11, 31, 23, 59, 59, 999)
    const params = {
      start: start.toISOString(),
      end: end.toISOString(),
    }
    if (authStore.user?.department_id) {
      params.department_id = authStore.user.department_id
    }
    notesEvents.value = await apiClient.get('/reports/events',{params}).then(r=>r.data)
    notesLoaded.value = true
  } catch {
    notesError.value = t.value.messages.loadFailed
  } finally {
    notesLoading.value = false
  }
}

const fetchObjectiveEvents = async () => {
  try {
    const now = new Date()
    const start = new Date(now.getFullYear(), 0, 1)
    const end = new Date(now.getFullYear(), 11, 31, 23, 59, 59, 999)
    const params = {
      start: start.toISOString(),
      end: end.toISOString(),
    }
    if (!canChooseDepartment.value && authStore.user?.department_id) {
      params.department_id = authStore.user.department_id
    } else if (objectiveDepartmentFilter.value !== 'all') {
      params.department_id = objectiveDepartmentFilter.value
    }
    if (objectiveFilter.value !== 'all') {
      params.objective_id = objectiveFilter.value
    }
    objectiveEvents.value = await apiClient.get('/reports/events',{params}).then(r=>r.data)
  } catch {
    // keep previous events if fetch fails
  }
}

const formatDate = (value) => {
  if (!value) return '—'
  return new Date(value).toLocaleDateString()
}

const formatTime = (event) => {
  const start = new Date(event.start_at)
  const end = new Date(event.end_at)
  return `${start.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  })}`
}

const formatNoteTime = (value) => {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

const statusLabel = (status) => {
  const value = status || 'pending'
  if (value === 'changes_requested') return t.value.status.changes
  if (value === 'resubmitted') return locale.value === 'es' ? 'Reenviado' : 'Resubmitted'
  return t.value.status[value] || value
}

const statusClass = (status) => {
  if (status === 'approved') {
    return 'status-approved'
  }
  if (status === 'denied') {
    return 'status-denied'
  }
  return 'status-pending'
}

const toCsv = (rows) => {
  return rows
    .map((row) =>
      row
        .map((cell) => {
          const value = cell ?? ''
          const escaped = String(value).replace(/\"/g, '\"\"')
          return `"${escaped}"`
        })
        .join(',')
    )
    .join('\n')
}

const downloadCsv = (filename, rows) => {
  const blob = new Blob([toCsv(rows)], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(link.href)
}

const exportPdf = (filename, title, headers, rows) => {
  const doc = new jsPDF({ orientation: 'landscape' })
  doc.text(title, 14, 12)
  doc.autoTable({
    startY: 18,
    head: [headers],
    body: rows,
    styles: { fontSize: 8 },
    headStyles: { fillColor: [31, 111, 92] },
  })
  doc.save(filename)
}

const notesExportData = () => ({
  headers: [
    t.value.columns.event,
    t.value.columns.date,
    t.value.columns.department,
    t.value.columns.status,
    locale.value === 'es' ? 'Autor' : 'Author',
    locale.value === 'es' ? 'Fecha de nota' : 'Note date',
    locale.value === 'es' ? 'Nota' : 'Note',
  ],
  rows: notesByEvent.value.flatMap(({ event, notes }) => notes.map((note) => [
    event.title || '',
    formatDate(event.start_at),
    event.department?.name || '',
    statusLabel(event.review_status),
    note.author?.name || '—',
    `${formatDate(note.created_at)} ${formatNoteTime(note.created_at)}`,
    note.note || '',
  ])),
})

const exportNotes = () => {
  const { headers, rows } = notesExportData()
  downloadCsv('reports-notes.csv', [headers, ...rows])
}

const exportNotesPdf = () => {
  const { headers, rows } = notesExportData()
  exportPdf('reports-notes.pdf', t.value.tabs.notes, headers, rows)
}

const exportObjectives = async () => {
  if (includeObjectiveEvents.value) {
    await fetchObjectiveEvents()
  }
  const rows = [
    ['ID', t.value.columns.name, t.value.columns.department, t.value.columns.description, t.value.columns.metrics],
  ]

  sortedObjectives.value.forEach((objective) => {
    rows.push([
      objective.id,
      objective.name,
      objective.department?.name || '',
      objective.description,
      objective.evaluation_metrics,
    ])

    if (includeObjectiveEvents.value) {
      rows.push(['', t.value.labels.events, '', '', ''])
      const childEvents = eventsByObjective.value.get(objective.id) || []
      if (childEvents.length === 0) {
        rows.push(['', t.value.labels.noEvents, '', '', ''])
      } else {
        childEvents.forEach((event) => {
          rows.push([
            '',
            formatDate(event.start_at),
            formatTime(event),
            event.location || '',
            `${event.title} (${statusLabel(event.review_status)})`,
          ])
        })
      }
    }
  })

  downloadCsv('reports-objectives.csv', rows)
}

const exportEvents = () => {
  const rows = [
    [
      t.value.columns.date,
      t.value.columns.time,
      t.value.columns.place,
      t.value.columns.department,
      t.value.columns.event,
      t.value.columns.objective,
      t.value.columns.status,
      t.value.columns.acceptedAt,
    ],
  ]

  sortedEvents.value.forEach((event) => {
    rows.push([
      formatDate(event.start_at),
      formatTime(event),
      event.location || '',
      event.department?.name || '',
      event.title,
      event.objective?.name || '',
      statusLabel(event.review_status),
      formatDate(event.accepted_at),
    ])
  })

  downloadCsv('reports-events.csv', rows)
}

const exportObjectivesPdf = async () => {
  if (includeObjectiveEvents.value) {
    await fetchObjectiveEvents()
  }
  const headers = ['ID', t.value.columns.name, t.value.columns.department, t.value.columns.description, t.value.columns.metrics]
  const rows = []

  sortedObjectives.value.forEach((objective) => {
    rows.push([
      objective.id,
      objective.name,
      objective.department?.name || '',
      objective.description,
      objective.evaluation_metrics,
    ])

    if (includeObjectiveEvents.value) {
      rows.push(['', t.value.labels.events, '', '', ''])
      const childEvents = eventsByObjective.value.get(objective.id) || []
      if (childEvents.length === 0) {
        rows.push(['', t.value.labels.noEvents, '', '', ''])
      } else {
        childEvents.forEach((event) => {
          rows.push([
            '',
            formatDate(event.start_at),
            formatTime(event),
            event.location || '',
            `${event.title} (${statusLabel(event.review_status)})`,
          ])
        })
      }
    }
  })

  exportPdf('reports-objectives.pdf', t.value.tabs.objectives, headers, rows)
}

const exportEventsPdf = () => {
  const headers = [
    t.value.columns.date,
    t.value.columns.time,
    t.value.columns.place,
    t.value.columns.department,
    t.value.columns.event,
    t.value.columns.objective,
    t.value.columns.status,
    t.value.columns.acceptedAt,
  ]

  const rows = sortedEvents.value.map((event) => [
    formatDate(event.start_at),
    formatTime(event),
    event.location || '',
    event.department?.name || '',
    event.title,
    event.objective?.name || '',
    statusLabel(event.review_status),
    formatDate(event.accepted_at),
  ])

  exportPdf('reports-events.pdf', t.value.tabs.events, headers, rows)
}

watch([eventDepartmentFilter, eventStatusFilter], () => {
  if (activeTab.value === 'events' && eventsLoaded.value) {
    fetchEvents()
  }
})
watch(activeTab, (next) => {
  if (next === 'events' && eventsLoaded.value) {
    fetchEvents()
  }
  if (next === 'notes' && notesLoaded.value) {
    fetchNotes()
  }
})
watch([objectiveDepartmentFilter, objectiveFilter], () => {
  if (expandedObjectiveIds.value.size > 0 && objectivesLoaded.value) {
    fetchObjectiveEvents()
  }
})
watch(objectiveDepartmentFilter, () => {
  if (objectiveFilter.value !== 'all' && !objectiveOptions.value.some((item) => item.id === Number(objectiveFilter.value))) {
    objectiveFilter.value = 'all'
  }
  if (!objectivesLoaded.value) {
    loadObjectives()
  }
})

onMounted(async () => {
  await loadDepartments()
  if (showNotesTab.value) {
    await fetchEvents()
    await fetchNotes()
  }
})

const searchObjectives = async () => {
  await loadObjectives()
  if (includeObjectiveEvents.value) {
    await fetchObjectiveEvents()
  }
}

const searchEvents = async () => {
  await fetchEvents()
}

const openNoteEvent = (item) => {
  const event = item?.event || item || null
  selectedEvent.value = event
  replyError.value = ''
  isEventDetailsOpen.value = true
}

const closeEventDetails = () => {
  isEventDetailsOpen.value = false
  selectedEvent.value = null
}

const handleDetailsReply = async ({ reply }) => {
  if (!selectedEvent.value?.id) {
    return
  }
  try {
    replySaving.value = true
    replyError.value = ''
    const response = await apiClient.post(`/reports/events/${selectedEvent.value.id}/notes`,{note:reply}).then(r=>r.data)
    selectedEvent.value.notes = [...(selectedEvent.value.notes || []), response]
    await fetchNotes()
  } catch {
    replyError.value = t.value.messages.loadFailed
  } finally {
    replySaving.value = false
  }
}

const toggleObjectiveEvents = async (objectiveId) => {
  if (!includeObjectiveEvents.value) {
    return
  }
  const next = new Set(expandedObjectiveIds.value)
  if (next.has(objectiveId)) {
    next.delete(objectiveId)
  } else {
    next.add(objectiveId)
    if (objectiveEvents.value.length === 0) {
      await fetchObjectiveEvents()
    }
  }
  expandedObjectiveIds.value = next
}

const isObjectiveExpanded = (objectiveId) => expandedObjectiveIds.value.has(objectiveId)

const setObjectiveSort = (key) => {
  const current = objectiveSort.value
  if (current.key === key) {
    objectiveSort.value = { key, dir: current.dir === 'asc' ? 'desc' : 'asc' }
    return
  }
  objectiveSort.value = { key, dir: 'asc' }
}

const setEventSort = (key) => {
  const current = eventSort.value
  if (current.key === key) {
    eventSort.value = { key, dir: current.dir === 'asc' ? 'desc' : 'asc' }
    return
  }
  eventSort.value = { key, dir: 'asc' }
}

const sortIndicator = (sortState, key) => {
  const current = sortState?.value ?? sortState
  if (!current || current.key !== key) {
    return ''
  }
  return current.dir === 'asc' ? '▲' : '▼'
}

const compareObjectives = (a, b, key, dir) => {
  const factor = dir === 'asc' ? 1 : -1
  const getValue = (item) => {
    switch (key) {
      case 'department':
        return item.department?.name || ''
      case 'description':
        return item.description || ''
      case 'metrics':
        return item.evaluation_metrics || ''
      default:
        return item.name || ''
    }
  }
  return getValue(a).localeCompare(getValue(b)) * factor
}

const compareEvents = (a, b, key, dir) => {
  const factor = dir === 'asc' ? 1 : -1
  const getValue = (item) => {
    switch (key) {
      case 'place':
        return item.location || ''
      case 'department':
        return item.department?.name || ''
      case 'event':
        return item.title || ''
      case 'objective':
        return item.objective?.name || ''
      case 'status':
        return item.review_status || ''
      case 'date':
      default:
        return item.start_at || ''
    }
  }
  if (key === 'date') {
    return (new Date(getValue(a)) - new Date(getValue(b))) * factor
  }
  return getValue(a).localeCompare(getValue(b)) * factor
}
</script>

<style scoped>
.reports-page { display: grid; gap: 20px; font-size: var(--font-body); }
.reports-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
.reports-eyebrow { margin: 0 0 6px; color: var(--dominant); font-size: 12px; font-weight: 500; }
.reports-header h1 { font-size: var(--font-page-title); margin-bottom: 8px; }
.reports-intro { max-width: 560px; margin: 0; line-height: 1.6; }
.reports-scope { display: grid; gap: 3px; flex-shrink: 0; padding-left: 18px; border-left: 2px solid #c8d9d0; color: var(--muted); font-size: 12px; }
.reports-scope strong { color: #264d3e; font-size: 18px; font-weight: 500; }
.reports-tabs { display: flex; gap: 6px; border-bottom: 1px solid var(--border); }
.report-tab { width: auto; border-radius: 6px 6px 0 0; padding: 10px 18px; border: 0; border-bottom: 2px solid transparent; background: transparent; color: #617069; font-weight: 500; }
.report-tab.active { color: #205b48; border-bottom-color: var(--dominant); background: #e6eee8; }
.report-tab:hover { background: #eaf0ec; }
.report-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
.report-stat { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 18px; background: #fff; border: 1px solid var(--border); border-radius: 9px; }
.report-stat span { color: #65736b; font-size: 12px; }
.report-stat strong { font-size: 22px; font-weight: 500; color: #254b3d; }
.report-surface { background: #fff; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.report-filters { margin: 0; padding: 6px 12px 18px; background: #f8faf9; border-bottom: 1px solid var(--border); }
.report-filters label { margin: 0; width: 100%; }
.report-filters select { margin-top: 6px; }
.report-filters .export-toggle { display: inline-flex; align-items: center; justify-content: flex-start; gap: 8px; width: auto; margin-top: 10px; }
.report-filters .export-toggle input { width: auto; margin: 0; flex: 0 0 auto; }
.report-toolbar { align-items: center; }
.reports-page .btn-primary { background: var(--dominant); border-color: var(--dominant); }
.report-empty { padding: 42px 24px; text-align: center; color: #6b7971; font-size: 13px; }
.report-error { margin: 16px; padding: 12px; border-radius: 6px; background: #fff1ef; color: #8a3931; }
.reports-page .table { font-size: 12px; border: 0; }
.reports-page .table th { padding: 12px; background: #f6f8f7; color: #596b61; font-weight: 500; white-space: nowrap; border-bottom: 1px solid var(--border); }
.reports-page .table td { padding: 12px; vertical-align: middle; border-color: #edf0ed; background: white; }
.reports-page .table tbody tr:hover > td { background: #f9fbfa; }
.reports-page .table-sort { font-size: inherit; font-weight: 500; }
.report-status { display: inline-flex; align-items: center; border-radius: 20px; padding: 4px 9px; font-size: 11px; white-space: nowrap; font-weight: 500; }
.report-status.status-approved { color: #246046; background: #e6f1e9; }
.report-status.status-denied { color: #8a443e; background: #f8e9e6; }
.report-status.status-pending { color: #78612c; background: #fbf2db; }
.report-event-link, .report-detail-link { width: auto; background: none; padding: 0; border: 0; text-align: left; color: #28634d; font-size: inherit; font-weight: 500; }
.report-event-link:hover, .report-detail-link:hover { text-decoration: underline; }
.notes-report-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px; border-bottom: 1px solid var(--border); }
.notes-report-heading h2 { font-size: 15px; margin: 0 0 5px; }
.notes-report-heading p { margin: 0; font-size: 12px; }
.notes-report-list { display: grid; gap: 14px; padding: 20px; background: #fafbf9; }
.note-report-card { border: 1px solid #dce5de; border-radius: 8px; padding: 18px; background: #fff; }
.note-report-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.note-report-top h3 { font-size: 14px; font-weight: 500; margin: 0 0 5px; }
.note-report-top p { font-size: 12px; margin: 0; }
.note-preview { margin: 16px 0; padding: 12px 14px; background: #f4f7f5; border-left: 2px solid #bdd0c4; border-radius: 0 5px 5px 0; }
.note-preview > span { font-size: 11px; color: #6b7a70; }
.note-preview p { margin: 6px 0 0; color: #354b3d; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
.note-report-actions { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.note-total { display: inline-block; margin-left: 6px; padding: 0 6px; border-radius: 10px; background: #e9eeeb; color: #365642; }
.report-conversation { list-style: none; margin: 18px 0 0; padding: 0; border-top: 1px solid var(--border); }
.report-conversation li { padding: 14px 0; border-bottom: 1px solid #edf0ed; }
.report-conversation li:last-child { border: 0; padding-bottom: 0; }
.report-conversation li > div { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; font-size: 11px; }
.report-conversation time { color: #6b7a70; }
.report-conversation p { margin: 6px 0 0; white-space: pre-wrap; overflow-wrap: anywhere; }
@media (max-width: 600px) {
  .reports-header { flex-direction: column; gap: 14px; }
  .reports-scope { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; border: 0; padding: 0; }
  .report-summary { gap: 8px; grid-template-columns: 1fr; }
  .report-stat { padding: 10px 14px; }
  .notes-report-list, .notes-report-heading { padding: 14px; }
  .note-report-card { padding: 14px; }
  .note-report-top { flex-wrap: wrap; }
}
</style>
