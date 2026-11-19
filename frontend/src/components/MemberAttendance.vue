<template>
  <section class="attendance-panel" aria-labelledby="attendance-title">
    <header><p class="attendance-eyebrow">{{ tr('BIENVENIDA','WELCOME') }}</p><h1 id="attendance-title">{{ tr('Asistencia de miembros','Member attendance') }}</h1><p>{{ tr('Busca tu primer apellido y marca a quienes están presentes hoy.','Search your first surname and mark everyone present today.') }}</p></header>
    <label for="family-search">{{ tr('Primer apellido','First surname') }}</label>
    <input id="family-search" v-model="query" type="search" class="form-control" autocomplete="off" maxlength="80" :placeholder="tr('Escribe al menos 2 letras…','Type at least 2 letters…')" :disabled="saving" @keydown.esc="reset" />
    <p v-if="searching" role="status">{{ tr('Buscando…','Searching…') }}</p>
    <ul v-if="suggestions.length" class="surname-suggestions" :aria-label="tr('Apellidos encontrados','Matching surnames')"><li v-for="surname in suggestions" :key="surname"><button type="button" @click="selectFamily(surname)">{{ surname }} <span aria-hidden="true">→</span></button></li></ul>
    <p v-else-if="searched && !family && !searching">{{ tr('No se encontró ese apellido. Pide al encargado que revise tu registro de miembro.','No matching surname. Ask the attendant to check your member record.') }}</p>
    <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
    <section v-if="family" class="attendance-family">
      <header><div><h2>{{ family }}</h2><p>{{ date }} · {{ tr('Una asistencia por persona al día','One attendance per person per day') }}</p></div><button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="reset">{{ tr('Otra familia','Next family') }}</button></header>
      <p v-if="!members.length">{{ tr('No hay miembros con ese apellido.','No members with that surname.') }}</p>
      <label v-for="member in members" :key="member.id" class="attendance-person" :class="{present:member.present}">
        <input type="checkbox" :aria-label="member.name" :checked="member.present" :disabled="saving || !auth.can('greeting.attendance')" @change="mark(member,$event)" />
        <span>{{ member.name }}<small>{{ member.present ? tr('Presente','Present') : tr('Sin registrar','Not checked in') }}</small></span>
      </label>
      <p class="attendance-feedback" role="status">{{ saving ? tr('Guardando…','Saving…') : notice }}</p>
    </section>
  </section>
</template>
<script setup>
import { ref, watch, onUnmounted } from 'vue'
import { apiClient } from '../services/authApi'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
const auth=useAuthStore(),ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const query=ref(''),suggestions=ref([]),members=ref([]),family=ref(''),date=ref(''),error=ref(''),notice=ref(''),saving=ref(false),searching=ref(false),searched=ref(false)
const timezone=Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'
let timer,clearTimer,version=0
function reset(){version++;clearTimeout(timer);clearTimeout(clearTimer);query.value='';family.value='';members.value=[];suggestions.value=[];error.value='';notice.value='';searched.value=false;searching.value=false}
watch(query,()=>{
  const current=++version;clearTimeout(timer);clearTimeout(clearTimer);family.value='';members.value=[];suggestions.value=[];error.value='';searched.value=false;searching.value=false
  if(query.value.trim().length<2)return
  searching.value=true
  timer=setTimeout(async()=>{try{const {data}=await apiClient.get('/attendance/surnames',{params:{q:query.value.trim()}});if(current===version){suggestions.value=data.surnames;searched.value=true}}catch(e){if(current===version)error.value=tr('No se pudo buscar. Inténtalo de nuevo.','Search failed. Please try again.')}finally{if(current===version)searching.value=false}},250)
})
async function selectFamily(surname){
  const current=++version;clearTimeout(timer);suggestions.value=[];searching.value=true;error.value='';notice.value=''
  try{const {data}=await apiClient.get('/attendance/family',{params:{surname,timezone}});if(current!==version)return;family.value=surname;members.value=data.members;date.value=data.date;clearTimer=setTimeout(reset,90000)}catch{if(current===version)error.value=tr('No se pudo cargar la familia. Inténtalo de nuevo.','Unable to load this family. Please try again.')}finally{if(current===version)searching.value=false}
}
async function mark(member,event){
  const previous=member.present,present=event.target.checked;member.present=present;saving.value=true;error.value='';notice.value='';clearTimeout(clearTimer)
  try{await apiClient.post('/attendance',{member_id:member.id,present,date:date.value,timezone});member.present=present;notice.value=tr('Asistencia guardada.','Attendance saved.')}catch(e){member.present=previous;error.value=e.response?.status===409?tr('Cambió el día. Busca la familia nuevamente.','The day changed. Search the family again.'):tr('No se guardó el cambio. Inténtalo nuevamente.','The change was not saved. Please try again.')}finally{saving.value=false;clearTimer=setTimeout(reset,90000)}
}
onUnmounted(()=>{version++;clearTimeout(timer);clearTimeout(clearTimer)})
</script>
<style scoped>
.attendance-panel{min-width:0;color:#243c33}.attendance-panel h1{font-size:1.5rem;font-weight:500}.attendance-panel p{font-size:.9rem;color:#53685d}.attendance-eyebrow{font-size:.72rem!important;letter-spacing:.1em}.attendance-panel>label{display:block;margin:22px 0 8px}.attendance-panel input[type=search]{width:100%;min-height:48px}.surname-suggestions{list-style:none;margin:8px 0;padding:0;border:1px solid #ccdcd3;border-radius:12px;overflow:hidden}.surname-suggestions button{display:flex;justify-content:space-between;width:100%;padding:14px 16px;border:0;border-bottom:1px solid #e1e9e5;background:#fff;color:#245844;text-align:left}.surname-suggestions button:hover,.surname-suggestions button:focus{background:#edf5f0}.attendance-family{margin-top:24px;border-top:1px solid #d7e3db;padding-top:20px}.attendance-family header{display:flex;gap:12px;justify-content:space-between;flex-wrap:wrap}.attendance-family h2{font-size:1.2rem}.attendance-person{display:flex;align-items:center;gap:16px;padding:16px;margin-top:10px;border:1px solid #d3dfd7;border-radius:12px;background:#fff;cursor:pointer;overflow-wrap:anywhere}.attendance-person.present{background:#eef7f0;border-color:#79a68b}.attendance-person input{width:24px;height:24px;flex-shrink:0;accent-color:#206b50}.attendance-person small{display:block;color:#53685d;margin-top:4px}.attendance-feedback{min-height:24px;margin-top:16px}.attendance-family .btn{width:auto;background:white;color:#385649;border-color:#9cb3a6}.attendance-panel .alert{margin-top:16px}
</style>
