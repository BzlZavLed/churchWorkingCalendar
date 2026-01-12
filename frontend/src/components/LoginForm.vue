<template>
  <main class="min-vh-100">
   

    <section class="container py-4">
      <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
          <div class="card shadow-sm">
            <div class="card-body p-4">
              <h1 class="h4 mb-3 text-center">{{ showRecovery ? t.recoverTitle : t.title }}</h1>

              <form v-if="!showRecovery" @submit.prevent="submit">
                <div class="mb-3">
                  <label class="form-label">
                    {{ t.email }}
                    <input v-model="form.email" class="form-control" type="email" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    {{ t.password }}
                    <input v-model="form.password" class="form-control" type="password" required />
                  </label>
                </div>
                <button class="btn btn-primary w-100" type="submit">{{ t.login }}</button>
              </form>

              <form v-else @submit.prevent="recover">
                <div class="mb-3">
                  <label class="form-label">
                    {{ t.email }}
                    <input v-model="recovery.email" class="form-control" type="email" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    {{ t.churchCode }}
                    <input v-model="recovery.church_code" class="form-control" type="text" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    {{ t.newPassword }}
                    <input v-model="recovery.password" class="form-control" type="password" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">
                    {{ t.confirmPassword }}
                    <input v-model="recovery.password_confirmation" class="form-control" type="password" required />
                  </label>
                </div>
                <button class="btn btn-primary w-100" type="submit">{{ t.reset }}</button>
              </form>

              <div class="auth-actions mt-3">
                <button class="btn btn-outline-secondary" type="button" @click="toggleRecovery">
                  {{ showRecovery ? t.back : t.forgot }}
                </button>
                <button v-if="!showRecovery" class="btn btn-outline-secondary" type="button" @click="goToRegister">
                  {{ t.register }}
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
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { authApi } from '../services/authApi'
import { useUiStore } from '../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../i18n/translations'

const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const router = useRouter()
const error = ref('')
const notice = ref('')
const showRecovery = ref(false)
const t = computed(() => translations[locale.value].login)

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
    error.value = t.value.errorLogin
  }
}

const recover = async () => {
  error.value = ''
  notice.value = ''
  try {
    await authApi.recoverPassword(recovery)
    notice.value = t.value.success
    showRecovery.value = false
    recovery.email = ''
    recovery.church_code = ''
    recovery.password = ''
    recovery.password_confirmation = ''
  } catch (err) {
    error.value = extractErrorMessage(err) || t.value.errorReset
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
