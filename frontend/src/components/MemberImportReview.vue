<template>
 <section class="import-review" aria-label="Revisión de importación">
  <h3>{{ tr('Revisar y corregir miembros','Review and correct members') }}</h3>
  <p>{{ tr('Importa las filas completas ahora. Para las demás, elige si autorizas los datos faltantes o prefieres completarlos después.','Import complete rows now. For the rest, choose whether to authorize missing information or complete it later.') }}</p>
  <div class="review-summary">
   <div><strong>{{ rows.length - invalidCount }}</strong><span>{{ tr('Completas','Complete') }}</span><small>{{ tr('Listas para importar.','Ready to import.') }}</small></div>
   <div><strong>{{ authorizedCount }}</strong><span>{{ tr('Autorizadas por ti','Authorized by you') }}</span><small>{{ tr('Se importarán con una marca de datos faltantes.','Will be imported with a missing-data label.') }}</small></div>
   <div><strong>{{ rows.length - eligibleCount }}</strong><span>{{ tr('Por completar','Needs attention') }}</span><small>{{ tr('Puedes corregirlas aquí o guardarlas para después.','Correct them here or save them for later.') }}</small></div>
  </div>
  <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
  <p v-if="savedAt" class="save-status" role="status">{{ savedCount }} {{ tr('filas guardadas en este borrador. Guarda de nuevo para conservar solo las pendientes.','rows saved in this draft. Save again to keep only pending rows.') }}</p>
  <p v-if="dirty" class="alert alert-warning" role="status">{{ tr('Revisa tus cambios antes de importar. Los conteos se actualizarán al validar.','Validate your changes before importing. Counts will update after validation.') }}</p>
  <div class="member-import-actions">
   <div><button type="button" class="btn btn-primary" :disabled="busy||dirty||!eligibleCount" @click="commit">{{ authorizedCount ? tr('Importar completas y autorizadas','Import complete and authorized rows') : tr('Importar solo completas','Import complete rows only') }} ({{ eligibleCount }})</button><small>{{ tr('Las demás quedan guardadas para completarlas después.','Other rows are saved for you to complete later.') }}</small></div>
   <div><button type="button" class="btn btn-outline-secondary" :disabled="busy" @click="validate">{{ tr('Guardar pendientes para después','Save pending rows for later') }}</button><small>{{ tr('No importa miembros. Guarda solo filas con errores o datos faltantes.','Does not import members. Saves only rows with errors or missing information.') }}</small></div>
  </div>
  <div class="review-toolbar"><label><input type="checkbox" v-model="onlyErrors" @change="page=1" /> {{ tr('Mostrar solo filas por revisar','Show only rows needing review') }}</label><button v-if="dirty" type="button" class="btn btn-outline-primary" :disabled="busy" @click="validate">{{ tr('Validar correcciones','Validate corrections') }}</button></div>
  <p class="authorization-help">{{ tr('¿Te falta información? Marca “Importar con datos faltantes” en cada fila que quieras autorizar. Los valores inválidos deben corregirse.','Missing information? Select “Import with missing information” on each row you want to authorize. Invalid values must be corrected.') }}</p>
  <fieldset :disabled="busy">
   <table class="review-table" data-dt="off"><thead><tr><th>{{ tr('Fila / nombre','Row / name') }}</th><th>{{ tr('Contacto','Contact') }}</th><th>{{ tr('Nacimiento','Birth date') }}</th><th>{{ tr('Otros datos','Other information') }}</th></tr></thead><tbody>
    <tr v-for="row in pageRows" :key="row.line" :class="{'invalid-row':Object.keys(row.errors).length}">
     <td><strong>{{ tr('Fila','Row') }} {{ row.line }}</strong><label v-if="row.can_authorize" class="row-authorization"><input type="checkbox" v-model="row.authorize_missing"  /> {{ tr('Importar con datos faltantes','Import with missing information') }}: {{ row.missing_fields.map(missingLabel).join(', ') }}</label><small v-else-if="Object.keys(row.errors).length" class="field-error">{{ tr('Corrige los valores inválidos; no se pueden autorizar.','Correct invalid values; they cannot be authorized.') }}</small><label v-for="key in ['FirstName','LastName']" :key="key">{{ label(key) }}<input v-model="row.values[key]" class="form-control" :class="{'is-invalid':row.errors[key]}" :aria-invalid="!!row.errors[key]" :aria-label="`${label(key)} · ${row.line}`" @input="changed(row)" /><small v-for="msg in row.errors[key]" :key="msg" class="field-error">{{ msg }}</small></label></td>
     <td><label v-for="key in ['Email','MobilePhone','FamilyPhone','WorkPhone']" :key="key">{{ label(key) }}<input v-model="row.values[key]" class="form-control" :class="{'is-invalid':row.errors[key]}" :aria-invalid="!!row.errors[key]" :aria-label="`${label(key)} · ${row.line}`" @input="changed(row)" /><small v-for="msg in row.errors[key]" :key="msg" class="field-error">{{ msg }}</small></label></td>
     <td><label>{{ label('BornOn') }}<input v-model="row.values.BornOn" class="form-control" :class="{'is-invalid':row.errors.BornOn}" :aria-invalid="!!row.errors.BornOn" :aria-label="`${label('BornOn')} · ${row.line}`" placeholder="YYYY-MM-DD" @input="changed(row)" /><small class="date-hint">YYYY-MM-DD / MM/DD/YYYY</small><small v-for="msg in row.errors.BornOn" :key="msg" class="field-error">{{ msg }}</small></label></td>
     <td><details :open="hasExtraErrors(row)"><summary>{{ tr('Ver / editar otros datos','View / edit other information') }}</summary><label v-for="key in extraHeaders" :key="key">{{ key }}<input v-model="row.values[key]" class="form-control" :class="{'is-invalid':row.errors[key]}" :aria-invalid="!!row.errors[key]" :aria-label="`${key} · ${row.line}`" @input="changed(row)" /><small v-for="msg in row.errors[key]" :key="msg" class="field-error">{{ msg }}</small></label></details></td>
    </tr>
   </tbody></table>
  </fieldset>
  <p v-if="!filtered.length">{{ tr('No hay filas con errores. Desmarca el filtro para ver todas.','No rows with errors. Clear the filter to see all rows.') }}</p>
  <nav class="review-toolbar" :aria-label="tr('Páginas de importación','Import pages')"><button class="btn btn-outline-secondary" :disabled="page<=1||busy" @click="page--">{{ tr('Anterior','Previous') }}</button><span>{{ page }} / {{ pages }}</span><button class="btn btn-outline-secondary" :disabled="page>=pages||busy" @click="page++">{{ tr('Siguiente','Next') }}</button></nav>
 </section>
</template>
<script setup>
import {ref,computed,watch} from 'vue'
import {membersApi} from '../services/membersApi'
import {useUiStore} from '../stores/uiStore'
const props=defineProps({review:{type:Object,required:true}}),emit=defineEmits(['imported','busy','saved'])
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const rows=ref([]),headers=ref([]),valid=ref(false),dirty=ref(false),busy=ref(false),error=ref(''),page=ref(1),onlyErrors=ref(false),draftId=ref(null),revision=ref(null),savedAt=ref(null),savedCount=ref(0)
const missingLabel=key=>({name:tr('nombre','name'),email:tr('correo','email'),phone:tr('teléfono','phone'),dob:tr('nacimiento','birth date')})[key]||key
const eligibleCount=computed(()=>rows.value.filter(r=>!Object.keys(r.errors).length||(r.can_authorize&&r.authorize_missing)).length)
const authorizedCount=computed(()=>rows.value.filter(r=>Object.keys(r.errors).length&&r.can_authorize&&r.authorize_missing).length)
const core=['FirstName','LastName','Email','MobilePhone','FamilyPhone','WorkPhone','BornOn']
const labels={FirstName:['Nombre *','Name *'],LastName:['Apellido','Surname'],Email:['Correo *','Email *'],MobilePhone:['Móvil','Mobile'],FamilyPhone:['Familiar','Family'],WorkPhone:['Trabajo','Work'],BornOn:['Nacimiento *','Birth date *']}
const label=key=>tr(...labels[key])
const extraHeaders=computed(()=>headers.value.filter(k=>!core.includes(k)))
const invalidCount=computed(()=>rows.value.filter(r=>Object.keys(r.errors).length).length)
const filtered=computed(()=>onlyErrors.value?rows.value.filter(r=>Object.keys(r.errors).length):rows.value)
const pages=computed(()=>Math.max(1,Math.ceil(filtered.value.length/10)))
const pageRows=computed(()=>filtered.value.slice((page.value-1)*10,page.value*10))
const hasExtraErrors=row=>extraHeaders.value.some(key=>row.errors[key])
function apply(data){draftId.value=data.draft_id??null;revision.value=data.revision??null;savedAt.value=data.updated_at??null;savedCount.value=data.saved_count??data.count??0;rows.value=data.rows;headers.value=data.headers;valid.value=data.valid;dirty.value=false;page.value=Math.min(page.value,pages.value)}
watch(()=>props.review,data=>{page.value=1;onlyErrors.value=false;apply(JSON.parse(JSON.stringify(data)))},{immediate:true})
function changed(row){row.authorize_missing=false;dirty.value=true;valid.value=false}
const message=e=>Object.entries(e.response?.data?.errors||{}).map(([k,v])=>`${k}: ${v.join(' ')}`).join('\n')||tr('No se pudo completar la operación. Tus cambios siguen en la tabla.','Unable to complete the operation. Your edits remain in the table.')
function working(value){busy.value=value;emit('busy',value)}
async function persist(pendingOnly=true){const data=await membersApi.saveDraft(draftId.value,{revision:revision.value,pending_only:pendingOnly,rows:rows.value.map(r=>({line:r.line,values:r.values,authorize_missing:r.authorize_missing||false}))});apply(data);emit('saved',draftId.value)}
async function validate(){working(true);error.value='';try{await persist()}catch(e){error.value=message(e);valid.value=false;dirty.value=true}finally{working(false)}}
async function commit(){if(dirty.value||!eligibleCount.value)return;working(true);error.value='';try{await persist(false);const result=await membersApi.commitDraft(draftId.value,revision.value);apply(result.review);emit('imported',result)}catch(e){error.value=message(e);dirty.value=true}finally{working(false)}}
</script>
<style scoped>
.review-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:18px 0}.review-summary>div{padding:14px;border:1px solid #dce5df;border-radius:10px;background:#f7faf8;display:grid;gap:4px}.review-summary strong{font-size:1.25rem;color:#215c4e}.review-summary span{font-weight:600}.review-summary small,.member-import-actions small{color:#52645a;line-height:1.5}.member-import-actions{display:flex;gap:20px;flex-wrap:wrap;margin:20px 0}.member-import-actions>div{display:flex;flex-direction:column;align-items:flex-start;gap:7px;flex:1 1 260px}.member-import-actions button{white-space:normal;text-align:left}.authorization-help{font-size:.8125rem;color:#52645a}.save-status{padding:10px 12px;border-left:3px solid #28705b;background:#f1f8f4;font-size:.8125rem}@media(max-width:600px){.review-summary{grid-template-columns:1fr;gap:8px}.review-summary>div{grid-template-columns:36px 1fr;padding:10px}.review-summary small{grid-column:2}.member-import-actions{gap:14px}.member-import-actions>div{flex-basis:100%}.member-import-actions button{width:100%}}

.import-review{margin-top:24px;border-top:1px solid #dce5df;padding-top:20px;min-width:0}.review-toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:16px 0}.review-toolbar label{display:flex;align-items:center;gap:8px}.review-table{width:100%;table-layout:fixed;font-size:.8125rem}.review-table th{font-weight:500;text-align:left;padding:10px}.review-table td{vertical-align:top;padding:12px 10px;border-top:1px solid #dce5df;overflow-wrap:anywhere}.review-table label{display:block;margin:8px 0}.review-table input{font-size:.8125rem;min-width:0;width:100%}.review-table summary{cursor:pointer}.invalid-row{background:#fffaf6}.row-authorization{border:1px solid #d5a349;padding:8px;border-radius:6px;background:#fff6df}.row-authorization input[type="checkbox"]{width:16px;height:16px;display:inline-block;margin-right:6px}.field-error{display:block;color:#a42b27;margin-top:4px}.date-hint{font-size:.65rem;color:#52645a}.alert{white-space:pre-wrap;overflow-wrap:anywhere}fieldset{min-width:0}@media(max-width:850px){.review-table thead{display:none}.review-table tr{display:block;border:1px solid #dce5df;border-radius:8px;margin:12px 0;padding:10px}.review-table td{display:block;border:0;padding:0 4px}.review-table{display:block}.review-table tbody{display:block}.review-toolbar .btn{max-width:100%}}
</style>
