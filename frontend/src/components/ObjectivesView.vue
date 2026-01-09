<template>
  <section class="container py-4">
    <div class="row g-2 align-items-center mb-3">
      <div class="col-12 col-md-6">
        <h1 class="h3 m-0">{{ t.title }}</h1>
      </div>
      <div class="col-12 col-md-6">
        <label class="d-flex align-items-center gap-2 justify-content-md-end mb-0">
          <span class="form-label small mb-0">{{ t.labels.language }}</span>
          <select v-model="locale" class="form-select w-auto">
            <option value="es">Espanol</option>
            <option value="en">English</option>
          </select>
        </label>
      </div>
    </div>

    <form class="bg-white border rounded p-4 mb-4" @submit.prevent="createObjective">
      <div class="row g-3 align-items-end">
        <div class="col-12 col-lg-4">
          <label class="form-label">
            {{ t.fields.department }}
            <select v-if="canChooseDepartment" v-model="createForm.department_id" class="form-select" required>
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
    <div v-else class="table-responsive bg-white border rounded">
      <table class="table mb-0" data-dt="off">
        <thead>
          <tr>
            <th>ID</th>
            <th v-if="showDepartmentColumn">{{ t.fields.department }}</th>
            <th>{{ t.fields.name }}</th>
            <th>{{ t.fields.description }}</th>
            <th>{{ t.fields.metrics }}</th>
            <th class="text-end">{{ t.labels.actions }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="objective in filteredObjectives" :key="objective.id">
            <td>{{ objective.id }}</td>
            <td v-if="showDepartmentColumn">{{ objective.department?.name || '—' }}</td>
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
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { objectiveApi } from '../services/objectiveApi'
import { publicApi } from '../services/publicApi'

const authStore = useAuthStore()

const LOCALE_KEY = 'ui_locale'
const locale = ref('es')

const translations = {
  es: {
    title: 'Objetivos',
    fields: {
      department: 'Departamento',
      name: 'Nombre',
      description: 'Descripcion',
      metrics: 'Metricas de evaluacion anual',
    },
    labels: {
      language: 'Idioma',
      selectDepartment: 'Selecciona departamento',
      actions: 'Acciones',
      create: 'Crear',
      save: 'Guardar',
      delete: 'Eliminar',
    },
    messages: {
      loading: 'Cargando objetivos...',
      empty: 'No hay objetivos registrados.',
      loadError: 'No se pudieron cargar los objetivos.',
      createError: 'No se pudo crear el objetivo.',
      updateError: 'No se pudo actualizar el objetivo.',
      deleteError: 'No se pudo eliminar el objetivo.',
      departmentRequired: 'El departamento es obligatorio.',
    },
  },
  en: {
    title: 'Objectives',
    fields: {
      department: 'Department',
      name: 'Name',
      description: 'Description',
      metrics: 'End of year evaluation metrics',
    },
    labels: {
      language: 'Language',
      selectDepartment: 'Select department',
      actions: 'Actions',
      create: 'Create',
      save: 'Save',
      delete: 'Delete',
    },
    messages: {
      loading: 'Loading objectives...',
      empty: 'No objectives yet.',
      loadError: 'Unable to load objectives.',
      createError: 'Unable to create objective.',
      updateError: 'Unable to update objective.',
      deleteError: 'Unable to delete objective.',
      departmentRequired: 'Department is required.',
    },
  },
}

const t = computed(() => translations[locale.value] || translations.es)

const objectives = ref([])
const departments = ref([])
const loading = ref(false)
const error = ref('')
const isActive = ref(true)

const canChooseDepartment = computed(() =>
  ['superadmin', 'secretary'].includes(authStore.user?.role || '')
)
const showDepartmentColumn = computed(() =>
  ['superadmin', 'secretary'].includes(authStore.user?.role || '')
)

const createForm = reactive({
  department_id: '',
  name: '',
  description: '',
  evaluation_metrics: '',
})

const filteredObjectives = computed(() => {
  const list = objectives.value || []
  const role = authStore.user?.role
  if (role === 'member' || role === 'admin') {
    const deptId = authStore.user?.department_id
    return list.filter((item) => item.department_id === deptId)
  }
  return list
})

const loadDepartments = async () => {
  if (!authStore.user?.church_id) {
    departments.value = []
    return
  }
  departments.value = await publicApi.listDepartments(authStore.user.church_id)
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

const storedLocale = localStorage.getItem(LOCALE_KEY)
if (storedLocale && translations[storedLocale]) {
  locale.value = storedLocale
}

watch(locale, (next) => {
  if (next) {
    localStorage.setItem(LOCALE_KEY, next)
  }
})
</script>
