<template>
  <section class="container-fluid py-4">
    <div class="row g-4">
      <div class="col-12">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
          <h1 class="h3 m-0">{{ t.title }}</h1>
        </div>

        <p v-if="inviteCode" class="alert alert-success">
          {{ t.latestInvite }} <strong>{{ inviteCode }}</strong>
        </p>

        <form class="bg-white border rounded p-4 mb-4" @submit.prevent="createChurch">
          <h2 class="h5 mb-3">{{ t.createTitle }}</h2>
          <div class="mb-3">
            <label class="form-label">
              {{ t.name }}
              <input v-model="createForm.name" class="form-control" type="text" required />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              {{ t.conference }}
              <input v-model="createForm.conference_name" class="form-control" type="text" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              {{ t.pastor }}
              <input v-model="createForm.pastor_name" class="form-control" type="text" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              {{ t.address }}
              <input v-model="createForm.address" class="form-control" type="text" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              {{ t.ethnicity }}
              <input v-model="createForm.ethnicity" class="form-control" type="text" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              {{ t.inviteRole }}
              <select v-model="createForm.invite_role" class="form-select">
                <option value="admin">{{ roleLabels.admin }}</option>
                <option value="member">{{ roleLabels.member }}</option>
                <option value="secretary">{{ roleLabels.secretary }}</option>
              </select>
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              {{ t.inviteEmail }}
              <input v-model="createForm.invite_email" class="form-control" type="email" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              {{ t.inviteMax }}
              <input v-model.number="createForm.invite_max_uses" class="form-control" type="number" min="1" />
            </label>
          </div>
          <div class="mb-3">
            <label class="form-label">
              {{ t.inviteExpires }}
              <input v-model="createForm.invite_expires_at" class="form-control" type="datetime-local" />
            </label>
          </div>
          <button class="btn btn-primary" type="submit">{{ t.create }}</button>
        </form>

        <div v-if="loading">{{ t.loading }}</div>
        <p v-if="error" class="text-danger">{{ error }}</p>
        <p v-if="success" class="text-success">{{ success }}</p>

        <div v-if="churches.length === 0 && !loading">{{ t.empty }}</div>
        <div v-else class="table-responsive bg-white border rounded">
          <table class="table mb-0" data-dt="off">
            <thead>
              <tr>
                <th>{{ t.columns.id }}</th>
                <th>{{ t.columns.name }}</th>
                <th>{{ t.columns.slug }}</th>
                <th>{{ t.columns.feedUrl }}</th>
                <th>{{ t.columns.latestInvite }}</th>
                <th class="text-end">{{ t.columns.actions }}</th>
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
                        {{ t.save }}
                      </button>
                      <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteChurchEvents(church)">
                        {{ t.deleteEvents }}
                      </button>
                      <button class="btn btn-sm btn-outline-danger" type="button" @click="deleteChurch(church)">
                        {{ t.delete }}
                      </button>
                      <button class="btn btn-sm btn-outline-primary" type="button" @click="generateInvite(church)">
                        {{ t.generateInvite }}
                      </button>
                      <router-link
                        v-if="church.latest_invitation?.code"
                        class="btn btn-sm btn-outline-success"
                        :to="{ path: '/superadmin/users', query: { invite: church.latest_invitation.code } }"
                      >
                        {{ t.users }}
                      </router-link>
                      <button v-else class="btn btn-sm btn-outline-success" type="button" disabled>
                        {{ t.users }}
                      </button>
                      <button class="btn btn-sm btn-outline-dark" type="button" @click="toggleDetails(church.id)">
                        {{ isExpanded(church.id) ? t.hide : t.details }}
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="isExpanded(church.id)">
                  <td colspan="6">
                    <div class="row g-3">
                      <div class="col-12 col-md-6">
                        <strong>{{ t.conference }}:</strong> {{ church.conference_name || '—' }}
                      </div>
                      <div class="col-12 col-md-6">
                        <strong>{{ t.pastor }}:</strong> {{ church.pastor_name || '—' }}
                      </div>
                      <div class="col-12 col-md-6">
                        <strong>{{ t.address }}:</strong> {{ church.address || '—' }}
                      </div>
                      <div class="col-12 col-md-6">
                        <strong>{{ t.ethnicity }}:</strong> {{ church.ethnicity || '—' }}
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
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { superAdminApi } from '../../services/superAdminApi'
import { useUiStore } from '../../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../../i18n/translations'

const churches = ref([])
const loading = ref(false)
const error = ref('')
const inviteCode = ref('')
const success = ref('')
const successTimer = ref(null)
const expandedIds = ref(new Set())
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].superadmin.churches)
const roleLabels = computed(() => translations[locale.value].appLayout.roleLabels)

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
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const createChurch = async () => {
  error.value = ''
  inviteCode.value = ''
  success.value = ''
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
    error.value = t.value.createError
  }
}

const updateChurch = async (church) => {
  error.value = ''
  success.value = ''
  try {
    await superAdminApi.updateChurch(church.id, { name: church.name })
  } catch {
    error.value = t.value.updateError
  }
}

const deleteChurch = async (church) => {
  error.value = ''
  success.value = ''
  try {
    await superAdminApi.deleteChurch(church.id)
    await loadChurches()
  } catch {
    error.value = t.value.deleteError
  }
}

const generateInvite = async (church) => {
  error.value = ''
  inviteCode.value = ''
  success.value = ''
  try {
    const response = await superAdminApi.generateInvite(church.id, { invite_role: 'admin' })
    inviteCode.value = response.code
    await loadChurches()
  } catch {
    error.value = t.value.inviteError
  }
}

const setSuccessMessage = (message) => {
  success.value = message
  if (successTimer.value) {
    clearTimeout(successTimer.value)
  }
  successTimer.value = setTimeout(() => {
    success.value = ''
    successTimer.value = null
  }, 3000)
}

const formatCountMessage = (template, count) => template.replace('{count}', count)

const deleteChurchEvents = async (church) => {
  error.value = ''
  success.value = ''
  const confirmMessage = t.value.deleteEventsConfirm.replace('{name}', church.name)
  if (!window.confirm(confirmMessage)) {
    return
  }
  try {
    const response = await superAdminApi.deleteChurchEvents(church.id)
    const message = formatCountMessage(t.value.deleteEventsSuccess, response.deleted ?? 0)
    setSuccessMessage(message)
  } catch {
    error.value = t.value.deleteEventsError
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

onUnmounted(() => {
  if (successTimer.value) {
    clearTimeout(successTimer.value)
  }
})
</script>
