<template>
  <section class="container py-4 management-page">
    <header class="management-heading">
      <p class="management-eyebrow">{{ authStore.user?.church?.name || (locale === 'es' ? 'Administración' : 'Administration') }}</p>
      <h1>{{ t.title }}</h1>
      <p>{{ locale === 'es' ? 'Organiza los departamentos, sus responsables y sus funciones.' : 'Organize departments, their administrators and responsibilities.' }}</p>
    </header>
    <div class="management-summary"><article><span>{{ t.title }}</span><strong>{{ loading ? '—' : departments.length }}</strong></article>
      <article><span>{{ locale === 'es' ? 'Con responsable' : 'With an administrator' }}</span><strong>{{ loading ? '—' : departments.filter(department => department.users?.some(user => user.role === 'admin')).length }}</strong></article>
      <article><span>{{ locale === 'es' ? 'Usuarios vinculados' : 'Linked users' }}</span><strong>{{ loading ? '—' : departments.reduce((total, department) => total + (department.users?.length || 0), 0) }}</strong></article></div>

    <div v-if="showChurchSelector" class="management-surface management-create">
      <label class="form-label">
        {{ t.church }}
        <select v-model="selectedChurchId" class="form-select" @change="loadDepartments">
          <option value="">{{ common.select }}</option>
          <option v-for="church in churches" :key="church.id" :value="church.id">
            {{ church.name }}
          </option>
        </select>
      </label>
    </div>

    <form
      v-if="canCreate && selectedChurchId"
      class="management-surface management-create"
      @submit.prevent="createDepartment"
    >
      <h2 class="h5 mb-3">{{ t.createTitle }}</h2>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">
            {{ t.name }}
            <input v-model="createForm.name" class="form-control" type="text" required />
          </label>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            {{ t.color }}
            <input v-model="createForm.color" class="form-control form-control-color" type="color" />
          </label>
        </div>
        <div class="col-12">
          <label class="form-label">
            {{ t.userName }}
            <input v-model="createForm.user_name" class="form-control" type="text" />
          </label>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input id="dept-club" v-model="createForm.is_club" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="dept-club">
              {{ t.clubLabel }}
            </label>
          </div>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input id="dept-greeting" v-model="createForm.is_greeting" class="form-check-input" type="checkbox" />
            <label class="form-check-label" for="dept-greeting">
              {{ t.greetingLabel }}
            </label>
<label class="form-check d-flex gap-2"><input v-model="createForm.is_treasury" class="form-check-input" type="checkbox" /><span>{{ locale === 'es' ? 'Tesorería (publica asignaciones)' : 'Treasury (publishes allocations)' }}</span></label>
          </div>
        </div>
      </div>
      <button class="btn btn-primary mt-3" type="submit">{{ t.create }}</button>
    </form>

    <div v-if="loading" class="management-state" role="status">{{ t.loading }}</div>
    <p v-if="error" class="management-error" role="alert">{{ error }}</p>

    <div v-if="departments.length === 0 && !loading && selectedChurchId" class="management-state">{{ t.empty }}</div>
    <div v-else-if="selectedChurchId && !loading" class="management-surface">
      <div class="management-toolbar"><h2>{{ locale === 'es' ? 'Directorio de departamentos' : 'Department directory' }}</h2><p>{{ locale === 'es' ? 'Revisa los responsables y guarda los cambios de cada departamento.' : 'Review administrators and save changes for each department.' }}</p></div>
      <div class="table-responsive d-none d-md-block">
        <table class="table mb-0" data-dt="off">
        <thead>
          <tr>

            <th>{{ t.columns.name }}</th>
            <th>{{ t.columns.color }}</th>
            <th>{{ t.columns.userName }}</th>
            <th>
              {{ t.columns.users }}
              <span class="column-help" tabindex="0" :aria-label="locale === 'es' ? 'Información sobre usuarios del departamento' : 'About department users'" aria-describedby="department-users-help">i
                <span id="department-users-help" class="column-help-tooltip" role="tooltip">{{ locale === 'es' ? 'Personas asignadas al departamento que no son administradores. El responsable aparece en la columna Administrador.' : 'People assigned to this department who are not administrators. The responsible administrator appears in the Administrator column.' }}</span>
              </span>
            </th>
            <th>{{ t.columns.club }}</th>
            <th>{{ t.columns.greeting }}</th>
            <th>{{ locale === 'es' ? 'Tesorería' : 'Treasury' }}</th>
            <th class="text-end">{{ t.columns.actions }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="department in departments" :key="department.id">

            <td><input :aria-label="t.name" v-model="department.name" class="form-control" type="text" /></td>
            <td><input :aria-label="t.color" v-model="department.color" class="form-control form-control-color" type="color" /></td>
            <td>{{ adminNameForDepartment(department) }}</td>
            <td>
              <span v-if="memberNamesForDepartment(department)">
                {{ memberNamesForDepartment(department) }}
              </span>
              <span v-else>—</span>
            </td>
            <td>
              <input :aria-label="t.clubLabel" v-model="department.is_club" class="form-check-input" type="checkbox" />
            </td>
            <td>
              <input :aria-label="t.greetingLabel" v-model="department.is_greeting" class="form-check-input" type="checkbox" />
            </td>
            <td><input v-model="department.is_treasury" :aria-label="locale === 'es' ? 'Tesorería' : 'Treasury'" class="form-check-input" type="checkbox" /></td>
            <td class="text-end">
              <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateDepartment(department)">{{ t.save }}</button>
                <button
                  v-if="canDelete"
                  class="btn btn-sm btn-outline-danger"
                  type="button"
                  @click="confirmDeleteDepartmentEvents(department)"
                >
                  {{ t.deleteEvents }}
                </button>
                <button
                  v-if="canDelete"
                  class="btn btn-sm btn-outline-danger"
                  type="button"
                  @click="confirmDeleteDepartment(department)"
                >
                  {{ t.delete }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
        </table>
      </div>

      <div class="d-md-none p-3">
        <div v-for="department in departments" :key="department.id" class="card mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h5 class="card-title mb-1">{{ department.name }}</h5>
                <p class="card-subtitle text-muted mb-0">#{{ department.id }}</p>
              </div>
              <input :aria-label="t.color" v-model="department.color" class="form-control form-control-color" type="color" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.name }}</label>
              <input :aria-label="t.name" v-model="department.name" class="form-control" type="text" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.userName }}</label>
              <p class="mb-0">{{ adminNameForDepartment(department) }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.columns.users }}</label>
              <p class="mb-0">
                <span v-if="memberNamesForDepartment(department)">
                  {{ memberNamesForDepartment(department) }}
                </span>
                <span v-else>—</span>
              </p>
            </div>
            <div class="form-check mb-3">
              <input :id="`dept-club-${department.id}`" v-model="department.is_club" class="form-check-input" type="checkbox" />
              <label class="form-check-label" :for="`dept-club-${department.id}`">
                {{ t.clubLabel }}
              </label>
            </div>
            <div class="form-check mb-3">
              <input :id="`dept-greeting-${department.id}`" v-model="department.is_greeting" class="form-check-input" type="checkbox" />
              <label class="form-check-label" :for="`dept-greeting-${department.id}`">
                {{ t.greetingLabel }}
              </label>
<label class="form-check d-flex gap-2"><input v-model="department.is_treasury" class="form-check-input" type="checkbox" /><span>{{ locale === 'es' ? 'Tesorería (publica asignaciones)' : 'Treasury (publishes allocations)' }}</span></label>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateDepartment(department)">{{ t.save }}</button>
              <button
                v-if="canDelete"
                class="btn btn-sm btn-outline-danger"
                type="button"
                @click="confirmDeleteDepartmentEvents(department)"
              >
                {{ t.deleteEvents }}
              </button>
              <button
                v-if="canDelete"
                class="btn btn-sm btn-outline-danger"
                type="button"
                @click="confirmDeleteDepartment(department)"
              >
                {{ t.delete }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="confirmOpen" class="confirm-modal-backdrop" role="presentation">
      <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-action-title">
        <h2 id="confirm-action-title" class="confirm-modal-title">{{ confirmTitle }}</h2>
        <p class="confirm-modal-text">{{ confirmMessage }}</p>
        <div class="confirm-modal-actions">
          <button class="btn btn-outline-secondary" type="button" @click="closeConfirm">
            {{ common.cancel }}
          </button>
          <button class="btn btn-outline-danger" type="button" @click="runConfirmAction">
            {{ confirmActionLabel }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import '../../styles/clerk-management.css'
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { superAdminApi } from '../../services/superAdminApi'
import { secretaryApi } from '../../services/secretaryApi'
import { useAuthStore } from '../../stores/authStore'
import { useUiStore } from '../../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../../i18n/translations'

const churches = ref([])
const selectedChurchId = ref('')
const departments = ref([])
const loading = ref(false)
const error = ref('')
const confirmOpen = ref(false)
const confirmMessage = ref('')
const confirmActionLabel = ref('')
const confirmAction = ref(null)
const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].superadmin.departments)
const common = computed(() => translations[locale.value].common)
const confirmTitle = computed(() => t.value.confirmTitle || t.value.delete)
const isSuperAdmin = computed(() => authStore.user?.role === 'superadmin')
const isSecretary = computed(() => authStore.user?.role === 'secretary')
const showChurchSelector = computed(() => isSuperAdmin.value)
const canCreate = computed(() => isSuperAdmin.value)
const canDelete = computed(() => isSuperAdmin.value)
const showSuccessToast = (message = '') => {
  const fallback = locale.value === 'es' ? 'Guardado correctamente.' : 'Saved successfully.'
  uiStore.showToast(message || fallback, 'success')
}

const createForm = reactive({
  name: '',
  color: '',
  user_name: '',
  is_treasury: false,
  is_club: false,
  is_greeting: false,
})

const loadChurches = async () => {
  if (!isSuperAdmin.value) {
    return
  }
  const response = await superAdminApi.listChurches()
  churches.value = response.data
}

const loadDepartments = async () => {
  if (!selectedChurchId.value) {
    departments.value = []
    return
  }
  loading.value = true
  error.value = ''
  try {
    departments.value = isSecretary.value
      ? await secretaryApi.listDepartments()
      : await superAdminApi.listDepartments(selectedChurchId.value)
  } catch {
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const setSuccessMessage = (message = '') => {
  showSuccessToast(message)
}

const createDepartment = async () => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    const payload = {
      ...createForm,
      color: createForm.color || null,
      user_name: createForm.user_name || null,
      is_treasury: Boolean(createForm.is_treasury),
      is_club: Boolean(createForm.is_club),
      is_greeting: Boolean(createForm.is_greeting),
    }
    await superAdminApi.createDepartment(selectedChurchId.value, payload)
    createForm.name = ''
    createForm.color = ''
    createForm.user_name = ''
    createForm.is_treasury = false
    createForm.is_club = false
    createForm.is_greeting = false
    setSuccessMessage()
    await loadDepartments()
  } catch {
    error.value = t.value.createError
  }
}

const formatCountMessage = (template, count) => template.replace('{count}', count)

const deleteDepartmentEvents = async (department) => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    const response = await superAdminApi.deleteDepartmentEvents(selectedChurchId.value, department.id)
    const message = formatCountMessage(t.value.deleteEventsSuccess, response.deleted ?? 0)
    setSuccessMessage(message)
  } catch {
    error.value = t.value.deleteEventsError
  }
}

const adminNameForDepartment = (department) => {
  const admin = department.users?.find((user) => user.role === 'admin')
  return admin?.name || admin?.email || '—'
}

const memberNamesForDepartment = (department) => {
  const names = (department.users || [])
    .filter((user) => user.role !== 'admin')
    .map((user) => user.name || user.email)
    .filter(Boolean)
  return names.length ? names.join(', ') : ''
}

const openConfirm = (message, actionLabel, action) => {
  confirmMessage.value = message
  confirmActionLabel.value = actionLabel
  confirmAction.value = action
  confirmOpen.value = true
}

const closeConfirm = () => {
  confirmOpen.value = false
  confirmAction.value = null
}

const runConfirmAction = async () => {
  if (!confirmAction.value) {
    return
  }
  await confirmAction.value()
  closeConfirm()
}

const confirmDeleteDepartment = (department) => {
  const template = t.value.deleteConfirm
    || (locale.value === 'es'
      ? 'Esto eliminara {name}. Esta accion no se puede deshacer. Continuar?'
      : 'This will delete {name}. This cannot be undone. Continue?')
  const message = template.replace('{name}', department.name)
  openConfirm(message, t.value.delete, () => deleteDepartment(department))
}

const confirmDeleteDepartmentEvents = (department) => {
  const template = t.value.deleteEventsConfirm
    || (locale.value === 'es'
      ? 'Esto eliminara todos los eventos de {name}. Esta accion no se puede deshacer. Continuar?'
      : 'This will delete all events for {name}. This cannot be undone. Continue?')
  const message = template.replace('{name}', department.name)
  openConfirm(message, t.value.deleteEvents, () => deleteDepartmentEvents(department))
}

const updateDepartment = async (department) => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    const payload = {
      name: department.name,
      color: department.color || null,
      user_name: department.user_name || null,
      is_treasury: Boolean(department.is_treasury),
      is_club: Boolean(department.is_club),
      is_greeting: Boolean(department.is_greeting),
    }
    if (isSecretary.value) {
      await secretaryApi.updateDepartment(department.id, payload)
    } else {
      await superAdminApi.updateDepartment(selectedChurchId.value, department.id, payload)
    }
    setSuccessMessage()
  } catch {
    error.value = t.value.updateError
  }
}

const deleteDepartment = async (department) => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    await superAdminApi.deleteDepartment(selectedChurchId.value, department.id)
    await loadDepartments()
    setSuccessMessage()
  } catch {
    error.value = t.value.deleteError
  }
}

onMounted(async () => {
  await loadChurches()
  if (isSecretary.value && authStore.user?.church_id) {
    selectedChurchId.value = String(authStore.user.church_id)
    await loadDepartments()
  }
})

onUnmounted(() => {
  document.body.style.overflow = ''
})

watch(confirmOpen, (next) => {
  document.body.style.overflow = next ? 'hidden' : ''
})
</script>

<style scoped>
.column-help { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; margin-left: 4px; border: 1px solid #91a299; border-radius: 50%; color: #526d60; font-size: 11px; cursor: help; white-space: normal; }
.column-help:focus-visible { outline: 2px solid #1f6f5c; outline-offset: 3px; }
.column-help-tooltip { display: none; position: absolute; top: calc(100% + 8px); left: 50%; transform: translateX(-50%); z-index: 10; width: 240px; padding: 10px 12px; border-radius: 7px; background: #254b3d; color: #fff; font-size: 12px; font-weight: 400; line-height: 1.5; box-shadow: 0 4px 14px #0002; }
.column-help:hover .column-help-tooltip, .column-help:focus .column-help-tooltip { display: block; }
</style>
