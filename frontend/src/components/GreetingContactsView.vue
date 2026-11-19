<template>
  <section class="container py-4 management-page visits-page">
    <div class="management-heading d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div>
        <p class="management-eyebrow">{{ t.kicker }}</p>
        <h1 >{{ t.title }}</h1>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a v-if="auth.user?.role === 'pastor' && auth.user?.church_id" :href="visitUrl" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">{{ locale === 'es' ? 'Ver formulario público ↗' : 'View public form ↗' }}</a>
        <RouterLink v-if="auth.can('visitors.configure')" to="/greeting/form-builder" class="btn btn-primary">{{ locale === 'es' ? 'Configurar formulario' : 'Configure form' }}</RouterLink>
        <button class="btn btn-outline-secondary" :disabled="loading || !filteredContacts.length" @click="exportVisitors">CSV</button>
        <button class="btn btn-primary" type="button" :disabled="exportingQr || !auth.user?.church_id" @click="exportQr">{{ exportingQr ? (locale === 'es' ? 'Preparando…' : 'Preparing…') : (locale === 'es' ? 'Descargar QR para imprimir' : 'Download printable QR') }}</button>
        <button class="btn btn-outline-secondary" type="button" :disabled="loading" @click="loadContacts">
          {{ t.refresh }}
        </button>
        <button class="btn btn-outline-secondary" type="button" @click="copyPublicPageLink">
          {{ t.copyLink }}
        </button>
      </div>
    </div>

    <p class="text-muted mb-0">{{ auth.user?.role === 'pastor' ? (locale === 'es' ? 'Consulta y actualiza las preferencias y respuestas de los visitantes.' : 'View and update visitor preferences and answers.') : t.subtitle }}</p>
    <nav v-if="canGreet" class="visits-tabs" :aria-label="locale === 'es' ? 'Vistas de visitas' : 'Visitor views'">
      <RouterLink to="/greeting/contacts" aria-current="page">{{ locale === 'es' ? 'Visitas' : 'Visitors' }}</RouterLink>
      <RouterLink to="/greeting">{{ locale === 'es' ? 'Bienvenida · Registrar visita' : 'Welcome · Register visitor' }}</RouterLink>
      <RouterLink to="/greeting/attendance">{{ locale === 'es' ? 'Asistencia' : 'Attendance' }}</RouterLink>
    </nav>
    <div class="management-summary">
      <article><span>{{ locale === 'es' ? 'Registros' : 'Records' }}</span><strong>{{ loading ? '—' : contacts.length }}</strong></article>
      <article><span>{{ locale === 'es' ? 'Autorizan SMS' : 'SMS consent' }}</span><strong>{{ loading ? '—' : contacts.filter(c => c.phone && c.sms_consent).length }}</strong></article>
      <article><span>{{ locale === 'es' ? 'Autorizan correo' : 'Email consent' }}</span><strong>{{ loading ? '—' : contacts.filter(c => c.email && c.email_consent).length }}</strong></article>
    </div>
    <div class="visits-filters management-surface">
      <label><span class="visually-hidden">{{ locale === 'es' ? 'Buscar visitas' : 'Search visitors' }}</span><input v-model="search" type="search" class="form-control" :placeholder="locale === 'es' ? 'Nombre, teléfono o correo' : 'Name, phone or email'" /></label>
      <label>{{ locale === 'es' ? 'Tipo de visita' : 'Visitor type' }}<select v-model="visitorFilter" class="form-select"><option value="all">{{ locale === 'es' ? 'Todos' : 'All' }}</option><option value="visitor">{{ t.memberNo }}</option><option value="sda">{{ t.memberYes }}</option></select></label>
      <label>{{ locale === 'es' ? 'Permiso de contacto' : 'Contact permission' }}<select v-model="consentFilter" class="form-select"><option value="all">{{ locale === 'es' ? 'Todos' : 'All' }}</option><option value="sms">SMS</option><option value="email">{{ t.columns.email }}</option><option value="none">{{ locale === 'es' ? 'Sin autorización' : 'No permission' }}</option></select></label>
      <label>{{ locale === 'es' ? 'Desde (UTC)' : 'From (UTC)' }}<input v-model="dateFrom" type="date" class="form-control" /></label>
      <label>{{ locale === 'es' ? 'Hasta (UTC)' : 'To (UTC)' }}<input v-model="dateTo" type="date" class="form-control" /></label>
    </div>
    <div class="visits-results-bar">
      <span role="status">{{ filteredContacts.length }} / {{ contacts.length }} {{ locale === 'es' ? 'registros' : 'records' }}</span>
      <button v-if="search || dateFrom || dateTo || visitorFilter !== 'all' || consentFilter !== 'all'" class="btn btn-sm btn-outline-secondary" @click="dateFrom = ''; dateTo = ''; search = ''; visitorFilter = 'all'; consentFilter = 'all'">{{ locale === 'es' ? 'Limpiar filtros' : 'Clear filters' }}</button>
    </div>
    <div v-if="loading" class="management-state" role="status">{{ t.loading }}</div>
    <p v-else-if="error" class="management-error" role="alert">{{ error }}</p>
    <div v-else-if="filteredContacts.length === 0" class="management-state">{{ contacts.length ? (locale === 'es' ? 'No hay registros que coincidan con los filtros.' : 'No records match these filters.') : t.empty }}</div>
    <template v-else>
    <div v-if="customColumns.length" ref="tableContainer" class="management-surface visitor-table-wrap"><table data-dt="off" class="table visitor-table"><thead><tr><th v-for="column in visibleColumns" :key="column.key">{{ column.label }}</th><th>{{ locale==='es'?'Detalle':'Details' }}</th></tr></thead><tbody><template v-for="contact in filteredContacts" :key="contact.id"><tr><td v-for="column in visibleColumns" :key="column.key"><span v-if="column.channel" class="visits-permission" :class="{'is-allowed':permissionAllowed(contact,column.channel)}">{{ permissionAllowed(contact,column.channel)?t.consentYes:t.consentNo }}</span><template v-else>{{ columnValue(contact,column) }}</template></td><td><button v-if="hiddenColumns.length" class="btn btn-sm btn-outline-secondary" :aria-expanded="expandedRows.has(contact.id)" :aria-controls="`visitor-extra-${contact.id}`" @click="toggleRow(contact.id)">{{ expandedRows.has(contact.id)?(locale==='es'?'Menos':'Less'):(locale==='es'?'Más datos':'More details') }}</button><button class="btn btn-sm btn-outline-secondary mt-1" :aria-expanded="selectedContact===contact.id" @click="selectedContact=selectedContact===contact.id?null:contact.id">{{ selectedContact===contact.id ? (locale==='es'?'Cerrar registro':'Close record') : (locale==='es'?'Ver registro':'View record') }}</button></td></tr><tr v-if="hiddenColumns.length && expandedRows.has(contact.id)" :id="`visitor-extra-${contact.id}`" class="visitor-child-row"><td :colspan="visibleColumns.length+1"><dl><div v-for="column in hiddenColumns" :key="column.key"><dt>{{ column.label }}</dt><dd><span v-if="column.channel" class="visits-permission" :class="{'is-allowed':permissionAllowed(contact,column.channel)}">{{ permissionAllowed(contact,column.channel)?t.consentYes:t.consentNo }}</span><template v-else>{{ columnValue(contact,column) }}</template></dd></div></dl></td></tr></template></tbody></table></div>
    <div v-if="!customColumns.length || selectedContact" class="management-surface visits-directory">
      <div class="visits-directory-head"><span>{{ locale === 'es' ? 'Persona y registro' : 'Person and registration' }}</span><span>{{ locale === 'es' ? 'Datos de contacto' : 'Contact details' }}</span><span>{{ locale === 'es' ? 'Preferencias de contacto' : 'Contact preferences' }}</span></div>
      <article v-for="contact in directoryContacts" :key="contact.id" class="visits-record">
        <div class="visits-person">
          <span class="visits-avatar" aria-hidden="true">{{ contact.name?.trim().charAt(0).toUpperCase() }}</span>
          <div><h2>{{ contact.name }}</h2><span class="visits-type">{{ contact.is_sda ? t.memberYes : t.memberNo }}</span><p>{{ formatDate(contact.created_at) }}</p><small>{{ t.columns.by }}: {{ contact.creator?.name || '—' }}</small></div>
        </div>
        <div class="visits-contact-data">
          <span><strong>{{ t.columns.phone }}</strong>{{ contact.phone || '—' }}</span>
          <span><strong>{{ t.columns.email }}</strong>{{ contact.email || '—' }}</span>
          <span v-if="contact.address"><strong>{{ t.columns.address }}</strong>{{ contact.address }}</span>
          <details v-if="contact.visitor_form?.fields?.length" class="visitor-answers"><summary>{{ locale==='es'?'Respuestas originales':'Original answers' }} · {{ contact.visitor_form.version }}</summary><dl><template v-for="field in contact.visitor_form.fields.filter(f=>f.type!=='label')" :key="field.id"><dt>{{ field.label }}</dt><dd>{{ answer((contact.original_custom_answers ?? contact.custom_answers)?.[field.id]) }}</dd></template></dl></details>
        </div>
        <div class="visits-preferences"><button v-if="auth.can('visitors.edit')" class="btn btn-sm btn-outline-primary" @click="editing?.id===contact.id ? editing=null : editVisitor(contact)" :aria-expanded="editing?.id===contact.id">{{ editing?.id===contact.id ? (locale==='es'?'Cancelar':'Cancel') : (locale==='es'?'Editar preferencias y respuestas':'Edit preferences and answers') }}</button>
          <div class="visits-permissions">
            <span class="visits-permission" :class="{ 'is-allowed': contact.phone && contact.sms_consent }">SMS · {{ contact.phone && contact.sms_consent ? t.consentYes : t.consentNo }}</span>
            <span class="visits-permission" :class="{ 'is-allowed': contact.email && contact.email_consent }">{{ t.columns.email }} · {{ contact.email && contact.email_consent ? t.consentYes : t.consentNo }}</span>
          </div>
          <div v-if="canGreet && auth.canEdit('visitors')" class="visits-consent-options">
            <label v-for="channel in ['sms', 'email']" :key="channel" class="visits-consent-option">
              <input
                type="checkbox"
                class="form-check-input"
                :checked="!!contact[channel + '_consent'] || (consentTarget?.id === contact.id && consentChannel === channel)"
                :disabled="savingConsent || !!contact[channel + '_consent'] || !(channel === 'sms' ? contact.phone : contact.email)"
                @change="$event.target.checked ? beginConsent(contact, channel) : consentTarget = null"
              />
              <span>{{ channel === 'sms' ? 'SMS' : t.columns.email }}</span>
            </label>
          </div>
          <form v-if="consentTarget?.id === contact.id" class="visits-consent-confirm" @submit.prevent="restoreConsent">
            <p>{{ locale === 'es' ? 'Habilitar permiso para' : 'Enable permission for' }} {{ consentChannel === 'sms' ? 'SMS' : t.columns.email }}</p>
            <label class="d-flex align-items-start gap-2"><input v-model="consentConfirmed" type="checkbox" required /><span>{{ locale === 'es' ? 'Confirmo que esta persona volvió a autorizar este medio de contacto.' : 'I confirm this person has given renewed permission for this contact channel.' }}</span></label>
            <p v-if="consentError" role="alert" class="text-danger">{{ consentError }}</p>
            <div class="d-flex flex-wrap gap-2 mt-2">
              <button class="btn btn-sm btn-primary" :disabled="!consentConfirmed || savingConsent">{{ savingConsent ? (locale === 'es' ? 'Guardando…' : 'Saving…') : (locale === 'es' ? 'Confirmar' : 'Confirm') }}</button>
              <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="savingConsent" @click="consentTarget = null">{{ locale === 'es' ? 'Cancelar' : 'Cancel' }}</button>
            </div>
          </form>
          <button v-if="auth.user?.role !== 'pastor'" class="btn btn-sm btn-outline-secondary" type="button" @click="copyConsentLink(contact)">{{ t.copyPersonalLink }}</button>
          <small v-if="auth.user?.role !== 'pastor'">{{ locale === 'es' ? 'Comparte solo con esta persona para que retire su permiso de contacto.' : 'Share only with this person so they can withdraw contact permission.' }}</small>
        </div>
      </article>
    </div>
    </template>
    <section v-if="editing && auth.canEdit('visitors')" class="management-surface visitor-editor" aria-labelledby="visitor-editor-title">
      <h2 id="visitor-editor-title">{{ locale==='es'?'Editar registro':'Edit record' }} · {{ editing.name }}</h2>
      <p>{{ locale==='es'?'Completa las preguntas actuales, incluso para visitas anteriores. No es necesario llenar todas; las respuestas originales se conservan.':'Complete current questions, including for older visits. You do not need to fill every question; original answers are retained.' }}</p>
      <form @submit.prevent="saveVisitor"><fieldset :disabled="savingVisitor"><legend>{{ locale==='es'?'Permisos de contacto':'Contact permissions' }}</legend>
        <label class="d-flex gap-2"><input type="checkbox" v-model="editDraft.sms_consent" :disabled="!editing.phone" /> SMS</label>
        <label class="d-flex gap-2"><input type="checkbox" v-model="editDraft.email_consent" :disabled="!editing.email" /> {{ locale==='es'?'Correo':'Email' }}</label>
        <label v-if="needsConsentConfirmation" class="d-flex gap-2"><input type="checkbox" v-model="editDraft.consent_confirmed" required /> {{ locale==='es'?'Confirmo que esta persona autorizó recibir mensajes por los medios seleccionados.':'I confirm this visitor authorized messages through the selected channels.' }}</label>
        <VisitorCustomFields :fields="editableFields" v-model="editDraft.custom_answers" />
        <p v-if="editError" class="text-danger" role="alert">{{ editError }}</p>
        <div class="d-flex gap-2 mt-3"><button class="btn btn-primary">{{ locale==='es'?'Guardar cambios':'Save changes' }}</button><button type="button" class="btn btn-outline-secondary" @click="editing=null">{{ locale==='es'?'Cancelar':'Cancel' }}</button></div>
      </fieldset></form>
    </section>
    <details class="visits-help management-surface">
      <summary>{{ locale === 'es' ? '¿Cómo funcionan las preferencias?' : 'How do contact preferences work?' }}</summary>
      <p>{{ locale === 'es' ? 'Registrar una visita no envía mensajes. SMS y correo indican si la persona autorizó recibir comunicaciones. Su enlace personal permite retirar ese permiso; el registro se conserva.' : 'Registering a visitor does not send messages. SMS and email indicate permission to receive communications. Their personal link allows them to withdraw permission; the record is retained.' }}</p>
      <p>{{ locale === 'es' ? 'La página general explica cómo obtener un enlace personal de la iglesia. No muestra ni busca registros de personas.' : 'The general page explains how to obtain a personal link from the church. It does not display or search visitor records.' }}</p>
    </details>
  </section>
</template>

<script setup>
import '../styles/clerk-management.css'
import '../styles/visits.css'
import VisitorCustomFields from './VisitorCustomFields.vue'
import QRCode from 'qrcode'
import { useRoute } from 'vue-router'
import jsPDF from 'jspdf'
import { useAuthStore } from '../stores/authStore'
import { computed, onMounted, onUnmounted, watch, ref, nextTick } from 'vue'
import { storeToRefs } from 'pinia'
import { churchContactsApi } from '../services/churchContactsApi'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'

const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const t = computed(() => translations[locale.value].greeting.contacts)
const auth = useAuthStore()
const editing=ref(null),editDraft=ref({}),savingVisitor=ref(false),editError=ref('')
const editableFields=computed(()=>visitorForm.value.fields.map(f=>({...f,required:false})))
const needsConsentConfirmation=computed(()=>editing.value && ((!editing.value.sms_consent && editDraft.value.sms_consent)||(!editing.value.email_consent && editDraft.value.email_consent)))
async function editVisitor(contact){editing.value=contact;editError.value='';editDraft.value={visitor_form_id:visitorForm.value.id,sms_consent:contact.sms_consent,email_consent:contact.email_consent,consent_confirmed:false,custom_answers:Object.fromEntries(customColumns.value.filter(f=>Object.hasOwn(contact.custom_answers||{},f.id)).map(f=>[f.id,contact.custom_answers[f.id]]))};await nextTick();document.querySelector('.visitor-editor')?.scrollIntoView({behavior:'smooth',block:'start'})}
async function saveVisitor(){savingVisitor.value=true;editError.value='';try{const updated=await churchContactsApi.update(editing.value.id,editDraft.value);contacts.value=contacts.value.map(c=>c.id===updated.id?updated:c);editing.value=null}catch(e){editError.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||e.response?.data?.message||'Unable to save'}finally{savingVisitor.value=false}}
const exportingQr = ref(false)
const visitUrl = computed(() => `${window.location.origin}/visit/${auth.user?.church_id}`)
const exportQr = async () => {
  if (exportingQr.value) return
  exportingQr.value = true
  try {
    const es = locale.value === 'es'
    const doc = new jsPDF({ unit: 'mm', format: 'a4' })
    const qr = await QRCode.toDataURL(visitUrl.value, { width: 1400, margin: 4, errorCorrectionLevel: 'M' })
    doc.setTextColor(31, 111, 92)
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(20)
    const churchLines = doc.splitTextToSize(auth.user?.church?.name || (es ? 'Bienvenidos' : 'Welcome'), 170)
    doc.text(churchLines, 105, 28, { align: 'center' })
    const headingY = 28 + churchLines.length * 9 + 12
    doc.setTextColor(35, 49, 41)
    doc.setFontSize(24)
    doc.text(es ? 'Registra tu visita' : 'Register your visit', 105, headingY, { align: 'center' })
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(13)
    doc.text(es ? 'Escanea con la cámara de tu teléfono' : 'Scan with your phone camera', 105, headingY + 12, { align: 'center' })
    const qrY = headingY + 22
    doc.addImage(qr, 'PNG', 45, qrY, 120, 120)
    doc.setFontSize(12)
    doc.text(es ? 'Sin cuenta. Completa el formulario cuando puedas.' : 'No account needed. Complete the form in your own time.', 105, qrY + 133, { align: 'center' })
    doc.setFontSize(10)
    doc.setTextColor(90, 100, 94)
    doc.text(doc.splitTextToSize(visitUrl.value, 170), 105, qrY + 147, { align: 'center' })
    doc.save('registro-visitas-qr.pdf')
  } catch {
    uiStore.showToast(locale.value === 'es' ? 'No se pudo exportar el PDF.' : 'Unable to export PDF.', 'error')
  } finally {
    exportingQr.value = false
  }
}
const canGreet = computed(() => auth.can('visitors.contact'))
const consentTarget = ref(null)
const consentChannel = ref('')
const consentConfirmed = ref(false)
const savingConsent = ref(false)
const consentError = ref('')
const beginConsent = (contact, channel) => {
  if (savingConsent.value) return
  consentTarget.value = contact
  consentChannel.value = channel
  consentConfirmed.value = false
  consentError.value = ''
}
const restoreConsent = async () => {
  if (!consentConfirmed.value || savingConsent.value || !consentTarget.value) return
  savingConsent.value = true
  consentError.value = ''
  try {
    const updated = await churchContactsApi.restoreConsent(consentTarget.value.id, consentChannel.value)
    contacts.value = contacts.value.map(c => c.id === updated.id ? updated : c)
    consentTarget.value = null
    uiStore.showToast(locale.value === 'es' ? 'Permiso de contacto habilitado.' : 'Contact permission enabled.', 'success')
  } catch (err) {
    consentError.value = err.response?.data?.message || (locale.value === 'es' ? 'No se pudo habilitar el permiso.' : 'Unable to enable permission.')
  } finally {
    savingConsent.value = false
  }
}
const route = useRoute()
const validDate = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : ''
const dateFrom = ref(validDate(route.query.from)), dateTo = ref(validDate(route.query.to))
const search = ref('')
const contacts = ref([])
const visitorForm = ref({fields:[]})
const selectedContact = ref(null)
const customColumns = computed(()=>visitorForm.value.fields.filter(f=>f.type!=='label'))
const tableContainer=ref(null),tableWidth=ref(0),expandedRows=ref(new Set())
let tableObserver
watch(tableContainer,element=>{tableObserver?.disconnect();if(element){tableObserver=new ResizeObserver(entries=>{tableWidth.value=entries[0].contentRect.width});tableObserver.observe(element)}})
onUnmounted(()=>tableObserver?.disconnect())
const tableColumns=computed(()=>[
  {key:'name',label:locale.value==='es'?'Nombre':'Name',width:170},
  {key:'sms_consent',channel:'sms',label:locale.value==='es'?'Permiso SMS':'SMS permission',width:125},
  {key:'email_consent',channel:'email',label:locale.value==='es'?'Permiso correo':'Email permission',width:135},
  {key:'phone',label:t.value.columns.phone,width:150},
  {key:'email',label:t.value.columns.email,width:200},
  ...customColumns.value.map(f=>({key:f.id,label:f.label,custom:true,width:180}))
])
const visibleCount=computed(()=>{let used=130,count=0;for(const column of tableColumns.value){if(count && used+column.width>tableWidth.value)break;used+=column.width;count++}return count})
const visibleColumns=computed(()=>tableColumns.value.slice(0,visibleCount.value))
const hiddenColumns=computed(()=>tableColumns.value.slice(visibleCount.value))
const permissionAllowed=(contact,channel)=>Boolean(contact[channel==='sms'?'phone':'email'] && contact[channel+'_consent'])
const columnValue=(contact,column)=>column.custom?answer(contact.custom_answers?.[column.key]):contact[column.key]||'—'
function toggleRow(id){const next=new Set(expandedRows.value);next.has(id)?next.delete(id):next.add(id);expandedRows.value=next}
const directoryContacts = computed(()=>customColumns.value.length?filteredContacts.value.filter(c=>c.id===selectedContact.value):filteredContacts.value)
const answer = value => value === true ? (locale.value==='es'?'Sí':'Yes') : value === false ? 'No' : value ?? '—'
const visitorFilter = ref('all')
const consentFilter = ref('all')
const filteredContacts = computed(() => contacts.value.filter(c => {
  const matchesSearch = [c.name, c.phone, c.email, ...Object.values(c.custom_answers || {})].join(' ').toLowerCase().includes(search.value.trim().toLowerCase())
  const matchesType = visitorFilter.value === 'all' || (visitorFilter.value === 'sda' ? c.is_sda : !c.is_sda)
  const sms = !!(c.phone && c.sms_consent)
  const email = !!(c.email && c.email_consent)
  const matchesConsent = consentFilter.value === 'all' || (consentFilter.value === 'sms' ? sms : consentFilter.value === 'email' ? email : !sms && !email)
  const date = new Date(c.created_at).toISOString().slice(0,10)
  return matchesSearch && matchesType && matchesConsent && (!dateFrom.value || date >= dateFrom.value) && (!dateTo.value || date <= dateTo.value)
}))
const loading = ref(false)
const error = ref('')

const loadContacts = async () => {
  loading.value = true
  error.value = ''
  try {
    const [records, definition] = await Promise.all([churchContactsApi.list(), churchContactsApi.form()])
    contacts.value = records
    visitorForm.value = definition
  } catch {
    error.value = t.value.loadError
  } finally {
    loading.value = false
  }
}

const exportVisitors = () => {
  // Include retired fields so an export never silently discards historic answers.
  const columns = new Map(customColumns.value.map(f=>[f.id,f]))
  contacts.value.forEach(c=>(c.visitor_form?.fields||[]).filter(f=>f.type!=='label').forEach(f=>{if(!columns.has(f.id))columns.set(f.id,f)}))
  const fields=[...columns.values()]
  const headers=['Name','Phone','Email','Address','Registered',...fields.map(f=>f.label)]
  const rows=filteredContacts.value.map(c=>[c.name,c.phone,c.email,c.address,c.created_at,...fields.map(f=>answer(c.custom_answers?.[f.id]))])
  const cell=v=>{let s=String(v??'');if(/^[\s]*[=+@-]/.test(s))s="'"+s;return '"'+s.replaceAll('"','""')+'"'}
  const url=URL.createObjectURL(new Blob(['\uFEFF'+[headers,...rows].map(r=>r.map(cell).join(',')).join('\r\n')],{type:'text/csv;charset=utf-8'}))
  const link=document.createElement('a');link.href=url;link.download='visitors.csv';link.click();URL.revokeObjectURL(url)
}

const copyPublicPageLink = async () => {
  try {
    await navigator.clipboard.writeText(`${window.location.origin}/consent`)
    uiStore.showToast(t.value.copySuccess, 'success')
  } catch {
    uiStore.showToast(t.value.copyError, 'error')
  }
}

const consentLinkFor = (contact) => `${window.location.origin}/consent/${contact.consent_token}`

const copyConsentLink = async (contact) => {
  if (!contact?.consent_token) {
    uiStore.showToast(t.value.copyPersonalError, 'error')
    return
  }
  try {
    await navigator.clipboard.writeText(consentLinkFor(contact))
    uiStore.showToast(t.value.copyPersonalSuccess, 'success')
  } catch {
    uiStore.showToast(t.value.copyPersonalError, 'error')
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

<style scoped>
.visitor-table-wrap{width:100%;min-width:0;max-width:100%}.visitor-table{table-layout:fixed;width:100%;font-size:12px;margin:0}.visitor-table th,.visitor-table td{min-width:0;max-width:none;white-space:normal;overflow-wrap:anywhere;padding:12px 8px}.visitor-table th{font-weight:500;background:#f4f7f5}.visitor-table th:last-child{width:125px}.visitor-table .btn{white-space:normal;max-width:100%;width:auto}.visitor-table .visits-permission{white-space:normal;max-width:100%}.visitor-child-row td{background:#f8faf9}.visitor-child-row dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr));gap:12px 20px;margin:0;padding:8px}.visitor-child-row dl>div{min-width:0}.visitor-child-row dt{font-size:12px;color:#52645a;font-weight:500}.visitor-child-row dd{margin:4px 0 0;white-space:pre-wrap;overflow-wrap:anywhere}.visitor-answers{margin-top:8px}.visitor-answers summary{cursor:pointer}.visitor-answers dt{font-weight:500;margin-top:10px}.visitor-answers dd{white-space:pre-wrap;overflow-wrap:anywhere}
</style>

<style scoped>
.visitor-editor{padding:20px;margin:20px 0}.visitor-editor h2{font-size:18px}.visitor-editor legend{font-size:14px}.visitor-editor input[type=checkbox]{width:16px;flex-shrink:0}.visitor-editor .visitor-fields{margin-top:20px}
</style>
