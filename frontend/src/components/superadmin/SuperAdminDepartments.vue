<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">{{ t.title }}</h1>
    </div>

    <div v-if="showChurchSelector" class="bg-white border rounded p-3 mb-4">
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
      class="bg-white border rounded p-4 mb-4"
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
      </div>
      <button class="btn btn-primary mt-3" type="submit">{{ t.create }}</button>
    </form>

    <div v-if="loading">{{ t.loading }}</div>
    <p v-if="error" class="text-danger">{{ error }}</p>

    <div v-if="departments.length === 0 && !loading && selectedChurchId">{{ t.empty }}</div>
    <div v-else-if="selectedChurchId" class="bg-white border rounded">
      <div class="table-responsive d-none d-md-block">
        <table class="table mb-0" data-dt="off">
        <thead>
          <tr>
            <th>{{ t.columns.id }}</th>
            <th>{{ t.columns.name }}</th>
            <th>{{ t.columns.color }}</th>
            <th>{{ t.columns.userName }}</th>
            <th>{{ t.columns.users }}</th>
            <th>{{ t.columns.club }}</th>
            <th class="text-end">{{ t.columns.actions }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="department in departments" :key="department.id">
            <td>{{ department.id }}</td>
            <td><input v-model="department.name" class="form-control" type="text" /></td>
            <td><input v-model="department.color" class="form-control form-control-color" type="color" /></td>
            <td>{{ adminNameForDepartment(department) }}</td>
            <td>
              <span v-if="memberNamesForDepartment(department)">
                {{ memberNamesForDepartment(department) }}
              </span>
              <span v-else>—</span>
            </td>
            <td>
              <input v-model="department.is_club" class="form-check-input" type="checkbox" />
            </td>
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
              <input v-model="department.color" class="form-control form-control-color" type="color" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.name }}</label>
              <input v-model="department.name" class="form-control" type="text" />
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
  is_club: false,
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
      is_club: Boolean(createForm.is_club),
    }
    await superAdminApi.createDepartment(selectedChurchId.value, payload)
    createForm.name = ''
    createForm.color = ''
    createForm.user_name = ''
    createForm.is_club = false
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
      is_club: Boolean(department.is_club),
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
