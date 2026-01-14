<template>
  <section class="container py-4">
    <div class="row g-2 align-items-center mb-3">
      <div class="col-12 col-md-6">
        <h1 class="h3 m-0">{{ t.title }}</h1>
      </div>
      <div class="col-12 col-md-6"></div>
    </div>

    <form class="bg-white border rounded p-4 mb-4" @submit.prevent="createObjective">
      <div class="row g-3 align-items-end">
        <div v-if="isSuperAdmin" class="col-12 col-lg-4">
          <label class="form-label">
            {{ t.fields.church }}
            <select v-model="selectedChurchId" class="form-select" required>
              <option value="">{{ common.select }}</option>
              <option v-for="church in churches" :key="church.id" :value="String(church.id)">
                {{ church.name }}
              </option>
            </select>
          </label>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label">
            {{ t.fields.department }}
            <select
              v-if="canChooseDepartment"
              v-model="createForm.department_id"
              class="form-select"
              :disabled="isSuperAdmin && !selectedChurchId"
              required
            >
              <option value="" disabled>{{ t.labels.selectDepartment }}</option>
              <option v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                {{ dept.name }}
              </option>
            </select>
            <input
              v-else
              class="form-control"
              type="text"
              :value="authStore.user?.department?.name || '—'"
              disabled
            />
          </label>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label">
            {{ t.fields.name }}
            <input v-model="createForm.name" class="form-control" type="text" required />
          </label>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label">
            {{ t.fields.metrics }}
            <input v-model="createForm.evaluation_metrics" class="form-control" type="text" required />
          </label>
        </div>
        <div class="col-12">
          <label class="form-label">
            {{ t.fields.description }}
            <textarea v-model="createForm.description" class="form-control" rows="3" required></textarea>
          </label>
        </div>
        <div class="col-12">
          <button class="btn btn-primary" type="submit">{{ t.labels.create }}</button>
        </div>
      </div>
    </form>

    <div v-if="loading">{{ t.messages.loading }}</div>
    <p v-if="error" class="text-danger">{{ error }}</p>

    <div v-if="filteredObjectives.length === 0 && !loading">{{ t.messages.empty }}</div>
    <div v-else class="objectives-list bg-white border rounded p-3">
      <div class="row g-2 align-items-center mb-3">
        <div class="col-12 col-md-6">
          <label class="form-label mb-0 w-100">
            <span class="d-block small mb-1">{{ t.labels.filter }}</span>
            <input v-model="filterText" class="form-control" type="search" :placeholder="t.labels.filterPlaceholder" />
          </label>
        </div>
      </div>

      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0 objectives-table" data-dt="off">
          <thead>
            <tr>
              <th>{{ t.labels.id }}</th>
              <th v-if="showDepartmentColumn">
                <button class="table-sort" type="button" @click="toggleSort('department')">
                  {{ t.fields.department }}
                  <span v-if="sortKey === 'department'">{{ sortDir === 'asc' ? '▲' : '▼' }}</span>
                </button>
              </th>
              <th>{{ t.fields.name }}</th>
              <th>{{ t.fields.description }}</th>
              <th>{{ t.fields.metrics }}</th>
              <th class="text-end">{{ t.labels.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="objective in paginatedObjectives" :key="objective.id">
              <td>{{ objective.id }}</td>
              <td v-if="showDepartmentColumn">
                <span
                  class="dept-pill"
                  :style="{
                    backgroundColor: objective.department?.color || '#cfd4da',
                    color: textColorForBg(objective.department?.color),
                  }"
                >
                  {{ objective.department?.name || '—' }}
                </span>
              </td>
              <td><input v-model="objective.name" class="form-control" type="text" /></td>
              <td><textarea v-model="objective.description" class="form-control" rows="2"></textarea></td>
              <td><textarea v-model="objective.evaluation_metrics" class="form-control" rows="2"></textarea></td>
              <td class="text-end">
                <div class="d-flex flex-wrap justify-content-end gap-2">
                  <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateObjective(objective)">
                    {{ t.labels.save }}
                  </button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteObjective(objective)">
                    {{ t.labels.delete }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-md-none">
        <div v-for="objective in paginatedObjectives" :key="objective.id" class="card mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h5 class="card-title mb-1">{{ objective.name }}</h5>
                <p class="card-subtitle text-muted mb-0">#{{ objective.id }}</p>
              </div>
              <span
                v-if="showDepartmentColumn"
                class="dept-badge"
                :style="{
                  backgroundColor: objective.department?.color || '#cfd4da',
                  color: textColorForBg(objective.department?.color),
                }"
              >
                {{ objective.department?.name || '—' }}
              </span>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.description }}</label>
              <textarea v-model="objective.description" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.metrics }}</label>
              <textarea v-model="objective.evaluation_metrics" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">{{ t.fields.name }}</label>
              <input v-model="objective.name" class="form-control" type="text" />
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateObjective(objective)">
                {{ t.labels.save }}
              </button>
              <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteObjective(objective)">
                {{ t.labels.delete }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 mt-3">
        <div class="text-muted small">
          {{ t.labels.page }} {{ currentPage }} / {{ totalPages }}
        </div>
        <div class="btn-group">
          <button class="btn btn-outline-secondary btn-sm" type="button" :disabled="currentPage === 1" @click="goPrev">
            {{ t.labels.prev }}
          </button>
          <button
            v-for="page in totalPages"
            :key="page"
            class="btn btn-outline-secondary btn-sm"
            :class="{ active: currentPage === page }"
            type="button"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
          <button
            class="btn btn-outline-secondary btn-sm"
            type="button"
            :disabled="currentPage === totalPages"
            @click="goNext"
          >
            {{ t.labels.next }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'
import { objectiveApi } from '../services/objectiveApi'
import { publicApi } from '../services/publicApi'
import { superAdminApi } from '../services/superAdminApi'

const authStore = useAuthStore()

const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)

const t = computed(() => translations[locale.value].objectives)
const common = computed(() => translations[locale.value].common)


const objectives = ref([])
const departments = ref([])
const churches = ref([])
const selectedChurchId = ref('')
const loading = ref(false)
const error = ref('')
const isActive = ref(true)
const filterText = ref('')
const currentPage = ref(1)
const pageSize = 10
const sortKey = ref('department')
const sortDir = ref('asc')

const canChooseDepartment = computed(() =>
  ['superadmin', 'secretary', 'admin'].includes(authStore.user?.role || '')
)
const showDepartmentColumn = computed(() =>
  ['superadmin', 'secretary', 'admin'].includes(authStore.user?.role || '')
)
const isSuperAdmin = computed(() => authStore.user?.role === 'superadmin')

const createForm = reactive({
  department_id: '',
  name: '',
  description: '',
  evaluation_metrics: '',
})

const filteredObjectives = computed(() => {
  const list = objectives.value || []
  const role = authStore.user?.role
  const term = filterText.value.trim().toLowerCase()
  const churchId = Number(selectedChurchId.value || 0)
  const scopedList =
    role === 'superadmin' && churchId
      ? list.filter((item) => item.department?.church_id === churchId)
      : list
  if (role === 'member') {
    const deptId = authStore.user?.department_id
    return scopedList
      .filter((item) => item.department_id === deptId)
      .filter((item) => matchesFilter(item, term))
      .sort((a, b) => compareObjectives(a, b))
  }
  return scopedList
    .filter((item) => matchesFilter(item, term))
    .sort((a, b) => compareObjectives(a, b))
})

const totalPages = computed(() => {
  const count = filteredObjectives.value.length
  return Math.max(1, Math.ceil(count / pageSize))
})

const paginatedObjectives = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredObjectives.value.slice(start, start + pageSize)
})

const matchesFilter = (item, term) => {
  if (!term) {
    return true
  }
  const haystack = [
    item.name,
    item.description,
    item.evaluation_metrics,
    item.department?.name,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase()

  return haystack.includes(term)
}

const compareObjectives = (a, b) => {
  if (sortKey.value !== 'department') {
    return 0
  }
  const nameA = (a.department?.name || '').toLowerCase()
  const nameB = (b.department?.name || '').toLowerCase()
  if (nameA === nameB) {
    return 0
  }
  const dir = sortDir.value === 'asc' ? 1 : -1
  return nameA > nameB ? dir : -dir
}

const toggleSort = (key) => {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    return
  }
  sortKey.value = key
  sortDir.value = 'asc'
}

const textColorForBg = (color) => {
  if (!color) {
    return '#111111'
  }
  const hex = color.trim()
  if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex)) {
    return '#111111'
  }
  let normalized = hex.slice(1)
  if (normalized.length === 3) {
    normalized = normalized
      .split('')
      .map((ch) => ch + ch)
      .join('')
  }
  const intValue = Number.parseInt(normalized, 16)
  const r = (intValue >> 16) & 255
  const g = (intValue >> 8) & 255
  const b = intValue & 255
  const luminance = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255
  return luminance > 0.7 ? '#111111' : '#ffffff'
}

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value) {
    return
  }
  currentPage.value = page
}

const goPrev = () => {
  goToPage(currentPage.value - 1)
}

const goNext = () => {
  goToPage(currentPage.value + 1)
}

const loadDepartments = async () => {
  const churchId = isSuperAdmin.value ? Number(selectedChurchId.value || 0) : authStore.user?.church_id
  if (!churchId) {
    departments.value = []
    return
  }
  departments.value = await publicApi.listDepartments(churchId)
}

const loadObjectives = async () => {
  loading.value = true
  error.value = ''
  try {
    const data = await objectiveApi.list()
    if (!isActive.value) {
      return
    }
    objectives.value = Array.isArray(data) ? data.map((item) => ({ ...item })) : []
    currentPage.value = 1
  } catch {
    if (!isActive.value) {
      return
    }
    error.value = t.value.messages.loadError
  } finally {
    if (!isActive.value) {
      return
    }
    loading.value = false
  }
}

const createObjective = async () => {
  error.value = ''
  const departmentId = canChooseDepartment.value
    ? Number(createForm.department_id || 0)
    : authStore.user?.department_id

  if (!departmentId) {
    error.value = t.value.messages.departmentRequired
    return
  }

  try {
    await objectiveApi.create({
      department_id: departmentId,
      name: createForm.name,
      description: createForm.description,
      evaluation_metrics: createForm.evaluation_metrics,
    })
    createForm.name = ''
    createForm.description = ''
    createForm.evaluation_metrics = ''
    if (canChooseDepartment.value) {
      createForm.department_id = ''
    }
    await loadObjectives()
  } catch {
    error.value = t.value.messages.createError
  }
}

const updateObjective = async (objective) => {
  error.value = ''
  try {
    await objectiveApi.update(objective.id, {
      name: objective.name,
      description: objective.description,
      evaluation_metrics: objective.evaluation_metrics,
    })
  } catch {
    error.value = t.value.messages.updateError
  }
}

const deleteObjective = async (objective) => {
  error.value = ''
  try {
    await objectiveApi.remove(objective.id)
    await loadObjectives()
  } catch {
    error.value = t.value.messages.deleteError
  }
}

const loadData = async () => {
  if (!authStore.user) {
    return
  }
  if (isSuperAdmin.value) {
    const response = await superAdminApi.listChurches()
    churches.value = response.data
  }
  await loadDepartments()
  await loadObjectives()
}

onMounted(() => {
  if (authStore.user) {
    loadData()
    return
  }
  const stop = watch(
    () => authStore.user,
    (next) => {
      if (next) {
        loadData()
        stop()
      }
    }
  )
})

onUnmounted(() => {
  isActive.value = false
})

// Locale is managed by the sidebar selector.

watch(filterText, () => {
  currentPage.value = 1
})

watch(selectedChurchId, () => {
  if (!isSuperAdmin.value) {
    return
  }
  createForm.department_id = ''
  loadDepartments()
  currentPage.value = 1
})

watch(
  () => filteredObjectives.value.length,
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value
    }
  }
)
</script>
