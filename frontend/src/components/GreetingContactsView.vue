<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <div>
        <p class="text-uppercase small text-muted mb-1">{{ t.kicker }}</p>
        <h1 class="h3 m-0">{{ t.title }}</h1>
      </div>
      <button class="btn btn-outline-secondary" type="button" @click="loadContacts">
        {{ t.refresh }}
      </button>
    </div>

    <p class="text-muted">{{ t.subtitle }}</p>
    <div v-if="loading">{{ t.loading }}</div>
    <p v-if="error" class="text-danger">{{ error }}</p>
    <div v-if="!loading && contacts.length === 0" class="text-muted">{{ t.empty }}</div>

    <div v-else class="bg-white border rounded p-3">
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0" data-dt="off">
          <thead>
            <tr>
              <th>{{ t.columns.name }}</th>
              <th>{{ t.columns.phone }}</th>
              <th>{{ t.columns.email }}</th>
              <th>{{ t.columns.address }}</th>
              <th>{{ t.columns.member }}</th>
              <th>{{ t.columns.date }}</th>
              <th>{{ t.columns.by }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="contact in contacts" :key="contact.id">
              <td>{{ contact.name }}</td>
              <td>{{ contact.phone || '—' }}</td>
              <td>{{ contact.email || '—' }}</td>
              <td>{{ contact.address || '—' }}</td>
              <td>
                <span class="badge" :class="contact.is_sda ? 'text-bg-success' : 'text-bg-warning'">
                  {{ contact.is_sda ? t.memberYes : t.memberNo }}
                </span>
              </td>
              <td>{{ formatDate(contact.created_at) }}</td>
              <td>{{ contact.creator?.name || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-md-none">
        <div v-for="contact in contacts" :key="contact.id" class="card mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between gap-3 mb-2">
              <div>
                <h5 class="card-title mb-1">{{ contact.name }}</h5>
                <p class="card-subtitle text-muted mb-0">{{ formatDate(contact.created_at) }}</p>
              </div>
              <span class="badge align-self-start" :class="contact.is_sda ? 'text-bg-success' : 'text-bg-warning'">
                {{ contact.is_sda ? t.memberYes : t.memberNo }}
              </span>
            </div>
            <p class="mb-1"><strong>{{ t.columns.phone }}:</strong> {{ contact.phone || '—' }}</p>
            <p class="mb-1"><strong>{{ t.columns.email }}:</strong> {{ contact.email || '—' }}</p>
            <p class="mb-1"><strong>{{ t.columns.address }}:</strong> {{ contact.address || '—' }}</p>
            <p class="mb-0"><strong>{{ t.columns.by }}:</strong> {{ contact.creator?.name || '—' }}</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { churchContactsApi } from '../services/churchContactsApi'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'

const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].greeting.contacts)
const contacts = ref([])
const loading = ref(false)
const error = ref('')

const loadContacts = async () => {
  loading.value = true
  error.value = ''
  try {
    contacts.value = await churchContactsApi.list()
  } catch {
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const formatDate = (value) => {
  if (!value) {
    return '—'
  }

  return new Intl.DateTimeFormat(locale.value === 'es' ? 'es-US' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

onMounted(loadContacts)
</script>
