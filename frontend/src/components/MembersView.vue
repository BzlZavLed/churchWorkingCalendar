<template>
 <section class="container py-4 management-page members-page">
  <header class="management-heading"><div><p class="management-eyebrow">{{ auth.user?.church?.name }}</p><h1>{{ tr('Miembros','Members') }}</h1><p>{{ tr('El directorio de tu iglesia. Selecciona un nombre para abrir su ficha completa.','Your church directory. Select a name to open the full member record.') }}</p></div><button class="btn btn-outline-primary" :aria-expanded="birthdays" @click="birthdays=!birthdays">{{ birthdays?tr('Cerrar cumpleaños','Close birthdays'):tr('Cumpleaños','Birthdays') }}</button></header>
  <MemberBirthdays v-if="birthdays" />
  <MemberFamilyDialog v-if="familyOpen" :members="members" @close="familyOpen=false" @saved="load" />
  <MemberExportDialog v-if="exportOpen" :can-manage="auth.can('members.manage')" @close="exportOpen=false" />
  <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p><p v-if="notice" role="status" class="alert alert-success">{{ notice }}</p>
  <details v-if="auth.can('members.manage')" class="management-surface member-panel">
   <summary>{{ tr('Importar directorio CSV','Import CSV directory') }}</summary>
   <p>{{ tr('Usa el archivo de directorio o descarga los encabezados estándar. Obligatorios: nombre, correo, fecha de nacimiento y al menos un teléfono (móvil, familiar o trabajo). Los demás campos son opcionales.','Use your directory file or download the standard headers. Required: name, email, date of birth and at least one phone (mobile, family or work). Other fields are optional.') }}</p>
   <p>{{ tr('BornOn: AAAA-MM-DD o MM/DD/AAAA. Casillas: Y/N, sí/no o true/false. Se aceptan UTF-8 y Windows-1252. Máximo 1,000 personas y 2 MB. Se permiten correos compartidos. PersonId repetidos se rechazan; no se reemplazan registros.','BornOn: YYYY-MM-DD or MM/DD/YYYY. Checkboxes: Y/N, yes/no or true/false. UTF-8 and Windows-1252 accepted. Maximum 1,000 people and 2 MB. Shared emails are allowed. Duplicate PersonId values are rejected; records are not overwritten.') }}</p>
   <div class="member-toolbar"><button class="btn btn-outline-primary" :disabled="busy" @click="download">{{ tr('Descargar encabezados CSV','Download CSV headers') }}</button><label>{{ tr('Archivo CSV','CSV file') }}<input ref="fileInput" type="file" accept=".csv,text/csv" class="form-control" :disabled="busy" @change="file=$event.target.files?.[0];preview=null" /></label><button class="btn btn-outline-secondary" :disabled="busy||!file" @click="importFile(true)">{{ tr('Cargar para revisar','Load for review') }}</button></div>
   <section v-if="drafts.length" class="saved-member-drafts"><h3>{{ tr('Importaciones pendientes','Pending imports') }}</h3><p>{{ tr('Borradores guardados para tu cuenta en esta iglesia.','Drafts saved for your account in this church.') }}</p><div v-for="d in drafts" :key="d.id" class="member-draft-row"><button class="btn btn-outline-secondary" :disabled="busy" @click="resume(d.id)">#{{ d.id }} · {{ d.count }} {{ tr('filas','rows') }} · {{ new Date(d.updated_at).toLocaleString() }}</button><button class="btn btn-outline-danger" :disabled="busy" @click="deleteDraft(d)" :aria-label="tr('Eliminar borrador','Delete draft')+' #'+d.id">{{ tr('Eliminar','Delete') }}</button></div></section>
   <MemberImportReview v-if="preview" :review="preview" @busy="busy=$event" @imported="imported" @saved="draftSaved" />
  </details>
  <section class="management-surface member-panel">
   <div class="member-toolbar"><div class="member-list-heading"><h2>{{ tr('Lista de miembros','Member list') }}</h2><router-link v-if="auth.can('members.manage')" to="/members/new" class="creation-plus" :aria-label="tr('Agregar miembro','Add member')" :title="tr('Agregar miembro','Add member')">+</router-link><span>{{ filtered.length }}</span><button class="btn btn-outline-primary" @click="exportOpen=true">{{ tr('Exportar','Export') }}</button></div><label><span class="visually-hidden">{{ tr('Buscar','Search') }}</span><input v-model="search" type="search" class="form-control" :placeholder="tr('Nombre, correo, teléfono…','Name, email, phone…')" /></label></div>
   <div class="member-toolbar mt-3"><label><input type="checkbox" v-model="groupFamilies" /> {{ tr('Agrupar por familia','Group by family') }}</label><button v-if="auth.can('members.manage')" class="btn btn-outline-primary" @click="familyOpen=true">{{ tr('Organizar familias','Organize families') }}</button></div>
   <p v-if="loading" role="status">{{ tr('Cargando…','Loading…') }}</p>
   <p v-else-if="!filtered.length">{{ tr('No hay miembros que mostrar.','No members to display.') }}</p>
   <div v-else><section v-for="group in memberGroups" :key="group.id" class="member-family-group"><h3 v-if="groupFamilies">{{ group.label }} · {{ group.members.length }}</h3><table class="directory-table" data-dt="off"><thead><tr><th>{{ tr('Nombre','Name') }}</th><th>{{ tr('Correo','Email') }}</th><th>{{ tr('Teléfono','Phone') }}</th><th>{{ tr('Nacimiento','Birth date') }}</th></tr></thead><tbody><tr v-for="member in group.members" :key="member.id"><td><router-link :to="`/members/${member.id}`">{{ member.name || tr('Sin nombre','Missing name')+' #'+member.id }}</router-link><small v-if="member.missing_fields?.length" class="member-missing">{{ tr('Datos faltantes','Missing data') }}: {{ member.missing_fields.map(missingLabel).join(', ') }}</small></td><td>{{ member.email }}</td><td>{{ member.phone }}</td><td><span class="member-dob-label">{{ tr('Nacimiento','Birth date') }}: </span>{{ member.dob || tr('Sin registrar','Not recorded') }}</td></tr></tbody></table></section></div>
  </section>
 </section>
</template>
<script setup>
import {ref,computed,onMounted} from 'vue'
import MemberFamilyDialog from './MemberFamilyDialog.vue'
import MemberExportDialog from './MemberExportDialog.vue'
import MemberBirthdays from './MemberBirthdays.vue'
import MemberImportReview from './MemberImportReview.vue'
import {useAuthStore} from '../stores/authStore'
import {useUiStore} from '../stores/uiStore'
import {membersApi} from '../services/membersApi'
import '../styles/clerk-management.css'
import '../styles/member-directory.css'
const auth=useAuthStore(),ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const exportOpen=ref(false),familyOpen=ref(false),groupFamilies=ref(false)
const members=ref([]),loading=ref(true),busy=ref(false),error=ref(''),notice=ref(''),search=ref(''),birthdays=ref(false),file=ref(null),preview=ref(null),fileInput=ref(null),drafts=ref([]),activeDraftId=ref(null)
const filtered=computed(()=>members.value.filter(m=>[m.name,m.email,m.phone].join(' ').toLocaleLowerCase().includes(search.value.toLocaleLowerCase())))
const memberGroups=computed(()=>{
 if(!groupFamilies.value)return [{id:'all',members:filtered.value}]
 const groups=new Map()
 for(const member of filtered.value){const id=member.family_id?String(member.family_id):'';if(!groups.has(id))groups.set(id,{id,label:id?tr('Familia','Family')+' · '+id:tr('Sin familia asignada','No family assigned'),members:[]});groups.get(id).members.push(member)}
 return [...groups.values()].sort((a,b)=>!a.id?1:!b.id?-1:a.members[0].name.localeCompare(b.members[0].name))
})
const message=e=>Object.entries(e.response?.data?.errors||{}).map(([key,values])=>`${key}: ${values.join(' ')}`).join('\n')||e.response?.data?.message||tr('No se pudo completar la operación.','Unable to complete the operation.')
const missingLabel=key=>({name:tr('nombre','name'),email:tr('correo','email'),phone:tr('teléfono','phone'),dob:tr('nacimiento','birth date')})[key]||key
async function loadDrafts(){if(auth.can('members.manage'))drafts.value=await membersApi.drafts()}
async function resume(id){error.value='';try{preview.value=await membersApi.draft(id);activeDraftId.value=id}catch(e){error.value=message(e)}}
async function draftSaved(id){activeDraftId.value=id;await loadDrafts()}
async function deleteDraft(d){
 if(!window.confirm(tr('¿Eliminar el borrador #','Delete draft #')+d.id+tr(' y sus filas pendientes? No se eliminarán miembros ya importados. Esta acción no se puede deshacer.',' and its pending rows? Already imported members will not be deleted. This cannot be undone.')))return
 busy.value=true;error.value=''
 try{await membersApi.deleteDraft(d.id);if(activeDraftId.value===d.id){preview.value=null;activeDraftId.value=null}await loadDrafts();notice.value=tr('Borrador eliminado.','Draft deleted.')}catch(e){error.value=message(e)}finally{busy.value=false}
}
async function load(){members.value=(await membersApi.list()).members;await loadDrafts()}
onMounted(async()=>{try{await load()}catch(e){error.value=message(e)}finally{loading.value=false}})
async function download(){busy.value=true;error.value='';try{const url=URL.createObjectURL(await membersApi.download());const link=document.createElement('a');link.href=url;link.download='members-directory.csv';link.click();setTimeout(()=>URL.revokeObjectURL(url),1000)}catch(e){error.value=message(e)}finally{busy.value=false}}
async function importFile(){busy.value=true;error.value='';notice.value='';try{preview.value=await membersApi.import(file.value,true,true);activeDraftId.value=null}catch(e){error.value=message(e)}finally{busy.value=false}}
async function imported(result){preview.value=result.remaining?result.review:null;file.value=null;fileInput.value.value='';notice.value=`${result.created} ${tr('miembros importados.','members imported.')} ${result.remaining} ${tr('filas guardadas para después.','rows saved for later.')}`;try{await load()}catch(e){error.value=message(e)}}
</script>
