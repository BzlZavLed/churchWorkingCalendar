<template>
 <section class="container py-4 management-page church-settings">
  <header class="management-heading"><div><p class="management-eyebrow">{{ settings?.church_name }}</p><h1>{{ tr('Configuración','Settings') }}</h1><p>{{ tr('Administra los datos de tu iglesia, el asistente y sus límites asignados.','Manage church information, the assistant and its assigned limits.') }}</p></div></header>
  <router-link to="/whatsapp-tools" class="btn btn-outline-secondary mb-3">{{ tr('Guía y ejemplos de WhatsApp','WhatsApp guide and examples') }}</router-link>
  <p v-if="loading" role="status">{{ tr('Cargando…','Loading…') }}</p><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p v-if="saved" class="alert alert-success" role="status">{{ tr('Configuración guardada.','Settings saved.') }}</p>
  <template v-if="settings && !loading">
   <section class="management-surface p-3 mb-3">
    <h2>{{ tr('Logo de la iglesia','Church logo') }}</h2>
    <p>{{ tr('Se usa en los PDF generados, los códigos QR y los formularios públicos. PNG, JPG o WebP; máximo 2 MB.','Used in generated PDFs, QR posters and public forms. PNG, JPG or WebP; up to 2 MB.') }}</p>
    <img v-if="settings.logo_url" :src="settings.logo_url" alt="Logo" style="display:block;max-width:200px;max-height:100px;object-fit:contain;margin-bottom:14px" />
    <label>{{ tr('Seleccionar logo','Choose logo') }}<input type="file" accept="image/png,image/jpeg,image/webp" class="form-control" :disabled="saving" @change="uploadLogo" /></label>
    <button v-if="settings.logo_url" type="button" class="btn btn-outline-primary ms-2" :disabled="saving" @click="prepareTransparentLogo">{{ tr('Quitar fondo blanco','Remove white background') }}</button>
    <p v-if="logoNotice" role="status">{{ logoNotice }}</p>
    <section v-if="transparentPreview" class="logo-background-preview">
     <h3>{{ tr('Revisa antes de aplicar','Review before applying') }}</h3>
     <p>{{ tr('Solo se elimina el blanco conectado a los bordes. Los detalles blancos que toquen el borde también pueden desaparecer. El original se conserva hasta aplicar.','Only white connected to the edges is removed. White details touching the edge may also disappear. The original is retained until you apply.') }}</p>
     <div class="logo-comparison"><figure><figcaption>{{ tr('Original','Original') }}</figcaption><div><img :src="settings.logo_url" alt="Original" /></div></figure><figure><figcaption>{{ tr('Sin fondo blanco','Without white background') }}</figcaption><div><img :src="transparentPreview" :alt="tr('Vista previa','Preview')" /></div></figure></div>
     <button type="button" class="btn btn-primary me-2" :disabled="saving" @click="applyTransparentLogo">{{ tr('Aplicar resultado','Apply result') }}</button><button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="clearTransparentPreview">{{ tr('Conservar original','Keep original') }}</button>
    </section>
    <button v-if="settings.logo_url" type="button" class="btn btn-outline-danger ms-2" :disabled="saving" @click="removeLogo">{{ tr('Quitar logo','Remove logo') }}</button>
   </section>
   <form class="management-surface p-3"  @submit.prevent="save">
    <fieldset :disabled="saving">
     <h2>{{ tr('Datos de la iglesia','Church information') }}</h2>
     <label class="d-block mb-2">{{ tr('Iglesia','Church') }}<input class="form-control" :value="settings.church_name" readonly /></label>
     <label class="d-block mb-2">{{ tr('Código de organización','Organization code') }}<input v-model="draft.organization_code" class="form-control" type="text" maxlength="100" /></label>
     <p>{{ tr('Este código y el nombre de la iglesia se asignan automáticamente a los miembros y no se editan en sus fichas.','This code and the church name are assigned automatically to members and cannot be edited in their records.') }}</p>
     <label class="d-block mb-2">{{ tr('Formato de fecha de nacimiento','Birth date format') }}
      <select v-model="draft.member_date_format" class="form-select">
       <option value="mdy">{{ tr('Mes / día / año','Month / day / year') }} (12/11/1998)</option>
       <option value="dmy">{{ tr('Día / mes / año','Day / month / year') }} (11/12/1998)</option>
       <option value="ydm">{{ tr('Año / día / mes (CSV)','Year / day / month (CSV)') }} (1998-11-12)</option>
      </select>
     </label>
     <p>{{ tr('Elige el orden de las fechas del CSV. Año / día / mes convierte 1998-11-12 en 11 de diciembre de 1998. Los registros ya guardados conservan su fecha.','Choose the date order in your CSV. Year / day / month reads 1998-11-12 as December 11, 1998. Saved records keep their dates.') }}</p>
     <h2>{{ tr('Funciones de la iglesia','Church features') }}</h2>
     <label class="setting-switch"><input type="checkbox" v-model="draft.whatsapp_enabled" /> WhatsApp</label>
     <p>{{ tr('Permite o detiene los mensajes del asistente para todos los usuarios de esta iglesia. Desactivarlo descarta el trabajo pendiente.','Allow or stop assistant messages for every user in this church. Disabling discards pending work.') }}</p>
     <p v-if="!settings.admin_whatsapp_enabled || !settings.global_enabled || !settings.pilot" class="alert alert-warning">{{ tr('WhatsApp está bloqueado por la configuración del superadmin o del servidor. Activarlo aquí no puede quitar ese bloqueo.','WhatsApp is blocked by superadmin or server configuration. Enabling it here cannot remove that block.') }}</p>
     <label class="setting-switch"><input type="checkbox" v-model="draft.ai_enabled" />{{ tr('Inteligencia artificial','Artificial intelligence') }}</label>
     <p>{{ tr('Incluye solicitudes de lenguaje natural por WhatsApp y mensajes de cumpleaños con IA. Los comandos sin IA siguen disponibles si WhatsApp está activo.','Includes natural-language WhatsApp requests and AI birthday messages. Non-AI commands remain available when WhatsApp is enabled.') }}</p>
     <p v-if="!settings.admin_ai_enabled" class="alert alert-warning">{{ tr('La IA está desactivada por el superadmin.','AI is disabled by the superadmin.') }}</p>
     <button class="btn btn-primary" :disabled="saving">{{ saving?tr('Guardando…','Saving…'):tr('Guardar','Save') }}</button>
    </fieldset>
   </form>
   <section class="management-surface p-3 mt-3">
    <h2>{{ tr('Límites asignados','Assigned limits') }}</h2><p>{{ tr('Estos valores están prellenados con los límites actuales. Solo el superadmin puede modificarlos. Son cantidades de solicitudes, no importes de dinero.','These fields show current assigned limits. Only superadmin can change them. They count requests, not money.') }}</p>
    <div class="limits-grid"><label v-for="(label,key) in labels" :key="key">{{ label }}<input type="number" class="form-control" :value="settings.limits[key]" readonly /></label></div>
   </section>
   <section class="management-surface p-3 mt-3">
    <h2>{{ tr('Uso de esta iglesia','This church’s usage') }}</h2><label>{{ tr('Mes (UTC)','Month (UTC)') }}<input type="month" v-model="month" class="form-control" @change="load" /></label>
    <div class="usage-grid"><article v-for="kind in ['ai','replies','inbox']" :key="kind"><span>{{ labels['whatsapp_'+kind+'_limit'] }}</span><strong>{{ settings.usage[kind].used }} / {{ settings.limits['whatsapp_'+kind+'_limit'] }}</strong></article><article><span>{{ labels.birthday_ai_limit }}</span><strong>{{ settings.usage.birthdays.used }} / {{ settings.limits.birthday_ai_limit }}</strong></article></div>
    <p>{{ tr('Números vinculados','Linked numbers') }}: {{ settings.linked }} · {{ tr('Pendientes de activación','Awaiting activation') }}: {{ settings.pending }}</p>
    <p>{{ tr('Los límites mostrados son los actuales. Los totales de facturación de las cuentas compartidas solo están disponibles para superadmin.','Shown limits are current. Shared-account billing totals are available only to superadmin.') }}</p>
   </section>
  </template>
  <WhatsAppDiagnostics />
  <WhatsAppHistory church-wide />
 </section>
</template>
<script setup>
import {removeEdgeWhite} from '../../utils/removeWhiteBackground'
import WhatsAppDiagnostics from '../WhatsAppDiagnostics.vue'
import WhatsAppHistory from '../WhatsAppHistory.vue'
import {ref,reactive,computed,onMounted,onUnmounted} from 'vue'
import {apiClient} from '../../services/authApi'
import {useUiStore} from '../../stores/uiStore'
import '../../styles/clerk-management.css'
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const settings=ref(null),draft=reactive({organization_code:'',member_date_format:'mdy',whatsapp_enabled:false,ai_enabled:false}),loading=ref(false),saving=ref(false),error=ref(''),saved=ref(false),month=ref(new Date().toISOString().slice(0,7))
let version=0
const labels=computed(()=>({whatsapp_ai_limit:tr('IA WhatsApp / mes','WhatsApp AI / month'),whatsapp_replies_limit:tr('Respuestas / mes','Replies / month'),whatsapp_inbox_limit:tr('Mensajes recibidos / mes','Incoming messages / month'),whatsapp_daily_limit:tr('Por usuario / día / operación','Per user / day / operation'),birthday_ai_limit:tr('IA cumpleaños / mes','Birthday AI / month')}))
function apply(data){
 const limitKeys=Object.keys(labels.value)
 const valid=data && typeof data==='object' && limitKeys.every(key=>Number.isFinite(data.limits?.[key])) && ['ai','replies','inbox','birthdays'].every(key=>Number.isFinite(data.usage?.[key]?.used)) && typeof data.whatsapp_enabled==='boolean' && typeof data.ai_enabled==='boolean'
 if(!valid){settings.value=null;throw new Error('Invalid integration settings response')}
 settings.value=data;Object.assign(draft,{organization_code:data.organization_code||'',member_date_format:data.member_date_format||'mdy',whatsapp_enabled:data.whatsapp_enabled,ai_enabled:data.ai_enabled})
}
async function load(){if(!month.value)return;const current=++version;loading.value=true;error.value='';try{const {data}=await apiClient.get('/secretary/integrations',{params:{month:month.value}});if(current===version)apply(data)}catch{if(current===version)error.value=tr('No se pudo cargar la configuración. Verifica que el servidor esté actualizado y que se haya limpiado la caché de rutas.','Unable to load settings. Verify that the server is updated and its route cache has been cleared.')}finally{if(current===version)loading.value=false}}
async function save(){saving.value=true;error.value='';saved.value=false;try{const {data}=await apiClient.put('/secretary/integrations',draft,{params:{month:month.value}});apply(data);saved.value=true}catch{error.value=tr('No se pudo guardar la configuración.','Unable to save settings.')}finally{saving.value=false}}
const logoNotice=ref('')
const transparentPreview=ref(null)
let transparentBlob=null
function clearTransparentPreview(){if(transparentPreview.value)URL.revokeObjectURL(transparentPreview.value);transparentPreview.value=null;transparentBlob=null}
onUnmounted(clearTransparentPreview)
async function prepareTransparentLogo(){
  saving.value=true;error.value='';logoNotice.value='';clearTransparentPreview()
  let bitmap
  try{
    const response=await fetch(settings.value.logo_url)
    if(!response.ok)throw new Error('load')
    bitmap=await createImageBitmap(await response.blob())
    if(bitmap.width>4096 || bitmap.height>4096)throw new Error('size')
    const canvas=document.createElement('canvas');canvas.width=bitmap.width;canvas.height=bitmap.height
    const context=canvas.getContext('2d');context.drawImage(bitmap,0,0)
    const image=context.getImageData(0,0,canvas.width,canvas.height)
    const removed=removeEdgeWhite(image.data,canvas.width,canvas.height)
    if(!removed){logoNotice.value=tr('No se encontró fondo blanco conectado a los bordes. Se conserva el original.','No white background connected to the edges was found. The original is retained.');return}
    if(!image.data.some((value,index)=>index%4===3 && value>0)){error.value=tr('El resultado quedaría vacío. Se conserva el original.','The result would be empty. The original is retained.');return}
    context.putImageData(image,0,0)
    const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'))
    if(!blob || blob.size>2*1024*1024){error.value=tr('El PNG resultante supera el límite de 2 MB. Usa una imagen más pequeña.','The resulting PNG exceeds the 2 MB limit. Use a smaller image.');return}
    transparentBlob=blob;transparentPreview.value=URL.createObjectURL(blob)
  }catch{error.value=tr('No se pudo procesar el logo. Se conserva el original.','Unable to process the logo. The original is retained.')}
  finally{bitmap?.close();saving.value=false}
}
async function applyTransparentLogo(){
 if(!transparentBlob)return
 saving.value=true;error.value=''
 try{const body=new FormData();body.append('logo',new File([transparentBlob],'church-logo.png',{type:'image/png'}));const {data}=await apiClient.post('/secretary/church-logo',body);settings.value.logo_url=data.logo_url;clearTransparentPreview();logoNotice.value=tr('Logo sin fondo guardado.','Logo without background saved.')}catch{error.value=tr('No se pudo guardar el logo.','Unable to save logo.')}finally{saving.value=false}
}
async function uploadLogo(event){const file=event.target.files?.[0];if(!file)return;saving.value=true;error.value='';try{const body=new FormData();body.append('logo',file);const {data}=await apiClient.post('/secretary/church-logo',body);settings.value.logo_url=data.logo_url;clearTransparentPreview();logoNotice.value=''}catch(e){error.value=Object.values(e.response?.data?.errors||{}).flat().join(' ')||tr('No se pudo cargar el logo.','Unable to upload logo.')}finally{saving.value=false;event.target.value=''}}
async function removeLogo(){if(!window.confirm(tr('¿Quitar el logo de la iglesia?','Remove the church logo?')))return;saving.value=true;try{await apiClient.delete('/secretary/church-logo');settings.value.logo_url=null;clearTransparentPreview();logoNotice.value=''}catch{error.value=tr('No se pudo quitar el logo.','Unable to remove logo.')}finally{saving.value=false}}
onMounted(load);onUnmounted(()=>version++)
</script>
<style scoped>
.logo-background-preview{padding:16px;margin:16px 0;border:1px solid #9aaea1;border-radius:8px}.logo-background-preview h3{font-size:1rem}.logo-comparison{display:grid;grid-template-columns:1fr 1fr;gap:16px}.logo-comparison figure{min-width:0}.logo-comparison figure>div{padding:16px;text-align:center;background:repeating-conic-gradient(#e0e5e1 0% 25%,#fff 0% 50%) 0/16px 16px}.logo-comparison img{max-width:100%;max-height:140px;object-fit:contain}@media(max-width:500px){.logo-comparison{grid-template-columns:1fr}}

.church-settings h2{font-size:1rem}.church-settings p,.church-settings label{font-size:.8125rem}.setting-switch{display:flex;align-items:center;gap:9px;margin:16px 0 8px}.setting-switch input{width:18px;height:18px}.limits-grid,.usage-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}.limits-grid input{margin-top:6px;background:#f3f6f4}.usage-grid article{display:flex;flex-direction:column;padding:12px;border:1px solid #d3dfd7;border-radius:8px;font-size:.8125rem}.church-settings .btn{width:auto}@media(max-width:600px){.limits-grid,.usage-grid{grid-template-columns:1fr}}
</style>
