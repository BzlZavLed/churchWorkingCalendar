<template>
  <main class="min-vh-100">
   

    <section class="container py-4">
      <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
          <div class="card shadow-sm">
            <div class="card-body p-4">
              <h1 class="h4 mb-3 text-center">{{ showRecovery ? t.recoverTitle : t.title }}</h1>

              <form v-if="!showRecovery" class="login-form" @submit.prevent="submit">
                <div class="mb-3">
                  <label class="form-label">
                    {{ t.email }}
                    <input v-model="form.email" class="form-control" type="email" required />
                  </label>
                </div>
                <div class="mb-3">
                  <label class="form-label">{{ t.password }}</label>
                  <div class="position-relative">
                    <input
                      v-model="form.password"
                      class="form-control password-toggle-input"
                      :type="showPassword ? 'text' : 'password'"
                      required
                    />
                    <button
                      class="password-toggle-btn"
                      type="button"
                      :aria-label="showPassword ? t.hidePassword : t.showPassword"
                      @click="showPassword = !showPassword"
                    >
                      <svg
                        v-if="!showPassword"
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6-10-6-10-6Z" />
                        <circle cx="12" cy="12" r="3" />
                      </svg>
                      <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                      >
                        <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-6 0-10-8-10-8a21.77 21.77 0 0 1 5.06-6.06" />
                        <path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c6 0 10 8 10 8a21.77 21.77 0 0 1-4.12 5.11" />
                        <path d="M14.12 14.12a3 3 0 0 1-4.24-4.24" />
                        <path d="M1 1l22 22" />
                      </svg>
                    </button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-check mb-0 login-remember">
                    <input v-model="rememberMe" class="form-check-input" type="checkbox" />
                    <span class="form-check-label">{{ t.remember }}</span>
                  </label>
                </div>
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                  <button
                    v-if="supportsWebAuthn"
                    class="btn btn-outline-secondary btn-sm login-faceid-btn"
                    type="button"
                    :disabled="faceIdLoading"
                    @click="startFaceIdLogin"
                    :aria-label="t.faceId"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="20"
                      height="20"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      aria-hidden="true"
                    >
                      <path d="M4 7V6a2 2 0 0 1 2-2h1" />
                      <path d="M17 4h1a2 2 0 0 1 2 2v1" />
                      <path d="M20 17v1a2 2 0 0 1-2 2h-1" />
                      <path d="M7 20H6a2 2 0 0 1-2-2v-1" />
                      <path d="M8 12s1.5 2 4 2 4-2 4-2" />
                      <path d="M9 9h.01" />
                      <path d="M15 9h.01" />
                    </svg>
                  </button>
                </div>
                <button class="btn btn-primary w-100" type="submit">{{ t.login }}</button>
              </form>

              <form v-else class="login-form" @submit.prevent="recover">
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
              <p v-if="faceIdNotice" class="alert alert-info mt-3 mb-0">{{ faceIdNotice }}</p>
              <p v-if="faceIdError" class="alert alert-danger mt-3 mb-0">{{ faceIdError }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div v-if="faceIdSetupOpen" class="modal-backdrop" @click.self="closeFaceIdSetup">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.faceIdSetupTitle }}</h2>
          <button type="button" class="modal-close" @click="closeFaceIdSetup">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-text">{{ t.faceIdSetupBody }}</p>
          <label class="form-label mt-2">
            {{ t.email }}
            <input v-model="faceIdEmail" class="form-control" type="email" required />
          </label>
          <label class="form-label mt-2">
            {{ t.password }}
            <input v-model="faceIdPassword" class="form-control" type="password" minlength="8" />
          </label>
          <label class="form-label mt-2">
            {{ t.faceIdDeviceName }}
            <input v-model="faceIdDeviceName" class="form-control" type="text" :placeholder="t.faceIdDeviceHint" />
          </label>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline-secondary" @click="closeFaceIdSetup">
            {{ t.cancel }}
          </button>
          <button
            type="button"
            class="btn btn-outline-primary"
            :disabled="faceIdLoading || !faceIdPassword || !faceIdEmail"
            @click="registerFaceId"
          >
            {{ t.faceIdRegister }}
          </button>
        </div>
      </div>
    </div>
  </main>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { authApi } from '../services/authApi'
import { webauthnApi } from '../services/webauthnApi'
import { useUiStore } from '../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../i18n/translations'
import {
  credentialToJSON,
  prepareAuthenticationOptions,
  prepareRegistrationOptions,
} from '../utils/webauthn'

const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const router = useRouter()
const error = ref('')
const notice = ref('')
const showRecovery = ref(false)
const showPassword = ref(false)
const rememberMe = ref(true)
const supportsWebAuthn = ref(false)
const faceIdError = ref('')
const faceIdNotice = ref('')
const faceIdLoading = ref(false)
const faceIdSetupOpen = ref(false)
const faceIdEmail = ref('')
const faceIdPassword = ref('')
const faceIdDeviceName = ref('')
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
  faceIdError.value = ''
  faceIdNotice.value = ''
  try {
    await authStore.login(form, rememberMe.value)
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
  faceIdError.value = ''
  faceIdNotice.value = ''
  showRecovery.value = !showRecovery.value
}

const startFaceIdLogin = async () => {
  faceIdError.value = ''
  faceIdNotice.value = ''
  faceIdLoading.value = true
  try {
    const payload = form.email ? { email: form.email } : {}
    const { publicKey, options_id: optionsId } = await webauthnApi.authenticateOptions(payload)
    const options = prepareAuthenticationOptions(publicKey)
    const credential = await navigator.credentials.get({ publicKey: options })
    const verifyPayload = {
      options_id: optionsId,
      credential: credentialToJSON(credential),
    }
    const session = await webauthnApi.authenticateVerify(verifyPayload)
    authStore.setSession(session, rememberMe.value)
    if (authStore.user?.role === 'superadmin') {
      await router.push('/superadmin/churches')
    } else {
      await router.push('/calendar')
    }
  } catch (err) {
    if (err?.response?.data?.status === 'no_credentials') {
      faceIdNotice.value = t.value.faceIdRegisterPrompt
      openFaceIdSetup()
    } else if (err?.response?.data?.message === 'The credential ID is invalid.') {
      faceIdNotice.value = t.value.faceIdRegisterPrompt
      openFaceIdSetup()
    } else if (err?.name === 'NotAllowedError' || err?.name === 'NotFoundError') {
      faceIdNotice.value = t.value.faceIdRegisterPrompt
      openFaceIdSetup()
    } else {
      faceIdError.value = t.value.faceIdError
    }
  } finally {
    faceIdLoading.value = false
  }
}

const openFaceIdSetup = () => {
  faceIdNotice.value = t.value.faceIdRegisterPrompt
  faceIdEmail.value = form.email || ''
  faceIdPassword.value = ''
  faceIdDeviceName.value = ''
  faceIdSetupOpen.value = true
}

const closeFaceIdSetup = () => {
  faceIdSetupOpen.value = false
  faceIdEmail.value = ''
  faceIdPassword.value = ''
  faceIdDeviceName.value = ''
}

const registerFaceId = async () => {
  faceIdError.value = ''
  if (!faceIdEmail.value || !faceIdPassword.value) {
    faceIdError.value = t.value.faceIdSetupError
    return
  }
  faceIdLoading.value = true
  try {
    const { publicKey } = await webauthnApi.registerOptions({
      email: faceIdEmail.value,
      password: faceIdPassword.value,
    })
    const options = prepareRegistrationOptions(publicKey)
    const credential = await navigator.credentials.create({ publicKey: options })
    const payload = {
      email: faceIdEmail.value,
      credential: credentialToJSON(credential),
      device_name: faceIdDeviceName.value || undefined,
    }
    const session = await webauthnApi.registerVerify(payload)
    authStore.setSession(session, rememberMe.value)
    closeFaceIdSetup()
    if (authStore.user?.role === 'superadmin') {
      await router.push('/superadmin/churches')
    } else {
      await router.push('/calendar')
    }
  } catch (err) {
    faceIdError.value = t.value.faceIdError
  } finally {
    faceIdLoading.value = false
  }
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
  if (window.PublicKeyCredential?.isUserVerifyingPlatformAuthenticatorAvailable) {
    window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
      .then((available) => {
        supportsWebAuthn.value = available
      })
      .catch(() => {
        supportsWebAuthn.value = false
      })
  }
})

onUnmounted(() => {
  document.body.classList.remove('sidebar-open')
})
</script>
