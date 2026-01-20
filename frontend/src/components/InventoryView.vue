<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">{{ t.title }}</h1>
    </div>

    <form v-if="canEdit" class="bg-white border rounded p-4 mb-4" @submit.prevent="createItem">
      <h2 class="h5 mb-3">{{ t.createTitle }}</h2>
      <div class="row g-3">
        <div v-if="isSuperAdmin" class="col-12 col-md-6">
          <label class="form-label">
            {{ t.fields.church }}
            <select v-model="selectedChurchId" class="form-select" required>
              <option value="">{{ common.select }}</option>
              <option v-for="church in churches" :key="church.id" :value="String(church.id)">
                {{ church.name }}
              </option>
            </select>
          </label>
        </div>
        <div v-if="isSuperAdmin" class="col-12 col-md-6">
          <label class="form-label">
            {{ t.fields.department }}
            <select v-model="createForm.department_id" class="form-select" :disabled="departments.length === 0" required>
              <option value="">{{ common.select }}</option>
              <option v-for="department in departments" :key="department.id" :value="department.id">
                {{ department.name }}
              </option>
            </select>
          </label>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">
            {{ t.fields.quantity }}
            <input v-model.number="createForm.quantity" class="form-control" type="number" min="1" />
          </label>
        </div>
        <div class="col-12 col-md-8">
          <label class="form-label">
            {{ t.fields.description }}
            <input v-model="createForm.description" class="form-control" type="text" required />
          </label>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">
            {{ t.fields.brand }}
            <input v-model="createForm.brand" class="form-control" type="text" />
          </label>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">
            {{ t.fields.model }}
            <input v-model="createForm.model" class="form-control" type="text" />
          </label>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">
            {{ t.fields.serial }}
            <input v-model="createForm.serial_number" class="form-control" type="text" />
          </label>
        </div>
        <div class="col-12">
          <label class="form-label">
            {{ t.fields.location }}
            <textarea v-model="createForm.location" class="form-control" rows="2"></textarea>
          </label>
        </div>
      </div>
      <button class="btn btn-primary mt-3" type="submit">{{ t.create }}</button>
    </form>

    <div v-if="loading">{{ t.loading }}</div>
    <p v-if="error" class="text-danger">{{ error }}</p>

    <div v-if="items.length === 0 && !loading" class="text-muted">{{ t.empty }}</div>
    <div v-else class="bg-white border rounded p-3">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <div class="flex-grow-1">
          <label class="form-label mb-0 w-100">
            <span class="d-block small mb-1">{{ t.search }}</span>
            <input v-model="filterText" class="form-control" type="search" :placeholder="t.searchPlaceholder" />
          </label>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm" type="button" @click="exportCsv">
            {{ t.export }}
          </button>
        </div>
      </div>
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0 inventory-table" data-dt="off">
          <thead>
            <tr>
              <th v-if="showChurchColumn">{{ t.fields.church }}</th>
              <th>{{ t.fields.department }}</th>
              <th>{{ t.fields.quantity }}</th>
              <th>{{ t.fields.description }}</th>
              <th>{{ t.fields.brand }}</th>
              <th>{{ t.fields.model }}</th>
              <th>{{ t.fields.serial }}</th>
              <th>{{ t.fields.location }}</th>
              <th v-if="canEdit" class="text-end">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredItems" :key="item.id">
              <td v-if="showChurchColumn">{{ item.department?.church?.name || '—' }}</td>
              <td>{{ item.department?.name || '—' }}</td>
              <td><input v-if="canEdit" v-model.number="item.quantity" class="form-control" type="number" min="1" />
                  <span v-else>{{ item.quantity }}</span>
              </td>
              <td>
                <input v-if="canEdit" v-model="item.description" class="form-control" type="text" />
                <span v-else class="inventory-description">{{ item.description }}</span>
              </td>
              <td><input v-if="canEdit" v-model="item.brand" class="form-control" type="text" />
                  <span v-else>{{ item.brand || '—' }}</span>
              </td>
              <td><input v-if="canEdit" v-model="item.model" class="form-control" type="text" />
                  <span v-else>{{ item.model || '—' }}</span>
              </td>
              <td><input v-if="canEdit" v-model="item.serial_number" class="form-control" type="text" />
                  <span v-else>{{ item.serial_number || '—' }}</span>
              </td>
              <td>
                <textarea v-if="canEdit" v-model="item.location" class="form-control" rows="2"></textarea>
                <span v-else>{{ item.location || '—' }}</span>
              </td>
              <td v-if="canEdit" class="text-end">
                <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
                  <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateItem(item)">
                    {{ t.save }}
                  </button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="confirmDeleteItem(item)">
                    {{ t.delete }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="d-md-none">
        <div v-for="item in filteredItems" :key="item.id" class="card mb-3 inventory-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h5 class="card-title mb-1 inventory-description">{{ item.description }}</h5>
                <p class="card-subtitle text-muted mb-0">#{{ item.id }}</p>
              </div>
            </div>
            <div v-if="showChurchColumn" class="mb-2">
              <strong>{{ t.fields.church }}:</strong> {{ item.department?.church?.name || '—' }}
            </div>
            <div class="mb-2">
              <strong>{{ t.fields.department }}:</strong> {{ item.department?.name || '—' }}
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.quantity }}</label>
              <input v-if="canEdit" v-model.number="item.quantity" class="form-control" type="number" min="1" />
              <p v-else class="mb-0">{{ item.quantity }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.brand }}</label>
              <input v-if="canEdit" v-model="item.brand" class="form-control" type="text" />
              <p v-else class="mb-0">{{ item.brand || '—' }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.model }}</label>
              <input v-if="canEdit" v-model="item.model" class="form-control" type="text" />
              <p v-else class="mb-0">{{ item.model || '—' }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.serial }}</label>
              <input v-if="canEdit" v-model="item.serial_number" class="form-control" type="text" />
              <p v-else class="mb-0">{{ item.serial_number || '—' }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.location }}</label>
              <textarea v-if="canEdit" v-model="item.location" class="form-control" rows="2"></textarea>
              <p v-else class="mb-0">{{ item.location || '—' }}</p>
            </div>
            <div v-if="canEdit" class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-secondary" type="button" @click="updateItem(item)">{{ t.save }}</button>
              <button class="btn btn-sm btn-outline-danger" type="button" @click="confirmDeleteItem(item)">{{ t.delete }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="confirmOpen" class="confirm-modal-backdrop" role="presentation">
      <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-action-title">
        <h2 id="confirm-action-title" class="confirm-modal-title">{{ confirmTitle }}</h2>
        <p class="confirm-modal-text">{{ confirmMessage }}</p>
        <div class="confirm-modal-actions">
          <button class="btn btn-outline-secondary" type="button" @click="closeConfirm">
            {{ common.cancel }}
          </button>
          <button class="btn btn-outline-danger" type="button" @click="runConfirmAction">
            {{ t.delete }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../i18n/translations'
import { inventoryApi } from '../services/inventoryApi'
import { superAdminApi } from '../services/superAdminApi'
import { publicApi } from '../services/publicApi'

const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)

const items = ref([])
const churches = ref([])
const departments = ref([])
const selectedChurchId = ref('')
const loading = ref(false)
const error = ref('')
const confirmOpen = ref(false)
const confirmMessage = ref('')
const confirmAction = ref(null)
const filterText = ref('')

const t = computed(() => translations[locale.value].inventory)
const common = computed(() => translations[locale.value].common)

const isSuperAdmin = computed(() => authStore.user?.role === 'superadmin')
const canEdit = computed(() => authStore.user?.role === 'admin' || authStore.user?.role === 'superadmin')
const showChurchColumn = computed(() => authStore.user?.role === 'superadmin')
const confirmTitle = computed(() => t.value.confirmTitle || t.value.delete)

const createForm = reactive({
  department_id: '',
  quantity: 1,
  description: '',
  brand: '',
  model: '',
  serial_number: '',
  location: '',
})

const filteredItems = computed(() => {
  const term = filterText.value.trim().toLowerCase()
  if (!term) {
    return items.value
  }
  return items.value.filter((item) => {
    const haystack = [
      item.description,
      item.brand,
      item.model,
      item.serial_number,
      item.location,
      item.department?.name,
      item.department?.church?.name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return haystack.includes(term)
  })
})

const showSuccessToast = (message = '') => {
  const fallback = locale.value === 'es' ? 'Guardado correctamente.' : 'Saved successfully.'
  uiStore.showToast(message || fallback, 'success')
}

const showErrorToast = (message = '') => {
  const fallback = locale.value === 'es' ? 'Ocurrio un error.' : 'Something went wrong.'
  uiStore.showToast(message || fallback, 'error')
}

const loadItems = async () => {
  loading.value = true
  error.value = ''
  try {
    items.value = await inventoryApi.list()
  } catch {
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const loadChurches = async () => {
  if (!isSuperAdmin.value) {
    return
  }
  const response = await superAdminApi.listChurches()
  churches.value = response.data
}

const loadDepartments = async () => {
  if (!isSuperAdmin.value || !selectedChurchId.value) {
    departments.value = []
    return
  }
  try {
    departments.value = await publicApi.listDepartments(selectedChurchId.value)
  } catch {
    departments.value = []
  }
}

const createItem = async () => {
  error.value = ''
  try {
    if (isSuperAdmin.value && !createForm.department_id) {
      error.value = t.value.createError
      showErrorToast(error.value)
      return
    }
    const payload = {
      ...createForm,
      department_id: isSuperAdmin.value ? Number(createForm.department_id || 0) : undefined,
    }
    await inventoryApi.create(payload)
    createForm.quantity = 1
    createForm.description = ''
    createForm.brand = ''
    createForm.model = ''
    createForm.serial_number = ''
    createForm.location = ''
    if (isSuperAdmin.value) {
      createForm.department_id = ''
    }
    await loadItems()
    showSuccessToast()
  } catch {
    error.value = t.value.createError
    showErrorToast(error.value)
  }
}

const updateItem = async (item) => {
  error.value = ''
  try {
    await inventoryApi.update(item.id, {
      quantity: item.quantity,
      description: item.description,
      brand: item.brand || null,
      model: item.model || null,
      serial_number: item.serial_number || null,
      location: item.location || null,
    })
    showSuccessToast()
  } catch {
    error.value = t.value.updateError
    showErrorToast(error.value)
  }
}

const deleteItem = async (item) => {
  error.value = ''
  try {
    await inventoryApi.remove(item.id)
    await loadItems()
    showSuccessToast()
  } catch {
    error.value = t.value.deleteError
    showErrorToast(error.value)
  }
}

const openConfirm = (message, action) => {
  confirmMessage.value = message
  confirmAction.value = action
  confirmOpen.value = true
}

const closeConfirm = () => {
  confirmOpen.value = false
  confirmAction.value = null
}

const runConfirmAction = async () => {
  if (!confirmAction.value) {
    return
  }
  await confirmAction.value()
  closeConfirm()
}

const confirmDeleteItem = (item) => {
  const template = t.value.deleteConfirm
    || (locale.value === 'es'
      ? 'Esto eliminara {name}. Esta accion no se puede deshacer. Continuar?'
      : 'This will delete {name}. This cannot be undone. Continue?')
  const name = item.description || t.value.untitled
  openConfirm(template.replace('{name}', name), () => deleteItem(item))
}

const csvEscape = (value) => {
  const text = value === null || value === undefined ? '' : String(value)
  if (/[\",\n]/.test(text)) {
    return `\"${text.replace(/\"/g, '\"\"')}\"`
  }
  return text
}

const exportCsv = () => {
  const headers = [
    ...(showChurchColumn.value ? [t.value.fields.church] : []),
    t.value.fields.department,
    t.value.fields.quantity,
    t.value.fields.description,
    t.value.fields.brand,
    t.value.fields.model,
    t.value.fields.serial,
    t.value.fields.location,
  ]

  const rows = filteredItems.value.map((item) => ([
    ...(showChurchColumn.value ? [item.department?.church?.name || ''] : []),
    item.department?.name || '',
    item.quantity || '',
    item.description || '',
    item.brand || '',
    item.model || '',
    item.serial_number || '',
    item.location || '',
  ]))

  const csvLines = [
    headers.map(csvEscape).join(','),
    ...rows.map((row) => row.map(csvEscape).join(',')),
  ]

  const blob = new Blob([csvLines.join('\\n')], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  const now = new Date().toISOString().slice(0, 10)
  link.href = window.URL.createObjectURL(blob)
  link.download = `inventory-${now}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(link.href)
}

watch(selectedChurchId, async () => {
  await loadDepartments()
})

watch(confirmOpen, (next) => {
  document.body.style.overflow = next ? 'hidden' : ''
})

onMounted(async () => {
  await loadItems()
  await loadChurches()
  if (isSuperAdmin.value) {
    await loadDepartments()
  }
})

onUnmounted(() => {
  document.body.style.overflow = ''
})
</script>
