<template>
  <section class="greeting-shell">
    <div class="greeting-panel">
      <nav class="visits-tabs" :aria-label="locale === 'es' ? 'Vistas de visitas' : 'Visitor views'">
        <RouterLink to="/greeting/contacts">{{ locale === 'es' ? 'Visitas' : 'Visitors' }}</RouterLink>
        <RouterLink to="/greeting" aria-current="page">{{ locale === 'es' ? 'Bienvenida · Registrar visita' : 'Welcome · Register visitor' }}</RouterLink>
      </nav>
      <div class="greeting-header">
        <p class="greeting-kicker">{{ t.kicker }}</p>
        <h1 class="greeting-title">{{ t.title }}</h1>
        <p class="greeting-copy">{{ t.subtitle }}</p>
        <p v-if="!auth.isPlatformAdmin && auth.user?.department?.is_greeting" class="greeting-shortcut">{{ t.menuHint }}</p>
        <div class="greeting-disclaimer">
          <strong>{{ t.disclaimerTitle }}</strong>
          <span>{{ t.disclaimerBody }}</span>
        </div>
      </div>

      <form class="greeting-form" @submit.prevent="submit">
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
      </form>

      <p v-if="error" class="alert alert-danger mb-0 mt-3">{{ error }}</p>
    </div>
  </section>
</template>

<script setup>
import '../styles/visits.css'
import { useAuthStore } from '../stores/authStore'
import { computed, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { churchContactsApi } from '../services/churchContactsApi'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'

const auth = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].greeting.intake)
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
    await churchContactsApi.create({
      name: form.name,
      phone: form.phone || null,
      email: form.email || null,
      address: form.address || null,
      is_sda: Boolean(form.is_sda),
      sms_consent: Boolean(form.phone && form.sms_consent),
      email_consent: Boolean(form.email && form.email_consent),
    })
    resetForm()
    uiStore.showToast(t.value.success, 'success')
  } catch (err) {
    error.value = err?.response?.data?.message || t.value.error
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.greeting-shell {
  min-height: 100%;
  display: grid;
  place-items: center;
  padding: 2rem;
  
}

.greeting-panel {
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
  display: grid;
  gap: 0.25rem;
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
