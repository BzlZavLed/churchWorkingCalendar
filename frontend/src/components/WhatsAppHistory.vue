<template>
 <section class="wa-history management-surface">
  <header><div><h2>{{ churchWide ? tr('Interacciones de la iglesia','Church interactions') : tr('Mis interacciones por día','My daily interactions') }}</h2><p>{{ churchWide ? tr('Mensajes de todos los usuarios y departamentos de esta iglesia. Historial de los últimos 30 días; las conversaciones anteriores a su activación no se pueden recuperar.','Messages from all users and departments in this church. Last 30 days; conversations before history was enabled cannot be recovered.') : tr('Mensajes de tu cuenta de los últimos 30 días.','Your account’s messages from the last 30 days.') }}</p></div><button class="btn btn-outline-secondary" :disabled="loading" @click="load">{{ tr('Actualizar','Refresh') }}</button></header>
  <label>{{ tr('Fecha','Date') }}<input type="date" v-model="date" class="form-control" @change="page=1;load()" /></label><small>{{ timezone }}</small>
  <label v-if="churchWide">{{ tr('Departamento actual','Current department') }}<select v-model="department" class="form-select" @change="page=1;load()"><option value="">{{ tr('Todos (incluye secretaría y pastor)','All (includes clerk and pastor)') }}</option><option v-for="item in departments" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
  <p v-if="churchWide && whatsappActive === false" class="alert alert-warning">{{ tr('WhatsApp no está activo para esta iglesia en este servidor. Revisa los interruptores y la iglesia piloto configurada.','WhatsApp is not active for this church on this server. Check switches and the configured pilot church.') }}</p>
  <p v-if="loading" role="status">{{ tr('Cargando…','Loading…') }}</p><p v-if="error" role="alert">{{ tr('No se pudo cargar el historial.','Unable to load history.') }}</p>
  <p v-if="!loading && !error && !rows.length">{{ tr('No hay interacciones registradas para este día.','No recorded interactions for this day.') }}</p>
  <ol v-if="!loading" class="wa-history-list"><li v-for="row in rows" :key="row.id" :class="{outgoing:row.direction==='outgoing'}"><header><strong>{{ row.direction==='incoming' ? (churchWide ? row.user_name || tr('Usuario','User') : tr('Tú','You')) : tr('Asistente','Assistant') }}</strong><time :datetime="row.created_at">{{ new Date(row.created_at).toLocaleTimeString() }}</time></header><small v-if="churchWide">{{ row.user_name || tr('Usuario anterior','Former user') }} · {{ row.department_name || tr('Sin departamento','No department') }}</small><p>{{ row.content }}</p><small>{{ status(row.status) }}</small></li></ol>
  <nav v-if="lastPage>1" class="wa-history-pages" :aria-label="tr('Páginas del historial','History pages')"><button class="btn btn-outline-secondary" :disabled="page===1||loading" @click="page--;load()">{{ tr('Anterior','Previous') }}</button><span>{{ page }} / {{ lastPage }}</span><button class="btn btn-outline-secondary" :disabled="page===lastPage||loading" @click="page++;load()">{{ tr('Siguiente','Next') }}</button></nav>
  <small>{{ tr('“Enviado” significa aceptado por Meta; no confirma entrega ni lectura. Los mensajes más recientes aparecen primero.','“Sent” means accepted by Meta; it does not confirm delivery or reading. Newest messages appear first.') }}</small>
 </section>
</template>
<script setup>
import {ref,watch,onUnmounted} from 'vue'
import {apiClient} from '../services/authApi'
import {useUiStore} from '../stores/uiStore'
const props=defineProps({churchWide:{type:Boolean,default:false}})
const department=ref(''),departments=ref([]),whatsappActive=ref(null)
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const now=new Date(),date=ref(`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`)
const timezone=Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'
const rows=ref([]),page=ref(1),lastPage=ref(1),loading=ref(false),error=ref(false)
let version=0
const status=value=>{
 if(value.startsWith('meta_error_')){
  const code=value.slice(11)
  const known={
   '190':tr('Token de Meta vencido o inválido.','Meta token expired or invalid.'),
   '131030':tr('Meta: número destinatario no autorizado en el número de prueba.','Meta: recipient not allowed for the test number.'),
   '131047':tr('Meta: ventana de respuesta vencida.','Meta: reply window expired.')
  }
  return (known[code]||tr('Meta rechazó el envío.','Meta rejected the send.'))+' ('+code+')'
 }
 return ({window_expired:tr('No enviado: ventana de respuesta vencida','Not sent: reply window expired'),not_configured:tr('No enviado: configuración incompleta','Not sent: incomplete configuration'),reply_limit:tr('No enviado: límite de respuestas alcanzado','Not sent: reply limit reached'),inbox_limit:tr('No procesado: límite de mensajes recibidos alcanzado','Not processed: incoming message limit reached'),received:tr('Recibido','Received'),pending:tr('Pendiente de procesar; si persiste, revisa el worker de WhatsApp','Waiting for processing; if persistent, check the WhatsApp worker'),processing:tr('Procesando','Processing'),processed:tr('Procesado','Processed'),rejected:tr('Revisa los datos solicitados','Check requested details'),failed:tr('No se pudo procesar','Processing failed'),reply_failed:tr('Procesado; respuesta no confirmada','Processed; reply not confirmed'),ignored:tr('No procesado','Not processed'),disabled:tr('Asistente desactivado','Assistant disabled'),sending:tr('Envío iniciado','Sending started'),sent:tr('Enviado a Meta','Sent to Meta'),send_failed:tr('Meta rechazó el envío','Meta rejected send'),send_unknown:tr('Envío sin confirmar','Send unconfirmed')})[value]||value
}
async function load(){if(!date.value)return;const current=++version;loading.value=true;error.value=false;try{const {data}=await apiClient.get(props.churchWide?'/secretary/whatsapp-history':'/auth/whatsapp-history',{params:{date:date.value,timezone,page:page.value,department_id:department.value||undefined}});if(current===version){rows.value=data.data;lastPage.value=data.last_page;departments.value=data.departments||[];whatsappActive.value=data.whatsapp_active??null}}catch{if(current===version){error.value=true;rows.value=[]}}finally{if(current===version)loading.value=false}}
watch(()=>props.churchWide,()=>{page.value=1;department.value='';rows.value=[];load()},{immediate:true})
onUnmounted(()=>version++)
</script>
<style scoped>
.wa-history{padding:20px;margin-top:24px}.wa-history header{display:flex;align-items:start;justify-content:space-between;gap:12px}.wa-history h2{font-size:1rem}.wa-history p,.wa-history small{font-size:.8125rem}.wa-history label{display:block;max-width:220px}.wa-history-list{list-style:none;padding:0;display:grid;gap:12px;margin:18px 0}.wa-history-list li{padding:14px;border:1px solid #d2ddd7;border-radius:12px;background:#fff}.wa-history-list li.outgoing{background:#f0f7f3}.wa-history-list p{white-space:pre-wrap;overflow-wrap:anywhere;margin:10px 0}.wa-history-list time,.wa-history-list small{color:#53675b;font-size:.75rem}.wa-history-pages{display:flex;align-items:center;gap:12px;margin-bottom:16px}.wa-history .btn{width:auto}
</style>
