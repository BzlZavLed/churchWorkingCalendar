<template>
 <section class="container py-4 management-page attendance-manager">
  <header class="management-heading"><div><h1>{{ tr('Asistencia y servicios','Attendance and services') }}</h1><p>{{ tr('Abre los servicios de hoy antes de recibir registros. Comparte el QR o abre la página pública.','Open today’s services before check-in. Share the QR or open the public page.') }}</p></div></header>
  <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p><p v-if="notice" role="status" class="alert alert-success">{{ notice }}</p>
  <div class="attendance-manager-grid">
   <section class="management-surface p-3">
    <h2>{{ tr('Servicios recurrentes','Recurring services') }}</h2>
    <p>{{ tr('Separados del calendario departamental y de ExCom. Los cambios afectan futuras aperturas.','Separate from the departmental calendar and ExCom. Changes apply to future openings.') }}</p>
    <div v-if="data.can_manage" class="actions"><button class="btn btn-outline-primary" @click="edit()">{{ editor?tr('Cerrar formulario','Close form'):tr('Agregar servicio','Add service') }}</button><button class="btn btn-outline-secondary" @click="example(6,'Escuela Sabática','10:30','12:00')">+ Escuela Sabática</button><button class="btn btn-outline-secondary" @click="example(6,'Culto Divino','12:00','14:00')">+ Culto Divino</button><button class="btn btn-outline-secondary" @click="example(5,'Servicio de viernes','19:30','21:00')">+ {{ tr('Viernes','Friday') }}</button></div>
    <form v-if="editor" class="service-editor" @submit.prevent="save">
     <label>{{ tr('Nombre','Name') }}<input v-model="editor.name" required maxlength="150" class="form-control" /></label>
     <label>{{ tr('Día semanal','Weekday') }}<select v-model="editor.weekday" class="form-select"><option v-for="(day,i) in days" :value="i">{{ day }}</option></select></label>
     <label>{{ tr('Inicio','Start') }}<input v-model="editor.start_time" type="time" required class="form-control" /></label><label>{{ tr('Fin / cierre automático','End / automatic closing') }}<input v-model="editor.end_time" type="time" required class="form-control" /></label>
     <label><input type="checkbox" v-model="editor.active" /> {{ tr('Activo','Active') }}</label><button class="btn btn-primary" :disabled="busy">{{ tr('Guardar servicio','Save service') }}</button>
    </form>
    <article v-for="s in data.services" :key="s.id" class="service-row"><div><strong>{{ s.name }}</strong><p>{{ days[s.weekday] }} · {{ s.start_time }}–{{ s.end_time }} · {{ s.active?tr('Activo','Active'):tr('Inactivo','Inactive') }}</p></div><button v-if="data.can_manage" class="btn btn-outline-secondary" @click="editor={...s}">{{ tr('Editar','Edit') }}</button></article>
    <p v-if="!data.services.length">{{ tr('Agrega los servicios habituales de tu iglesia para comenzar.','Add your church’s recurring services to begin.') }}</p>
   </section>
   <section class="management-surface p-3">
    <h2>{{ tr('Abrir asistencia de hoy','Open today’s attendance') }}</h2>
    <label>{{ tr('Zona horaria de la iglesia','Church time zone') }}<input v-model="timezone" class="form-control" placeholder="America/New_York" :disabled="busy" /></label>
    <p>{{ today }} · {{ tr('Selecciona juntos los servicios consecutivos. Antes del inicio del siguiente se registran ambos; desde su inicio, solo el siguiente.','Select consecutive services together. Before the next service starts, both are recorded; from its start, only the next is recorded.') }}</p>
    <form v-if="data.can_manage" @submit.prevent="open">
     <label v-for="s in todaysServices" :key="s.id" class="service-choice"><input type="checkbox" :value="s.id" v-model="selected" /> {{ s.name }} · {{ s.start_time }}–{{ s.end_time }}</label>
     <p v-if="!todaysServices.length">{{ tr('No hay servicios activos programados para hoy.','No active services are scheduled today.') }}</p>
     <button class="btn btn-primary" :disabled="busy||!selected.length">{{ tr('Abrir seleccionados','Open selected services') }}</button>
    </form>
    <p v-else>{{ tr('La secretaría debe abrir la asistencia.','The clerk must open attendance.') }}</p>
    <h3 class="mt-3">{{ tr('Estado actual','Current status') }}</h3><p v-if="!data.eligible.length">{{ tr('Asistencia cerrada.','Attendance closed.') }}</p><p v-else>{{ tr('Los registros ahora cuentan para','Check-ins now count for') }}: {{ data.eligible.map(s=>s.name).join(' + ') }}</p>
    <article v-for="block in blocks" :key="block.id" class="service-row"><div><strong>{{ block.names }}</strong><p>{{ block.date }} · {{ block.timezone }} · {{ block.closed?tr('Cerrado','Closed'):block.expired?tr('Finalizado','Ended'):tr('Abierto','Open') }}</p></div><button v-if="data.can_manage&&!block.expired" class="btn btn-outline-secondary" :disabled="busy" @click="setOpen(block)">{{ block.closed?tr('Reabrir bloque','Reopen block'):tr('Cerrar bloque','Close block') }}</button></article>
   </section>
  </div>
  <section class="management-surface p-3 mt-3"><h2>{{ tr('QR para miembros','Member QR') }}</h2><p>{{ tr('El mismo QR sirve para todos los servicios. Solo permite registrar asistencia cuando la secretaría abre un servicio.','The same QR works for every service. Check-in is available only while a service is open.') }}</p><QrPosterPreview :church-id="auth.user.church_id" kind="attendance" :revision="previewRevision"><div class="actions"><a :href="publicUrl" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary">{{ tr('Ver página pública','Preview public page') }}</a><button class="btn btn-outline-secondary" :disabled="busy" @click="builderOpen=true">{{ tr('Diseñar y descargar QR','Design and download QR') }}</button><button class="btn btn-outline-secondary" @click="copy">{{ tr('Copiar enlace','Copy link') }}</button></div></QrPosterPreview></section>
 <QrPosterBuilder v-if="builderOpen" :church-id="auth.user.church_id" kind="attendance" @close="builderOpen=false;previewRevision++" />
 </section>
</template>
<script setup>
import {ref,computed,onMounted,onUnmounted} from 'vue'
import {apiClient} from '../services/authApi'
import {useAuthStore} from '../stores/authStore'
import {useUiStore} from '../stores/uiStore'
import QrPosterPreview from './QrPosterPreview.vue'
import QrPosterBuilder from './QrPosterBuilder.vue'
import '../styles/clerk-management.css'
const builderOpen=ref(false),previewRevision=ref(0)
const auth=useAuthStore(),ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const data=ref({services:[],sessions:[],eligible:[],can_manage:false}),editor=ref(null),selected=ref([]),timezone=ref(Intl.DateTimeFormat().resolvedOptions().timeZone||'America/New_York'),error=ref(''),notice=ref(''),busy=ref(false),clock=ref(Date.now())
const days=computed(()=>ui.locale==='es'?['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado']:['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'])
const today=computed(()=>{try{return new Intl.DateTimeFormat('en-CA',{timeZone:timezone.value,year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(clock.value))}catch{return ''}})
const weekday=computed(()=>today.value?new Date(today.value+'T12:00:00').getDay():-1)
const todaysServices=computed(()=>data.value.services.filter(s=>s.active&&s.weekday===weekday.value))
const blocks=computed(()=>{const map=new Map();for(const s of data.value.sessions){if(!map.has(s.batch_id))map.set(s.batch_id,[]);map.get(s.batch_id).push(s)}return [...map].slice(0,10).map(([id,rows])=>({id,session:rows[0].id,names:rows.map(s=>s.name).join(' + '),date:rows[0].service_date,timezone:rows[0].timezone,closed:!!rows[0].closed_at,expired:rows.every(s=>new Date(s.ends_at).getTime()<=clock.value)}))})
const publicUrl=computed(()=>window.location.origin+'/attendance/'+auth.user?.church_id)
async function load(){const {data:result}=await apiClient.get('/attendance/services');data.value=result}
async function run(fn){busy.value=true;error.value='';notice.value='';try{await fn();await load()}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||e.response?.data?.message||tr('No se pudo completar.','Unable to complete.')}finally{busy.value=false}}
function edit(){editor.value=editor.value?null:{name:'',weekday:6,start_time:'10:30',end_time:'12:00',active:true}}
function example(weekday,name,start_time,end_time){editor.value={weekday,name,start_time,end_time,active:true}}
async function save(){await run(async()=>{await apiClient[editor.value.id?'put':'post']('/attendance/services'+(editor.value.id?'/'+editor.value.id:''),editor.value);editor.value=null})}
async function open(){await run(async()=>{await apiClient.post('/attendance/sessions',{service_ids:selected.value,date:today.value,timezone:timezone.value});selected.value=[];notice.value=tr('Asistencia abierta.','Attendance opened.')})}
async function setOpen(block){await run(()=>apiClient.patch('/attendance/sessions/'+block.session,{open:block.closed}))}
async function copy(){try{await navigator.clipboard.writeText(publicUrl.value);notice.value=tr('Enlace copiado.','Link copied.')}catch{error.value=tr('No se pudo copiar.','Unable to copy.')}}
let timer
onMounted(async()=>{await run(async()=>{});timer=setInterval(()=>{clock.value=Date.now();if(!busy.value)load().catch(()=>{})},30000)})
onUnmounted(()=>clearInterval(timer))
</script>
<style scoped>
.attendance-manager h2{font-size:1rem}.attendance-manager h3{font-size:.9rem}.attendance-manager p,.attendance-manager label{font-size:.8125rem}.attendance-manager-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.actions{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0}.attendance-manager .btn{width:auto;white-space:normal}.service-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #dce5df}.service-row p{margin:5px 0 0}.service-editor{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:16px;background:#f3f7f4;margin:12px 0}.service-choice{display:flex;align-items:center;gap:8px;padding:10px 0}.attendance-manager input[type=checkbox]{width:16px;height:16px}.manager-logo{display:block;max-width:160px;max-height:80px;object-fit:contain}@media(max-width:750px){.attendance-manager-grid{grid-template-columns:1fr}.service-editor{grid-template-columns:1fr}}
</style>
