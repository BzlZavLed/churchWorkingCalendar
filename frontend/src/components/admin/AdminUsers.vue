<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">Users</h1>
    </div>

    <form class="bg-white border rounded p-4 mb-4" @submit.prevent="createUser">
      <h2 class="h5 mb-3">Create User</h2>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">
            Name
            <input v-model="createForm.name" class="form-control" type="text" required />
          </label>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            Email
            <input v-model="createForm.email" class="form-control" type="email" required />
          </label>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            Password
            <input v-model="createForm.password" class="form-control" type="password" required />
          </label>
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">
            Role
            <select v-model="createForm.role" class="form-select">
              <option value="admin">admin</option>
              <option value="member">member</option>
              <option value="secretary">secretary</option>
            </select>
          </label>
        </div>
        <div class="col-12">
          <label class="form-label">
            Department (optional)
            <select v-model="createForm.department_id" class="form-select" :disabled="departments.length === 0">
              <option value="">Select...</option>
              <option v-for="department in departments" :key="department.id" :value="department.id">
                {{ department.name }}
              </option>
            </select>
          </label>
        </div>
      </div>
      <button class="btn btn-primary mt-3" type="submit">Create User</button>
    </form>

    <div v-if="loading">Loading users...</div>
    <p v-if="error" class="text-danger">{{ error }}</p>

    <div v-if="users.length === 0 && !loading">No users yet.</div>
    <div v-else class="bg-white border rounded p-3">
      <div class="row g-2 align-items-center mb-3">
        <div class="col-12 col-md-6">
          <label class="form-label mb-0 w-100">
            <span class="d-block small mb-1">Search</span>
            <input v-model="filterText" class="form-control" type="search" placeholder="Search by name, email, or role" />
          </label>
        </div>
      </div>

      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0" data-dt="off">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Department</th>
              <th>Security</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td>{{ user.id }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <input v-model="user.name" class="form-control" type="text" />
                  <span v-if="user.id === currentUserId" class="badge bg-info text-dark">Logged in</span>
                </div>
              </td>
              <td><input v-model="user.email" class="form-control" type="email" /></td>
              <td>
                <select v-model="user.role" class="form-select">
                  <option value="admin">admin</option>
                  <option value="member">member</option>
                  <option value="secretary">secretary</option>
                </select>
              </td>
              <td>
                <select v-model="user.department_id" class="form-select">
                  <option value="">Select...</option>
                  <option v-for="department in departments" :key="department.id" :value="department.id">
                    {{ department.name }}
                  </option>
                </select>
              </td>
              <td>
                <button class="btn btn-sm btn-outline-primary" type="button" @click="openPasswordModal(user)">
                  Change password
                </button>
              </td>
              <td class="text-end">
                <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
                  <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateUser(user)">Save</button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteUser(user)">Delete</button>
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
                <span class="badge bg-secondary">{{ user.role }}</span>
                <span v-if="user.id === currentUserId" class="badge bg-info text-dark">Logged in</span>
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">Email</label>
              <input v-model="user.email" class="form-control" type="email" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">Name</label>
              <input v-model="user.name" class="form-control" type="text" />
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">Role</label>
              <select v-model="user.role" class="form-select">
                <option value="admin">admin</option>
                <option value="member">member</option>
                <option value="secretary">secretary</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small mb-1">Department</label>
              <select v-model="user.department_id" class="form-select">
                <option value="">Select...</option>
                <option v-for="department in departments" :key="department.id" :value="department.id">
                  {{ department.name }}
                </option>
              </select>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2">
              <button class="btn btn-sm btn-outline-primary" type="button" @click="openPasswordModal(user)">
                Change password
              </button>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateUser(user)">Save</button>
              <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteUser(user)">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="passwordModalOpen && activeUser" class="modal-backdrop" @click.self="closePasswordModal">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>Update password</h2>
          <button type="button" class="modal-close" @click="closePasswordModal">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-text">
            <strong>User:</strong> {{ activeUser.name }} ({{ activeUser.email }})
          </p>
          <label class="form-label mt-2">
            New password
            <input v-model="passwordForm.password" class="form-control" type="password" minlength="8" />
          </label>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline-secondary" @click="closePasswordModal">Cancel</button>
          <button
            type="button"
            class="btn btn-outline-primary"
            :disabled="!passwordForm.password"
            @click="submitPasswordUpdate"
          >
            Save password
          </button>
        </div>
      </div>
    </div>

    <div v-if="passwordSuccessOpen" class="modal-backdrop" @click.self="passwordSuccessOpen = false">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>Password updated</h2>
          <button type="button" class="modal-close" @click="passwordSuccessOpen = false">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-text">The password was updated successfully.</p>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline-primary" @click="passwordSuccessOpen = false">
            Close
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { adminApi } from '../../services/adminApi'
import { useAuthStore } from '../../stores/authStore'

const departments = ref([])
const users = ref([])
const loading = ref(false)
const error = ref('')
const filterText = ref('')
const authStore = useAuthStore()
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
    error.value = 'Unable to load users.'
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
    error.value = 'Unable to create user.'
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
    error.value = 'Unable to update user.'
  }
}

const deleteUser = async (user) => {
  error.value = ''
  try {
    await adminApi.deleteUser(user.id)
    await loadUsers()
  } catch {
    error.value = 'Unable to delete user.'
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
    error.value = 'Unable to update password.'
  }
}

onMounted(async () => {
  await loadDepartments()
  await loadUsers()
})
</script>
