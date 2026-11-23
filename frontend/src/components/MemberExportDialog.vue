<template>
 <dialog ref="dialog" class="member-export-dialog" aria-labelledby="member-export-title" @close="$emit('close')">
  <header><div><h2 id="member-export-title">{{ tr('Exportar miembros','Export members') }}</h2><p>{{ tr('Todos los miembros de esta iglesia, solo con las columnas que elijas.','All members in this church, with only the columns you choose.') }}</p></div><button class="btn btn-outline-secondary" @click="dialog.close()" :aria-label="tr('Cerrar','Close')">×</button></header>
  <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
  <p v-if="notice" role="status">{{ notice }}</p>
  <p v-if="loading">{{ tr('Cargando…','Loading…') }}</p>
  <template v-else>
   <div class="export-layout">
    <label>{{ tr('Diseño guardado','Saved layout') }}<select v-model="presetId" class="form-select" @change="applyPreset"><option value="">{{ tr('Selección personalizada','Custom selection') }}</option><option v-for="p in presets" :key="p.id" :value="p.id">{{ p.name }}</option></select></label>
    <button v-if="presetId && canManage" class="btn btn-outline-danger" :disabled="busy" @click="remove">{{ tr('Eliminar diseño','Delete layout') }}</button>
   </div>
   <div class="export-selection-tools"><span>{{ selected.length }} {{ tr('columnas seleccionadas','selected columns') }}</span><button class="btn btn-link" @click="selected=columns.map(c=>c.id)">{{ tr('Todas','All') }}</button><button class="btn btn-link" @click="selected=[]">{{ tr('Ninguna','None') }}</button></div>
   <fieldset class="export-columns" :disabled="busy"><legend class="visually-hidden">{{ tr('Columnas para exportar','Columns to export') }}</legend><label v-for="c in columns" :key="c.id"><input type="checkbox" :value="c.id" v-model="selected" /><span>{{ c.label }}</span></label></fieldset>
   <div class="export-layout">
    <label>{{ tr('Ordenar por','Sort by') }}<select v-model="sortBy" class="form-select" :disabled="busy||!selected.length"><option v-for="c in columns.filter(c=>selected.includes(c.id))" :key="c.id" :value="c.id">{{ c.label }}</option></select></label>
    <label>{{ tr('Orden','Order') }}<select v-model="sortDirection" class="form-select" :disabled="busy"><option value="asc">{{ tr('Ascendente · A–Z / menor a mayor','Ascending · A–Z / lowest first') }}</option><option value="desc">{{ tr('Descendente · Z–A / mayor a menor','Descending · Z–A / highest first') }}</option></select></label>
   </div>
   <p>{{ tr('Los valores iguales quedan juntos. Las fechas se ordenan por año, mes y día; los datos vacíos van al final.','Matching values stay together. Dates sort by year, month and day; empty values go last.') }}</p>
   <div v-if="canManage" class="export-save-layout"><label>{{ tr('Guardar esta selección como','Save this selection as') }}<input v-model="name" maxlength="100" class="form-control" :placeholder="tr('Ej. Cumpleaños o Visitas','E.g. Birthdays or Visits')" /></label><button class="btn btn-outline-primary" :disabled="busy||!name.trim()||!selected.length" @click="save">{{ tr('Guardar diseño','Save layout') }}</button><small>{{ tr('Si usas el mismo nombre, se actualizará ese diseño. Guarda las columnas y el orden, no los datos de miembros.','Using the same name updates that layout. The columns and sorting are saved, not member data.') }}</small></div>
   <footer><button class="btn btn-outline-secondary" @click="dialog.close()">{{ tr('Cancelar','Cancel') }}</button><button class="btn btn-primary" :disabled="busy||!selected.length" @click="download">{{ tr('Descargar CSV','Download CSV') }}</button></footer>
  </template>
 </dialog>
</template>
<script setup>
import {ref,onMounted,watch} from 'vue'
import {membersApi} from '../services/membersApi'
import {useUiStore} from '../stores/uiStore'
defineProps({canManage:Boolean})
defineEmits(['close'])
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const sortBy=ref('given_name'),sortDirection=ref('asc')
const dialog=ref(null),columns=ref([]),presets=ref([]),selected=ref(['given_name','first_surname','second_surname','data.BornOn']),presetId=ref(''),name=ref(''),loading=ref(true),busy=ref(false),error=ref(''),notice=ref('')
watch(selected,()=>{if(!selected.value.includes(sortBy.value))sortBy.value=selected.value[0]||''},{deep:true})
const fail=e=>{error.value=e.response?.data?.message||tr('No se pudo completar la operación. Intenta de nuevo.','Unable to complete the operation. Try again.')}
onMounted(async()=>{dialog.value.showModal();try{const data=await membersApi.exportOptions();columns.value=data.columns;presets.value=data.presets}catch(e){fail(e)}finally{loading.value=false}})
function applyPreset(){const p=presets.value.find(p=>p.id===presetId.value);if(p){selected.value=p.columns.filter(id=>columns.value.some(c=>c.id===id));name.value=p.name;sortBy.value=p.sort_by||selected.value[0]||'';sortDirection.value=p.sort_direction||'asc'}}
async function save(){busy.value=true;error.value='';notice.value='';try{const data=await membersApi.saveExportPreset({name:name.value.trim(),columns:selected.value,sort_by:sortBy.value,sort_direction:sortDirection.value});presets.value=data.presets;presetId.value=presets.value.find(p=>p.name===name.value.trim())?.id||'';notice.value=tr('Diseño guardado.','Layout saved.')}catch(e){fail(e)}finally{busy.value=false}}
async function remove(){if(!window.confirm(tr('¿Eliminar este diseño guardado?','Delete this saved layout?')))return;busy.value=true;error.value='';try{await membersApi.deleteExportPreset(presetId.value);presets.value=presets.value.filter(p=>p.id!==presetId.value);presetId.value='';name.value='';notice.value=tr('Diseño eliminado.','Layout deleted.')}catch(e){fail(e)}finally{busy.value=false}}
async function download(){busy.value=true;error.value='';try{const blob=await membersApi.exportMembers(selected.value,sortBy.value,sortDirection.value);const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=(name.value.trim().replace(/[^\p{L}\p{N}_ -]/gu,'')||'miembros')+'.csv';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000)}catch(e){fail(e)}finally{busy.value=false}}
</script>
<style scoped>
.member-export-dialog{width:min(760px,calc(100vw - 28px));max-height:90dvh;overflow-y:auto;border:1px solid #dce5df;border-radius:14px;padding:24px;color:#24372e;background:#fff}.member-export-dialog::backdrop{background:#15271c88}.member-export-dialog header{display:flex;gap:16px;align-items:flex-start;justify-content:space-between}.member-export-dialog h2{font-size:1.1rem;margin:0 0 8px}.member-export-dialog p,.member-export-dialog label,.member-export-dialog small{font-size:.8125rem}.export-layout,.export-save-layout{display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:18px 0}.export-layout label,.export-save-layout label{flex:1;min-width:180px}.export-selection-tools{display:flex;align-items:center;gap:8px;font-size:.8125rem}.export-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 16px;border:1px solid #dce5df;border-radius:8px;padding:14px;min-width:0}.export-columns label{display:flex;gap:9px;align-items:center;padding:6px;overflow-wrap:anywhere}.export-columns input{width:16px;height:16px;flex:0 0 16px;margin:0}.export-save-layout small{flex-basis:100%;color:#52645a}.member-export-dialog footer{display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #dce5df;padding-top:16px}@media(max-width:550px){.export-columns{grid-template-columns:1fr}.member-export-dialog{padding:16px}}
</style>
