<template>
 <section class="management-surface wa-diagnostics">
  <header><div><h2>{{ tr('Diagnóstico de WhatsApp','WhatsApp diagnostics') }}</h2><p>{{ tr('Estado de esta iglesia y sus últimas 20 solicitudes, sin comandos de terminal.','This church’s status and latest 20 requests, without terminal commands.') }}</p></div><button class="btn btn-outline-secondary" :disabled="loading" @click="load">{{ tr('Actualizar diagnóstico','Refresh diagnostics') }}</button></header>
  <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p>
  <p v-if="loading" role="status">{{ tr('Consultando…','Checking…') }}</p>
  <template v-if="data">
   <div class="diagnostic-switches"><span v-for="(enabled,key) in data.switches" :key="key" :class="{off:!enabled}">{{ switchLabel(key) }}: <strong>{{ enabled?tr('Activo','On'):tr('Inactivo','Off') }}</strong></span></div>
   <p v-if="data.pending_over_two_minutes || data.processing_over_two_minutes" class="alert alert-warning">{{ tr('Hay solicitudes detenidas por más de dos minutos. El administrador debe revisar el proceso de cola WhatsApp en el servidor.','Requests have been waiting or processing for over two minutes. The administrator should check the server’s WhatsApp queue worker.') }}</p>
   <p>{{ tr('Pendientes','Pending') }}: <strong>{{ data.pending }}</strong> · {{ tr('Último procesamiento registrado','Last recorded processing') }}: {{ dateTime(data.last_worker_activity) }}</p>
   <p class="diagnostic-muted">{{ tr('Esta fecha demuestra actividad pasada; no confirma que el worker esté ejecutándose ahora.','This timestamp shows past activity; it does not confirm that the worker is running now.') }}</p>
   <details><summary>{{ tr('Configuración del servidor (sin mostrar claves)','Server configuration (keys hidden)') }}</summary><ul class="diagnostic-config"><li v-for="(present,key) in data.configuration" :key="key"><span>{{ key }}</span><strong :class="{missing:!present}">{{ present?tr('Configurado','Configured'):tr('Falta','Missing') }}</strong></li></ul></details>
   <div class="provider-check"><button class="btn btn-outline-secondary" :disabled="checking" @click="check">{{ checking?tr('Comprobando acceso…','Checking access…'):tr('Comprobar acceso a Meta y OpenAI','Check Meta and OpenAI access') }}</button><p>{{ tr('Solo consulta acceso al número de Meta y al modelo de OpenAI. No envía mensajes ni genera respuestas de IA. Un resultado correcto no garantiza crédito, permisos de envío o entrega.','Only checks access to the Meta number and OpenAI model. Does not send messages or generate AI responses. Success does not guarantee credit, sending permissions or delivery.') }}</p>
    <div v-if="providers" class="provider-results"><p v-for="provider in ['meta','openai']" :key="provider"><strong>{{ provider==='meta'?'Meta':'OpenAI' }}</strong>: {{ providerLabel(providers[provider]) }}<span v-if="providers[provider].code"> · {{ explain(providers[provider].code) }}</span></p><small>{{ tr('Consultado','Checked') }}: {{ dateTime(providers.checked_at) }}</small></div>
   </div>
   <h3>{{ tr('Últimas solicitudes recibidas','Latest received requests') }}</h3>
   <p v-if="!data.messages.length">{{ tr('No hay solicitudes registradas para esta iglesia. Verifica el número vinculado, la activación y la entrega del webhook de Meta.','No requests recorded for this church. Check the linked number, enabled settings and Meta webhook delivery.') }}</p>
   <ol class="diagnostic-messages"><li v-for="row in data.messages" :key="row.id"><header><strong>#{{ row.id }} · {{ row.user_name }}</strong><time :datetime="row.created_at">{{ dateTime(row.created_at) }}</time></header>
    <p>{{ tr('Procesamiento','Processing') }}: <strong>{{ messageLabel(row.status) }}</strong> <code>{{ row.status }}</code></p>
    <p v-if="row.error_code">{{ explain(row.error_code) }} <code>{{ row.error_code }}</code></p>
    <p>{{ tr('Respuesta','Reply') }}: {{ row.reply_status ? explain(row.reply_status) : tr('Sin detalle de envío registrado. Los registros antiguos no guardaban la causa exacta.','No send details recorded. Older records did not retain the exact cause.') }} <code v-if="row.reply_status">{{ row.reply_status }}</code></p>
   </li></ol>
   <p class="diagnostic-muted">{{ tr('Procesado no significa evento creado: puede ser un borrador o una pregunta. Si no recibiste respuesta, consulta ESTADO antes de repetir CONFIRMAR. “Enviado” significa aceptado por Meta, no entregado o leído.','Processed does not mean an event was created: it may be a draft or a question. If you did not receive a reply, check ESTADO before repeating CONFIRMAR. “Sent” means accepted by Meta, not delivered or read.') }}</p>
   <small>{{ tr('Actualizado','Updated') }}: {{ dateTime(data.checked_at) }}</small>
  </template>
 </section>
</template>
<script setup>
import {ref,onMounted,onUnmounted} from 'vue'
import {apiClient} from '../services/authApi'
import {useUiStore} from '../stores/uiStore'
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const data=ref(null),providers=ref(null),loading=ref(false),checking=ref(false),error=ref('')
let mounted=true
const dateTime=value=>value?new Date(value).toLocaleString():tr('Sin registros','No records')
const switchLabel=key=>({server:tr('Servidor','Server'),pilot:tr('Iglesia piloto','Pilot church'),superadmin_whatsapp:'WhatsApp · Superadmin',clerk_whatsapp:tr('WhatsApp · Secretaría','WhatsApp · Clerk'),superadmin_ai:'IA · Superadmin',clerk_ai:tr('IA · Secretaría','AI · Clerk')})[key]||key
const messageLabel=key=>({pending:tr('Esperando al worker','Waiting for worker'),processing:tr('En proceso','Processing'),processed:tr('Procesado','Processed'),reply_failed:tr('Procesado; falló la respuesta','Processed; reply failed'),failed:tr('Falló la solicitud','Request failed'),rejected:tr('Datos incompletos o inválidos','Incomplete or invalid data'),ignored:tr('Descartado','Ignored'),inbox_limit:tr('Límite de entrada alcanzado','Incoming limit reached')})[key]||key
const explain=code=>{
 const descriptions={
  sent:['Meta aceptó el envío.','Meta accepted the send.'],
  sending:['Envío iniciado, sin resultado registrado aún.','Send started; no result recorded yet.'],
  send_failed:['Meta rechazó el envío sin un código disponible. Revisa el acceso del proveedor.','Meta rejected the send without an available code. Check provider access.'],
  send_unknown:['Conexión interrumpida o envío sin confirmar. No se reintenta automáticamente para evitar duplicados.','Connection interrupted or send unconfirmed. Automatic retries are disabled to avoid duplicates.'],
  meta_error_190:['Token de Meta inválido o vencido. El administrador debe renovarlo.','Meta token invalid or expired. The administrator must renew it.'],
  meta_error_131030:['Destinatario no autorizado para el número de prueba. Agrégalo y verifícalo en Meta API Setup.','Recipient not allowed for the test number. Add and verify it in Meta API Setup.'],
  meta_error_131047:['Meta indica que venció la ventana de respuesta. El usuario debe escribir de nuevo.','Meta says the reply window expired. The user must send a new message.'],
  meta_error_10:['Meta denegó permisos. Revisa los permisos y activos del token.','Meta denied permission. Check token permissions and assigned assets.'],
  meta_error_200:['Meta denegó permisos. Revisa los permisos y activos del token.','Meta denied permission. Check token permissions and assigned assets.'],
  window_expired:['El usuario debe escribir de nuevo para abrir la ventana de respuesta.','The user must message again to open the reply window.'],
  reply_limit:['Se alcanzó un límite de respuestas. Revisa los límites de iglesia, usuario y servidor.','A reply limit was reached. Check church, user and server limits.'],
  not_configured:['Configuración de WhatsApp incompleta o inválida. Revisa el panel de configuración.','WhatsApp configuration incomplete or invalid. Check configuration status.'],
  access_disabled:['El acceso del usuario o de la iglesia fue desactivado o revocado.','User or church access was disabled or revoked.'],
  ai_disabled:['IA desactivada para esta iglesia. Revisa ambos interruptores.','AI disabled for this church. Check both switches.'],
  ai_limit:['Límite de IA alcanzado. Revisa límites de iglesia, usuario y servidor.','AI limit reached. Check church, user and server limits.'],
  ai_not_configured:['Falta clave o modelo de OpenAI.','OpenAI key or model is missing.'],
  openai_connection:['No hubo conexión con OpenAI. Revisa red y disponibilidad del proveedor.','Unable to connect to OpenAI. Check network and provider availability.'],
  openai_insufficient_quota:['OpenAI indica falta de crédito o cuota. Revisa su facturación.','OpenAI reports insufficient credit or quota. Check its billing.'],
  openai_model_not_found:['OpenAI no reconoce el modelo o no permite acceso.','OpenAI model was not found or is not accessible.'],
  openai_invalid_api_key:['Clave de OpenAI inválida.','Invalid OpenAI API key.'],
  openai_http_401:['OpenAI rechazó la clave.','OpenAI rejected the key.'],
  openai_http_403:['OpenAI denegó permisos.','OpenAI denied access.'],
  openai_http_429:['OpenAI limitó la solicitud. Revisa cuota y límites del proveedor.','OpenAI rate-limited the request. Check provider quota and limits.'],
  openai_incomplete:['OpenAI devolvió una respuesta incompleta. No se confirmó ninguna acción por esta interpretación.','OpenAI returned an incomplete response. This interpretation did not confirm any action.'],
  openai_invalid_output:['La respuesta de OpenAI no se pudo interpretar.','OpenAI response could not be interpreted.'],
  input_too_long:['Solicitud demasiado larga. Envía un mensaje más corto.','Request too long. Send a shorter message.'],
  validation:['Faltan datos o tienen un formato inválido. Revisa la respuesta del asistente.','Missing or invalid details. Review the assistant reply.'],
  request_http_403:['El usuario no tiene permiso para esta acción.','User lacks permission for this action.'],
  request_http_404:['No se encontró el registro solicitado en el ámbito del usuario.','Requested record was not found within the user scope.'],
  request_http_422:['La acción no cumple las reglas del evento. Revisa su estado y los datos enviados.','Action does not meet event rules. Check its status and supplied details.'],
  expired_or_revoked:['Solicitud vencida o acceso revocado antes de procesarla.','Request expired or access revoked before processing.'],
  internal_error:['Error interno. Proporciona al administrador el número de solicitud y su fecha.','Internal error. Give the administrator the request number and timestamp.']
 }
 return descriptions[code]?tr(...descriptions[code]):tr('Error del proveedor o de la solicitud. Comunica este código al administrador.','Provider or request error. Give this code to the administrator.')
}
const providerLabel=row=>({reachable:tr('Acceso comprobado','Access verified'),rejected:tr('Acceso rechazado','Access rejected'),connection_failed:tr('No se pudo conectar','Unable to connect'),not_configured:tr('Configuración incompleta','Incomplete configuration')})[row.status]||row.status
async function load(){loading.value=true;error.value='';try{const response=await apiClient.get('/secretary/whatsapp-diagnostics');if(!response.data || !Array.isArray(response.data.messages) || !response.data.configuration || !response.data.switches)throw new Error('Invalid diagnostics response');if(mounted)data.value=response.data}catch{if(mounted)error.value=tr('No se pudo cargar el diagnóstico. Comprueba que el servidor tenga las últimas migraciones.','Unable to load diagnostics. Check that the server has the latest migrations.')}finally{if(mounted)loading.value=false}}
async function check(){checking.value=true;error.value='';try{const response=await apiClient.post('/secretary/whatsapp-diagnostics/check');if(!response.data?.meta?.status || !response.data?.openai?.status)throw new Error('Invalid provider response');if(mounted)providers.value=response.data}catch{if(mounted)error.value=tr('No se pudo completar la comprobación. Espera un minuto e intenta de nuevo.','Unable to complete checks. Wait a minute and try again.')}finally{if(mounted)checking.value=false}}
onMounted(load);onUnmounted(()=>mounted=false)
</script>
<style scoped>
.wa-diagnostics{padding:20px;margin-top:24px;min-width:0}.wa-diagnostics header{display:flex;align-items:start;justify-content:space-between;gap:12px}.wa-diagnostics h2{font-size:1rem}.wa-diagnostics h3{font-size:.9375rem}.wa-diagnostics p,.wa-diagnostics summary{font-size:.8125rem}.wa-diagnostics .btn{width:auto;flex-shrink:0}.diagnostic-switches{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.diagnostic-switches span{padding:6px 9px;background:#edf5ef;border:1px solid #cedfd2;border-radius:8px;font-size:.75rem}.diagnostic-switches .off{background:#fff5e6;border-color:#dec8a5}.diagnostic-muted,.wa-diagnostics small{color:#52645a}.diagnostic-config{list-style:none;padding:0;margin:12px 0}.diagnostic-config li{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid #e0e7e2;font-size:.75rem;overflow-wrap:anywhere}.missing{color:#a13232}.provider-check{margin:18px 0;padding:14px;border:1px solid #d3dfd6;border-radius:10px}.provider-check p{margin-top:10px}.diagnostic-messages{list-style:none;padding:0;display:grid;gap:12px}.diagnostic-messages li{padding:14px;border:1px solid #d3dfd6;border-radius:10px}.diagnostic-messages header,.diagnostic-messages time{font-size:.75rem}.diagnostic-messages p{margin:8px 0;overflow-wrap:anywhere}.diagnostic-messages code{font-size:.7rem;color:#52645a}@media(max-width:600px){.wa-diagnostics>header,.diagnostic-messages header{flex-direction:column}.wa-diagnostics{padding:14px}}
</style>
