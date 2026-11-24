<template>
  <section class="container py-4 builder">
    <header class="builder-header">
      <div><p class="eyebrow">{{ auth.user?.church?.name }}</p><h1>Formulario de visitas</h1><p>Diseña la tarjeta que verán los visitantes al abrir el enlace o escanear el QR.</p></div>
      <div class="header-actions"><a v-if="auth.user?.church_id" :href="`/visit/${auth.user.church_id}`" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">Ver formulario público ↗</a><RouterLink to="/greeting/contacts" class="btn btn-outline-secondary">Volver a visitas</RouterLink></div>
    </header>
    <p v-if="loading" class="muted">Cargando editor…</p><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p v-if="notice" class="alert alert-success" role="status">{{ notice }}</p>
    <div v-if="!loading && loaded" class="builder-workspace">
      <main class="editor-panel">
        <details class="surface how-to"><summary>¿Cómo se usa este editor?</summary><p>Elige una plantilla, ajusta el diseño y las preguntas, revisa el resultado a la derecha y publica cuando esté listo. Nada cambia en el QR o quiosco hasta publicar.</p></details>
        <section class="surface start-card"><div class="section-heading"><div><h2>Comienza con una plantilla</h2><p>Una plantilla reemplaza solo las preguntas adicionales del borrador.</p></div></div><div class="template-grid"><button v-for="template in templates" :key="template.id" class="template-option" :disabled="busy" @click="applyTemplate(template)"><strong>{{ template.title }}</strong><span>{{ template.description }}</span></button></div></section>
        <VisitorLayoutEditor v-if="layout" v-write="'visitors.configure'" v-model="layout" @uploading="imageUploading=$event" />
        <section v-write="'visitors.configure'" class="surface fields-card">
          <div class="section-heading"><div><h2>Preguntas adicionales</h2><p>Nombre, teléfono, correo y permisos de contacto ya están incluidos.</p></div><span class="version">Versión publicada {{ version }}</span></div>
          <p v-if="!fields.length" class="empty-state">No hay preguntas adicionales todavía.</p>
          <details v-for="(field,index) in fields" :key="field.id" class="field-editor">
            <summary class="field-summary"><span class="field-icon" aria-hidden="true">{{ typeIcon(field.type) }}</span><span class="field-summary-text"><strong>{{ field.label || 'Nueva pregunta' }}</strong><small>{{ typeLabel(field.type) }}</small></span></summary>
            <div class="field-content">
            <div class="field-top"><strong>{{ index + 1 }}. {{ typeLabel(field.type) }}</strong><div class="field-actions"><button type="button" :disabled="busy || index===0" aria-label="Subir pregunta" @click="move(index,-1)">↑</button><button type="button" :disabled="busy || index===fields.length-1" aria-label="Bajar pregunta" @click="move(index,1)">↓</button><button type="button" :disabled="busy" @click="fields.splice(index,1)">Quitar</button></div></div>
            <label>{{ field.type==='label' ? 'Texto informativo' : 'Pregunta' }}<textarea v-model="field.label" class="form-control" :disabled="busy" maxlength="500" rows="2" /></label>
            <div class="field-options"><label>Ancho<select class="form-select" v-model.number="field.span"><option :value="12">Fila completa</option><option :value="6">Media fila</option><option :value="4">Un tercio</option></select></label><label v-if="field.type!=='label'" class="check-label"><input v-model="field.required" type="checkbox" :disabled="busy" /> Obligatorio</label></div>
            <label v-if="field.type==='select'">Opciones — una por línea<textarea :value="safeOptions(field).join('\n')" class="form-control" :disabled="busy" rows="3" @input="field.options=$event.target.value.split('\n')" /></label>
          </div></details>
          <section class="add-field-panel" aria-labelledby="add-field-title"><div><h3 id="add-field-title">Agregar una pregunta</h3><p>Elige el tipo de respuesta y agrégalo al formulario.</p></div><div class="add-field-controls"><label>Tipo de campo<select v-model="newType" class="form-select" :disabled="busy"><option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option></select></label><button type="button" class="btn btn-primary" :disabled="busy || fields.length>=40" @click="add">+ Agregar</button></div></section>
        </section>
        <footer class="publish-bar"><div><strong>¿Listo para compartirlo?</strong><p>Publicar actualiza el formulario del quiosco, el enlace y el QR.</p></div><div class="publish-actions"><button class="btn btn-primary" :disabled="busy || imageUploading" @click="publish">{{ busy ? 'Publicando…' : 'Publicar formulario' }}</button><button class="btn btn-outline-secondary" :disabled="busy" @click="load">Descartar cambios</button></div></footer>
      </main>
      <aside class="preview-panel surface"><div class="section-heading"><div><p class="eyebrow">Vista previa</p><h2>Así lo verá el visitante</h2></div><button class="btn btn-outline-primary" :disabled="pdfBusy || imageUploading" @click="downloadFormPdf">{{ pdfBusy ? 'Generando…' : 'Descargar PDF' }}</button></div><label class="print-options">Tarjetas por página<select v-model.number="copiesPerPage" class="form-select"><option :value="1">1 tarjeta (tamaño mayor)</option><option :value="2">2 tarjetas</option><option :value="4">4 tarjetas (tamaño reducido)</option></select><small>Las tarjetas se reducen para caber; revisa que el texto sea legible antes de imprimir.</small></label><div class="public-card" :style="{ '--accent': layout?.accent || '#1f6f5c' }"><VisitorFormImages :layout="layout" position="header" :church-id="auth.user.church_id" :logo-url="logoUrl" editable /><h3>{{ layout?.title }}</h3><p>{{ layout?.introduction }}</p><div class="standard-preview"><label>Nombre<input class="form-control" disabled placeholder="Nombre del visitante" /></label><label>Teléfono<input class="form-control" disabled /></label><label>Correo electrónico<input class="form-control" disabled /></label></div><VisitorCustomFields :fields="fields" v-model="preview" /><VisitorFormImages :layout="layout" position="after" :church-id="auth.user.church_id" :logo-url="logoUrl" editable /><p class="permissions-note">El formulario también incluye pertenencia a la iglesia y permisos de contacto.</p><VisitorFormImages :layout="layout" position="footer" :church-id="auth.user.church_id" :logo-url="logoUrl" editable /></div></aside>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import VisitorCustomFields from './VisitorCustomFields.vue'
import VisitorFormImages from './VisitorFormImages.vue'
import VisitorLayoutEditor from './VisitorLayoutEditor.vue'
import { churchContactsApi } from '../services/churchContactsApi'
import { useAuthStore } from '../stores/authStore'
import { blankVisitorLayout, welcomeVisitorFields, welcomeVisitorLayout } from '../services/visitorFormDesign'
import { exportVisitorForm } from '../services/visitorFormPdf'

const auth = useAuthStore()
const types = ['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'label']
const typeLabels = { text: 'Texto corto', textarea: 'Texto largo', number: 'Número', date: 'Fecha', select: 'Lista de opciones', checkbox: 'Casilla', label: 'Texto informativo' }
const typeLabel = (type) => typeLabels[type] || type
const typeIcon = (type) => ({ text: 'T', textarea: '≡', number: '#', date: '▦', select: '☷', checkbox: '☑', label: 'ℹ' })[type] || 'T'
const fields = ref([]), layout = ref(null), version = ref(0), logoUrl = ref(null), newType = ref('text'), preview = ref({})
const loading = ref(true), loaded = ref(false), busy = ref(false), imageUploading = ref(false), pdfBusy = ref(false), error = ref(''), notice = ref('')
const copiesPerPage = ref(1)
const templates = computed(() => [
  { id: 'card', title: 'Tarjeta de bienvenida', description: 'Preguntas e imágenes basadas en la tarjeta de referencia.' },
  { id: 'welcome', title: 'Primera visita', description: 'Preguntas sencillas sobre cómo conoció la iglesia.' },
  { id: 'blank', title: 'Lienzo en blanco', description: 'Empieza solo con los datos estándar.' },
])
const message = (e) => Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'No se pudo guardar el formulario.'
const safeOptions = (field) => Array.isArray(field.options) ? field.options : []
function applyTemplate(template) {
  const card = template.id === 'card'
  layout.value = card ? welcomeVisitorLayout() : blankVisitorLayout()
  fields.value = (card || template.id === 'welcome' ? welcomeVisitorFields() : []).map((field) => ({ span: 12, required: false, options: [], ...field }))
  preview.value = {}
  notice.value = 'Plantilla cargada como borrador. Revísala y publica cuando esté lista.'
}
function add() { fields.value.push({ id: crypto.randomUUID(), type: newType.value, label: '', required: false, options: [], span: 12 }) }
function move(index, direction) { const [field] = fields.value.splice(index, 1); fields.value.splice(index + direction, 0, field) }
async function load() {
  loading.value = true; error.value = ''; notice.value = ''
  try { const form = await churchContactsApi.form(); fields.value = form.version ? (form.fields || []).map((field) => ({ span: 12, required: false, options: [], ...field })) : welcomeVisitorFields(); layout.value = form.layout || (form.version ? blankVisitorLayout() : welcomeVisitorLayout()); logoUrl.value = form.logo_url; version.value = form.version || 0; preview.value = {}; loaded.value = true }
  catch (e) { error.value = message(e) } finally { loading.value = false }
}
async function publish() {
  busy.value = true; error.value = ''; notice.value = ''
  try { const form = await churchContactsApi.publishForm({ base_version: version.value, layout: layout.value || blankVisitorLayout(), fields: fields.value.map((field) => ({ ...field, label: String(field.label || '').trim(), options: safeOptions(field).map((option) => String(option).trim()).filter(Boolean) })) }); version.value = form.version; fields.value = form.fields || []; layout.value = form.layout || blankVisitorLayout(); notice.value = 'Formulario publicado. Ya está disponible en el quiosco y el QR.' }
  catch (e) { error.value = message(e) } finally { busy.value = false }
}
async function downloadFormPdf() { pdfBusy.value = true; error.value = ''; try { await exportVisitorForm({ layout: layout.value, fields: fields.value, churchId: auth.user.church_id, churchName: auth.user?.church?.name, logoUrl: logoUrl.value, copiesPerPage: copiesPerPage.value }) } catch { error.value = 'No se pudo generar el PDF. Verifica que las imágenes se carguen correctamente.' } finally { pdfBusy.value = false } }
onMounted(load)
</script>

<style scoped>
.builder{color:#243d32;font-size:14px}.builder-header,.section-heading,.field-top,.publish-bar,.add-field-controls{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.builder-header{margin-bottom:20px}.builder h1{font-size:28px;margin:0}.builder h2{font-size:18px;margin:0 0 5px}.builder h3{font-size:16px}.builder p{color:#52645a}.eyebrow{margin:0 0 5px;color:#1f6f5c!important;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em}.header-actions{display:flex;gap:8px;flex-wrap:wrap}.surface{background:#fff;border:1px solid #dce5df;border-radius:14px;padding:20px}.builder-workspace{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(360px,.95fr);gap:20px;align-items:start}.editor-panel{display:grid;gap:16px;min-width:0}.how-to{padding:13px 16px;background:#f3f8f5;border:1px solid #d6e7dc;border-radius:10px}.how-to summary{cursor:pointer;font-weight:650}.how-to p{margin:10px 0 0}.template-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}.template-option{min-height:105px;text-align:left;border:1px solid #bed3c6;border-radius:10px;background:#f7faf8;padding:14px;color:#243d32;display:grid;gap:8px}.template-option:hover:not(:disabled){border-color:#1f6f5c;background:#eaf4ee}.template-option span{font-size:12px;color:#52645a}.version{font-size:12px;padding:4px 8px;background:#edf4ef;border-radius:999px;color:#32644e}.fields-card{display:grid;gap:14px}.empty-state{margin:0;padding:12px;background:#f7faf8;border-radius:8px}.field-editor{border:1px solid #dce5df;border-radius:10px;padding:14px;display:grid;gap:12px}.field-editor label{display:grid;gap:5px;font-weight:600}.field-actions{display:flex;gap:6px}.field-actions button{background:#fff;border:1px solid #a7b9ae;border-radius:6px;padding:4px 8px;color:#344f41}.field-options{display:flex;align-items:end;gap:16px;flex-wrap:wrap}.field-options>label:first-child{width:180px}.check-label{display:flex!important;align-items:center;grid-auto-flow:column;justify-content:start;gap:7px}.check-label input{width:16px;height:16px}.add-field-panel{padding:16px;border:2px dashed #7eaa94;border-radius:10px;background:#eff7f2}.add-field-panel h3,.add-field-panel p{margin:0}.add-field-controls{justify-content:flex-start;margin-top:14px}.add-field-controls label{display:grid;gap:5px;min-width:220px}.publish-bar{padding:16px 18px;background:#173f31;border-radius:12px;color:white}.publish-bar p{margin:3px 0 0;color:#d8e9df}.preview-panel{position:sticky;top:18px;max-height:calc(100vh - 36px);overflow:auto}.public-card{margin-top:16px;padding:18px;border:1px solid #dce5df;border-top:5px solid var(--accent);border-radius:10px;background:#fff}.public-card h3{color:var(--accent);font-size:22px}.standard-preview{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}.standard-preview label{font-size:12px}.permissions-note{font-size:12px;padding:10px;background:#f5f8f6;border-radius:7px}.builder .form-control,.builder .form-select{font-size:14px}.builder .btn-primary{background:#1f6f5c;border-color:#1f6f5c}.builder .btn-outline-primary{color:#1f6f5c;border-color:#1f6f5c}.builder .btn-outline-secondary{color:#4f6056;border-color:#76867c}@media(max-width:980px){.builder-workspace{grid-template-columns:1fr}.preview-panel{position:static;max-height:none}.template-grid{grid-template-columns:1fr}}@media(max-width:600px){.surface{padding:14px}.standard-preview{grid-template-columns:1fr}.publish-bar{align-items:flex-start}.add-field-controls{align-items:stretch}.add-field-controls label,.add-field-controls button{width:100%}}
</style>
<style scoped>
.field-editor{display:block;padding:0}.field-summary{display:flex;align-items:center;gap:12px;padding:12px 14px;cursor:pointer;list-style:none}.field-summary::-webkit-details-marker{display:none}.field-summary::after{content:'⌄';margin-left:auto;font-size:20px}.field-editor[open] .field-summary::after{transform:rotate(180deg)}.field-icon{display:grid;place-items:center;width:36px;height:36px;flex-shrink:0;border-radius:8px;background:#e9f4ed;color:#1f6f5c;font-size:20px;font-weight:700}.field-summary-text{min-width:0;display:grid;gap:2px}.field-summary-text strong{overflow-wrap:anywhere}.field-summary-text small{color:#52645a}.field-content{display:grid;gap:12px;padding:0 14px 14px}
.publish-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.builder .publish-actions .btn{width:auto;min-height:42px;margin:0;padding:9px 16px;white-space:nowrap}.builder .publish-actions .btn-primary{background:#fff;border-color:#fff;color:#174e3d}.builder .publish-actions .btn-outline-secondary{background:transparent;border-color:#c3dfd2;color:#fff}.builder .publish-actions .btn-outline-secondary:hover:not(:disabled){background:#285c49}.builder .publish-actions .btn:disabled{opacity:.65}@media(max-width:480px){.publish-actions{width:100%}.builder .publish-actions .btn{flex:1 1 auto}}
.print-options{display:block;margin-top:16px;font-weight:600}.print-options select{margin-top:5px}.print-options small{display:block;margin-top:6px;color:#52645a;font-weight:400}
</style>
