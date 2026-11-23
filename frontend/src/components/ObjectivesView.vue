<template>
  <section class="container py-4 objectives-page">
    <header class="objectives-heading">
      <div><p class="eyebrow">{{ auth.user?.church?.name }}<span v-if="auth.user?.department"> · {{ auth.user.department.name }}</span></p><div class="creation-heading"><h1>{{ t.title }}</h1><button v-if="canManage" class="creation-plus" type="button" :aria-label="tr('Nuevo objetivo', 'New objective')" :title="tr('Nuevo objetivo', 'New objective')" @click="openEditor()"><span aria-hidden="true">+</span></button></div><p>{{ tr('Define lo que quieres lograr y conecta cada objetivo con sus eventos.', 'Define what you want to achieve and connect each objective to its events.') }}</p></div>

      <span v-if="!canManage" class="readonly-badge">{{ tr('Solo consulta', 'Read only') }}</span>
    </header>
    <p v-if="error" class="notice error" role="alert">{{ error }}</p>
    <div class="summary">
      <article><span>{{ tr('Objetivos', 'Objectives') }}</span><strong>{{ loading ? '—' : filtered.length }}</strong></article>
      <article><span>{{ tr('Eventos vinculados', 'Linked events') }}</span><strong>{{ loading ? '—' : eventCount }}</strong></article>
      <article><span>{{ tr('Objetivos sin eventos', 'Objectives without events') }}</span><strong>{{ loading ? '—' : filtered.filter(o => !o.events_count).length }}</strong></article>
    </div>
    <div class="surface filters">
      <label class="search"><span class="visually-hidden">{{ tr('Buscar', 'Search') }}</span><input v-model="search" class="form-control" type="search" :placeholder="tr('Nombre, descripción o indicador de evaluación', 'Name, description or evaluation metric')" /></label>
      <label v-if="isSuperAdmin">{{ t.fields.church }}<select v-model="churchFilter" class="form-select"><option value="">{{ tr('Todas las iglesias', 'All churches') }}</option><option v-for="church in churches" :key="church.id" :value="String(church.id)">{{ church.name }}</option></select></label>
      <label v-if="canViewAll">{{ t.fields.department }}<select v-model="departmentFilter" class="form-select"><option value="">{{ tr('Todos los departamentos', 'All departments') }}</option><option v-for="department in filterDepartments" :key="department.id" :value="String(department.id)">{{ department.name }}</option></select></label>
      <label>{{ tr('Ordenar', 'Sort') }}<select v-model="sort" class="form-select"><option value="name">{{ tr('Nombre', 'Name') }}</option><option value="department">{{ t.fields.department }}</option><option value="events">{{ tr('Más eventos', 'Most events') }}</option></select></label>
    </div>
    <div class="export-row">
      <span class="muted">{{ tr('Se exportan todos los objetivos que coinciden con los filtros, en todas las páginas.', 'Exports include all objectives matching the filters, across all pages.') }}</span>
      <div><button class="btn btn-outline-secondary" :disabled="loading || !!error || !filtered.length" @click="exportCsv">{{ tr('Exportar CSV', 'Export CSV') }}</button><button class="btn btn-outline-secondary" :disabled="loading || !!error || !filtered.length" @click="exportPdf">{{ tr('Exportar PDF', 'Export PDF') }}</button></div>
    </div>
    <div v-if="loading" class="surface empty" role="status">{{ t.messages.loading }}</div>
    <div v-else-if="!filtered.length" class="surface empty">{{ search || departmentFilter ? tr('No hay objetivos con estos filtros.', 'No objectives match these filters.') : t.messages.empty }}</div>
    <div v-else class="objective-list">
      <article v-for="objective in paginated" :key="objective.id" class="surface objective-card">
        <div class="card-heading"><div><span class="department-pill" :style="{ '--department-color': objective.department?.color || '#8b9990' }"><i></i>{{ objective.department?.name || '—' }}</span><h2>{{ objective.name }}</h2></div><span class="event-count">{{ objective.events_count || 0 }} {{ tr('eventos', 'events') }}</span></div>
        <div class="objective-details"><div><h3>{{ t.fields.description }}</h3><p>{{ objective.description }}</p></div><div class="metrics"><h3>{{ tr('Cómo se evaluará', 'How success is measured') }}</h3><p>{{ objective.evaluation_metrics }}</p></div></div>
        <footer><button class="btn btn-outline-secondary" :aria-expanded="expanded === objective.id" :disabled="!objective.events_count" @click="toggleEvents(objective)">{{ expanded === objective.id ? tr('Ocultar eventos', 'Hide events') : tr('Ver eventos vinculados', 'View linked events') }}</button><span class="muted">{{ objective.approved_events_count || 0 }} {{ tr('aceptados', 'accepted') }}</span><div v-if="canManage" class="edit-actions"><button class="btn btn-outline-secondary" @click="openEditor(objective)">{{ tr('Editar', 'Edit') }}</button><button class="btn btn-outline-danger" :disabled="!!objective.events_count" :title="objective.events_count ? tr('Los objetivos vinculados a eventos no se pueden eliminar.', 'Objectives linked to events cannot be deleted.') : ''" @click="deleteTarget = objective">{{ tr('Eliminar', 'Delete') }}</button></div></footer>
        <div v-if="expanded === objective.id" class="linked-events">
          <p v-if="eventsLoading" role="status">{{ tr('Cargando eventos…', 'Loading events…') }}</p><p v-else-if="eventsError" class="notice error" role="alert">{{ eventsError }}</p>
          <div v-else class="table-responsive"><table class="table" data-dt="off"><thead><tr><th>{{ tr('Evento', 'Event') }}</th><th>{{ tr('Fecha', 'Date') }}</th><th>{{ tr('Estado', 'Status') }}</th><th>{{ tr('Lugar', 'Location') }}</th></tr></thead><tbody><tr v-for="event in linkedEvents" :key="event.id"><td>{{ event.title || '—' }}</td><td>{{ date(event.start_at) }}</td><td><span class="status" :class="event.review_status">{{ status(event.review_status) }}</span></td><td>{{ event.location || '—' }}</td></tr></tbody></table></div>
        </div>
      </article>
    </div>
    <div v-if="filtered.length" class="pagination-row"><span>{{ filtered.length }} {{ tr('objetivos', 'objectives') }} · {{ page }} / {{ totalPages }}</span><div><button class="btn btn-outline-secondary" :disabled="page === 1" @click="page--">{{ tr('Anterior', 'Previous') }}</button><button class="btn btn-outline-secondary" :disabled="page === totalPages" @click="page++">{{ tr('Siguiente', 'Next') }}</button></div></div>
    <div v-if="editor" class="modal-backdrop" @click.self="!saving && (editor = null)">
      <section class="modal-panel modal-panel--lg" role="dialog" aria-modal="true" aria-labelledby="objective-editor-title">
        <header class="modal-header"><h2 id="objective-editor-title">{{ editor.id ? tr('Editar objetivo', 'Edit objective') : tr('Nuevo objetivo', 'New objective') }}</h2><button class="modal-close" :disabled="saving" :aria-label="tr('Cerrar', 'Close')" @click="editor = null">×</button></header>
        <form @submit.prevent="saveObjective"><fieldset :disabled="saving">
          <template v-if="isSuperAdmin && !editor.id"><label>{{ t.fields.church }}<select v-model="editorChurch" class="form-select" required><option value="">{{ tr('Seleccionar', 'Select') }}</option><option v-for="church in churches" :key="church.id" :value="String(church.id)">{{ church.name }}</option></select></label><label>{{ t.fields.department }}<select v-model="editor.department_id" class="form-select" required :disabled="departmentsLoading"><option value="">{{ tr('Seleccionar', 'Select') }}</option><option v-for="department in editorDepartments" :key="department.id" :value="department.id">{{ department.name }}</option></select></label></template>
          <p v-else class="muted">{{ editor.departmentName || auth.user?.department?.name }}</p>
          <label>{{ t.fields.name }}<input v-model="editor.name" maxlength="255" class="form-control" required /></label>
          <label>{{ t.fields.description }}<textarea v-model="editor.description" class="form-control" rows="3" required></textarea></label>
          <label>{{ tr('Cómo se evaluará', 'How success is measured') }}<textarea v-model="editor.evaluation_metrics" class="form-control" rows="2" required :placeholder="tr('Ejemplo: realizar 4 talleres con al menos 20 participantes.', 'Example: hold 4 workshops with at least 20 participants.')"></textarea></label>
          <p v-if="formError" class="notice error" role="alert">{{ formError }}</p><div class="action-row"><button type="button" class="btn btn-outline-secondary" @click="editor = null">{{ tr('Cancelar', 'Cancel') }}</button><button class="btn btn-primary" :disabled="departmentsLoading">{{ saving ? tr('Guardando…', 'Saving…') : tr('Guardar objetivo', 'Save objective') }}</button></div>
        </fieldset></form>
      </section>
    </div>
    <div v-if="deleteTarget" class="modal-backdrop" @click.self="!saving && (deleteTarget = null)"><section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="objective-delete-title"><h2 id="objective-delete-title">{{ tr('Eliminar objetivo', 'Delete objective') }}</h2><p>{{ deleteTarget.name }}</p><p>{{ tr('Esta acción no se puede deshacer.', 'This action cannot be undone.') }}</p><p v-if="formError" class="notice error">{{ formError }}</p><div class="action-row"><button class="btn btn-outline-secondary" :disabled="saving" @click="deleteTarget = null">{{ tr('Cancelar', 'Cancel') }}</button><button class="btn btn-outline-danger" :disabled="saving" @click="removeObjective">{{ tr('Eliminar', 'Delete') }}</button></div></section></div>
  </section>
</template>

<script setup>
import {addChurchPdfLogo} from '../services/pdfBranding'
import jsPDF from 'jspdf'
import 'jspdf-autotable'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'
import { objectiveApi } from '../services/objectiveApi'
import { publicApi } from '../services/publicApi'
import { superAdminApi } from '../services/superAdminApi'
const auth = useAuthStore(), ui = useUiStore()
const tr = (es, en) => ui.locale === 'es' ? es : en
const t = computed(() => translations[ui.locale].objectives)
const isSuperAdmin = computed(() => auth.user?.role === 'superadmin')
const canViewAll = computed(() => isSuperAdmin.value || auth.user?.role === 'secretary' || !auth.user?.department_id)
const canManage = computed(() => auth.can('objectives.manage') && (isSuperAdmin.value || !!auth.user?.department_id))
const objectives = ref([]), churches = ref([]), loading = ref(false), error = ref(''), search = ref(''), departmentFilter = ref(''), churchFilter = ref(''), sort = ref('name'), page = ref(1)
const editor = ref(null), editorChurch = ref(''), editorDepartments = ref([]), departmentsLoading = ref(false), saving = ref(false), formError = ref(''), deleteTarget = ref(null)
const expanded = ref(null), linkedEvents = ref([]), eventsLoading = ref(false), eventsError = ref('')
let active = true, eventRequest = 0, departmentRequest = 0
const filterDepartments = computed(() => [...new Map(objectives.value.filter(o => o.department && (!churchFilter.value || String(o.department.church_id) === churchFilter.value)).map(o => [o.department.id, o.department])).values()].sort((a,b) => a.name.localeCompare(b.name)))
const filtered = computed(() => objectives.value.filter(o => (!churchFilter.value || String(o.department?.church_id) === churchFilter.value) && (!departmentFilter.value || String(o.department_id) === departmentFilter.value) && [o.name,o.description,o.evaluation_metrics,o.department?.name].join(' ').toLowerCase().includes(search.value.trim().toLowerCase())).sort((a,b) => sort.value === 'events' ? b.events_count - a.events_count || a.name.localeCompare(b.name) : sort.value === 'department' ? (a.department?.name || '').localeCompare(b.department?.name || '') || a.name.localeCompare(b.name) : a.name.localeCompare(b.name)))
const eventCount = computed(() => filtered.value.reduce((sum,o) => sum + (o.events_count || 0), 0))
const totalPages = computed(() => Math.max(1,Math.ceil(filtered.value.length / 8)))
const paginated = computed(() => filtered.value.slice((page.value-1)*8,page.value*8))
const exportScope = computed(() => filterDepartments.value.find(d => String(d.id) === departmentFilter.value)?.name || auth.user?.department?.name || tr('Todos los departamentos', 'All departments'))
const exportDate = () => new Date().toLocaleDateString('en-CA')
const exportFilename = extension => `objectives-${departmentFilter.value || auth.user?.department_id || 'all-departments'}-${exportDate()}.${extension}`
function exportData() {
  // The API scopes these records to the effective church/department context.
  return {
    headers: [tr('Departamento', 'Department'), tr('Objetivo', 'Objective'), tr('Descripción', 'Description'), tr('Cómo se evaluará', 'How success is measured'), tr('Eventos vinculados', 'Linked events'), tr('Aceptados', 'Accepted')],
    rows: filtered.value.map(o => [o.department?.name || '', o.name, o.description, o.evaluation_metrics, o.events_count || 0, o.approved_events_count || 0]),
  }
}
function exportCsv() {
  if (loading.value || error.value || !filtered.value.length) return
  const { headers, rows } = exportData()
  const cell = value => {
    let text = String(value ?? '')
    if (typeof value === 'string' && /^[\s]*[=+@-]/.test(text)) text = "'" + text
    return '"' + text.replaceAll('"', '""') + '"'
  }
  const csv = [headers, ...rows].map(row => row.map(cell).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = exportFilename('csv')
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
async function exportPdf() {
  if (loading.value || error.value || !filtered.value.length) return
  const { headers, rows } = exportData()
  const doc = new jsPDF({ orientation: 'landscape' })
  const offset = await addChurchPdfLogo(doc, churchFilter.value || auth.user?.church_id)
  const width = doc.internal.pageSize.getWidth() - 28
  doc.setFontSize(14)
  doc.text(tr('Objetivos', 'Objectives'), 14, 16 + offset)
  doc.setFontSize(9)
  const church = churches.value.find(c => String(c.id) === churchFilter.value)?.name || auth.user?.church?.name || tr('Todas las iglesias', 'All churches')
  const context = doc.splitTextToSize([church, exportScope.value, exportDate(), `${rows.length} ${tr('objetivos', 'objectives')}`, search.value.trim() ? `${tr('Buscar', 'Search')}: ${search.value.trim()}` : ''].filter(Boolean).join(' | '), width)
  doc.text(context, 14, 23 + offset)
  doc.autoTable({ startY: 27 + offset + context.length * 4, head: [headers], body: rows, styles: { fontSize: 8, overflow: 'linebreak', cellPadding: 3 }, headStyles: { fillColor: [31, 111, 92] }, columnStyles: { 0: { cellWidth: 32 }, 1: { cellWidth: 44 }, 4: { cellWidth: 23, halign: 'center' }, 5: { cellWidth: 23, halign: 'center' } }, margin: { bottom: 16 } })
  const pages = doc.getNumberOfPages()
  for (let page = 1; page <= pages; page++) {
    doc.setPage(page)
    doc.setFontSize(8)
    doc.text(`${page} / ${pages}`, doc.internal.pageSize.getWidth() - 24, doc.internal.pageSize.getHeight() - 8)
  }
  doc.save(exportFilename('pdf'))
}
const date = value => value ? new Intl.DateTimeFormat(ui.locale, { dateStyle:'medium', timeStyle:'short' }).format(new Date(value)) : '—'
const status = value => ({ approved:tr('Aceptado','Accepted'), denied:tr('Rechazado','Rejected'), pending:tr('Pendiente','Pending'), resubmitted:tr('Reenviado','Resubmitted'), changes_requested:tr('Cambios solicitados','Changes requested') }[value] || value)
const message = (e,fallback) => Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || fallback
async function load() { loading.value=true; error.value=''; try { const result=await objectiveApi.list(); if(active) objectives.value=result; page.value=Math.min(page.value,totalPages.value) } catch(e) { if(active) error.value=message(e,t.value.messages.loadError) } finally { if(active) loading.value=false } }
function openEditor(objective=null) { formError.value=''; editor.value=objective ? {...objective,departmentName:objective.department?.name} : {department_id:auth.user?.department_id || '',name:'',description:'',evaluation_metrics:''}; if(!objective) editorChurch.value=churchFilter.value }
async function saveObjective() { if(saving.value) return; saving.value=true;formError.value='';try { const payload={name:editor.value.name,description:editor.value.description,evaluation_metrics:editor.value.evaluation_metrics}; if(editor.value.id) await objectiveApi.update(editor.value.id,payload); else await objectiveApi.create({...payload,department_id:editor.value.department_id});editor.value=null; await load();ui.showToast(tr('Objetivo guardado.','Objective saved.'),'success') } catch(e) {formError.value=message(e,t.value.messages.createError)} finally {saving.value=false} }
async function removeObjective() {saving.value=true;formError.value='';try {await objectiveApi.remove(deleteTarget.value.id);deleteTarget.value=null;await load()} catch(e){formError.value=message(e,t.value.messages.deleteError)}finally{saving.value=false}}
async function toggleEvents(objective) { if(expanded.value===objective.id){expanded.value=null;eventRequest++;return} expanded.value=objective.id;eventsLoading.value=true;eventsError.value='';const request=++eventRequest;try {const rows=await objectiveApi.events(objective.id);if(active && request===eventRequest)linkedEvents.value=rows}catch(e){if(request===eventRequest)eventsError.value=message(e,tr('No se pudieron cargar los eventos.','Could not load events.'))}finally{if(request===eventRequest)eventsLoading.value=false} }
watch([search,departmentFilter,sort,churchFilter],()=>{page.value=1})
watch(churchFilter,()=>{departmentFilter.value=''})
watch(editorChurch,async value=>{const request=++departmentRequest;editorDepartments.value=[];if(editor.value && !editor.value.id)editor.value.department_id='';if(!value){departmentsLoading.value=false;return}departmentsLoading.value=true;try{const rows=await publicApi.listDepartments(value);if(active&&request===departmentRequest)editorDepartments.value=rows}catch(e){if(active&&request===departmentRequest)formError.value=message(e,t.value.messages.loadError)}finally{if(request===departmentRequest)departmentsLoading.value=false}})
watch(deleteTarget,()=>{formError.value=''})
watch([editor,deleteTarget],([edit,remove])=>{document.body.style.overflow=edit||remove?'hidden':''})
onMounted(async()=>{await load();if(isSuperAdmin.value){try{let result, page=1, list=[];do{result=await superAdminApi.listChurches({page:page++});list.push(...result.data)}while(result.next_page_url);if(active)churches.value=list}catch(e){if(active)error.value=message(e,t.value.messages.loadError)}}})
onUnmounted(()=>{active=false;eventRequest++;departmentRequest++;document.body.style.overflow=''})
</script>

<style scoped>
.export-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.export-row>div{display:flex;gap:8px}.export-row .muted{font-size:12px}
.objectives-page {display:grid;gap:20px;font-size:13px}.objectives-heading,.card-heading{display:flex;align-items:center;justify-content:space-between;gap:18px}h1{font-size:20px;font-weight:500;margin:0 0 8px}.objectives-heading p{margin:0;color:#68766e}.eyebrow{font-size:12px;color:#28705b!important;margin-bottom:6px!important}.readonly-badge,.department-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 9px;border-radius:6px;font-size:12px;background:#edf3ef;color:#436351}.department-pill{background:color-mix(in srgb,var(--department-color) 12%,white)}.department-pill i{width:8px;height:8px;border-radius:50%;background:var(--department-color)}.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.summary article{background:white;border:1px solid #dfe5df;border-radius:9px;padding:16px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}.summary span,.muted{color:#68766e}.summary strong{font-size:21px;font-weight:500;color:#254b3d}.surface{background:white;border:1px solid #dfe5df;border-radius:10px;padding:20px}.filters{display:flex;align-items:end;gap:14px;flex-wrap:wrap;background:#f8faf9}.filters .search{flex:1;min-width:200px}label{display:block;font-size:12px;margin:0}.form-control,.form-select{margin-top:6px}.btn{width:auto;font-size:12px}.btn-primary{background:#1f6f5c;border-color:#1f6f5c}.objective-list{display:grid;gap:14px}h2{font-size:16px;font-weight:500;margin:10px 0 0;overflow-wrap:anywhere}.event-count{color:#68766e;font-size:12px;white-space:nowrap}.objective-details{display:grid;grid-template-columns:1.4fr 1fr;gap:24px;margin:20px 0}.objective-details h3{font-size:11px;font-weight:500;color:#68766e;margin:0 0 8px}.objective-details p{margin:0;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.6}.metrics{border-left:2px solid #dce9e1;padding-left:18px}footer,.pagination-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}footer{padding-top:14px;border-top:1px solid #edf0ed}footer .edit-actions{margin-left:auto;display:flex;gap:8px}.pagination-row{justify-content:space-between;color:#68766e}.pagination-row button+button{margin-left:8px}.empty{text-align:center;padding:45px;color:#68766e}.notice{padding:12px;border-radius:7px;margin:0}.error{background:#fff1ef;color:#8a3931}.linked-events{margin-top:16px}.table{font-size:12px}.table th{font-weight:500;background:#f6f8f7;color:#596b61}.table th,.table td{padding:12px}.status{display:inline-block;padding:4px 8px;border-radius:12px;background:#fbf2db;color:#78612c}.status.approved{background:#e6f1e9;color:#246046}.status.denied{background:#f8e9e6;color:#8a443e}.modal-panel fieldset{border:0;padding:0;display:grid;gap:16px}.modal-panel h2{margin:0}.modal-panel form{margin-top:18px}@media(max-width:767px){.summary{grid-template-columns:1fr}.objective-details{grid-template-columns:1fr;gap:16px}.objectives-heading{align-items:start;flex-wrap:wrap}.filters label{width:100%}.edit-actions{margin-left:0!important}}
</style>
