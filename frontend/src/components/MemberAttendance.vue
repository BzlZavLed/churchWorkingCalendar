<template>
  <section class="attendance-panel" :class="{'public-attendance':publicChurchId}" aria-labelledby="attendance-title">
    <header><img v-if="logo" :src="logo" :alt="churchName" style="display:block;max-width:180px;max-height:100px;object-fit:contain;margin:0 auto 16px" /><p v-if="churchName">{{ churchName }}</p><p class="attendance-eyebrow">{{ tr('BIENVENIDA','WELCOME') }}</p><h1 id="attendance-title">{{ tr('Asistencia de miembros','Member attendance') }}</h1><p>{{ tr('Busca tu primer apellido y marca a quienes están presentes hoy.','Search your first surname and mark everyone present today.') }}</p></header>
    <p v-if="!statusLoaded" role="status">{{ tr('Consultando servicios…','Checking services…') }}</p>
    <p v-else-if="!services.length" class="alert alert-warning" role="status">{{ tr('La asistencia está cerrada. La secretaría debe abrir los servicios de hoy.','Attendance is closed. The clerk must open today’s services.') }}</p>
    <div v-else class="alert alert-success"><strong>{{ tr('Tu registro cuenta para','Your check-in counts for') }}:</strong> {{ services.map(s=>s.name+' ('+s.time+')').join(' + ') }}<p>{{ tr('Se calcula según la hora de envío.','Calculated using the time you submit.') }}</p></div>
    <label for="family-search">{{ tr('Primer apellido','First surname') }}</label>
    <input id="family-search" v-model="query" type="search" class="form-control" autocomplete="off" maxlength="80" :placeholder="tr('Escribe al menos 2 letras…','Type at least 2 letters…')" :disabled="saving||!services.length" @keydown.esc="reset" />
    <p v-if="searching" role="status">{{ tr('Buscando…','Searching…') }}</p>
    <ul v-if="suggestions.length" class="surname-suggestions" :aria-label="tr('Apellidos encontrados','Matching surnames')"><li v-for="surname in suggestions" :key="surname"><button type="button" @click="selectFamily(surname)">{{ surname }} <span aria-hidden="true">→</span></button></li></ul>
    <p v-else-if="searched && !family && !searching">{{ tr('No se encontró ese apellido. Pide al encargado que revise tu registro de miembro.','No matching surname. Ask the attendant to check your member record.') }}</p>
    <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
    <section v-if="family" class="attendance-family">
      <header><div><h2>{{ family }}</h2><p>{{ date }} · {{ tr('Una asistencia por persona y servicio','One attendance per person per service') }}</p></div><button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="reset">{{ tr('Otra familia','Next family') }}</button></header>
      <p v-if="!members.length">{{ tr('No hay miembros con ese apellido.','No members with that surname.') }}</p>
      <label class="mb-3">{{ tr('Vista','View') }}<select v-model="view" class="form-select"><option value="family">{{ tr('Por familia','By family') }}</option><option value="person">{{ tr('Por persona','By person') }}</option></select></label>
      <section v-for="group in groups" :key="group.id"><div v-if="view==='family'" class="family-checkin-heading"><h3>{{ group.id.startsWith('person-')?tr('Miembro sin familia asignada','Member without assigned family'):tr('Familia','Family')+' · '+group.members.map(m=>m.name).join(', ') }}</h3><button class="btn btn-outline-primary" :disabled="saving||!canMark||group.members.every(m=>m.present)" @click="markGroup(group.members)">{{ tr('Marcar todos presentes','Mark all present') }}</button></div>
      <label v-for="member in group.members"  :key="member.id" class="attendance-person" :class="{present:member.present}">
        <input type="checkbox" :aria-label="member.name" :checked="member.present" :disabled="saving || !canMark || (publicChurchId && member.present)" @change="mark(member,$event)" />
        <span>{{ member.name }}<small>{{ member.present ? tr('Presente','Present') : tr('Sin registrar','Not checked in') }}</small></span>
      </label>
      </section><p class="attendance-feedback" role="status">{{ saving ? tr('Guardando…','Saving…') : notice }}</p>
    </section>
  </section>
</template>
<script setup>
import { ref, watch, onUnmounted, computed, onMounted } from 'vue'
import { apiClient } from '../services/authApi'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
const props=defineProps({publicChurchId:String})
const services=ref([]),statusLoaded=ref(false)
const logo=ref(''),churchName=ref(''),token=ref(''),view=ref('family')
const auth=useAuthStore(),ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const query=ref(''),suggestions=ref([]),members=ref([]),family=ref(''),date=ref(''),error=ref(''),notice=ref(''),saving=ref(false),searching=ref(false),searched=ref(false)
const canMark=computed(()=>services.value.length>0 && (!!props.publicChurchId||auth.can('greeting.attendance')))
const endpoint=computed(()=>props.publicChurchId?'/public/churches/'+props.publicChurchId+'/attendance':'/attendance')
const groups=computed(()=>{
 if(view.value==='person')return [{id:'all',members:members.value}]
 const result=new Map()
 for(const m of members.value){const id=m.family_id?String(m.family_id):'person-'+m.id;if(!result.has(id))result.set(id,{id,members:[]});result.get(id).members.push(m)}
 return [...result.values()]
})
onMounted(async()=>{await refreshStatus();statusTimer=setInterval(()=>{if(!saving.value)refreshStatus()},30000);if(props.publicChurchId){try{const {data}=await apiClient.get('/public/churches/'+props.publicChurchId+'/visit');logo.value=data.logo_url;churchName.value=data.name}catch{error.value=tr('No se pudo cargar la iglesia.','Unable to load church.')}}})
async function refreshStatus(){try{const {data}=await apiClient.get(endpoint.value+'/status');if(services.value.map(s=>s.id).join(',')!==data.services.map(s=>s.id).join(','))reset();services.value=data.services;statusLoaded.value=true}catch{services.value=[];error.value=tr('No se pudo consultar la asistencia.','Unable to check attendance status.')}}
const timezone=Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
let timer,clearTimer,statusTimer,version=0
function reset(){version++;clearTimeout(timer);clearTimeout(clearTimer);query.value='';family.value='';members.value=[];suggestions.value=[];error.value='';notice.value='';searched.value=false;searching.value=false}
watch(query,()=>{
  const current=++version;clearTimeout(timer);clearTimeout(clearTimer);family.value='';members.value=[];suggestions.value=[];error.value='';searched.value=false;searching.value=false
  if(query.value.trim().length<2)return
  searching.value=true
  timer=setTimeout(async()=>{try{const {data}=await apiClient.get(endpoint.value+'/surnames',{params:{q:query.value.trim()}});if(current===version){suggestions.value=data.surnames;searched.value=true}}catch(e){if(current===version)error.value=tr('No se pudo buscar. Inténtalo de nuevo.','Search failed. Please try again.')}finally{if(current===version)searching.value=false}},250)
})
async function selectFamily(surname){
  const current=++version;clearTimeout(timer);suggestions.value=[];searching.value=true;error.value='';notice.value=''
  try{const {data}=await apiClient.get(endpoint.value+'/family',{params:{surname,timezone}});if(current!==version)return;family.value=surname;members.value=data.members;date.value=data.date;token.value=data.token;services.value=data.services;clearTimer=setTimeout(reset,90000)}catch{if(current===version)error.value=tr('No se pudo cargar la familia. Inténtalo de nuevo.','Unable to load this family. Please try again.')}finally{if(current===version)searching.value=false}
}
async function markGroup(group){
 saving.value=true;error.value='';clearTimeout(clearTimer)
 try{const {data}=await apiClient.post(props.publicChurchId?endpoint.value:endpoint.value+'/batch',{member_ids:group.map(m=>m.id),token:token.value});services.value=data.services;group.forEach(m=>m.present=true);notice.value=tr('Asistencia guardada.','Attendance saved.')}catch{error.value=tr('No se pudo guardar. Busca la familia nuevamente e intenta otra vez.','Unable to save. Search the family again and retry.')}finally{saving.value=false;clearTimer=setTimeout(reset,90000)}
}
async function mark(member,event){
 if(props.publicChurchId){event.target.checked=member.present;await markGroup([member]);return}
  const previous=member.present,present=event.target.checked;member.present=present;saving.value=true;error.value='';notice.value='';clearTimeout(clearTimer)
  try{const {data}=await apiClient.post('/attendance',{member_id:member.id,present,date:date.value,timezone});services.value=data.services;member.present=present;notice.value=tr('Asistencia guardada.','Attendance saved.')}catch(e){member.present=previous;error.value=e.response?.status===409?tr('Cambió la asistencia disponible. Busca la familia nuevamente.','Attendance availability changed. Search the family again.'):tr('No se guardó el cambio. Inténtalo nuevamente.','The change was not saved. Please try again.')}finally{saving.value=false;clearTimer=setTimeout(reset,90000)}
}
onUnmounted(()=>{version++;clearTimeout(timer);clearTimeout(clearTimer);clearInterval(statusTimer)})
</script>
<style scoped>
.public-attendance{max-width:720px;margin:24px auto;padding:24px;background:white;border-radius:14px}.family-checkin-heading{margin:18px 0 10px}.family-checkin-heading h3{font-size:.95rem;overflow-wrap:anywhere}

.attendance-panel{min-width:0;color:#243c33}.attendance-panel h1{font-size:1.5rem;font-weight:500}.attendance-panel p{font-size:.9rem;color:#53685d}.attendance-eyebrow{font-size:.72rem!important;letter-spacing:.1em}.attendance-panel>label{display:block;margin:22px 0 8px}.attendance-panel input[type=search]{width:100%;min-height:48px}.surname-suggestions{list-style:none;margin:8px 0;padding:0;border:1px solid #ccdcd3;border-radius:12px;overflow:hidden}.surname-suggestions button{display:flex;justify-content:space-between;width:100%;padding:14px 16px;border:0;border-bottom:1px solid #e1e9e5;background:#fff;color:#245844;text-align:left}.surname-suggestions button:hover,.surname-suggestions button:focus{background:#edf5f0}.attendance-family{margin-top:24px;border-top:1px solid #d7e3db;padding-top:20px}.attendance-family header{display:flex;gap:12px;justify-content:space-between;flex-wrap:wrap}.attendance-family h2{font-size:1.2rem}.attendance-person{display:flex;align-items:center;gap:16px;padding:16px;margin-top:10px;border:1px solid #d3dfd7;border-radius:12px;background:#fff;cursor:pointer;overflow-wrap:anywhere}.attendance-person.present{background:#eef7f0;border-color:#79a68b}.attendance-person input{width:24px;height:24px;flex-shrink:0;accent-color:#206b50}.attendance-person small{display:block;color:#53685d;margin-top:4px}.attendance-feedback{min-height:24px;margin-top:16px}.attendance-family .btn{width:auto;background:white;color:#385649;border-color:#9cb3a6}.attendance-panel .alert{margin-top:16px}
</style>
