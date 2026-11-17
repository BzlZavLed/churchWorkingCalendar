<template>
  <section class="container py-4 management-page">
    <header class="management-heading">
      <p class="management-eyebrow">{{ authStore.user?.church?.name || (locale === 'es' ? 'Administración' : 'Administration') }}</p>
      <h1>{{ t.title }}</h1>
      <p>{{ locale === 'es' ? 'Administra los usuarios, sus roles y su departamento.' : 'Manage users, their roles and department.' }}</p>
    </header>
    <details v-write="'users.manage'" v-if="authStore.can('users.manage')" class="management-surface management-create mb-3">
      <summary>{{ locale === 'es' ? 'Crear usuario' : 'Create user' }}</summary>
      <form class="row g-3 mt-1" @submit.prevent="createUser">
        <label class="col-12 col-md-6">{{ t.name }}<input v-model="createForm.name" class="form-control" required maxlength="255" autocomplete="name" /></label>
        <label class="col-12 col-md-6">{{ t.email }}<input v-model="createForm.email" class="form-control" type="email" required maxlength="255" autocomplete="off" /></label>
        <label class="col-12 col-md-6">{{ t.password }}<input v-model="createForm.password" class="form-control" type="password" required minlength="8" autocomplete="new-password" /></label>
        <label class="col-12 col-md-6">{{ t.role }}<select v-model="createForm.role" class="form-select"><option v-for="role in ['admin', 'member', 'secretary', 'pastor']" :key="role" :value="role">{{ roleLabels[role] }}</option></select></label>
        <label class="col-12">{{ t.department }}<select v-model="createForm.department_id" class="form-select"><option value="">{{ common.select }}</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
        <p v-if="createError" class="management-error col-12" role="alert">{{ createError }}</p>
        <p v-if="createSuccess" class="text-success col-12" role="status">{{ locale === 'es' ? 'Usuario creado.' : 'User created.' }}</p>
        <div class="col-12"><button class="btn btn-primary" :disabled="creating || loading">{{ creating ? (locale === 'es' ? 'Guardando…' : 'Saving…') : (locale === 'es' ? 'Crear usuario' : 'Create user') }}</button></div>
      </form>
    </details>
    <div class="management-summary"><article><span>{{ t.title }}</span><strong>{{ loading ? '—' : users.length }}</strong></article>
      <article><span>{{ locale === 'es' ? 'Con departamento' : 'Assigned to a department' }}</span><strong>{{ loading ? '—' : users.filter(user => user.department_id).length }}</strong></article>
      <article><span>{{ locale === 'es' ? 'Administradores' : 'Administrators' }}</span><strong>{{ loading ? '—' : users.filter(user => user.role === 'admin').length }}</strong></article></div>

    <div v-if="loading" class="management-state" role="status">{{ t.loading }}</div>
    <p v-if="error" class="management-error" role="alert">{{ error }}</p>

    <div v-if="users.length === 0 && !loading" class="management-state">{{ t.empty }}</div>
    <div v-else-if="!loading" class="management-surface">
      <div class="row g-2 align-items-center m-0 management-toolbar">
        <div class="col-12 col-md-6">
          <label class="form-label mb-0 w-100">
            <span class="d-block small mb-1">{{ t.search }}</span>
            <input v-model="filterText" class="form-control" type="search" :placeholder="t.searchPlaceholder" />
          </label>
        </div>
      </div>

      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0" data-dt="off">
          <thead>
            <tr>

              <th>{{ t.columns.name }}</th>
              <th>{{ t.columns.email }}</th>
              <th>{{ t.columns.role }}</th>
              <th>{{ t.columns.department }}</th>
              <th>{{ t.columns.password }}</th>
              <th class="text-end">{{ t.columns.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">

              <td>
                <div class="d-flex align-items-center gap-2">
                  <input v-model="user.name" :aria-label="t.name" class="form-control" type="text" :disabled="!authStore.can('users.manage')" />
                  <span v-if="user.id === currentUserId" class="badge bg-info text-dark">{{ common.loggedIn }}</span>
                </div>
              </td>
              <td><input v-model="user.email" :aria-label="t.email" class="form-control" type="email" :disabled="!authStore.can('users.manage')" /></td>
              <td>
                <select :aria-label="t.role" v-model="user.role" class="form-select" :disabled="!authStore.can('users.manage')">
                  <option value="admin">{{ roleLabels.admin }}</option>
                  <option value="member">{{ roleLabels.member }}</option>
                  <option value="secretary">{{ roleLabels.secretary }}</option><option value="pastor">{{ roleLabels.pastor }}</option>
                </select>
              </td>
              <td>
                <select :aria-label="t.department" v-model="user.department_id" class="form-select" :disabled="!authStore.can('users.manage')">
                  <option value="">{{ common.select }}</option>
                  <option v-for="department in departments" :key="department.id" :value="department.id">
                    {{ department.name }}
                  </option>
                </select>
              </td>
              <td>
                <button v-write="'users.manage'" class="btn btn-sm btn-outline-primary" type="button" @click="openPasswordModal(user)">
                  {{ t.changePassword }}
                </button>
              </td>
              <td class="text-end">
                <button v-write="'users.manage'" class="btn btn-sm btn-outline-secondary" type="button" @click="updateUser(user)">{{ t.save }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-md-none p-3">
        <div v-for="user in filteredUsers" :key="user.id" class="card mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h5 class="card-title mb-1">{{ user.name }}</h5>
                <p class="card-subtitle text-muted mb-0">#{{ user.id }}</p>
              </div>
              <div class="d-flex flex-column align-items-end gap-1">
                <span class="badge bg-secondary">{{ roleLabels[user.role] || user.role }}</span>
                <span v-if="user.id === currentUserId" class="badge bg-info text-dark">{{ common.loggedIn }}</span>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.email }}</label>
              <input v-model="user.email" :aria-label="t.email" class="form-control" type="email" :disabled="!authStore.can('users.manage')" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.name }}</label>
              <input v-model="user.name" :aria-label="t.name" class="form-control" type="text" :disabled="!authStore.can('users.manage')" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.role }}</label>
              <select :aria-label="t.role" v-model="user.role" class="form-select" :disabled="!authStore.can('users.manage')">
                <option value="admin">{{ roleLabels.admin }}</option>
                <option value="member">{{ roleLabels.member }}</option>
                <option value="secretary">{{ roleLabels.secretary }}</option><option value="pastor">{{ roleLabels.pastor }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">{{ t.department }}</label>
              <select :aria-label="t.department" v-model="user.department_id" class="form-select" :disabled="!authStore.can('users.manage')">
                <option value="">{{ common.select }}</option>
                <option v-for="department in departments" :key="department.id" :value="department.id">
                  {{ department.name }}
                </option>
              </select>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <button v-write="'users.manage'" class="btn btn-sm btn-outline-primary" type="button" @click="openPasswordModal(user)">
                {{ t.changePassword }}
              </button>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button v-write="'users.manage'" class="btn btn-sm btn-outline-secondary" type="button" @click="updateUser(user)">{{ t.save }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p v-if="!loading && users.length && !filteredUsers.length" class="management-state">{{ locale === 'es' ? 'No hay usuarios que coincidan con la búsqueda.' : 'No users match your search.' }}</p>
    <div v-if="passwordModalOpen && activeUser" class="modal-backdrop" @click.self="closePasswordModal">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.updatePassword }}</h2>
          <button type="button" class="modal-close" @click="closePasswordModal">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-text">
            <strong>{{ t.title }}:</strong> {{ activeUser.name }} ({{ activeUser.email }})
          </p>
          <label class="form-label mt-2">
            {{ t.password }}
            <input v-model="passwordForm.password" class="form-control" type="password" minlength="8" />
          </label>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline-secondary" @click="closePasswordModal">{{ common.cancel }}</button>
          <button
            type="button"
            class="btn btn-outline-primary"
            :disabled="!passwordForm.password"
            @click="submitPasswordUpdate"
          >
            {{ t.updatePassword }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="passwordSuccessOpen" class="modal-backdrop" @click.self="passwordSuccessOpen = false">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.passwordSuccessTitle }}</h2>
          <button type="button" class="modal-close" @click="passwordSuccessOpen = false">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-text">{{ t.passwordSuccessBody }}</p>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline-primary" @click="passwordSuccessOpen = false">{{ t.close }}</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import '../../styles/clerk-management.css'
import { computed, onMounted, reactive, ref } from 'vue'
import { publicApi } from '../../services/publicApi'
import { secretaryApi } from '../../services/secretaryApi'
import { useAuthStore } from '../../stores/authStore'
import { useUiStore } from '../../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../../i18n/translations'

const departments = ref([])
const users = ref([])
const loading = ref(false)
const error = ref('')
const filterText = ref('')
const createForm = reactive({ name: '', email: '', password: '', role: 'member', department_id: '' })
const creating = ref(false)
const createError = ref('')
const createSuccess = ref(false)
const createUser = async () => {
  if (creating.value) return
  creating.value = true
  createError.value = ''
  createSuccess.value = false
  try {
    await secretaryApi.createUser({ ...createForm, department_id: createForm.department_id || null })
    Object.assign(createForm, { name: '', email: '', password: '', role: 'member', department_id: '' })
    await loadUsers()
    createSuccess.value = true
  } catch (err) {
    createError.value = Object.values(err.response?.data?.errors || {}).flat().join(' ') || err.response?.data?.message || (locale.value === 'es' ? 'No se pudo crear el usuario.' : 'Unable to create user.')
  } finally {
    creating.value = false
  }
}
const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const passwordModalOpen = ref(false)
const activeUser = ref(null)
const passwordForm = reactive({
  password: '',
})
const passwordSuccessOpen = ref(false)

const t = computed(() => translations[locale.value].adminUsers)
const common = computed(() => translations[locale.value].common)
const roleLabels = computed(() => translations[locale.value].appLayout.roleLabels)

const filteredUsers = computed(() => {
  const term = filterText.value.trim().toLowerCase()
  if (!term) {
    return users.value
  }
  return users.value.filter((user) => {
    const haystack = [
      user.name,
      user.email,
      user.role,
      user.department?.name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return haystack.includes(term)
  })
})

const currentUserId = computed(() => authStore.user?.id || null)

const loadUsers = async () => {
  loading.value = true
  error.value = ''
  try {
    users.value = await secretaryApi.listUsers()
  } catch {
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const loadDepartments = async () => {
  try {
    departments.value = await publicApi.listDepartments(authStore.user.church_id)
  } catch {
    departments.value = []
  }
}

const updateUser = async (user) => {
  error.value = ''
  try {
    await secretaryApi.updateUser(user.id, {
      name: user.name,
      email: user.email,
      role: user.role,
      department_id: user.department_id || null,
    })
  } catch {
    error.value = t.value.updateError
  }
}

const openPasswordModal = (user) => {
  activeUser.value = user
  passwordForm.password = ''
  passwordModalOpen.value = true
}

const closePasswordModal = () => {
  passwordModalOpen.value = false
  activeUser.value = null
  passwordForm.password = ''
}

const submitPasswordUpdate = async () => {
  if (!activeUser.value || !passwordForm.password) {
    return
  }
  error.value = ''
  try {
    await secretaryApi.updateUser(activeUser.value.id, {
      password: passwordForm.password,
    })
    closePasswordModal()
    passwordSuccessOpen.value = true
  } catch {
    error.value = t.value.passwordError
  }
}

onMounted(async () => {
  await loadDepartments()
  await loadUsers()
})
</script>
