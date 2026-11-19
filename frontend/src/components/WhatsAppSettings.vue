<template>
 <section class="container py-4 management-page">
  <header class="management-heading"><h1>{{ tr('Asistente WhatsApp','WhatsApp assistant') }}</h1><p>{{ tr('Activa o desactiva el asistente para tu propia cuenta.','Enable or disable the assistant for your own account.') }}</p></header>
  <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
  <p v-if="loading" role="status">{{ tr('Cargando…','Loading…') }}</p>
  <section v-else class="management-surface p-3">
   <p>{{ tr('Vincula tu WhatsApp para preparar eventos y consultar visitas según tus permisos. Las consultas de IA y respuestas están sujetas a límites de uso.','Link WhatsApp to prepare events and query visitors according to your permissions. AI queries and replies are subject to usage limits.') }}</p>
   <label class="d-flex align-items-start gap-2"><input type="checkbox" :checked="enabled" :disabled="saving" @change="save($event.target.checked)" /><span>{{ tr('Permitir el asistente WhatsApp en mi cuenta','Allow the WhatsApp assistant for my account') }}</span></label>
   <p class="small mt-3">{{ tr('Desactivado de forma predeterminada. El interruptor general y tus permisos siguen aplicando. Antes de usarlo será necesario verificar y vincular tu número.','Off by default. The system switch and your permissions still apply. Your number must be verified and linked before use.') }}</p>
   <p class="mt-3" role="status">{{ systemEnabled ? tr('Piloto habilitado.','Pilot enabled.') : tr('El piloto está desactivado por el administrador. No se procesarán mensajes.','The pilot is disabled by the administrator. Messages will not be processed.') }}</p>
   <p v-if="blockReason" class="alert alert-info" role="status">{{ reasonText(blockReason) }}</p>
   <p>{{ linked ? tr('Número vinculado','Number linked') : tr('Sin número vinculado','No linked number') }}</p>
   <button v-if="linked" class="btn btn-outline-secondary" :disabled="saving" @click="unlink">{{ tr('Desvincular número','Unlink number') }}</button>
   <button v-else class="btn btn-primary" :disabled="saving || !enabled || !systemEnabled || blockReason" @click="link">{{ tr('Generar código de vinculación','Generate linking code') }}</button>
   <div v-if="command" class="alert alert-info mt-3"><p>{{ tr('Desde tu WhatsApp, envía este texto al número del piloto. Vence en diez minutos. No lo compartas.','From your WhatsApp, send this text to the pilot number. It expires in ten minutes. Do not share it.') }}</p><code class="text-break">{{ command }}</code></div>
   <p class="small mt-3">{{ tr('CONFIRMAR envía el evento a revisión. EXTENDER renueva la reserva. BAJA desactiva el asistente sin llamadas de IA.','CONFIRMAR submits the event for review. EXTENDER renews the reservation. BAJA disables the assistant without an AI call.') }}</p>
   <p v-if="notice" class="text-success" role="status">{{ notice }}</p>
  </section>
 </section>
</template>
<script setup>
import {ref,onMounted} from 'vue'
import {apiClient} from '../services/authApi'
import {useUiStore} from '../stores/uiStore'
import '../styles/clerk-management.css'
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const blockReason=ref(null)
const reasonText = reason => {
 const messages = {
  account_without_church: ['Esta es tu cuenta personal de superadministrador, sin iglesia asignada. La vista de trabajo no cambia la cuenta vinculada a WhatsApp. Inicia sesión con la cuenta del pastor o del departamento de la iglesia piloto.', 'This is your personal superadmin account, with no assigned church. Working context does not change the account linked to WhatsApp. Sign in with the pilot church’s pastor or department account.'],
  pilot_not_configured: ['Falta configurar la iglesia piloto en el servidor (WHATSAPP_CHURCH_ID).', 'The pilot church is not configured on this server (WHATSAPP_CHURCH_ID).'],
  church_not_in_pilot: ['Tu cuenta pertenece a una iglesia que no participa en este piloto.', 'Your account belongs to a church outside this pilot.'],
  opt_in_required: ['Activa la casilla para vincular tu número.', 'Enable the checkbox to link your number.'],
  system_disabled: ['El piloto está desactivado. El administrador debe habilitarlo antes de vincular números.', 'The pilot is disabled. An administrator must enable it before numbers can be linked.'],
  rate_limited: ['Demasiados intentos. Espera un minuto y vuelve a intentarlo.', 'Too many attempts. Wait a minute and try again.']
 };
 return messages[reason] ? tr(...messages[reason]) : tr('No se pudo vincular. Inténtalo de nuevo.', 'Unable to link. Try again.')
}
const linked=ref(false),systemEnabled=ref(false),command=ref('')
function apply(data){blockReason.value=data.link_block_reason;enabled.value=data.enabled;linked.value=data.linked;systemEnabled.value=data.system_enabled}
const loading=ref(true),saving=ref(false),enabled=ref(false),error=ref(''),notice=ref('')
onMounted(async()=>{try{const {data}=await apiClient.get('/auth/whatsapp-preferences');apply(data)}catch{error.value=tr('No se pudo cargar la preferencia.','Unable to load preference.')}finally{loading.value=false}})
async function save(value){saving.value=true;error.value='';notice.value='';try{const {data}=await apiClient.patch('/auth/whatsapp-preferences',{enabled:value});apply(data);command.value='';notice.value=tr('Preferencia guardada.','Preference saved.')}catch{error.value=tr('No se pudo guardar.','Unable to save.')}finally{saving.value=false}}
async function link(){saving.value=true;error.value='';try{const {data}=await apiClient.post('/auth/whatsapp-link');command.value=data.command}catch(e){const reason=e.response?.status===429?'rate_limited':e.response?.data?.reason;error.value=reasonText(reason);if(e.response?.data?.reason)blockReason.value=reason}finally{saving.value=false}}
async function unlink(){saving.value=true;try{const {data}=await apiClient.delete('/auth/whatsapp-link');apply(data);command.value=''}catch{error.value=tr('No se pudo desvincular.','Unable to unlink.')}finally{saving.value=false}}
</script>
<style scoped>input[type=checkbox]{flex:none;width:18px;height:18px;margin:2px 0 0}</style>
