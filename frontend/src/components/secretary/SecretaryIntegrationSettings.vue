<template>
 <section class="container py-4 management-page church-settings">
  <header class="management-heading"><div><p class="management-eyebrow">{{ settings?.church_name }}</p><h1>{{ tr('Configuración','Settings') }}</h1><p>{{ tr('Controla el asistente de tu iglesia y consulta sus límites asignados.','Control your church assistant and view its assigned limits.') }}</p></div></header>
  <router-link to="/whatsapp-tools" class="btn btn-outline-secondary mb-3">{{ tr('Guía y ejemplos de WhatsApp','WhatsApp guide and examples') }}</router-link>
  <p v-if="loading" role="status">{{ tr('Cargando…','Loading…') }}</p><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p v-if="saved" class="alert alert-success" role="status">{{ tr('Configuración guardada.','Settings saved.') }}</p>
  <template v-if="settings && !loading">
   <form class="management-surface p-3" @submit.prevent="save">
    <fieldset :disabled="saving">
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
 </section>
</template>
<script setup>
import {ref,reactive,computed,onMounted,onUnmounted} from 'vue'
import {apiClient} from '../../services/authApi'
import {useUiStore} from '../../stores/uiStore'
import '../../styles/clerk-management.css'
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const settings=ref(null),draft=reactive({whatsapp_enabled:false,ai_enabled:false}),loading=ref(false),saving=ref(false),error=ref(''),saved=ref(false),month=ref(new Date().toISOString().slice(0,7))
let version=0
const labels=computed(()=>({whatsapp_ai_limit:tr('IA WhatsApp / mes','WhatsApp AI / month'),whatsapp_replies_limit:tr('Respuestas / mes','Replies / month'),whatsapp_inbox_limit:tr('Mensajes recibidos / mes','Incoming messages / month'),whatsapp_daily_limit:tr('Por usuario / día / operación','Per user / day / operation'),birthday_ai_limit:tr('IA cumpleaños / mes','Birthday AI / month')}))
function apply(data){settings.value=data;Object.assign(draft,{whatsapp_enabled:data.whatsapp_enabled,ai_enabled:data.ai_enabled})}
async function load(){if(!month.value)return;const current=++version;loading.value=true;error.value='';try{const {data}=await apiClient.get('/secretary/integrations',{params:{month:month.value}});if(current===version)apply(data)}catch{if(current===version)error.value=tr('No se pudo cargar la configuración.','Unable to load settings.')}finally{if(current===version)loading.value=false}}
async function save(){saving.value=true;error.value='';saved.value=false;try{const {data}=await apiClient.put('/secretary/integrations',draft,{params:{month:month.value}});apply(data);saved.value=true}catch{error.value=tr('No se pudo guardar la configuración.','Unable to save settings.')}finally{saving.value=false}}
onMounted(load);onUnmounted(()=>version++)
</script>
<style scoped>
.church-settings h2{font-size:1rem}.church-settings p,.church-settings label{font-size:.8125rem}.setting-switch{display:flex;align-items:center;gap:9px;margin:16px 0 8px}.setting-switch input{width:18px;height:18px}.limits-grid,.usage-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}.limits-grid input{margin-top:6px;background:#f3f6f4}.usage-grid article{display:flex;flex-direction:column;padding:12px;border:1px solid #d3dfd7;border-radius:8px;font-size:.8125rem}.church-settings .btn{width:auto}@media(max-width:600px){.limits-grid,.usage-grid{grid-template-columns:1fr}}
</style>
