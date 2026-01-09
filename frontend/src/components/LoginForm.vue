<template>
  <section>
    <h1>Login</h1>
    <form v-if="!showRecovery" @submit.prevent="submit">
      <label>
        Email
        <input v-model="form.email" type="email" required />
      </label>
      <label>
        Password
        <input v-model="form.password" type="password" required />
      </label>
      <button type="submit">Login</button>
    </form>
    <form v-else @submit.prevent="recover">
      <label>
        Email
        <input v-model="recovery.email" type="email" required />
      </label>
      <label>
        Church Code
        <input v-model="recovery.church_code" type="text" required />
      </label>
      <label>
        New Password
        <input v-model="recovery.password" type="password" required />
      </label>
      <label>
        Confirm Password
        <input v-model="recovery.password_confirmation" type="password" required />
      </label>
      <button type="submit">Reset Password</button>
    </form>
    <div class="auth-actions">
      <button class="btn-accent" type="button" @click="toggleRecovery">
        {{ showRecovery ? 'Back to Login' : 'Forgot password?' }}
      </button>
      <button v-if="!showRecovery" class="btn-accent" type="button" @click="goToRegister">Register</button>
    </div>
    <p v-if="notice">{{ notice }}</p>
    <p v-if="error">{{ error }}</p>
  </section>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { authApi } from '../services/authApi'

const authStore = useAuthStore()
const router = useRouter()
const error = ref('')
const notice = ref('')
const showRecovery = ref(false)

const form = reactive({
  email: '',
  password: '',
})

const recovery = reactive({
  email: '',
  church_code: '',
  password: '',
  password_confirmation: '',
})

const submit = async () => {
  error.value = ''
  notice.value = ''
  try {
    await authStore.login(form)
    if (authStore.user?.role === 'superadmin') {
      await router.push('/superadmin/churches')
    } else {
      await router.push('/calendar')
    }
  } catch (err) {
    error.value = 'Login failed.'
  }
}

const recover = async () => {
  error.value = ''
  notice.value = ''
  try {
    await authApi.recoverPassword(recovery)
    notice.value = 'Password updated. Please log in.'
    showRecovery.value = false
    recovery.email = ''
    recovery.church_code = ''
    recovery.password = ''
    recovery.password_confirmation = ''
  } catch (err) {
    error.value = extractErrorMessage(err) || 'Password reset failed.'
  }
}

const toggleRecovery = () => {
  error.value = ''
  notice.value = ''
  showRecovery.value = !showRecovery.value
}

const extractErrorMessage = (err) => {
  const data = err?.response?.data
  if (!data) {
    return ''
  }
  if (typeof data.message === 'string' && data.message.trim()) {
    return data.message
  }
  const firstKey = data.errors ? Object.keys(data.errors)[0] : null
  if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey][0]) {
    return data.errors[firstKey][0]
  }
  return ''
}

const goToRegister = async () => {
  await router.push('/register')
}
</script>
