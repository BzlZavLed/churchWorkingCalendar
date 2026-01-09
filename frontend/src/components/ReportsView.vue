<template>
  <section class="container py-4">
    <div class="row g-2 align-items-center mb-3">
      <div class="col-12 col-md-6">
        <h1 class="h3 m-0">{{ t.title }}</h1>
      </div>
      <div class="col-12 col-md-6">
        <label class="d-flex align-items-center gap-2 justify-content-md-end mb-0">
          <span class="form-label small mb-0">Language</span>
          <select v-model="locale" class="form-select w-auto">
            <option value="es">Espanol</option>
            <option value="en">English</option>
          </select>
        </label>
      </div>
    </div>

    <div class="review-tabs d-flex flex-wrap gap-2 mb-3">
      <button
        v-if="!showNotesTab"
        type="button"
        class="review-tab"
        :class="{ active: activeTab === 'objectives' }"
        @click="activeTab = 'objectives'"
      >
        {{ t.tabs.objectives }}
      </button>
      <button
        v-if="showNotesTab"
        type="button"
        class="review-tab"
        :class="{ active: activeTab === 'notes' }"
        @click="activeTab = 'notes'"
      >
        {{ t.tabs.notes }}
      </button>
      <button
        type="button"
        class="review-tab"
        :class="{ active: activeTab === 'events' }"
        @click="activeTab = 'events'"
      >
        {{ t.tabs.events }}
      </button>
    </div>

    <div v-if="activeTab === 'objectives' && !showNotesTab" class="bg-white border rounded p-4">
      <div class="row g-3 mb-3">
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
        <div class="col-12 d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-secondary w-auto" @click="searchObjectives">
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

      <div v-if="objectivesLoading">{{ t.messages.loadingObjectives }}</div>
      <p v-if="objectivesError" class="text-danger">{{ objectivesError }}</p>

      <div v-if="!objectivesLoaded && !objectivesLoading">{{ t.messages.searchToLoad }}</div>
      <div v-else-if="filteredObjectives.length === 0 && !objectivesLoading">{{ t.messages.noObjectives }}</div>
      <div v-else class="table-responsive">
        <table class="table mb-0 main-table" data-dt="off">
          <thead>
            <tr>
              <th>ID</th>
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
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="event in eventsByObjective.get(objective.id) || []" :key="event.id" class="child-row">
                        <td>{{ formatDate(event.start_at) }}</td>
                        <td>{{ formatTime(event) }}</td>
                        <td>{{ event.location || '—' }}</td>
                        <td>{{ event.title }}</td>
                        <td>{{ statusLabel(event.review_status) }}</td>
                      </tr>
                      <tr v-if="(eventsByObjective.get(objective.id) || []).length === 0" class="child-row">
                        <td colspan="5" class="text-muted">{{ t.labels.noEvents }}</td>
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

    <div v-else-if="activeTab === 'notes' && showNotesTab" class="bg-white border rounded p-4">
      <div v-if="notesLoading">{{ t.messages.loadingNotes }}</div>
      <p v-if="notesError" class="text-danger">{{ notesError }}</p>

      <div v-if="notesLoaded && notesByEvent.length === 0" class="text-muted">
        {{ t.messages.noNotes }}
      </div>

      <div v-else-if="notesByEvent.length" class="table-responsive">
        <table class="table mb-0 main-table" data-dt="off">
          <thead>
            <tr>
              <th>{{ t.columns.event }}</th>
              <th>{{ t.columns.finalStatus }}</th>
              <th>{{ t.columns.date }}</th>
              <th>{{ t.columns.time }}</th>
              <th>{{ t.columns.note }}</th>
              <th>{{ t.columns.author }}</th>
              <th>{{ t.columns.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="group in notesByEvent" :key="group.event.id">
              <tr>
                <td>{{ group.event.title || '—' }}</td>
                <td>{{ finalOutcomeLabel(group.event) }}</td>
                <td>{{ formatDate(group.event.start_at) }}</td>
                <td>{{ formatTime(group.event) }}</td>
                <td>—</td>
                <td>—</td>
                <td class="notes-actions">
                  <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    @click="toggleNotesForEvent(group.event.id)"
                  >
                    {{ isNotesExpanded(group.event.id) ? t.labels.hideNotes : t.labels.viewNotes }}
                  </button>
                  <button type="button" class="btn btn-outline-secondary btn-sm" @click="openNoteEvent(group.event)">
                    {{ t.labels.viewDetails }}
                  </button>
                </td>
              </tr>
              <template v-if="isNotesExpanded(group.event.id)">
                <tr
                  v-for="note in group.notes"
                  :key="note.id"
                  class="child-row note-row"
                  :class="noteRowClass(note)"
                >
                  <td>—</td>
                  <td>—</td>
                  <td>{{ formatDate(note.created_at) }}</td>
                  <td>{{ formatNoteTime(note.created_at) }}</td>
                  <td>{{ note.note }}</td>
                  <td>{{ note.author?.name || '—' }}</td>
                  <td></td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else class="bg-white border rounded p-4">
      <div class="row g-3 mb-3">
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
              <option value="approved">{{ t.status.approved }}</option>
              <option value="denied">{{ t.status.denied }}</option>
              <option value="changes_requested">{{ t.status.changes }}</option>
            </select>
          </label>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-outline-secondary w-auto" @click="searchEvents">
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

      <div v-if="eventsLoading">{{ t.messages.loadingEvents }}</div>
      <p v-if="eventsError" class="text-danger">{{ eventsError }}</p>

      <div v-if="events.length === 0 && !eventsLoading">{{ t.messages.noEvents }}</div>
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
              <td>{{ event.title }}</td>
              <td>{{ event.objective?.name || '—' }}</td>
              <td class="status-cell">
                <span class="status-dot" :class="statusClass(event.review_status)"></span>
                {{ statusLabel(event.review_status) }}
              </td>
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
      :show-status-details="false"
      :labels="t.eventDetails"
      @close="closeEventDetails"
      @reply-note="handleDetailsReply"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { objectiveApi } from '../services/objectiveApi'
import { calendarApi } from '../services/calendarApi'
import { publicApi } from '../services/publicApi'
import EventDetailsModal from './EventDetailsModal.vue'
import jsPDF from 'jspdf'
import 'jspdf-autotable'

const authStore = useAuthStore()

const LOCALE_KEY = 'ui_locale'
const locale = ref('es')
const translations = {
  es: {
    title: 'Reportes',
    tabs: { objectives: 'Objetivos', events: 'Eventos', notes: 'Notas' },
    labels: {
      department: 'Departamento',
      objective: 'Objetivo',
      status: 'Estado',
      all: 'Todos',
      includeEvents: 'Incluir eventos',
      events: 'Eventos',
      noEvents: 'Sin eventos',
      show: 'Ver',
      hide: 'Ocultar',
      search: 'Buscar',
      exportCsv: 'Exportar CSV',
      exportPdf: 'Exportar PDF',
      viewDetails: 'Ver detalles',
      viewNotes: 'Ver notas',
      hideNotes: 'Ocultar notas',
    },
    columns: {
      name: 'Nombre',
      department: 'Departamento',
      description: 'Descripcion',
      metrics: 'Metricas',
      date: 'Fecha',
      time: 'Hora',
      place: 'Lugar',
      event: 'Evento',
      objective: 'Objetivo',
      status: 'Estado',
      actions: 'Acciones',
      note: 'Nota',
      author: 'Autor',
      finalStatus: 'Resultado final',
    },
    status: {
      pending: 'Pendiente',
      approved: 'Aprobado',
      denied: 'Rechazado',
      changes: 'Cambios solicitados',
    },
    messages: {
      loadingObjectives: 'Cargando objetivos...',
      loadingNotes: 'Cargando notas...',
      noObjectives: 'No se encontraron objetivos.',
      searchToLoad: 'Usa Buscar para cargar datos.',
      loadingEvents: 'Cargando eventos...',
      noEvents: 'No se encontraron eventos.',
      noNotes: 'No se encontraron notas.',
    },
    eventDetails: {
      title: 'Detalles del evento',
      close: 'Cerrar',
      department: 'Departamento',
      objective: 'Objetivo',
      location: 'Ubicacion',
      status: 'Estado',
      reviewStatus: 'Revision',
      reviewNote: 'Nota de revision',
      finalOutcome: 'Resultado final',
      reviewPending: 'En revision',
      finalOutcomeAccepted: 'Aceptado',
      finalOutcomeRejected: 'Rechazado',
      finalOutcomeUpdateRequested: 'Cambios solicitados',
      start: 'Inicio',
      end: 'Fin',
      description: 'Descripcion',
      historyTitle: 'Historial',
      notesTitle: 'Notas',
      replyButton: 'Responder',
      replyLabel: 'Respuesta',
      replyPlaceholder: 'Escribe una respuesta...',
    },
  },
  en: {
    title: 'Reports',
    tabs: { objectives: 'Objectives', events: 'Events', notes: 'Notes' },
    labels: {
      department: 'Department',
      objective: 'Objective',
      status: 'Status',
      all: 'All',
      includeEvents: 'Include events',
      events: 'Events',
      noEvents: 'No events',
      show: 'Show',
      hide: 'Hide',
      search: 'Search',
      exportCsv: 'Export CSV',
      exportPdf: 'Export PDF',
      viewDetails: 'View details',
      viewNotes: 'View notes',
      hideNotes: 'Hide notes',
    },
    columns: {
      name: 'Name',
      department: 'Department',
      description: 'Description',
      metrics: 'Evaluation Metrics',
      date: 'Date',
      time: 'Time',
      place: 'Place',
      event: 'Event',
      objective: 'Objective',
      status: 'Status',
      actions: 'Actions',
      note: 'Note',
      author: 'Author',
      finalStatus: 'Final outcome',
    },
    status: {
      pending: 'Pending',
      approved: 'Approved',
      denied: 'Denied',
      changes: 'Changes requested',
    },
    messages: {
      loadingObjectives: 'Loading objectives...',
      loadingNotes: 'Loading notes...',
      noObjectives: 'No objectives found.',
      searchToLoad: 'Use Search to load data.',
      loadingEvents: 'Loading events...',
      noEvents: 'No events found.',
      noNotes: 'No notes found.',
    },
    eventDetails: {
      title: 'Event details',
      close: 'Close',
      department: 'Department',
      objective: 'Objective',
      location: 'Location',
      status: 'Status',
      reviewStatus: 'Review',
      reviewNote: 'Review note',
      finalOutcome: 'Final outcome',
      reviewPending: 'Pending review',
      finalOutcomeAccepted: 'Accepted',
      finalOutcomeRejected: 'Rejected',
      finalOutcomeUpdateRequested: 'Changes requested',
      start: 'Start',
      end: 'End',
      description: 'Description',
      historyTitle: 'History',
      notesTitle: 'Notes',
      replyButton: 'Reply',
      replyLabel: 'Reply',
      replyPlaceholder: 'Write a reply...',
    },
  },
}

const t = computed(() => translations[locale.value])

const storedLocale = localStorage.getItem(LOCALE_KEY)
if (storedLocale && translations[storedLocale]) {
  locale.value = storedLocale
}

watch(locale, (next) => {
  if (next) {
    localStorage.setItem(LOCALE_KEY, next)
  }
})

const isMember = computed(() => authStore.user?.role === 'member')
const isAdmin = computed(() => authStore.user?.role === 'admin')
const showNotesTab = computed(() => isMember.value || isAdmin.value)
const activeTab = ref(showNotesTab.value ? 'notes' : 'objectives')
const departments = ref([])
const canChooseDepartment = computed(() =>
  ['superadmin', 'secretary'].includes(authStore.user?.role || '')
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
  authStore.user?.role === 'member' && Boolean(authStore.user?.department_id)
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

const finalOutcomeLabel = (event) => {
  const value = event?.final_validation
  if (!value) {
    return t.value.eventDetails?.reviewPending || 'Pending review'
  }
  const map = {
    accepted: t.value.eventDetails?.finalOutcomeAccepted || 'Accepted',
    rejected: t.value.eventDetails?.finalOutcomeRejected || 'Rejected',
    update_requested: t.value.eventDetails?.finalOutcomeUpdateRequested || 'Changes requested',
  }
  return map[value] || value
}

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

const noteRowClass = (note) => {
  const role = note?.author?.role
  return role === 'secretary' || role === 'superadmin' ? 'note-row--incoming' : 'note-row--outgoing'
}

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
    objectives.value = await objectiveApi.list()
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
    events.value = await calendarApi.fetchEvents(params)
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
    notesEvents.value = await calendarApi.fetchEvents(params)
    notesLoaded.value = true
  } catch {
    notesError.value = locale.value === 'es'
      ? 'No se pudieron cargar las notas.'
      : 'Unable to load notes.'
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
    objectiveEvents.value = await calendarApi.fetchEvents(params)
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
  ]

  const rows = sortedEvents.value.map((event) => [
    formatDate(event.start_at),
    formatTime(event),
    event.location || '',
    event.department?.name || '',
    event.title,
    event.objective?.name || '',
    statusLabel(event.review_status),
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
    const response = await calendarApi.replyNote(selectedEvent.value.id, { reply })
    selectedEvent.value.notes = [...(selectedEvent.value.notes || []), response]
    await fetchNotes()
  } catch {
    // no-op
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
