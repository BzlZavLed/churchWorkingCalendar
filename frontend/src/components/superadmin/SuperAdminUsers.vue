<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">Users</h1>
    </div>

    <div class="bg-white border rounded p-3 mb-4">
      <label class="form-label">
        Invitation Code
        <div class="d-flex flex-column flex-md-row gap-2">
          <input v-model="inviteCode" class="form-control" type="text" />
          <button class="btn btn-outline-primary" type="button" @click="lookupInvite">Check Code</button>
        </div>
      </label>
      <p v-if="inviteStatus === 'active'" class="text-success mb-0">
        Invite active for: {{ inviteChurchName }}
      </p>
      <p v-else-if="inviteStatus === 'inactive'" class="text-danger mb-0">Invite is inactive.</p>
      <p v-else-if="inviteStatus === 'not_found'" class="text-danger mb-0">Invite not found.</p>
    </div>

    <form v-if="selectedChurchId" class="bg-white border rounded p-4 mb-4" @submit.prevent="createUser">
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
              <option value="manager">manager</option>
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

    <div v-if="users.length === 0 && !loading && selectedChurchId">No users yet.</div>
    <div v-else-if="selectedChurchId" class="table-responsive bg-white border rounded">
      <table class="table mb-0" data-dt="off">
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
          <tr v-for="user in users" :key="user.id">
            <td>{{ user.id }}</td>
            <td><input v-model="user.name" class="form-control" type="text" /></td>
            <td><input v-model="user.email" class="form-control" type="email" /></td>
            <td>
              <select v-model="user.role" class="form-select">
                <option value="admin">admin</option>
                <option value="manager">manager</option>
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
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { superAdminApi } from '../../services/superAdminApi'
import { publicApi } from '../../services/publicApi'

const route = useRoute()
const selectedChurchId = ref('')
const inviteCode = ref('')
const inviteStatus = ref('')
const inviteChurchName = ref('')
const departments = ref([])
const users = ref([])
const loading = ref(false)
const error = ref('')
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

const loadUsers = async () => {
  if (!selectedChurchId.value) {
    users.value = []
    return
  }
  loading.value = true
  error.value = ''
  try {
    users.value = await superAdminApi.listUsers(selectedChurchId.value)
  } catch {
    error.value = 'Unable to load users.'
  } finally {
    loading.value = false
  }
}

const loadDepartments = async (churchId) => {
  if (!churchId) {
    departments.value = []
    return
  }
  try {
    departments.value = await superAdminApi.listDepartments(churchId)
  } catch {
    departments.value = []
  }
}

const lookupInvite = async () => {
  error.value = ''
  inviteStatus.value = ''
  inviteChurchName.value = ''
  selectedChurchId.value = ''
  users.value = []
  departments.value = []

  if (!inviteCode.value) {
    inviteStatus.value = 'not_found'
    return
  }

  try {
    const response = await publicApi.lookupInvitation(inviteCode.value)
    inviteStatus.value = response.status
    selectedChurchId.value = response.church?.id || ''
    inviteChurchName.value = response.church?.name || ''
    if (inviteStatus.value === 'active' && selectedChurchId.value) {
      await loadDepartments(selectedChurchId.value)
      await loadUsers()
    }
  } catch (err) {
    if (err?.response?.status === 404) {
      inviteStatus.value = 'not_found'
    } else {
      inviteStatus.value = 'inactive'
    }
  }
}

const createUser = async () => {
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    const payload = {
      ...createForm,
      department_id: createForm.department_id || null,
    }
    await superAdminApi.createUser(selectedChurchId.value, payload)
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
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    await superAdminApi.updateUser(selectedChurchId.value, user.id, {
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
  if (!selectedChurchId.value) {
    return
  }
  error.value = ''
  try {
    await superAdminApi.deleteUser(selectedChurchId.value, user.id)
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
  if (!selectedChurchId.value || !activeUser.value || !passwordForm.password) {
    return
  }
  error.value = ''
  try {
    await superAdminApi.updateUserPassword(selectedChurchId.value, activeUser.value.id, {
      password: passwordForm.password,
    })
    closePasswordModal()
    passwordSuccessOpen.value = true
  } catch {
    error.value = 'Unable to update password.'
  }
}

const normalizeInvite = (value) => {
  if (Array.isArray(value)) {
    return value[0] || ''
  }
  return value || ''
}

const applyInviteFromRoute = async (value) => {
  const code = normalizeInvite(value)
  if (!code) {
    return
  }
  inviteCode.value = code
  await lookupInvite()
}

onMounted(async () => {
  await applyInviteFromRoute(route.query.invite)
})

watch(
  () => route.query.invite,
  async (next) => {
    await applyInviteFromRoute(next)
  }
)
</script>
