<template>
  <section>
    <h1>Superadmin: Church Registration</h1>

    <form @submit.prevent="submit">
      <label>
        Church Name
        <input v-model="form.name" type="text" required />
      </label>
      <label>
        Initial Invite Role
        <select v-model="form.invite_role">
          <option value="admin">admin</option>
          <option value="member">member</option>
        </select>
      </label>
      <label>
        Invite Email (optional)
        <input v-model="form.invite_email" type="email" />
      </label>
      <label>
        Invite Max Uses
        <input v-model.number="form.invite_max_uses" type="number" min="1" />
      </label>
      <label>
        Invite Expires At (optional)
        <input v-model="form.invite_expires_at" type="datetime-local" />
      </label>
      <button type="submit">Create Church & Invite</button>
    </form>

    <p v-if="error">{{ error }}</p>

    <div v-if="result">
      <h2>Created</h2>
      <p>Church: {{ result.church.name }} (ID {{ result.church.id }})</p>
      <p>Invite Code: <strong>{{ result.invitation.code }}</strong></p>
    </div>
  </section>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { superAdminApi } from '../services/superAdminApi'

const error = ref('')
const result = ref(null)

const form = reactive({
  name: '',
  invite_role: 'admin',
  invite_email: '',
  invite_max_uses: 1,
  invite_expires_at: '',
})

const submit = async () => {
  error.value = ''
  result.value = null

  try {
    const payload = {
      name: form.name,
      invite_role: form.invite_role,
      invite_email: form.invite_email || null,
      invite_max_uses: form.invite_max_uses || 1,
      invite_expires_at: form.invite_expires_at || null,
    }

    result.value = await superAdminApi.createChurch(payload)
  } catch (err) {
    error.value = 'Unable to create church.'
  }
}
</script>
