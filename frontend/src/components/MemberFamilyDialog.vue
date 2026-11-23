<template>
 <dialog ref="dialog" class="family-dialog" aria-labelledby="family-title" @close="$emit('close')">
  <header><h2 id="family-title">{{ tr('Organizar familias','Organize families') }}</h2><button class="btn btn-outline-secondary" @click="dialog.close()" aria-label="Cerrar">×</button></header>
  <p>{{ tr('Selecciona a las personas que se moverán. Sus fichas y datos individuales se conservan. Para unir familias completas, selecciona todos sus miembros.','Select the people to move. Their individual records remain intact. To merge entire families, select all their members.') }}</p>
  <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
  <input v-model="search" class="form-control" :placeholder="tr('Buscar nombre o ID de familia','Search name or family ID')" :aria-label="tr('Buscar miembros','Search members')" />
  <div class="family-candidates"><label v-for="m in filtered" :key="m.id"><input type="checkbox" :value="m.id" v-model="selected" :disabled="busy" /><span>{{ m.name || '#'+m.id }}<small>{{ m.family_id || tr('Sin familia asignada','No family assigned') }}</small></span></label></div>
  <p>{{ selected.length }} {{ tr('personas seleccionadas','people selected') }}</p>
  <label>{{ tr('Destino','Destination') }}<select v-model="mode" class="form-select" :disabled="busy"><option value="new">{{ tr('Crear nueva familia','Create new family') }}</option><option value="existing">{{ tr('Familia existente','Existing family') }}</option><option value="none">{{ tr('Sin familia asignada','No family assigned') }}</option></select></label>
  <label v-if="mode==='existing'">{{ tr('Familia','Family') }}<select v-model="familyId" class="form-select" :disabled="busy"><option value="">{{ tr('Selecciona una familia','Select a family') }}</option><option v-for="f in families" :key="f.id" :value="f.id">{{ f.names.join(', ') }} · {{ f.id }}</option></select></label>
  <footer><button class="btn btn-outline-secondary" :disabled="busy" @click="dialog.close()">{{ tr('Cancelar','Cancel') }}</button><button class="btn btn-primary" :disabled="busy||!selected.length||(mode==='existing'&&!familyId)" @click="save">{{ tr('Mover seleccionados','Move selected members') }}</button></footer>
 </dialog>
</template>
<script setup>
import {ref,computed,onMounted} from 'vue'
import {membersApi} from '../services/membersApi'
import {useUiStore} from '../stores/uiStore'
const props=defineProps({members:Array}),emit=defineEmits(['close','saved'])
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const dialog=ref(null),search=ref(''),selected=ref([]),mode=ref('new'),familyId=ref(''),families=ref([]),busy=ref(false),error=ref('')
const filtered=computed(()=>props.members.filter(m=>[m.name,m.family_id].join(' ').toLowerCase().includes(search.value.toLowerCase())))
onMounted(async()=>{dialog.value.showModal();try{families.value=await membersApi.families()}catch{error.value=tr('No se pudieron cargar las familias.','Unable to load families.')}})
async function save(){
 const target=mode.value==='new'?tr('una nueva familia','a new family'):mode.value==='none'?tr('sin familia asignada','no assigned family'):familyId.value
 if(!window.confirm(tr('¿Mover estas personas a ','Move these people to ')+target+'?\n'+props.members.filter(m=>selected.value.includes(m.id)).map(m=>m.name||'#'+m.id).join(', ')))return
 busy.value=true;error.value=''
 try{await membersApi.moveFamily({member_ids:selected.value,mode:mode.value,family_id:familyId.value||null});emit('saved');dialog.value.close()}catch(e){error.value=e.response?.data?.message||tr('No se pudo guardar.','Unable to save.')}finally{busy.value=false}
}
</script>
<style scoped>
.family-dialog{width:min(700px,calc(100vw - 28px));max-height:90dvh;overflow:auto;padding:24px;border:1px solid #dce5df;border-radius:12px;background:#fff;color:#24372e}.family-dialog::backdrop{background:#15271c88}.family-dialog header,.family-dialog footer{display:flex;justify-content:space-between;gap:12px;align-items:center}.family-dialog h2{font-size:1.1rem}.family-dialog p,.family-dialog label{font-size:.8125rem}.family-dialog>label{display:block;margin:14px 0}.family-candidates{max-height:280px;overflow:auto;margin:12px 0;border:1px solid #dce5df;border-radius:8px}.family-candidates label{display:flex;align-items:center;gap:10px;padding:10px;border-bottom:1px solid #edf1ee}.family-candidates input{width:16px;height:16px;flex:0 0 16px}.family-candidates small{display:block;color:#52645a;overflow-wrap:anywhere}.family-dialog footer{margin-top:20px;justify-content:flex-end}
</style>
