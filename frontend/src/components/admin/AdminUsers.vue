<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">{{ t.title }}</h1>
    </div>

    <form class="bg-white border rounded p-4 mb-4" @submit.prevent="createUser">
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
            {{ t.email }}
            <input v-model="createForm.email" class="form-control" type="email" required />
          </label>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            {{ t.password }}
            <input v-model="createForm.password" class="form-control" type="password" required />
          </label>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            {{ t.role }}
            <select v-model="createForm.role" class="form-select">
              <option value="admin">{{ roleLabels.admin }}</option>
              <option value="member">{{ roleLabels.member }}</option>
              <option value="secretary">{{ roleLabels.secretary }}</option>
            </select>
          </label>
        </div>
        <div class="col-12">
          <label class="form-label">
            {{ t.department }}
            <select v-model="createForm.department_id" class="form-select" :disabled="departments.length === 0">
              <option value="">{{ common.select }}</option>
              <option v-for="department in departments" :key="department.id" :value="department.id">
                {{ department.name }}
              </option>
            </select>
          </label>
        </div>
      </div>
      <button class="btn btn-primary mt-3" type="submit">{{ t.create }}</button>
    </form>

    <div v-if="loading">{{ t.loading }}</div>
    <p v-if="error" class="text-danger">{{ error }}</p>

    <div v-if="users.length === 0 && !loading">{{ t.empty }}</div>
    <div v-else class="bg-white border rounded p-3">
      <div class="row g-2 align-items-center mb-3">
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
              <th>{{ t.columns.id }}</th>
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
              <td>{{ user.id }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <input v-model="user.name" class="form-control" type="text" />
                  <span v-if="user.id === currentUserId" class="badge bg-info text-dark">{{ common.loggedIn }}</span>
                </div>
              </td>
              <td><input v-model="user.email" class="form-control" type="email" /></td>
              <td>
                <select v-model="user.role" class="form-select">
                  <option value="admin">{{ roleLabels.admin }}</option>
                  <option value="member">{{ roleLabels.member }}</option>
                  <option value="secretary">{{ roleLabels.secretary }}</option>
                </select>
              </td>
              <td>
                <select v-model="user.department_id" class="form-select">
                  <option value="">{{ common.select }}</option>
                  <option v-for="department in departments" :key="department.id" :value="department.id">
                    {{ department.name }}
                  </option>
                </select>
              </td>
              <td>
                <button class="btn btn-sm btn-outline-primary" type="button" @click="openPasswordModal(user)">
                  {{ t.changePassword }}
                </button>
              </td>
              <td class="text-end">
                <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
                  <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateUser(user)">{{ t.save }}</button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteUser(user)">{{ t.delete }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-md-none">
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
              <input v-model="user.email" class="form-control" type="email" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.name }}</label>
              <input v-model="user.name" class="form-control" type="text" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.role }}</label>
              <select v-model="user.role" class="form-select">
                <option value="admin">{{ roleLabels.admin }}</option>
                <option value="member">{{ roleLabels.member }}</option>
                <option value="secretary">{{ roleLabels.secretary }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">{{ t.department }}</label>
              <select v-model="user.department_id" class="form-select">
                <option value="">{{ common.select }}</option>
                <option v-for="department in departments" :key="department.id" :value="department.id">
                  {{ department.name }}
                </option>
              </select>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <button class="btn btn-sm btn-outline-primary" type="button" @click="openPasswordModal(user)">
                {{ t.changePassword }}
              </button>
            </div>
            <div class="d-flex flex-wrap gap-2">
                  <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateUser(user)">{{ t.save }}</button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteUser(user)">{{ t.delete }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

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
import { computed, onMounted, reactive, ref } from 'vue'
import { adminApi } from '../../services/adminApi'
import { useAuthStore } from '../../stores/authStore'
import { useUiStore } from '../../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../../i18n/translations'

const departments = ref([])
const users = ref([])
const loading = ref(false)
const error = ref('')
const filterText = ref('')
const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const passwordModalOpen = ref(false)
const activeUser = ref(null)
const passwordForm = reactive({
  password: '',
})
const passwordSuccessOpen = ref(false)

const createForm = reactive({
  name: '',
  email: '',
  password: '',
  role: 'member',
  department_id: '',
})

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
    users.value = await adminApi.listUsers()
  } catch {
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const loadDepartments = async () => {
  try {
    departments.value = await adminApi.listDepartments()
  } catch {
    departments.value = []
  }
}

const createUser = async () => {
  error.value = ''
  try {
    const payload = {
      ...createForm,
      department_id: createForm.department_id || null,
    }
    await adminApi.createUser(payload)
    createForm.name = ''
    createForm.email = ''
    createForm.password = ''
    createForm.role = 'member'
    createForm.department_id = ''
    await loadUsers()
  } catch {
    error.value = t.value.createError
  }
}

const updateUser = async (user) => {
  error.value = ''
  try {
    await adminApi.updateUser(user.id, {
      name: user.name,
      email: user.email,
      role: user.role,
      department_id: user.department_id || null,
    })
  } catch {
    error.value = t.value.updateError
  }
}

const deleteUser = async (user) => {
  error.value = ''
  try {
    await adminApi.deleteUser(user.id)
    await loadUsers()
  } catch {
    error.value = t.value.deleteError
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
    await adminApi.updateUser(activeUser.value.id, {
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
