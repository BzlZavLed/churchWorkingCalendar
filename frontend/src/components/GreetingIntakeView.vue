<template>
  <section class="greeting-shell">
    <div class="greeting-panel">
      <div class="greeting-header">
        <p class="greeting-kicker">{{ t.kicker }}</p>
        <h1 class="greeting-title">{{ t.title }}</h1>
        <p class="greeting-copy">{{ t.subtitle }}</p>
      </div>

      <form class="greeting-form" @submit.prevent="submit">
        <label class="form-label">
          {{ t.fields.name }}
          <input v-model="form.name" class="form-control form-control-lg" type="text" required />
        </label>

        <label class="form-label">
          {{ t.fields.phone }}
          <input v-model="form.phone" class="form-control form-control-lg" type="tel" />
        </label>

        <label class="form-label">
          {{ t.fields.email }}
          <input v-model="form.email" class="form-control form-control-lg" type="email" />
        </label>

        <label class="form-label">
          {{ t.fields.address }}
          <input
            v-model="form.address"
            class="form-control form-control-lg"
            type="text"
            :placeholder="t.fields.addressHint"
          />
        </label>

        <div class="greeting-toggle">
          <div>
            <div class="greeting-toggle-label">{{ t.fields.isSda }}</div>
            <div class="greeting-toggle-hint">{{ t.fields.isSdaHint }}</div>
          </div>
          <label class="form-check form-switch m-0">
            <input v-model="form.is_sda" class="form-check-input" type="checkbox" />
          </label>
        </div>

        <button class="btn btn-primary btn-lg greeting-submit" type="submit" :disabled="saving">
          {{ saving ? t.saving : t.submit }}
        </button>
      </form>

      <p v-if="error" class="alert alert-danger mb-0 mt-3">{{ error }}</p>
    </div>
  </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { churchContactsApi } from '../services/churchContactsApi'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'

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
})

const resetForm = () => {
  form.name = ''
  form.phone = ''
  form.email = ''
  form.address = ''
  form.is_sda = false
}

const submit = async () => {
  error.value = ''
  saving.value = true
  try {
    await churchContactsApi.create({
      name: form.name,
      phone: form.phone || null,
      email: form.email || null,
      address: form.address || null,
      is_sda: Boolean(form.is_sda),
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
  background:
    radial-gradient(circle at top left, rgba(216, 159, 88, 0.18), transparent 28%),
    linear-gradient(145deg, #f7f1e4 0%, #fffdf7 45%, #eef3eb 100%);
}

.greeting-panel {
  width: min(100%, 720px);
  padding: 2rem;
  border-radius: 28px;
  background: rgba(255, 255, 255, 0.92);
  border: 1px solid rgba(54, 73, 53, 0.08);
  box-shadow: 0 30px 70px rgba(73, 59, 34, 0.12);
}

.greeting-header {
  margin-bottom: 1.5rem;
}

.greeting-kicker {
  margin: 0 0 0.5rem;
  font-size: 0.85rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #7b6441;
}

.greeting-title {
  margin: 0;
  font-size: clamp(2rem, 4vw, 3rem);
  line-height: 1;
  color: #243126;
}

.greeting-copy {
  margin: 0.75rem 0 0;
  font-size: 1.05rem;
  color: #55645a;
}

.greeting-form {
  display: grid;
  gap: 1rem;
}

.greeting-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.1rem;
  border-radius: 18px;
  background: #f6f4ee;
}

.greeting-toggle-label {
  font-weight: 600;
  color: #243126;
}

.greeting-toggle-hint {
  font-size: 0.92rem;
  color: #66746a;
}

.greeting-submit {
  margin-top: 0.5rem;
}

@media (max-width: 767px) {
  .greeting-shell {
    padding: 1rem;
  }

  .greeting-panel {
    padding: 1.25rem;
    border-radius: 22px;
  }
}
</style>
