<template>
  <section class="attendance-report">
    <form class="attendance-filters" @submit.prevent="load">
      <label>{{ tr('Desde','From') }}<input v-model="from" type="date" required class="form-control" /></label>
      <label>{{ tr('Hasta','To') }}<input v-model="to" type="date" required :min="from" class="form-control" /></label>
      <label>{{ tr('Miembro','Member') }}<select v-model="member" class="form-select"><option value="">{{ tr('Todos los miembros','All members') }}</option><option v-for="person in people" :key="person.id" :value="String(person.id)">{{ person.name }}</option></select></label>
      <button class="btn btn-primary" :disabled="loading">{{ loading ? tr('Cargando…','Loading…') : tr('Consultar','View report') }}</button>
    </form>
    <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
    <template v-if="report">
      <p class="report-period">{{ applied.from }} — {{ applied.to }} · {{ applied.name }}</p>
      <div class="attendance-stats"><article><span>{{ tr('Asistencias registradas','Recorded check-ins') }}</span><strong>{{ report.summary.check_ins }}</strong></article><article><span>{{ tr('Miembros distintos','Unique members') }}</span><strong>{{ report.summary.members }}</strong></article><article><span>{{ tr('Días con registros','Days with records') }}</span><strong>{{ report.summary.recorded_days }}</strong></article></div>
      <section class="attendance-chart">
        <header><div><h2>{{ tr('Asistencia en el tiempo','Attendance over time') }}</h2><p>{{ tr('Un registro por persona y día. Cero significa sin registros, no ausencia confirmada.','One record per person per day. Zero means no records, not confirmed absence.') }}</p></div><label>{{ tr('Agrupar por','Group by') }}<select v-model="grouping" class="form-select"><option value="day">{{ tr('Día','Day') }}</option><option value="week">{{ tr('Semana','Week') }}</option><option value="month">{{ tr('Mes','Month') }}</option></select></label></header>
        <p v-if="!report.records.length" class="attendance-empty">{{ tr('No hay asistencias registradas en este período.','No attendance recorded in this period.') }}</p>
        <div v-else class="bar-chart" role="list" :aria-label="tr('Gráfico de asistencias','Attendance chart')">
          <button v-for="bar in bars" :key="bar.date" type="button" class="bar-row" :class="{selected: selected===bar.date}" :aria-pressed="selected===bar.date" :aria-label="`${bar.date}: ${bar.count}`" @click="selected=selected===bar.date?'':bar.date">
            <span>{{ bar.date }}</span><span class="bar-track"><span :style="{width:`${bar.count/max*100}%`}"></span></span><strong>{{ bar.count }}</strong>
          </button>
        </div>
        <p v-if="report.records.length" class="chart-hint">{{ tr('Selecciona una barra para ver sus registros.','Select a bar to see its records.') }}</p>
      </section>
      <section class="attendance-details"><header><h2>{{ tr('Detalle de asistencia','Attendance details') }} <small>({{ filtered.length }})</small></h2><button v-if="selected" type="button" class="btn btn-outline-secondary" @click="selected=''">{{ tr('Ver todo el período','Show entire period') }}</button></header>
        <div v-for="record in pageRecords" :key="`${record.date}-${record.member_id}`" class="attendance-record"><time :datetime="record.date">{{ record.date }}</time><span>{{ record.name }}</span><span class="present-badge">{{ tr('Presente','Present') }}</span></div>
        <p v-if="!filtered.length" class="attendance-empty">{{ tr('Sin registros.','No records.') }}</p>
        <nav v-if="filtered.length>25" class="attendance-pagination" :aria-label="tr('Páginas de registros','Record pages')"><button type="button" class="btn btn-outline-secondary" :disabled="page===1" @click="page--">{{ tr('Anterior','Previous') }}</button><span>{{ page }} / {{ Math.ceil(filtered.length/25) }}</span><button type="button" class="btn btn-outline-secondary" :disabled="page*25>=filtered.length" @click="page++">{{ tr('Siguiente','Next') }}</button></nav>
      </section>
    </template>
  </section>
</template>
<script setup>
import { ref,computed,onMounted,watch,onUnmounted } from 'vue'
import { apiClient } from '../services/authApi'
import { useUiStore } from '../stores/uiStore'
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const localDate=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
const today=new Date(),from=ref(localDate(new Date(today.getFullYear(),today.getMonth(),1))),to=ref(localDate(today)),member=ref(''),people=ref([]),report=ref(null),applied=ref({}),error=ref(''),loading=ref(false),grouping=ref('day'),selected=ref(''),page=ref(1)
let version=0
function bucket(date){if(grouping.value==='month')return date.slice(0,7);if(grouping.value==='week'){const d=new Date(`${date}T12:00:00`);d.setDate(d.getDate()-(d.getDay()+6)%7);return localDate(d)}return date}
const bars=computed(()=>{const groups=new Map();for(const row of report.value?.series||[]){const key=bucket(row.date);groups.set(key,(groups.get(key)||0)+row.count)}return [...groups].map(([date,count])=>({date,count}))})
const max=computed(()=>Math.max(1,...bars.value.map(b=>b.count)))
const filtered=computed(()=>(report.value?.records||[]).filter(r=>!selected.value||bucket(r.date)===selected.value))
const pageRecords=computed(()=>filtered.value.slice((page.value-1)*25,page.value*25))
watch(grouping,()=>{selected.value=''});watch(selected,()=>{page.value=1})
async function load(){const current=++version;loading.value=true;error.value='';report.value=null;selected.value='';page.value=1;const params={from:from.value,to:to.value,member_id:member.value||undefined};try{const {data}=await apiClient.get('/reports/attendance',{params});if(current!==version)return;people.value=data.members;report.value=data;applied.value={...params,name:params.member_id?data.members.find(m=>String(m.id)===params.member_id)?.name:tr('Todos los miembros','All members')};grouping.value=data.series.length>62?'month':data.series.length>31?'week':'day'}catch(e){if(current===version)error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||tr('No se pudo cargar el reporte.','Unable to load the report.')}finally{if(current===version)loading.value=false}}
onMounted(load);onUnmounted(()=>{version++})
</script>
<style scoped>
.attendance-report{min-width:0;display:grid;gap:18px}.attendance-filters{display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:14px;align-items:end;background:#f8faf9;padding:18px;border:1px solid #dce5de;border-radius:10px}.attendance-filters label,.attendance-chart label{min-width:0;font-size:12px;color:#53665b}.attendance-filters input,.attendance-filters select,.attendance-chart select{margin-top:6px;font-size:13px;min-width:0;width:100%}.attendance-report .btn{width:auto;white-space:normal}.attendance-report .btn-primary{background:#1f6f5c;color:white;border-color:#1f6f5c}.attendance-report .btn-outline-secondary{background:white;color:#405f50;border-color:#9eb3a5}.attendance-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.attendance-stats article{background:white;border:1px solid #dce5de;border-radius:9px;padding:16px;display:grid;gap:6px}.attendance-stats span{font-size:12px;color:#64746b}.attendance-stats strong{font-size:24px;font-weight:500;color:#24523c}.attendance-chart,.attendance-details{background:white;border:1px solid #dce5de;border-radius:10px;padding:20px;min-width:0}.attendance-report header{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}.attendance-report h2{font-size:16px;font-weight:500;margin:0 0 6px}.attendance-report p,.attendance-report small{font-size:12px;color:#617268}.bar-chart{display:grid;gap:6px;max-height:420px;overflow-y:auto}.bar-row{display:grid;grid-template-columns:90px minmax(0,1fr) 40px;align-items:center;gap:12px;width:100%;padding:8px;border:1px solid transparent;border-radius:6px;background:white;color:#3b5748;text-align:left;font-size:12px}.bar-row:hover,.bar-row.selected{background:#edf5ef;border-color:#aecabb}.bar-row strong{text-align:right;font-weight:500}.bar-track{height:20px;background:#f0f4f1;border-radius:4px;overflow:hidden}.bar-track>span{display:block;height:100%;background:#348364;border-radius:4px;min-width:0}.attendance-record{display:grid;grid-template-columns:100px minmax(0,1fr) auto;gap:12px;padding:12px 0;border-bottom:1px solid #e9eee9;font-size:13px;overflow-wrap:anywhere}.present-badge{background:#edf5ef;color:#245e42;border-radius:14px;padding:3px 8px;font-size:11px;align-self:start}.attendance-empty{text-align:center;padding:26px 0}.attendance-pagination{display:flex;justify-content:center;align-items:center;gap:14px;margin-top:18px}.chart-hint{margin:14px 0 0}.report-period{margin:0}@media(max-width:650px){.attendance-filters{grid-template-columns:1fr 1fr;padding:14px}.attendance-filters>label:nth-child(3){grid-column:1/-1}.attendance-stats{grid-template-columns:1fr}.attendance-stats article{display:flex;justify-content:space-between;align-items:center}.attendance-chart,.attendance-details{padding:14px}.attendance-record{grid-template-columns:1fr auto}.attendance-record time{grid-column:1/-1;color:#68786e}.bar-row{grid-template-columns:80px minmax(0,1fr) 28px;gap:7px;padding:8px 3px}}
</style>
