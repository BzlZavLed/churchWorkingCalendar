<template>
  <section>
    <h1>Register With Invite</h1>
    <form @submit.prevent="submit">
      <label>
        Invitation Code
        <div>
          <input v-model="form.invite_code" type="text" required /><br><br>
      <button class="btn-accent" type="button" @click="lookupInvite">Check Code</button>
        </div>
      </label>
      <p v-if="inviteStatus === 'active'">Invite active for: {{ inviteChurchName }}</p>
      <p v-else-if="inviteStatus === 'inactive'">Invite is inactive.</p>
      <p v-else-if="inviteStatus === 'not_found'">Invite not found.</p>

      <div v-if="inviteStatus === 'active'">
        <label>
          Church
          <input type="text" :value="inviteChurchName" readonly />
        </label>
        <label>
          Department (optional)
          <select v-model="form.department_id">
            <option value="">Select...</option>
            <option v-for="department in departments" :key="department.id" :value="department.id">
              {{ department.name }}
            </option>
          </select>
        </label>
        <label>
          Name
          <input v-model="form.name" type="text" required />
        </label>
        <label>
          Email
          <input v-model="form.email" type="email" required />
        </label>
        <label>
          Password
          <input v-model="form.password" type="password" required />
        </label>
        <label>
          Confirm Password
          <input v-model="form.password_confirmation" type="password" required />
        </label>
        <button class="btn-accent" type="submit">Register</button>
      </div>
    </form>
    <p v-if="error">{{ error }}</p>
    <p><router-link to="/login">Return to login</router-link></p>
  </section>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { publicApi } from '../services/publicApi'

const authStore = useAuthStore()
const router = useRouter()
const error = ref('')
const departments = ref([])
const inviteStatus = ref('')
const inviteChurchId = ref('')
const inviteChurchName = ref('')

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  invite_code: '',
  department_id: '',
})

const loadDepartments = async (churchId) => {
  if (!churchId) {
    departments.value = []
    form.department_id = ''
    return
  }
  departments.value = await publicApi.listDepartments(churchId)
}

const lookupInvite = async () => {
  error.value = ''
  inviteStatus.value = ''
  inviteChurchId.value = ''
  inviteChurchName.value = ''
  departments.value = []
  form.department_id = ''

  if (!form.invite_code) {
    inviteStatus.value = 'not_found'
    return
  }

  try {
    const response = await publicApi.lookupInvitation(form.invite_code)
    inviteStatus.value = response.status
    inviteChurchId.value = response.church?.id || ''
    inviteChurchName.value = response.church?.name || ''
    if (inviteStatus.value === 'active' && inviteChurchId.value) {
      await loadDepartments(inviteChurchId.value)
    }
  } catch (err) {
    if (err?.response?.status === 404) {
      inviteStatus.value = 'not_found'
    } else {
      inviteStatus.value = 'inactive'
    }
  }
}

const submit = async () => {
  error.value = ''
  try {
    if (inviteStatus.value !== 'active') {
      error.value = 'Invitation code is not active.'
      return
    }
    const payload = {
      ...form,
      department_id: form.department_id || null,
    }
    await authStore.register(payload)
    await router.push('/login')
  } catch (err) {
    error.value = 'Registration failed.'
  }
}
</script>
