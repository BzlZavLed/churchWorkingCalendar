<template>
  <section class="container py-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h1 class="h3 m-0">{{ t.title }}</h1>
    </div>

    <div v-if="loading">{{ t.loading }}</div>
    <p v-if="error" class="text-danger">{{ error }}</p>

    <div v-if="items.length === 0 && !loading" class="text-muted">
      {{ t.empty }}
      <div v-if="canEdit" class="mt-3">
        <button class="btn btn-outline-primary btn-sm" type="button" @click="openCreateModal">
          {{ t.newEntry }}
        </button>
      </div>
    </div>
    <div v-else class="bg-white border rounded p-3">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <div class="flex-grow-1">
          <h2 class="h5 m-0">{{ tableTitle }}</h2>
          <label class="form-label mb-0 w-100">
            <span class="d-block small mb-1">{{ t.search }}</span>
            <input v-model="filterText" class="form-control" type="search" :placeholder="t.searchPlaceholder" />
          </label>
        </div>
        <div class="d-flex gap-2">
          <button v-if="canEdit" class="btn btn-outline-primary btn-sm" type="button" @click="openCreateModal">
            {{ t.newEntry }}
          </button>
          <button class="btn btn-outline-secondary btn-sm" type="button" @click="exportCsv">
            {{ t.export }}
          </button>
        </div>
      </div>
      <div class="table-responsive d-none d-md-block">
        <table class="table table-sm align-middle mb-0 inventory-table" data-dt="off">
          <thead>
            <tr>
              <th>{{ tableFields.department }}</th>
              <th>{{ tableFields.quantity }}</th>
              <th>{{ tableFields.value }}</th>
              <th>{{ tableFields.totalValue }}</th>
              <th>{{ tableFields.description }}</th>
              <th>{{ tableFields.brand }}</th>
              <th>{{ tableFields.model }}</th>
              <th>{{ tableFields.serial }}</th>
              <th>{{ tableFields.location }}</th>
              <th>{{ tableFields.market }}</th>
              <th v-if="canEdit" class="text-end">{{ t.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in paginatedItems" :key="item.id">
              <td>{{ item.department?.name || '—' }}</td>
              <td>{{ item.quantity }}</td>
              <td>{{ formatCurrency(item.value) }}</td>
              <td>{{ formatCurrency(totalValue(item)) }}</td>
              <td><span class="inventory-description">{{ item.description }}</span></td>
              <td>{{ item.brand || '—' }}</td>
              <td>{{ item.model || '—' }}</td>
              <td>{{ item.serial_number || '—' }}</td>
              <td><span class="inventory-notes">{{ item.location || '—' }}</span></td>
              <td>
                <a
                  v-if="showAmazonLink(item)"
                  class="inventory-link"
                  :href="amazonSearchUrl(item)"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ t.searchMarket }}
                </a>
                <span v-else>—</span>
              </td>
              <td v-if="canEdit" class="text-end">
                <div class="d-flex flex-column flex-md-row justify-content-end gap-2">
                  <button class="btn btn-sm btn-outline-secondary" type="button" @click="openEditModal(item)">
                    ✎
                  </button>
                  <button class="btn btn-sm btn-outline-danger" type="button" @click="confirmDeleteItem(item)">
                    ✕
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th>{{ t.totals }}</th>
              <th></th>
              <th>{{ formatCurrency(pageValueTotal) }}</th>
              <th>{{ formatCurrency(pageTotalValue) }}</th>
              <th colspan="6"></th>
              <th v-if="canEdit"></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="d-md-none">
        <div v-for="item in paginatedItems" :key="item.id" class="card mb-3 inventory-card">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <h5 class="card-title mb-1 inventory-description">{{ item.description }}</h5>
                <p class="card-subtitle text-muted mb-0">#{{ item.id }}</p>
              </div>
            </div>
            <div class="mb-2">
              <strong>{{ t.fields.department }}:</strong> {{ item.department?.name || '—' }}
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.quantity }}</label>
              <p class="mb-0">{{ item.quantity }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.value }}</label>
              <p class="mb-0">{{ formatCurrency(item.value) }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.totalValue }}</label>
              <p class="mb-0">{{ formatCurrency(totalValue(item)) }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.brand }}</label>
              <p class="mb-0">{{ item.brand || '—' }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.model }}</label>
              <p class="mb-0">{{ item.model || '—' }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.serial }}</label>
              <p class="mb-0">{{ item.serial_number || '—' }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.location }}</label>
              <p class="mb-0">{{ item.location || '—' }}</p>
            </div>
            <div class="mb-2">
              <label class="form-label small mb-1">{{ t.fields.market }}</label>
              <p class="mb-0">
                <a
                  v-if="showAmazonLink(item)"
                  class="inventory-link"
                  :href="amazonSearchUrl(item)"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {{ t.searchMarket }}
                </a>
                <span v-else>—</span>
              </p>
            </div>
            <div v-if="canEdit" class="d-flex flex-wrap gap-2">
              <button class="btn btn-sm btn-outline-secondary" type="button" @click="openEditModal(item)">✎</button>
              <button class="btn btn-sm btn-outline-danger" type="button" @click="confirmDeleteItem(item)">✕</button>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 mt-3">
        <div class="text-muted small">
          {{ t.page }} {{ currentPage }} / {{ totalPages }}
        </div>
        <div class="btn-group">
          <button class="btn btn-outline-secondary btn-sm" type="button" :disabled="currentPage === 1" @click="goPrev">
            {{ t.prev }}
          </button>
          <button
            v-for="page in totalPages"
            :key="page"
            class="btn btn-outline-secondary btn-sm"
            :class="{ active: currentPage === page }"
            type="button"
            @click="goToPage(page)"
          >
            {{ page }}
          </button>
          <button
            class="btn btn-outline-secondary btn-sm"
            type="button"
            :disabled="currentPage === totalPages"
            @click="goNext"
          >
            {{ t.next }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="createModalOpen" class="modal-backdrop" @click.self="closeCreateModal">
      <div class="modal-panel modal-panel--lg">
        <header class="modal-header">
          <h2>{{ t.createTitle }}</h2>
          <button type="button" class="modal-close" @click="closeCreateModal">×</button>
        </header>
        <form class="mt-3" @submit.prevent="createItem">
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
            <div class="col-12 col-md-4">
              <label class="form-label">
                {{ t.fields.value }}
                <input v-model.number="createForm.value" class="form-control" type="number" min="0" step="0.01" />
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
          <div class="action-row mt-3">
            <button type="button" class="btn btn-outline-secondary" @click="closeCreateModal">{{ common.cancel }}</button>
            <button type="submit" class="btn btn-outline-primary">{{ t.create }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="editModalOpen && editForm" class="modal-backdrop" @click.self="closeEditModal">
      <div class="modal-panel modal-panel--lg">
        <header class="modal-header">
          <h2>{{ t.editTitle }}</h2>
          <button type="button" class="modal-close" @click="closeEditModal">×</button>
        </header>
        <form class="mt-3" @submit.prevent="submitEdit">
          <div class="row g-3">
            <div v-if="isSuperAdmin" class="col-12 col-md-6">
              <label class="form-label">
                {{ t.fields.church }}
                <select v-model="editChurchId" class="form-select" required>
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
                <select v-model="editForm.department_id" class="form-select" :disabled="departments.length === 0" required>
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
                <input v-model.number="editForm.quantity" class="form-control" type="number" min="1" />
              </label>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">
                {{ t.fields.value }}
                <input v-model.number="editForm.value" class="form-control" type="number" min="0" step="0.01" />
              </label>
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">
                {{ t.fields.description }}
                <input v-model="editForm.description" class="form-control" type="text" required />
              </label>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">
                {{ t.fields.brand }}
                <input v-model="editForm.brand" class="form-control" type="text" />
              </label>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">
                {{ t.fields.model }}
                <input v-model="editForm.model" class="form-control" type="text" />
              </label>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">
                {{ t.fields.serial }}
                <input v-model="editForm.serial_number" class="form-control" type="text" />
              </label>
            </div>
            <div class="col-12">
              <label class="form-label">
                {{ t.fields.location }}
                <textarea v-model="editForm.location" class="form-control" rows="2"></textarea>
              </label>
            </div>
          </div>
          <div class="action-row mt-3">
            <button type="button" class="btn btn-outline-secondary" @click="closeEditModal">{{ common.cancel }}</button>
            <button type="submit" class="btn btn-outline-primary">{{ t.save }}</button>
          </div>
        </form>
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
const createModalOpen = ref(false)
const editModalOpen = ref(false)
const editChurchId = ref('')
const editForm = ref(null)
const currentPage = ref(1)
const pageSize = 10

const t = computed(() => translations[locale.value].inventory)
const common = computed(() => translations[locale.value].common)
const tableFields = computed(() => t.value.tableFields || t.value.fields)

const isSuperAdmin = computed(() => authStore.user?.role === 'superadmin')
const canEdit = computed(() => authStore.user?.role === 'admin' || authStore.user?.role === 'superadmin')
const confirmTitle = computed(() => t.value.confirmTitle || t.value.delete)
const tableTitle = computed(() => {
  const churchesSet = new Set()
  items.value.forEach((item) => {
    const name = item.department?.church?.name
    if (name) {
      churchesSet.add(name)
    }
  })
  if (churchesSet.size === 1) {
    return `${t.value.title}: ${Array.from(churchesSet)[0]}`
  }
  if (churchesSet.size > 1) {
    return `${t.value.title}: ${t.value.allChurches}`
  }
  return t.value.title
})

const createForm = reactive({
  department_id: '',
  quantity: 1,
  value: '',
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

const totalPages = computed(() => {
  const count = filteredItems.value.length
  return Math.max(1, Math.ceil(count / pageSize))
})

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * pageSize
  return filteredItems.value.slice(start, start + pageSize)
})

const pageValueTotal = computed(() => {
  return paginatedItems.value.reduce((sum, item) => {
    const value = Number(item.value || 0)
    return Number.isNaN(value) ? sum : sum + value
  }, 0)
})

const pageTotalValue = computed(() => {
  return paginatedItems.value.reduce((sum, item) => {
    const total = totalValue(item)
    return Number.isNaN(total) ? sum : sum + total
  }, 0)
})

const showSuccessToast = (message = '') => {
  const fallback = locale.value === 'es' ? 'Guardado correctamente.' : 'Saved successfully.'
  uiStore.showToast(message || fallback, 'success')
}

const showErrorToast = (message = '') => {
  const fallback = locale.value === 'es' ? 'Ocurrio un error.' : 'Something went wrong.'
  uiStore.showToast(message || fallback, 'error')
}

const formatCurrency = (value) => {
  if (value === null || value === undefined || value === '') {
    return '—'
  }
  const numberValue = Number(value)
  if (Number.isNaN(numberValue)) {
    return '—'
  }
  const formatted = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numberValue)
  return `$${formatted} USD`
}

const totalValue = (item) => {
  const unit = Number(item.value || 0)
  const qty = Number(item.quantity || 0)
  if (Number.isNaN(unit) || Number.isNaN(qty)) {
    return 0
  }
  return unit * qty
}

const showAmazonLink = (item) => {
  return !item.value
}

const amazonSearchUrl = (item) => {
  const parts = [item.description, item.brand, item.model]
    .filter(Boolean)
    .join(' ')
    .trim()
  const query = encodeURIComponent(parts || '')
  return `https://www.amazon.com/s?k=${query}`
}

const loadItems = async () => {
  loading.value = true
  error.value = ''
  try {
    items.value = await inventoryApi.list()
    currentPage.value = 1
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
      value: createForm.value === '' ? null : createForm.value,
    }
    await inventoryApi.create(payload)
    createForm.quantity = 1
    createForm.value = ''
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
    closeCreateModal()
  } catch {
    error.value = t.value.createError
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

const openCreateModal = () => {
  createForm.quantity = 1
  createForm.value = ''
  createForm.description = ''
  createForm.brand = ''
  createForm.model = ''
  createForm.serial_number = ''
  createForm.location = ''
  if (isSuperAdmin.value) {
    createForm.department_id = ''
  }
  createModalOpen.value = true
}

const closeCreateModal = () => {
  createModalOpen.value = false
}

const openEditModal = (item) => {
  editForm.value = {
    id: item.id,
    department_id: item.department_id || '',
    quantity: item.quantity || 1,
    value: item.value ?? '',
    description: item.description || '',
    brand: item.brand || '',
    model: item.model || '',
    serial_number: item.serial_number || '',
    location: item.location || '',
  }
  if (isSuperAdmin.value) {
    editChurchId.value = String(item.department?.church?.id || '')
  }
  editModalOpen.value = true
}

const closeEditModal = () => {
  editModalOpen.value = false
  editForm.value = null
}

const submitEdit = async () => {
  if (!editForm.value) {
    return
  }
  error.value = ''
  try {
    const payload = {
      quantity: editForm.value.quantity,
      value: editForm.value.value === '' ? null : editForm.value.value,
      description: editForm.value.description,
      brand: editForm.value.brand || null,
      model: editForm.value.model || null,
      serial_number: editForm.value.serial_number || null,
      location: editForm.value.location || null,
    }
    if (isSuperAdmin.value) {
      payload.department_id = Number(editForm.value.department_id || 0)
    }
    await inventoryApi.update(editForm.value.id, payload)
    await loadItems()
    showSuccessToast()
    closeEditModal()
  } catch {
    error.value = t.value.updateError
    showErrorToast(error.value)
  }
}

const goToPage = (page) => {
  if (page < 1 || page > totalPages.value) {
    return
  }
  currentPage.value = page
}

const goPrev = () => {
  goToPage(currentPage.value - 1)
}

const goNext = () => {
  goToPage(currentPage.value + 1)
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
    t.value.fields.department,
    t.value.fields.quantity,
    t.value.fields.value,
    t.value.fields.totalValue,
    t.value.fields.description,
    t.value.fields.brand,
    t.value.fields.model,
    t.value.fields.serial,
    t.value.fields.location,
  ]

  const rows = filteredItems.value.map((item) => ([
    item.department?.name || '',
    item.quantity || '',
    item.value || '',
    totalValue(item) || '',
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
  if (isSuperAdmin.value) {
    createForm.department_id = ''
  }
  await loadDepartments()
  currentPage.value = 1
})

watch(editChurchId, async () => {
  if (!isSuperAdmin.value) {
    return
  }
  if (!editChurchId.value) {
    departments.value = []
    return
  }
  try {
    departments.value = await publicApi.listDepartments(editChurchId.value)
  } catch {
    departments.value = []
  }
})

watch(filterText, () => {
  currentPage.value = 1
})

watch(
  () => filteredItems.value.length,
  () => {
    if (currentPage.value > totalPages.value) {
      currentPage.value = totalPages.value
    }
  }
)

watch([confirmOpen, createModalOpen, editModalOpen], ([confirmOpenValue, createOpen, editOpen]) => {
  document.body.style.overflow = confirmOpenValue || createOpen || editOpen ? 'hidden' : ''
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
