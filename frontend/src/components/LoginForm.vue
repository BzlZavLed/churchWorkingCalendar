<template>
  <main class="min-vh-100">
   

    <section class="container py-4">
      <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
          <div class="card shadow-sm">
            <div class="card-body p-4">
              <h1 class="h4 mb-3 text-center">{{ showRecovery ? 'Recuperar contrasena' : 'Calendario de trabajo' }}</h1>

              <form v-if="!showRecovery" @submit.prevent="submit">
                <div class="mb-3">
                  <label class="form-label">
                    Email
                    <input v-model="form.email" class="form-control" type="email" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    Password
                    <input v-model="form.password" class="form-control" type="password" required />
                  </label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Login</button>
              </form>

              <form v-else @submit.prevent="recover">
                <div class="mb-3">
                  <label class="form-label">
                    Email
                    <input v-model="recovery.email" class="form-control" type="email" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    Church Code
                    <input v-model="recovery.church_code" class="form-control" type="text" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    New Password
                    <input v-model="recovery.password" class="form-control" type="password" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    Confirm Password
                    <input v-model="recovery.password_confirmation" class="form-control" type="password" required />
                  </label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Reset Password</button>
              </form>

              <div class="auth-actions mt-3">
                <button class="btn btn-outline-secondary" type="button" @click="toggleRecovery">
                  {{ showRecovery ? 'Back to Login' : 'Forgot password?' }}
                </button>
                <button v-if="!showRecovery" class="btn btn-outline-secondary" type="button" @click="goToRegister">
                  Register
                </button>
              </div>

              <p v-if="notice" class="alert alert-success mt-3 mb-0">{{ notice }}</p>
              <p v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>
</template>

<script setup>
import logoUrl from '../assets/logo.png'
import { onMounted, onUnmounted, reactive, ref } from 'vue'
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

onMounted(() => {
  document.body.classList.add('sidebar-open')
})

onUnmounted(() => {
  document.body.classList.remove('sidebar-open')
})
</script>
