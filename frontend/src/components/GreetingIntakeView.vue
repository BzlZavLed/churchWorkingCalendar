<template>
  <section class="greeting-shell">
    <Teleport v-if="!isPublic" to="body">
        <button
          type="button"
          class="greeting-kiosk-control"
          :aria-pressed="uiStore.greetingKiosk"
          :aria-label="kioskLabel"
          :title="kioskLabel"
          @click="uiStore.greetingKiosk = !uiStore.greetingKiosk"
        >
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path v-if="uiStore.greetingKiosk" d="M4 9h5V4m11 5h-5V4M4 15h5v5m11-5h-5v5" />
            <path v-else d="M9 4H4v5m11-5h5v5M4 15v5h5m11-5v5h-5" />
          </svg>
        </button>
    </Teleport>
    <div class="greeting-panel">
      <nav v-if="!isPublic && !uiStore.greetingKiosk" class="visits-tabs" :aria-label="locale === 'es' ? 'Vistas de visitas' : 'Visitor views'">
        <RouterLink to="/greeting/contacts">{{ locale === 'es' ? 'Visitas' : 'Visitors' }}</RouterLink>
        <RouterLink to="/greeting" aria-current="page">{{ locale === 'es' ? 'Bienvenida · Registrar visita' : 'Welcome · Register visitor' }}</RouterLink>
      </nav>
      <div v-if="!isPublic" class="greeting-display-controls">
        <button type="button" class="greeting-qr-button" :title="locale === 'es' ? 'Mostrar QR' : 'Show QR'" :aria-label="locale === 'es' ? 'Mostrar QR' : 'Show QR'" @click="showQr">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h2v2h-2zM20 14v3m-6 3h3m3 0h1v1" /></svg>
          <span>{{ locale === 'es' ? 'Regístrate con QR' : 'Register with QR' }}</span>
        </button>

      </div>
      <div class="greeting-header"><img v-if="churchLogo" :src="churchLogo" :alt="publicChurchName || auth.user?.church?.name || 'Church logo'" class="visitor-church-logo" :class="`logo-${logoAlignment}`" />
        <p class="greeting-kicker">{{ isPublic ? publicChurchName : t.kicker }}</p>
        <h1 class="greeting-title">{{ t.title }}</h1>
        <p class="greeting-copy">{{ isPublic ? (locale === 'es' ? 'Te damos la bienvenida. Completa tus datos para registrar tu visita.' : 'Welcome. Fill in your details to register your visit.') : t.subtitle }}</p>
        <p v-if="!isPublic && !uiStore.greetingKiosk && !auth.isPlatformAdmin && auth.user?.department?.is_greeting" class="greeting-shortcut">{{ t.menuHint }}</p>
        <details v-if="!isPublic && !uiStore.greetingKiosk" class="greeting-disclaimer">
          <summary>{{ t.disclaimerTitle }}</summary>
          <p>{{ t.disclaimerBody }}</p>
        </details>
      </div>

      <p v-if="submitted" class="alert alert-success" role="status">{{ t.success }}</p>
      <form v-if="!submitted && formLoaded" class="greeting-form" @submit.prevent="submit"><fieldset :disabled="!isPublic && !auth.can('greeting.register')">
        <div class="greeting-grid">
          <label class="form-label greeting-grid-span-2">
            {{ t.fields.name }}
            <input v-model="form.name" class="form-control" type="text" required />
          </label>

          <label class="form-label">
            {{ t.fields.phone }}
            <input v-model="form.phone" class="form-control" type="tel" />
          </label>

          <label class="form-label">
            {{ t.fields.email }}
            <input v-model="form.email" class="form-control" type="email" />
          </label>

          <label class="form-label greeting-grid-span-2">
            {{ t.fields.address }}
            <input
              v-model="form.address"
              class="form-control"
              type="text"
              :placeholder="t.fields.addressHint"
            />
          </label>
        </div>

        <VisitorCustomFields :fields="visitorForm.fields" v-model="customAnswers" />

        <div class="greeting-toggle">
          <div>
            <div class="greeting-toggle-label">{{ t.fields.isSda }}</div>
            <div class="greeting-toggle-hint">{{ t.fields.isSdaHint }}</div>
          </div>
          <label class="form-check form-switch m-0">
            <input v-model="form.is_sda" :aria-label="t.fields.isSda" class="form-check-input" type="checkbox" />
          </label>
        </div>

        <div v-if="form.phone" class="greeting-consent">
          <label class="form-check m-0">
            <input v-model="form.sms_consent" class="form-check-input" type="checkbox" />
            <span class="form-check-label">{{ t.fields.smsConsent }}</span>
          </label>
          <div class="greeting-consent-hint">{{ t.fields.smsConsentHint }}</div>
        </div>

        <div v-if="form.email" class="greeting-consent">
          <label class="form-check m-0">
            <input v-model="form.email_consent" class="form-check-input" type="checkbox" />
            <span class="form-check-label">{{ t.fields.emailConsent }}</span>
          </label>
          <div class="greeting-consent-hint">{{ t.fields.emailConsentHint }}</div>
        </div>

        <button class="btn btn-primary greeting-submit" type="submit" :disabled="saving">
          {{ saving ? t.saving : t.submit }}
        </button>
      </fieldset></form>

      <p v-if="error" class="alert alert-danger mb-0 mt-3">{{ error }}</p>
    </div>
    <div v-if="qrOpen" class="modal-backdrop" @click.self="qrOpen = false" @keydown.esc="qrOpen = false">
      <section class="modal-panel greeting-qr" role="dialog" aria-modal="true" aria-labelledby="visit-qr-title">
        <header class="modal-header"><h2 id="visit-qr-title">{{ locale === 'es' ? 'Registra tu visita desde tu teléfono' : 'Register your visit on your phone' }}</h2><button type="button" class="modal-close" :aria-label="locale === 'es' ? 'Cerrar' : 'Close'" @click="qrOpen = false">×</button></header>
        <p>{{ locale === 'es' ? 'Escanea el código con la cámara. No necesitas una cuenta y puedes completar el formulario más tarde.' : 'Scan with your camera. No account needed; you can complete the form later.' }}</p>
        <img v-if="qrImage" :src="qrImage" alt="QR" width="280" height="280" />
        <p v-if="qrError" role="alert">{{ qrError }}</p>
        <a class="d-block" :href="visitUrl" target="_blank" rel="noopener">{{ locale === 'es' ? 'Abrir formulario' : 'Open form' }}</a>
      </section>
    </div>
  </section>
</template>

<script setup>
import VisitorCustomFields from './VisitorCustomFields.vue'
import '../styles/visits.css'
import { useAuthStore } from '../stores/authStore'
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import QRCode from 'qrcode'
import { storeToRefs } from 'pinia'
import { churchContactsApi } from '../services/churchContactsApi'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'

const route = useRoute()
const isPublic = computed(() => !!route.params.churchId)
const churchLogo = ref(null)
const logoAlignment = ref('center')
const publicChurchName = ref('')
const visitorForm = ref({id:null,version:0,fields:[]})
const customAnswers = ref({})
const formLoaded = ref(false)
const initialAnswers = fields => Object.fromEntries(fields.filter(f=>f.type==='checkbox').map(f=>[f.id,false]))
const submitted = ref(false)
const qrOpen = ref(false)
const qrImage = ref('')
const qrError = ref('')
const auth = useAuthStore()
const visitUrl = computed(() => `${window.location.origin}/visit/${auth.user?.church_id}`)
const showQr = async () => {
  qrOpen.value = true
  qrError.value = ''
  try {
    qrImage.value = await QRCode.toDataURL(visitUrl.value, { width: 560, margin: 4, errorCorrectionLevel: 'M' })
  } catch {
    qrError.value = locale.value === 'es' ? 'No se pudo generar el QR.' : 'Unable to generate QR.'
  }
}
onMounted(async () => {
  try {
    if (isPublic.value) {
      const result = await churchContactsApi.publicForm(route.params.churchId)
      publicChurchName.value = result.name
      churchLogo.value = result.logo_url
      logoAlignment.value = result.logo_alignment || 'center'
      visitorForm.value = result.form
    } else { visitorForm.value = await churchContactsApi.form(); churchLogo.value = visitorForm.value.logo_url; logoAlignment.value = visitorForm.value.logo_alignment || 'center' }
    customAnswers.value = initialAnswers(visitorForm.value.fields)
    formLoaded.value = true
  } catch {
    error.value = locale.value === 'es' ? 'Este formulario no está disponible.' : 'This form is unavailable.'
  }
})
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
onUnmounted(() => { uiStore.greetingKiosk = false })
const t = computed(() => translations[locale.value].greeting.intake)
const kioskLabel = computed(() => uiStore.greetingKiosk
  ? (locale.value === 'es' ? 'Salir del modo quiosco' : 'Exit kiosk mode')
  : (locale.value === 'es' ? 'Modo quiosco' : 'Kiosk mode'))
const error = ref('')
const saving = ref(false)

const form = reactive({
  name: '',
  phone: '',
  email: '',
  address: '',
  is_sda: false,
  sms_consent: false,
  email_consent: false,
})

const resetForm = () => {
  customAnswers.value = initialAnswers(visitorForm.value.fields)
  form.name = ''
  form.phone = ''
  form.email = ''
  form.address = ''
  form.is_sda = false
  form.sms_consent = false
  form.email_consent = false
}

const submit = async () => {
  if (saving.value) return
  error.value = ''
  saving.value = true
  try {
    const payload = {
      visitor_form_id: visitorForm.value.id,
      custom_answers: customAnswers.value,
      name: form.name,
      phone: form.phone || null,
      email: form.email || null,
      address: form.address || null,
      is_sda: Boolean(form.is_sda),
      sms_consent: Boolean(form.phone && form.sms_consent),
      email_consent: Boolean(form.email && form.email_consent),
    }
    if (isPublic.value) {
      await churchContactsApi.publicCreate(route.params.churchId, payload)
      submitted.value = true
    } else {
      await churchContactsApi.create(payload)
      // A kiosk adopts the latest published version between visitors, never mid-response.
      try { visitorForm.value = await churchContactsApi.form() } catch {}
    }
    resetForm()
    uiStore.showToast(t.value.success, 'success')
  } catch (err) {
    error.value = Object.values(err?.response?.data?.errors || {}).flat().join(' ') || err?.response?.data?.message || t.value.error
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.greeting-qr { text-align: center; }
.greeting-qr img { display: block; max-width: 100%; height: auto; margin: 16px auto; }

.greeting-display-controls { margin-top: 14px; }
.greeting-qr-button { width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 44px; padding: 10px 16px; border: 1px solid #1f6f5c; border-radius: 8px; background: #1f6f5c; color: white; font-size: 13px; font-weight: 500; cursor: pointer; }
.greeting-qr-button:hover { background: #175747; }
.greeting-qr-button:focus-visible { outline: 2px solid #1f6f5c; outline-offset: 3px; }
.greeting-kiosk-control { position: fixed; top: max(12px, env(safe-area-inset-top)); right: max(12px, env(safe-area-inset-right)); z-index: 1040; display: grid; place-items: center; width: 36px; height: 36px; padding: 0; border: 0; border-radius: 6px; background: rgba(255, 255, 255, .9); color: #748078; cursor: pointer; }
.greeting-kiosk-control:hover { background: #eef3ef; color: #254b3d; }
.greeting-kiosk-control:focus-visible { outline: 2px solid #1f6f5c; outline-offset: 2px; }

.greeting-disclaimer summary { cursor: pointer; }
.greeting-disclaimer p { margin: 8px 0 0; }

.greeting-shell {
  min-height: 100%;
  display: grid;
  place-items: center;
  padding: 2rem;
  
}

.greeting-panel {
  position: relative;
  width: min(100%, 920px);
  padding: 24px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(54, 73, 53, 0.08);
  box-shadow: none;
}

.greeting-header {
  margin-top: 22px;
  margin-bottom: 1.5rem;
}

.greeting-kicker {
  margin: 0 0 0.5rem;
  font-size: 12px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #7b6441;
}

.greeting-title {
  margin: 0;
  font-size: 22px;
  font-weight: 500;
  line-height: 1;
  color: #243126;
}

.greeting-copy {
  margin: 0.75rem 0 0;
  font-size: 13px;
  color: #55645a;
}

.greeting-shortcut {
  display: inline-flex;
  align-items: center;
  margin: 0.85rem 0 0;
  padding: 0.45rem 0.7rem;
  border-radius: 999px;
  background: #eef3eb;
  border: 1px solid rgba(54, 73, 53, 0.12);
  font-size: 12px;
  font-weight: 500;
  color: #425247;
}

.greeting-disclaimer {
  margin-top: 0.85rem;
  padding: 0.9rem 1rem;
  border-radius: 8px;
  background: rgba(255, 249, 235, 0.92);
  border: 1px solid rgba(153, 122, 58, 0.16);
  color: #5d543d;
  font-size: 12px;
}

.greeting-form {
  font-size: 13px;
  display: grid;
  gap: 1rem;
}

.greeting-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem 1.25rem;
}

.greeting-grid-span-2 {
  grid-column: 1 / -1;
}

.greeting-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.1rem;
  border-radius: 8px;
  background: #f6f4ee;
}

.greeting-toggle-label {
  font-weight: 500;
  color: #243126;
}

.greeting-toggle-hint {
  font-size: 12px;
  color: #66746a;
}

.greeting-consent {
  display: grid;
  gap: 0.45rem;
  padding: 1rem 1.1rem;
  border-radius: 8px;
  background: #f8faf7;
  border: 1px solid rgba(84, 102, 90, 0.12);
}

.greeting-consent-hint {
  font-size: 12px;
  color: #66746a;
}

.greeting-submit {
  background: #1f6f5c;
  border-color: #1f6f5c;
  margin-top: 0.5rem;
}

@media (max-width: 767px) {
  .greeting-shell {
    padding: 1rem;
  }

  .greeting-panel {
    padding: 1.25rem;
    border-radius: 10px;
  }

  .greeting-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .greeting-grid-span-2 {
    grid-column: auto;
  }
}
</style>

<style scoped>
.visitor-church-logo.logo-left{margin-left:0;margin-right:auto}.visitor-church-logo.logo-right{margin-left:auto;margin-right:0}
.visitor-church-logo{display:block;max-width:min(220px,100%);width:auto;max-height:110px;height:auto;object-fit:contain;margin:0 auto 18px}
</style>
