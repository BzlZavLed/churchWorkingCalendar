<template>
  <section class="container py-4 inventory-page">
    <header class="inventory-heading">
      <div>
        <p class="inventory-eyebrow">{{ authStore.user?.church?.name || t.allChurches }}</p>
        <div class="creation-heading"><h1>{{ t.title }}</h1><button v-if="canEdit" class="creation-plus" type="button" :aria-label="t.newEntry" :title="t.newEntry" @click="openCreateModal"><span aria-hidden="true">+</span></button></div>
        <p>{{ locale === 'es' ? 'Consulta los bienes, cantidades y valores de tu equipo.' : 'Track your team’s items, quantities and values.' }}</p>
      </div>
      <span v-if="!canEdit" class="inventory-readonly">{{ locale === 'es' ? 'Consulta · Todos los departamentos' : 'Read only · All departments' }}</span>

    </header>

    <p v-if="error" class="inventory-error" role="alert">{{ error }}</p>
    <div class="inventory-summary" aria-live="polite">
      <div><span>{{ locale === 'es' ? 'Artículos' : 'Items' }}</span><strong>{{ loading ? '—' : filteredItems.length }}</strong></div>
      <div><span>{{ locale === 'es' ? 'Unidades' : 'Units' }}</span><strong>{{ loading ? '—' : quantityTotal }}</strong></div>
      <div><span>{{ locale === 'es' ? 'Valor registrado' : 'Recorded value' }}</span><strong>{{ loading ? '—' : formatCurrency(pageTotalValue) }}</strong><small v-if="unvaluedCount">{{ unvaluedCount }} {{ locale === 'es' ? 'artículos sin valor registrado' : 'items without a recorded value' }}</small></div>
    </div>

    <div class="inventory-surface">
      <div class="inventory-toolbar">
        <label class="inventory-search"><span class="visually-hidden">{{ t.search }}</span><input v-model="filterText" class="form-control" type="search" :placeholder="t.searchPlaceholder" /></label>
        <label v-if="!authStore.user?.department_id">{{ t.fields.department }}<select v-model="departmentFilter" class="form-select"><option value="all">{{ locale === 'es' ? 'Todos los departamentos' : 'All departments' }}</option><option v-for="department in inventoryDepartments" :key="department.id" :value="String(department.id)">{{ department.name }}</option></select></label>
        <button class="btn btn-outline-secondary w-auto" type="button" :disabled="loading || !!error || !filteredItems.length" @click="exportCsv">{{ t.export }}</button>
      </div>
      <div v-if="loading" class="inventory-empty" role="status">{{ t.loading }}</div>
      <div v-else-if="!filteredItems.length" class="inventory-empty">{{ items.length ? (locale === 'es' ? 'No hay artículos con estos filtros.' : 'No items match these filters.') : t.empty }}</div>
      <template v-else>
        <div class="table-responsive">
          <table class="table inventory-table mb-0" data-dt="off">
            <thead><tr><th>{{ t.fields.description }}</th><th>{{ t.fields.department }}</th><th>{{ t.fields.location }}</th><th class="text-end">{{ t.fields.quantity }}</th><th class="text-end">{{ t.fields.value }}</th><th class="text-end">{{ t.fields.totalValue }}</th><th v-if="canEdit" class="text-end">{{ t.actions }}</th></tr></thead>
            <tbody><tr v-for="item in paginatedItems" :key="item.id">
              <td class="inventory-item"><strong>{{ item.description }}</strong><span v-if="item.brand || item.model">{{ [item.brand, item.model].filter(Boolean).join(' · ') }}</span><span v-if="item.serial_number">{{ t.fields.serial }}: {{ item.serial_number }}</span><a v-if="showAmazonLink(item)" class="inventory-link" :href="amazonSearchUrl(item)" target="_blank" rel="noopener noreferrer">{{ t.searchMarket }} ↗</a></td>
              <td><span class="department-pill" :style="{ '--department-color': item.department?.color || '#8b9990' }"><span class="department-color-dot" aria-hidden="true"></span>{{ item.department?.name || '—' }}</span><small v-if="isSuperAdmin" class="d-block mt-1">{{ item.department?.church?.name }}</small></td>
              <td class="inventory-location">{{ item.location || '—' }}</td>
              <td class="text-end">{{ item.quantity }}</td><td class="text-end inventory-money">{{ formatCurrency(item.value) }}</td><td class="text-end inventory-money">{{ formatCurrency(totalValue(item)) }}</td>
              <td v-if="canEdit"><div class="inventory-actions"><button class="btn btn-sm btn-outline-secondary" type="button" @click="openEditModal(item)">{{ locale === 'es' ? 'Editar' : 'Edit' }}</button><button class="btn btn-sm btn-outline-danger" type="button" @click="confirmDeleteItem(item)">{{ t.delete }}</button></div></td>
            </tr></tbody>
          </table>
        </div>
        <footer class="inventory-pagination"><span>{{ filteredItems.length }} {{ locale === 'es' ? 'artículos' : 'items' }} · {{ t.page }} {{ currentPage }} / {{ totalPages }}</span><div class="d-flex gap-2"><button class="btn btn-outline-secondary w-auto" type="button" :disabled="currentPage === 1" @click="goPrev">{{ t.prev }}</button><button class="btn btn-outline-secondary w-auto" type="button" :disabled="currentPage === totalPages" @click="goNext">{{ t.next }}</button></div></footer>
      </template>
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
const departmentFilter = ref('all')
const createModalOpen = ref(false)
const editModalOpen = ref(false)
const editChurchId = ref('')
const editForm = ref(null)
const currentPage = ref(1)
const pageSize = 10

const t = computed(() => translations[locale.value].inventory)
const common = computed(() => translations[locale.value].common)

const isSuperAdmin = computed(() => authStore.user?.role === 'superadmin')
const canEdit = computed(() => authStore.can('inventory.manage') && (authStore.isSuperAdmin || !!authStore.user?.department_id))
const confirmTitle = computed(() => t.value.confirmTitle || t.value.delete)
const inventoryDepartments = computed(() => [...new Map(items.value.filter(item => item.department).map(item => [item.department.id, item.department])).values()].sort((a, b) => a.name.localeCompare(b.name)))
const quantityTotal = computed(() => filteredItems.value.reduce((sum, item) => sum + Number(item.quantity || 0), 0))
const unvaluedCount = computed(() => filteredItems.value.filter(item => item.value === null || item.value === undefined || item.value === '').length)

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
  return items.value.filter((item) => {
    if (departmentFilter.value !== 'all' && String(item.department_id) !== departmentFilter.value) return false
    if (!term) return true
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

const pageTotalValue = computed(() => {
  return filteredItems.value.reduce((sum, item) => {
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
  if (item.value === null || item.value === undefined || item.value === '') return null
  const unit = Number(item.value)
  const qty = Number(item.quantity || 0)
  if (Number.isNaN(unit) || Number.isNaN(qty)) {
    return 0
  }
  return unit * qty
}

const showAmazonLink = (item) => {
  return item.value === null || item.value === undefined || item.value === ''
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
    item.value ?? '',
    totalValue(item) ?? '',
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

  const blob = new Blob([csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' })
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

watch([filterText, departmentFilter], () => {
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

<style scoped>
.inventory-page { display: grid; gap: 20px; font-size: 13px; }
.inventory-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
.inventory-heading h1 { font-size: 20px; font-weight: 500; margin: 0 0 8px; }
.inventory-heading p { margin: 0; color: #68766e; }
.inventory-heading .inventory-eyebrow { color: #28705b; font-size: 12px; margin-bottom: 6px; }
.inventory-readonly { padding: 7px 12px; background: #e8efeb; border-radius: 20px; font-size: 12px; color: #426052; }
.inventory-summary { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.inventory-summary > div { border: 1px solid #dfe5df; border-radius: 9px; background: white; padding: 16px 18px; display: grid; gap: 8px; }
.inventory-summary span, .inventory-summary small { color: #68766e; font-size: 12px; }
.inventory-summary strong { font-size: 20px; font-weight: 500; color: #254b3d; }
.inventory-surface { border: 1px solid #dfe5df; border-radius: 10px; background: white; overflow: hidden; }
.inventory-toolbar { display: flex; align-items: end; gap: 16px; padding: 18px; background: #f8faf9; }
.inventory-toolbar label { margin: 0; font-size: 12px; min-width: 180px; }
.inventory-toolbar input, .inventory-toolbar select { margin-top: 6px; }
.inventory-search { flex: 1; }
.inventory-page .btn { font-size: 12px; white-space: nowrap; }
.inventory-page .btn-primary { background: #1f6f5c; border-color: #1f6f5c; }
.inventory-table { font-size: 12px; }
.inventory-table th { background: #f6f8f7; color: #596b61; font-weight: 500; padding: 12px 16px; white-space: nowrap; }
.inventory-table td { padding: 14px 16px; vertical-align: middle; border-color: #edf0ed; }
.inventory-table tr:hover td { background: #f9fbfa; }
.inventory-item { min-width: 190px; }
.inventory-item strong { display: block; font-weight: 500; color: #263d32; }
.inventory-item > span, .inventory-item > a { display: block; margin-top: 4px; font-size: 11px; color: #6b7971; }
.inventory-item > a { color: #28705b; }
.inventory-location { min-width: 130px; max-width: 250px; white-space: pre-wrap; overflow-wrap: anywhere; }
.inventory-money { white-space: nowrap; font-variant-numeric: tabular-nums; }
.department-pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 5px; padding: 4px 8px; color: #263d32; background: color-mix(in srgb, var(--department-color) 12%, white); }
.department-color-dot { width: 8px; height: 8px; flex: 0 0 8px; border-radius: 50%; background: var(--department-color); }
.inventory-actions { display: flex; gap: 6px; justify-content: end; }
.inventory-actions button { width: auto; }
.inventory-pagination { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; color: #68766e; font-size: 12px; }
.inventory-empty { padding: 48px 24px; text-align: center; color: #68766e; }
.inventory-error { padding: 12px; background: #fff1ef; color: #8a3931; border-radius: 6px; margin: 0; }
@media (max-width: 767px) { .inventory-heading, .inventory-toolbar { flex-direction: column; align-items: stretch; } .inventory-toolbar label { width: 100%; } .inventory-summary { grid-template-columns: 1fr; } .inventory-summary > div { grid-template-columns: 1fr auto; align-items: center; } .inventory-summary small { grid-column: 1 / -1; } .inventory-pagination { flex-wrap: wrap; } }
</style>
