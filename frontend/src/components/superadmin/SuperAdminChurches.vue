<template>
  <section class="container-fluid py-4">
    <div class="row g-4">
      <div class="col-12">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
          <h1 class="h3 m-0">Churches</h1>
        </div>

        <p v-if="inviteCode" class="alert alert-success">
          Latest invite: <strong>{{ inviteCode }}</strong>
        </p>

        <form class="bg-white border rounded p-4 mb-4" @submit.prevent="createChurch">
          <h2 class="h5 mb-3">Create Church</h2>
          <div class="mb-3">
            <label class="form-label">
              Name
              <input v-model="createForm.name" class="form-control" type="text" required />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Conference Name
              <input v-model="createForm.conference_name" class="form-control" type="text" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Pastor Name
              <input v-model="createForm.pastor_name" class="form-control" type="text" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Church Address
              <input v-model="createForm.address" class="form-control" type="text" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Ethnicity
              <input v-model="createForm.ethnicity" class="form-control" type="text" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Initial Invite Role
              <select v-model="createForm.invite_role" class="form-select">
                <option value="admin">admin</option>
                <option value="member">member</option>
                <option value="secretary">secretary</option>
              </select>
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Invite Email (optional)
              <input v-model="createForm.invite_email" class="form-control" type="email" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Invite Max Uses
              <input v-model.number="createForm.invite_max_uses" class="form-control" type="number" min="1" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Invite Expires At (optional)
              <input v-model="createForm.invite_expires_at" class="form-control" type="datetime-local" />
            </label>
          </div>
          <button class="btn btn-primary" type="submit">Create</button>
        </form>

        <div v-if="loading">Loading churches...</div>
        <p v-if="error" class="text-danger">{{ error }}</p>

        <div v-if="churches.length === 0 && !loading">No churches yet.</div>
        <div v-else class="table-responsive bg-white border rounded">
          <table class="table mb-0" data-dt="off">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Feed URL</th>
                <th>Invite Code</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="church in churches" :key="church.id">
                <tr>
                  <td>{{ church.id }}</td>
                  <td>
                    <input v-model="church.name" class="form-control" type="text" />
                  </td>
                  <td>{{ church.slug || '—' }}</td>
                  <td>
                    <input
                      v-if="church.slug"
                      class="form-control form-control-sm"
                      type="text"
                      :value="`https://www.mychurchadmin.net/api/public/churches/${church.slug}/events.ics`"
                      readonly
                    />
                    <span v-else>—</span>
                  </td>
                  <td>{{ church.latest_invitation?.code || '—' }}</td>
                  <td class="text-end">
                    <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
                      <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateChurch(church)">
                        Save
                      </button>
                      <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteChurch(church)">
                        Delete
                      </button>
                      <button class="btn btn-sm btn-outline-primary" type="button" @click="generateInvite(church)">
                        Generate Invite
                      </button>
                      <router-link
                        v-if="church.latest_invitation?.code"
                        class="btn btn-sm btn-outline-success"
                        :to="{ path: '/superadmin/users', query: { invite: church.latest_invitation.code } }"
                      >
                        Users
                      </router-link>
                      <button v-else class="btn btn-sm btn-outline-success" type="button" disabled>
                        Users
                      </button>
                      <button class="btn btn-sm btn-outline-dark" type="button" @click="toggleDetails(church.id)">
                        {{ isExpanded(church.id) ? 'Hide' : 'Details' }}
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="isExpanded(church.id)">
                  <td colspan="6">
                    <div class="row g-3">
                      <div class="col-12 col-md-6">
                        <strong>Conference Name:</strong> {{ church.conference_name || '—' }}
                      </div>
                      <div class="col-12 col-md-6">
                        <strong>Pastor Name:</strong> {{ church.pastor_name || '—' }}
                      </div>
                      <div class="col-12 col-md-6">
                        <strong>Address:</strong> {{ church.address || '—' }}
                      </div>
                      <div class="col-12 col-md-6">
                        <strong>Ethnicity:</strong> {{ church.ethnicity || '—' }}
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { superAdminApi } from '../../services/superAdminApi'

const churches = ref([])
const loading = ref(false)
const error = ref('')
const inviteCode = ref('')
const expandedIds = ref(new Set())

const createForm = reactive({
  name: '',
  conference_name: '',
  pastor_name: '',
  address: '',
  ethnicity: '',
  invite_role: 'admin',
  invite_email: '',
  invite_max_uses: 1,
  invite_expires_at: '',
})

const loadChurches = async () => {
  loading.value = true
  error.value = ''
  try {
    const response = await superAdminApi.listChurches()
    churches.value = response.data
  } catch {
    error.value = 'Unable to load churches.'
  } finally {
    loading.value = false
  }
}

const createChurch = async () => {
  error.value = ''
  inviteCode.value = ''
  try {
    const payload = {
      ...createForm,
      invite_email: createForm.invite_email || null,
      invite_expires_at: createForm.invite_expires_at || null,
    }
    const response = await superAdminApi.createChurch(payload)
    inviteCode.value = response.invitation.code
    createForm.name = ''
    createForm.conference_name = ''
    createForm.pastor_name = ''
    createForm.address = ''
    createForm.ethnicity = ''
    await loadChurches()
  } catch {
    error.value = 'Unable to create church.'
  }
}

const updateChurch = async (church) => {
  error.value = ''
  try {
    await superAdminApi.updateChurch(church.id, { name: church.name })
  } catch {
    error.value = 'Unable to update church.'
  }
}

const deleteChurch = async (church) => {
  error.value = ''
  try {
    await superAdminApi.deleteChurch(church.id)
    await loadChurches()
  } catch {
    error.value = 'Unable to delete church.'
  }
}

const generateInvite = async (church) => {
  error.value = ''
  inviteCode.value = ''
  try {
    const response = await superAdminApi.generateInvite(church.id, { invite_role: 'admin' })
    inviteCode.value = response.code
    await loadChurches()
  } catch {
    error.value = 'Unable to generate invite.'
  }
}

const toggleDetails = (id) => {
  const next = new Set(expandedIds.value)
  if (next.has(id)) {
    next.delete(id)
  } else {
    next.add(id)
  }
  expandedIds.value = next
}

const isExpanded = (id) => expandedIds.value.has(id)

onMounted(loadChurches)
</script>
