<template>
  <section class="container py-4 management-page">
    <header class="management-heading">
      <p class="management-eyebrow">{{ authStore.user?.church?.name || (locale === 'es' ? 'Administración' : 'Administration') }}</p>
      <h1>{{ t.title }}</h1>
      <p>{{ locale === 'es' ? 'Administra los usuarios, sus roles y su departamento.' : 'Manage users, their roles and department.' }}</p>
    </header>
    <FormSection :busy="creating" v-write="'users.manage'" v-if="authStore.can('users.manage')" class="management-surface management-create mb-3" :title="locale === 'es' ? 'Crear usuario' : 'Create user'">
      <form :key="createFormVersion" class="row g-3 mt-1" @submit.prevent="createUser">
        <label class="col-12 col-md-6">{{ t.name }}<input v-model="createForm.name" class="form-control" required maxlength="255" autocomplete="name" /></label>
        <label class="col-12 col-md-6">{{ t.email }}<input v-model="createForm.email" class="form-control" type="email" required maxlength="255" autocomplete="off" /></label>
        <label class="col-12 col-md-6">{{ t.role }}<select v-model="createForm.role" class="form-select"><option v-for="role in ['admin', 'member', 'secretary', 'pastor']" :key="role" :value="role">{{ roleLabels[role] }}</option></select></label>
        <label class="col-12">{{ t.department }}<select v-model="createForm.department_id" class="form-select"><option value="">{{ common.select }}</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
        <WhatsAppUserFields :form="createForm" class="col-12" />
        <p v-if="createError" class="management-error col-12" role="alert">{{ createError }}</p>
        <p v-if="createSuccess" class="col-12" :class="invitationStatus !== 'accepted' ? 'management-error' : 'text-success'" role="status">{{ invitationStatus === 'simulated' ? (locale === 'es' ? 'Usuario creado, pero el servidor está en modo de correo de prueba y no se envió la invitación.' : 'User created, but the server is using a test mailer and the invitation was not sent.') : invitationStatus !== 'accepted' ? (locale === 'es' ? 'Usuario creado, pero no se pudo enviar la invitación. Reenvía el enlace desde la lista.' : 'User created, but the invitation could not be sent. Resend the link from the list.') : (locale === 'es' ? 'Usuario creado. Se envió una invitación para activar su cuenta.' : 'User created. An activation invitation was sent.') }}</p>
        <div class="col-12"><button class="btn btn-primary" :disabled="creating || loading">{{ creating ? (locale === 'es' ? 'Guardando…' : 'Saving…') : (locale === 'es' ? 'Crear usuario' : 'Create user') }}</button></div>
      </form>
    </FormSection>
    <div class="management-summary"><article><span>{{ t.title }}</span><strong>{{ loading ? '—' : users.length }}</strong></article>
      <article><span>{{ locale === 'es' ? 'Con departamento' : 'Assigned to a department' }}</span><strong>{{ loading ? '—' : users.filter(user => user.department_id).length }}</strong></article>
      <article><span>{{ locale === 'es' ? 'Administradores' : 'Administrators' }}</span><strong>{{ loading ? '—' : users.filter(user => user.role === 'admin').length }}</strong></article></div>

    <div v-if="loading" class="management-state" role="status">{{ t.loading }}</div>
    <p v-if="error" class="management-error" role="alert">{{ error }}</p>

    <div v-if="users.length === 0 && !loading" class="management-state">{{ t.empty }}</div>
    <div v-else-if="!loading" class="management-surface">
      <div class="row g-2 align-items-center m-0 management-toolbar">
        <div class="col-12 col-md-6">
          <label class="form-label mb-0 w-100">
            <span class="visually-hidden">{{ t.search }}</span>
            <input v-model="filterText" class="form-control" type="search" :placeholder="t.searchPlaceholder" />
          </label>
        </div>
      </div>
      <p class="activity-explanation">{{ locale === 'es' ? 'Sesión abierta indica que existe una sesión válida; no confirma que la persona esté en línea. Actividad reciente indica uso en los últimos 5 minutos.' : 'Open session means a valid session exists; it does not confirm the person is online. Recently active means the system was used in the last 5 minutes.' }}</p>

      <div class="user-list">
        <article v-for="user in filteredUsers" :key="user.id" class="user-card">
          <header class="user-card-heading"><div class="user-identity"><strong>{{ user.name }}</strong><span>{{ user.email }}</span></div><div class="user-badges"><span v-if="!user.pending_activation && user.active_sessions_count" class="role-badge">{{ locale === 'es' ? 'Sesión abierta' : 'Open session' }} · {{ user.active_sessions_count }}</span><span v-if="isRecentlyActive(user)" class="active-badge">● {{ locale === 'es' ? 'Actividad reciente' : 'Recently active' }}</span><span class="role-badge">{{ roleLabels[user.role] || user.role }}</span><span v-if="user.pending_activation" class="pending-badge">{{ locale === 'es' ? 'Pendiente de activar' : 'Activation pending' }}</span><span v-if="user.id === currentUserId" class="role-badge">{{ locale === 'es' ? 'Tu cuenta' : 'Your account' }}</span></div></header>
          <div class="user-fields">
            <label>{{ t.name }}<input v-model="user.name" class="form-control" type="text" :disabled="!authStore.can('users.manage')" /></label>
            <label>{{ t.email }}<input v-model="user.email" class="form-control" type="email" :disabled="!authStore.can('users.manage')" /></label>
            <label>{{ t.role }}<select v-model="user.role" class="form-select" :disabled="!authStore.can('users.manage')"><option v-for="role in ['admin', 'member', 'secretary', 'pastor']" :key="role" :value="role">{{ roleLabels[role] }}</option></select></label>
            <label>{{ t.department }}<select v-model="user.department_id" class="form-select" :disabled="!authStore.can('users.manage')"><option value="">{{ common.select }}</option><option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label>
          </div>
          <WhatsAppUserFields :form="user" />
          <div class="user-actions"><button v-write="'users.manage'" class="btn btn-sm btn-outline-secondary" type="button" @click="updateUser(user)">{{ t.save }}</button><button v-write="'users.manage'" class="btn btn-sm btn-outline-primary" type="button" @click="sendPasswordLink(user)">{{ locale === 'es' ? 'Enviar enlace de contraseña' : 'Send password link' }}</button></div>
        </article>
      </div>
    </div>

    <p v-if="!loading && users.length && !filteredUsers.length" class="management-state">{{ locale === 'es' ? 'No hay usuarios que coincidan con la búsqueda.' : 'No users match your search.' }}</p>
    <div v-if="passwordModalOpen && activeUser" class="modal-backdrop" @click.self="closePasswordModal">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.updatePassword }}</h2>
          <button type="button" class="modal-close" @click="closePasswordModal">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-text">
            <strong>{{ t.title }}:</strong> {{ activeUser.name }} ({{ activeUser.email }})
          </p>
          <label class="form-label mt-2">
            {{ t.password }}
            <input v-model="passwordForm.password" class="form-control" type="password" minlength="8" />
          </label>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline-secondary" @click="closePasswordModal">{{ common.cancel }}</button>
          <button
            type="button"
            class="btn btn-outline-primary"
            :disabled="!passwordForm.password"
            @click="submitPasswordUpdate"
          >
            {{ t.updatePassword }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="passwordSuccessOpen" class="modal-backdrop" @click.self="passwordSuccessOpen = false">
      <div class="modal-panel">
        <header class="modal-header">
          <h2>{{ t.passwordSuccessTitle }}</h2>
          <button type="button" class="modal-close" @click="passwordSuccessOpen = false">×</button>
        </header>
        <div class="event-details">
          <p class="event-details-text">{{ t.passwordSuccessBody }}</p>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline-primary" @click="passwordSuccessOpen = false">{{ t.close }}</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import WhatsAppUserFields from '../WhatsAppUserFields.vue'
import FormSection from "../FormSection.vue"
import '../../styles/clerk-management.css'
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { publicApi } from '../../services/publicApi'
import { secretaryApi } from '../../services/secretaryApi'
import { useAuthStore } from '../../stores/authStore'
import { useUiStore } from '../../stores/uiStore'
import { storeToRefs } from 'pinia'
import { translations } from '../../i18n/translations'

const departments = ref([])
const users = ref([])
const loading = ref(false)
const error = ref('')
const filterText = ref('')
const createForm = reactive({ name: '', email: '', whatsapp_phone: '', whatsapp_assistant_enabled: false, role: 'member', department_id: '' })
const createFormVersion = ref(0)
const creating = ref(false)
const createError = ref('')
const createSuccess = ref(false)
const invitationStatus = ref('')
const createUser = async () => {
  if (creating.value) return
  creating.value = true
  createError.value = ''
  createSuccess.value = false
  try {
    const created = await secretaryApi.createUser({ ...createForm, department_id: createForm.department_id || null })
    invitationStatus.value = created.invitation_status
    Object.assign(createForm, { name: '', email: '', whatsapp_phone: '', whatsapp_assistant_enabled: false, role: 'member', department_id: '' })
    createFormVersion.value++
    createSuccess.value = true
    await loadUsers()
  } catch (err) {
    createError.value = Object.values(err.response?.data?.errors || {}).flat().join(' ') || err.response?.data?.message || (locale.value === 'es' ? 'No se pudo crear el usuario.' : 'Unable to create user.')
  } finally {
    creating.value = false
  }
}
const authStore = useAuthStore()
const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const passwordModalOpen = ref(false)
const activeUser = ref(null)
const passwordForm = reactive({
  password: '',
})
const passwordSuccessOpen = ref(false)

const t = computed(() => translations[locale.value].adminUsers)
const common = computed(() => translations[locale.value].common)
const roleLabels = computed(() => translations[locale.value].appLayout.roleLabels)

const filteredUsers = computed(() => {
  const term = filterText.value.trim().toLowerCase()
  if (!term) {
    return users.value
  }
  return users.value.filter((user) => {
    const haystack = [
      user.name,
      user.email,
      user.role,
      user.department?.name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return haystack.includes(term)
  })
})

const currentUserId = computed(() => authStore.user?.id || null)
const activityClock = ref(Date.now())
let activityTimer
const isRecentlyActive = user => {
  if (user.pending_activation || !user.last_activity_at) return false
  const value = String(user.last_activity_at)
  const timestamp = Date.parse(value.includes('T') ? value : value.replace(' ', 'T') + 'Z')
  return Number.isFinite(timestamp) && timestamp <= activityClock.value && timestamp >= activityClock.value - 5 * 60 * 1000
}

const loadUsers = async () => {
  loading.value = true
  error.value = ''
  try {
    users.value = await secretaryApi.listUsers()
  } catch {
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const loadDepartments = async () => {
  try {
    departments.value = await publicApi.listDepartments(authStore.user.church_id)
  } catch {
    departments.value = []
  }
}

const updateUser = async (user) => {
  error.value = ''
  try {
    await secretaryApi.updateUser(user.id, {
      name: user.name,
      email: user.email,
      role: user.role,
      department_id: user.department_id || null,
      whatsapp_phone: user.whatsapp_phone || null,
      whatsapp_assistant_enabled: !!user.whatsapp_assistant_enabled,
    })
  } catch (e) {
    error.value = Object.values(e.response?.data?.errors||{}).flat().join(' ') || t.value.updateError
  }
}
const sendPasswordLink = async user => {
  error.value = ''
  try { const result=await secretaryApi.sendPasswordLink(user.id); window.alert(result.status==='simulated' ? (locale.value === 'es' ? 'El servidor usa correo de prueba; no se envió el enlace.' : 'The server uses a test mailer; the link was not sent.') : (locale.value === 'es' ? 'Enlace enviado al correo guardado del usuario.' : 'A link was sent to the user’s saved email address.')) }
  catch (e) { error.value = e.response?.data?.message || 'No se pudo enviar el correo.' }
}

const openPasswordModal = (user) => {
  activeUser.value = user
  passwordForm.password = ''
  passwordModalOpen.value = true
}

const closePasswordModal = () => {
  passwordModalOpen.value = false
  activeUser.value = null
  passwordForm.password = ''
}

const submitPasswordUpdate = async () => {
  if (!activeUser.value || !passwordForm.password) {
    return
  }
  error.value = ''
  try {
    await secretaryApi.updateUser(activeUser.value.id, {
      password: passwordForm.password,
    })
    closePasswordModal()
    passwordSuccessOpen.value = true
  } catch {
    error.value = t.value.passwordError
  }
}

onMounted(async () => {
  await loadDepartments()
  await loadUsers()
  activityTimer=setInterval(async()=>{
    activityClock.value=Date.now()
    try { const latest=await secretaryApi.listUsers();const states=new Map(latest.map(user=>[user.id,user]));for(const user of users.value){const state=states.get(user.id);user.last_activity_at=state?.last_activity_at??null;user.active_sessions_count=state?.active_sessions_count??0} } catch { /* Keep the last known activity without affecting unsaved edits. */ }
  },60000)
})
onUnmounted(()=>clearInterval(activityTimer))
</script>

<style scoped>
.user-list{display:grid;gap:14px;padding:18px;min-width:0}.user-card{min-width:0;border:1px solid #dfe9e2;border-radius:10px;padding:16px;background:#fff}.user-card-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px}.user-identity{display:grid;gap:3px;min-width:0}.user-identity strong{font-size:15px;color:#244c3c;overflow-wrap:anywhere}.user-identity span{color:#62746a;overflow-wrap:anywhere}.user-badges{display:flex;gap:6px;flex-wrap:wrap}.role-badge,.pending-badge{padding:4px 8px;border-radius:6px;background:#eaf3ed;color:#28624a;font-size:11px}.pending-badge{background:#fff2d6;color:#805619}.user-fields{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.user-fields label{display:grid;gap:5px;min-width:0;color:#596b61}.user-fields input,.user-fields select{min-width:0;width:100%}.user-actions{display:flex;flex-wrap:wrap;gap:8px;border-top:1px solid #edf1ed;padding-top:12px;margin-top:12px}.management-page .user-actions .btn{white-space:normal;max-width:100%}@media(max-width:1100px){.user-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:540px){.user-list{padding:12px}.user-card{padding:14px}.user-fields{grid-template-columns:1fr}.user-actions .btn{flex:1 1 auto}}
.activity-explanation{margin:0;padding:12px 18px 0;color:#5b6d62;font-size:12px}.active-badge{padding:4px 8px;border-radius:6px;background:#dff3e5;color:#17683c;font-size:11px;font-weight:600}
</style>
