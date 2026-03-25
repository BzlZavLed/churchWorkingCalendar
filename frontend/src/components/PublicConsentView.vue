<template>
  <main class="consent-shell">
    <section class="consent-card">
      <p class="consent-kicker">{{ t.kicker }}</p>
      <h1 class="consent-title">{{ t.title }}</h1>
      <p class="consent-copy">{{ t.subtitle }}</p>

      <form class="consent-search" @submit.prevent="search">
        <label class="form-label">
          {{ t.searchEmail }}
          <input v-model="lookup.email" class="form-control" type="email" />
        </label>
        <label class="form-label">
          {{ t.searchPhone }}
          <input v-model="lookup.phone" class="form-control" type="tel" />
        </label>
        <button class="btn btn-primary" type="submit" :disabled="loading">
          {{ loading ? t.loading : t.search }}
        </button>
      </form>

      <p class="consent-note">{{ t.searchNote }}</p>
      <p v-if="error" class="alert alert-danger mb-0">{{ error }}</p>
      <p v-if="notice" class="alert alert-success mb-0 mt-3">{{ notice }}</p>

      <div v-if="searched && !loading && results.length === 0" class="alert alert-warning mt-3 mb-0">
        {{ t.empty }}
      </div>

      <div v-if="results.length > 0" class="consent-results">
        <article v-for="contact in results" :key="contact.token" class="consent-result-card">
          <div class="consent-result-header">
            <div>
              <h2 class="consent-result-title">{{ contact.name || t.guestLabel }}</h2>
              <p class="consent-result-church">{{ contact.church?.name || t.defaultChurchName }}</p>
            </div>
          </div>

          <div class="consent-status">
            <div class="consent-status-row">
              <span>{{ t.smsStatus }}</span>
              <strong>{{ contact.sms_consent ? t.active : t.inactive }}</strong>
            </div>
            <div class="consent-status-row">
              <span>{{ t.emailStatus }}</span>
              <strong>{{ contact.email_consent ? t.active : t.inactive }}</strong>
            </div>
          </div>

          <form class="consent-form" @submit.prevent="submit(contact)">
            <label class="form-check" :class="{ 'text-muted': !contact.sms_consent || !contact.phone }">
              <input
                v-model="contact.withdraw_sms"
                class="form-check-input"
                type="checkbox"
                :disabled="!contact.sms_consent || !contact.phone"
              />
              <span class="form-check-label">{{ t.withdrawSms }}</span>
            </label>

            <label class="form-check" :class="{ 'text-muted': !contact.email_consent || !contact.email }">
              <input
                v-model="contact.withdraw_email"
                class="form-check-input"
                type="checkbox"
                :disabled="!contact.email_consent || !contact.email"
              />
              <span class="form-check-label">{{ t.withdrawEmail }}</span>
            </label>

            <button
              class="btn btn-outline-primary"
              type="submit"
              :disabled="savingToken === contact.token || (!contact.withdraw_sms && !contact.withdraw_email)"
            >
              {{ savingToken === contact.token ? t.saving : t.submit }}
            </button>
          </form>
        </article>
      </div>
    </section>
  </main>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { publicApi } from '../services/publicApi'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'

const route = useRoute()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].consentPage)
const lookup = reactive({
  email: '',
  phone: '',
})
const loading = ref(false)
const savingToken = ref('')
const searched = ref(false)
const error = ref('')
const notice = ref('')
const results = ref([])

const normalizeResult = (contact) => ({
  ...contact,
  withdraw_sms: false,
  withdraw_email: false,
})

const search = async () => {
  loading.value = true
  searched.value = true
  error.value = ''
  notice.value = ''
  try {
    const response = await publicApi.searchConsent({
      email: lookup.email || undefined,
      phone: lookup.phone || undefined,
    })
    results.value = (response.contacts || []).map(normalizeResult)
  } catch (err) {
    error.value = err?.response?.data?.errors?.lookup?.[0] || t.value.loadError
    results.value = []
  } finally {
    loading.value = false
  }
}

const submit = async (contact) => {
  savingToken.value = contact.token
  error.value = ''
  notice.value = ''
  try {
    const response = await publicApi.revokeConsent(contact.token, {
      withdraw_sms: Boolean(contact.withdraw_sms),
      withdraw_email: Boolean(contact.withdraw_email),
    })
    const updated = normalizeResult({
      ...contact,
      ...(response.contact || {}),
      church: contact.church,
    })
    results.value = results.value.map((entry) => (entry.token === contact.token ? updated : entry))
    notice.value = t.value.success
  } catch {
    error.value = t.value.saveError
  } finally {
    savingToken.value = ''
  }
}

onMounted(async () => {
  if (route.params.token) {
    loading.value = true
    try {
      const response = await publicApi.getConsent(route.params.token)
      results.value = [normalizeResult(response.contact ? { ...response.contact, church: response.church } : null)].filter(Boolean)
      searched.value = true
    } catch {
      error.value = t.value.loadError
    } finally {
      loading.value = false
    }
  }
})
</script>

<style scoped>
.consent-shell {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 2rem 1rem;
  background: linear-gradient(180deg, #f8f3ea 0%, #eef4ec 100%);
}

.consent-card {
  width: min(100%, 760px);
  padding: 2rem;
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.96);
  border: 1px solid rgba(54, 73, 53, 0.08);
  box-shadow: 0 30px 70px rgba(73, 59, 34, 0.12);
}

.consent-kicker {
  margin: 0 0 0.5rem;
  font-size: 0.82rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #7b6441;
}

.consent-title {
  margin: 0;
  color: #243126;
}

.consent-copy {
  margin: 0.85rem 0 1rem;
  color: #55645a;
}

.consent-note {
  margin: 0.85rem 0 0;
  font-size: 0.92rem;
  color: #66746a;
}

.consent-search,
.consent-form {
  display: grid;
  gap: 1rem;
}

.consent-results {
  display: grid;
  gap: 1rem;
  margin-top: 1.25rem;
}

.consent-result-card {
  padding: 1rem;
  border-radius: 18px;
  background: #fbfcfa;
  border: 1px solid rgba(54, 73, 53, 0.08);
}

.consent-result-title {
  margin: 0;
  color: #243126;
}

.consent-result-church {
  margin: 0.35rem 0 0;
  color: #66746a;
}

.consent-status {
  display: grid;
  gap: 0.75rem;
  padding: 1rem;
  margin: 1rem 0;
  border-radius: 18px;
  background: #f8faf7;
}

.consent-status-row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
}
</style>
