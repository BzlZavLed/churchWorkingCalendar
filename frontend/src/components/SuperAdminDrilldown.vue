<template>
  <section v-if="auth.isPlatformAdmin" class="working-context" aria-labelledby="working-context-title">
    <h2 id="working-context-title" class="app-sidebar-title">
      {{ es ? 'Elegir contexto de trabajo' : 'Choose working context' }}
    </h2>
    <label class="d-block mb-2">
      <span class="form-label small mb-1">{{ es ? 'Iglesia' : 'Church' }}</span>
      <select class="form-select form-select-sm" :value="context?.church.id || ''" :disabled="loadingChurches" @change="selectChurch">
        <option value="">{{ es ? 'Superadmin · Todas las iglesias' : 'Superadmin · All churches' }}</option>
        <option v-for="church in churches" :key="church.id" :value="church.id">{{ church.name }}</option>
      </select>
    </label>
    <label v-if="context" class="d-block">
      <span class="form-label small mb-1">{{ es ? 'Vista de trabajo' : 'Working view' }}</span>
      <select class="form-select form-select-sm" :value="context.department?.id || ''" :disabled="loadingDepartments" @change="selectDepartment">
        <option value="">{{ es ? 'Secretaría · Todos los departamentos' : 'Clerk · All departments' }}</option>
        <option v-for="department in departments" :key="department.id" :value="department.id">{{ es ? 'Departamento' : 'Department' }} · {{ department.name }}</option>
      </select>
    </label>
    <p v-if="context" class="small mt-2 mb-0">
      {{ es ? 'Para revisar todos los eventos, elige Secretaría. Filtra por departamento dentro de la lista de eventos.' : 'Choose Clerk to review all events. Filter by department within the event list.' }}
    </p>
    <p v-if="error" class="text-danger small mt-2 mb-0" role="alert">{{ error }}</p>
  </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
import { superAdminApi } from '../services/superAdminApi'

const auth = useAuthStore()
const router = useRouter()
const ui = useUiStore()
const es = computed(() => ui.locale === 'es')
const context = computed(() => auth.adminContext)
const churches = ref([])
const departments = ref([])
const loadingChurches = ref(false)
const loadingDepartments = ref(false)
const error = ref('')

watch(() => auth.isPlatformAdmin, async (enabled) => {
  churches.value = []
  if (!enabled) return
  loadingChurches.value = true
  try {
    let page = 1
    let result
    const items = []
    do {
      result = await superAdminApi.listChurches({ page })
      items.push(...result.data)
      page += 1
    } while (result.next_page_url)
    if (auth.isPlatformAdmin) churches.value = items
  } catch {
    error.value = es.value ? 'No se pudieron cargar las iglesias.' : 'Unable to load churches.'
  } finally {
    loadingChurches.value = false
  }
}, { immediate: true })

watch([() => context.value?.church.id, () => auth.isPlatformAdmin], async ([churchId]) => {
  departments.value = []
  error.value = ''
  loadingDepartments.value = false
  if (!churchId || !auth.isPlatformAdmin) return
  loadingDepartments.value = true
  try {
    const result = await superAdminApi.listDepartments(churchId)
    if (context.value?.church.id === churchId) departments.value = result
  } catch {
    if (context.value?.church.id === churchId) {
      error.value = es.value ? 'No se pudieron cargar los departamentos.' : 'Unable to load departments.'
    }
  } finally {
    if (context.value?.church.id === churchId) loadingDepartments.value = false
  }
}, { immediate: true })

const selectChurch = async (event) => {
  const church = churches.value.find((item) => item.id === Number(event.target.value))
  if (church) {
    auth.enterChurch({ id: church.id, name: church.name, slug: church.slug })
    await router.push('/calendar')
  } else {
    auth.exitContext()
    await router.push('/superadmin/churches')
  }
}
const selectDepartment = async (event) => {
  const department = departments.value.find((item) => item.id === Number(event.target.value))
  if (department) auth.enterDepartment(department)
  else auth.enterChurch(context.value.church)
  await router.push('/calendar')
}
</script>

<style scoped>
.working-context {
  margin-bottom: 1.25rem;
  min-width: 0;
}
.working-context select {
  width: 100%;
  min-width: 0;
  text-overflow: ellipsis;
}
</style>
