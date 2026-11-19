<template>
 <section class="container py-4 management-page">
  <header class="management-heading"><h1>{{ tr('Asistente WhatsApp','WhatsApp assistant') }}</h1><p>{{ tr('Activa o desactiva el asistente para tu propia cuenta.','Enable or disable the assistant for your own account.') }}</p></header>
  <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
  <p v-if="loading" role="status">{{ tr('Cargando…','Loading…') }}</p>
  <section v-else class="management-surface p-3">
   <div class="wa-status"><span class="wa-status-dot" :class="{ 'wa-status-dot--ready': ready }"></span><strong>{{ ready ? tr('Listo para usar','Ready to use') : linked ? tr('Número vinculado · asistente inactivo','Number linked · assistant inactive') : tr('Pendiente de vinculación','Awaiting connection') }}</strong></div>
   <label class="d-flex align-items-start gap-2 mt-3"><input type="checkbox" :checked="enabled" :disabled="saving" @change="save($event.target.checked)" /><span>{{ tr('Permitir el asistente WhatsApp en mi cuenta','Allow the WhatsApp assistant for my account') }}</span></label>
   <p v-if="blockReason" class="alert alert-info mt-3" role="status">{{ reasonText(blockReason) }}</p>
   <section v-if="registeredNumber && !linked" class="wa-connect">
    <h2>{{ tr('Activa tu número registrado','Activate your registered number') }}</h2>
    <p>{{ tr('Al crear tu cuenta se registró este WhatsApp:','This WhatsApp was registered when your account was created:') }} <strong>{{ registeredNumber }}</strong>.</p>
    <p>{{ tr('Desde ese número, envía','From that number, send') }} <strong>Hola</strong> {{ tr('al WhatsApp de la iglesia','to the church WhatsApp') }}<template v-if="destinationNumber">: <strong>{{ destinationNumber }}</strong></template>.</p>
    <p>{{ tr('No necesitas generar ni copiar un código. Después de recibir la respuesta, actualiza el estado aquí. Si el número registrado es incorrecto, pide al encargado de usuarios que lo cambie.','No code is needed. After receiving the reply, refresh your status here. If your registered number is incorrect, ask your user administrator to change it.') }}</p>
    <a v-if="destinationNumber && !blockReason" class="btn btn-primary" :href="whatsappLink('Hola')" target="_blank" rel="noopener noreferrer">{{ tr('Enviar Hola por WhatsApp','Send Hola via WhatsApp') }}</a>
   </section>
   <section v-else-if="!linked" class="wa-connect">
    <h2>{{ tr('Conecta tu WhatsApp','Connect your WhatsApp') }}</h2>
    <p>{{ tr('No tienes un número registrado. Genera un código y envíalo desde tu WhatsApp','No number is registered. Generate a code and send it from your WhatsApp') }}<template v-if="destinationNumber"> {{ tr('al','to') }} <strong>{{ destinationNumber }}</strong></template>.</p>
    <button class="btn btn-primary" :disabled="saving || !enabled || !systemEnabled || !!blockReason" @click="link">{{ tr('Generar código de vinculación','Generate linking code') }}</button>
    <div v-if="command" class="alert alert-info mt-3"><p>{{ tr('Envía este texto completo. Vence en diez minutos; no lo compartas.','Send this complete text. It expires in ten minutes; do not share it.') }}</p><code class="text-break">{{ command }}</code><a v-if="destinationNumber" class="btn btn-outline-secondary mt-3" :href="whatsappLink(command)" target="_blank" rel="noopener noreferrer">{{ tr('Abrir WhatsApp','Open WhatsApp') }}</a></div>
   </section>
   <p v-else class="mt-3">{{ tr('Puedes escribir al asistente según los permisos de tu cuenta.','You can message the assistant according to your account permissions.') }} <strong v-if="destinationNumber">{{ destinationNumber }}</strong></p>
   <p v-if="!destinationNumber" class="alert alert-warning mt-3">{{ tr('El administrador debe configurar el número de WhatsApp de la iglesia para mostrar aquí el destino.','The administrator must configure the church WhatsApp number so the destination can be shown here.') }}</p>
   <div class="wa-actions"><button class="btn btn-outline-secondary" :disabled="saving" @click="refreshStatus">{{ tr('Actualizar estado','Refresh status') }}</button><button v-if="linked" class="btn btn-outline-secondary" :disabled="saving" @click="unlink">{{ tr('Desvincular número','Unlink number') }}</button></div>
   <details class="mt-3"><summary>{{ tr('Qué puedes hacer por WhatsApp','What you can do via WhatsApp') }}</summary><p class="small mt-2">{{ tr('Prepara eventos o consulta visitas según tus permisos. CONFIRMAR envía un evento a revisión; EXTENDER renueva la reserva; BAJA desactiva el asistente. El uso está sujeto a los límites de la iglesia.','Prepare events or query visitors according to your permissions. CONFIRMAR submits an event for review; EXTENDER renews its reservation; BAJA disables the assistant. Church usage limits apply.') }}</p></details>
   <p v-if="notice" class="text-success" role="status">{{ notice }}</p>
  </section>
  <router-link v-if="auth.isSecretary || auth.isPlatformAdmin" to="/whatsapp-tools" class="btn btn-outline-secondary mt-3">{{ tr('Guía y ejemplos de WhatsApp','WhatsApp guide and examples') }}</router-link>
  <WhatsAppHistory :church-wide="auth.isSecretary" />
</section>
</template>
<script setup>
import {useAuthStore} from '../stores/authStore'
import WhatsAppHistory from './WhatsAppHistory.vue'
import {ref,onMounted} from 'vue'
import {apiClient} from '../services/authApi'
import {useUiStore} from '../stores/uiStore'
import '../styles/clerk-management.css'
const auth=useAuthStore()
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const blockReason=ref(null)
const reasonText = reason => {
 const messages = {
  church_disabled: ['WhatsApp está desactivado para tu iglesia por la administración.', 'WhatsApp is disabled for your church by church administration.'],
  account_without_church: ['Esta es tu cuenta personal de superadministrador, sin iglesia asignada. La vista de trabajo no cambia la cuenta vinculada a WhatsApp. Inicia sesión con la cuenta del pastor o del departamento de la iglesia piloto.', 'This is your personal superadmin account, with no assigned church. Working context does not change the account linked to WhatsApp. Sign in with the pilot church’s pastor or department account.'],
  pilot_not_configured: ['Falta configurar la iglesia piloto en el servidor (WHATSAPP_CHURCH_ID).', 'The pilot church is not configured on this server (WHATSAPP_CHURCH_ID).'],
  church_not_in_pilot: ['Tu cuenta pertenece a una iglesia que no participa en este piloto.', 'Your account belongs to a church outside this pilot.'],
  opt_in_required: ['Activa la casilla para vincular tu número.', 'Enable the checkbox to link your number.'],
  system_disabled: ['El piloto está desactivado. El administrador debe habilitarlo antes de vincular números.', 'The pilot is disabled. An administrator must enable it before numbers can be linked.'],
  rate_limited: ['Demasiados intentos. Espera un minuto y vuelve a intentarlo.', 'Too many attempts. Wait a minute and try again.']
 };
 return messages[reason] ? tr(...messages[reason]) : tr('No se pudo vincular. Inténtalo de nuevo.', 'Unable to link. Try again.')
}
const registeredNumber=ref(null),destinationNumber=ref(null),ready=ref(false)
const whatsappLink=text=>`https://wa.me/${destinationNumber.value.replace(/\D/g,'')}?text=${encodeURIComponent(text)}`
const linked=ref(false),systemEnabled=ref(false),command=ref('')
function apply(data){destinationNumber.value=data.destination_number;ready.value=data.ready;if(data.linked)command.value='';registeredNumber.value=data.registered_number;blockReason.value=data.link_block_reason;enabled.value=data.enabled;linked.value=data.linked;systemEnabled.value=data.system_enabled}
const loading=ref(true),saving=ref(false),enabled=ref(false),error=ref(''),notice=ref('')
onMounted(async()=>{try{const {data}=await apiClient.get('/auth/whatsapp-preferences');apply(data)}catch{error.value=tr('No se pudo cargar la preferencia.','Unable to load preference.')}finally{loading.value=false}})
async function refreshStatus(){saving.value=true;error.value='';try{const {data}=await apiClient.get('/auth/whatsapp-preferences');apply(data)}catch{error.value=tr('No se pudo actualizar el estado.','Unable to refresh status.')}finally{saving.value=false}}
async function save(value){saving.value=true;error.value='';notice.value='';try{const {data}=await apiClient.patch('/auth/whatsapp-preferences',{enabled:value});apply(data);command.value='';notice.value=tr('Preferencia guardada.','Preference saved.')}catch{error.value=tr('No se pudo guardar.','Unable to save.')}finally{saving.value=false}}
async function link(){saving.value=true;error.value='';try{const {data}=await apiClient.post('/auth/whatsapp-link');command.value=data.command}catch(e){const reason=e.response?.status===429?'rate_limited':e.response?.data?.reason;error.value=reasonText(reason);if(e.response?.data?.reason)blockReason.value=reason}finally{saving.value=false}}
async function unlink(){saving.value=true;try{const {data}=await apiClient.delete('/auth/whatsapp-link');apply(data);command.value=''}catch{error.value=tr('No se pudo desvincular.','Unable to unlink.')}finally{saving.value=false}}
</script>
<style scoped>input[type=checkbox]{flex:none;width:18px;height:18px;margin:2px 0 0}
.wa-status{display:flex;align-items:center;gap:9px}.wa-status-dot{width:9px;height:9px;border-radius:50%;background:#a87522}.wa-status-dot--ready{background:#22856b}
.wa-connect{margin:18px 0;padding:18px;border:1px solid #cbded5;border-radius:12px;background:#f2f7f4}.wa-connect h2{font-size:1rem}.wa-connect p{font-size:.875rem}.wa-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.wa-connect .btn{display:inline-block;width:auto}.wa-actions .btn{width:auto}
</style>
