<template>
 <section class="wa-history management-surface">
  <header><div><h2>{{ tr('Mis interacciones por día','My daily interactions') }}</h2><p>{{ tr('Mensajes de tu cuenta de los últimos 30 días, desde que se habilitó el historial. Los mensajes anteriores no se pueden recuperar.','Your account’s messages from the last 30 days, starting when history was enabled. Earlier messages cannot be recovered.') }}</p></div><button class="btn btn-outline-secondary" :disabled="loading" @click="load">{{ tr('Actualizar','Refresh') }}</button></header>
  <label>{{ tr('Fecha','Date') }}<input type="date" v-model="date" class="form-control" @change="page=1;load()" /></label><small>{{ timezone }}</small>
  <p v-if="loading" role="status">{{ tr('Cargando…','Loading…') }}</p><p v-if="error" role="alert">{{ tr('No se pudo cargar el historial.','Unable to load history.') }}</p>
  <p v-if="!loading && !error && !rows.length">{{ tr('No hay interacciones registradas para este día.','No recorded interactions for this day.') }}</p>
  <ol v-if="!loading" class="wa-history-list"><li v-for="row in rows" :key="row.id" :class="{outgoing:row.direction==='outgoing'}"><header><strong>{{ row.direction==='incoming'?tr('Tú','You'):tr('Asistente','Assistant') }}</strong><time :datetime="row.created_at">{{ new Date(row.created_at).toLocaleTimeString() }}</time></header><p>{{ row.content }}</p><small>{{ status(row.status) }}</small></li></ol>
  <nav v-if="lastPage>1" class="wa-history-pages" :aria-label="tr('Páginas del historial','History pages')"><button class="btn btn-outline-secondary" :disabled="page===1||loading" @click="page--;load()">{{ tr('Anterior','Previous') }}</button><span>{{ page }} / {{ lastPage }}</span><button class="btn btn-outline-secondary" :disabled="page===lastPage||loading" @click="page++;load()">{{ tr('Siguiente','Next') }}</button></nav>
  <small>{{ tr('“Enviado” significa aceptado por Meta; no confirma entrega ni lectura. Los mensajes más recientes aparecen primero.','“Sent” means accepted by Meta; it does not confirm delivery or reading. Newest messages appear first.') }}</small>
 </section>
</template>
<script setup>
import {ref,onMounted,onUnmounted} from 'vue'
import {apiClient} from '../services/authApi'
import {useUiStore} from '../stores/uiStore'
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const now=new Date(),date=ref(`${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`)
const timezone=Intl.DateTimeFormat().resolvedOptions().timeZone||'UTC'
const rows=ref([]),page=ref(1),lastPage=ref(1),loading=ref(false),error=ref(false)
let version=0
const status=value=>({received:tr('Recibido','Received'),pending:tr('Pendiente','Pending'),processing:tr('Procesando','Processing'),processed:tr('Procesado','Processed'),rejected:tr('Revisa los datos solicitados','Check requested details'),failed:tr('No se pudo procesar','Processing failed'),reply_failed:tr('Procesado; respuesta no confirmada','Processed; reply not confirmed'),ignored:tr('No procesado','Not processed'),disabled:tr('Asistente desactivado','Assistant disabled'),sending:tr('Envío iniciado','Sending started'),sent:tr('Enviado a Meta','Sent to Meta'),send_failed:tr('Meta rechazó el envío','Meta rejected send'),send_unknown:tr('Envío sin confirmar','Send unconfirmed')})[value]||value
async function load(){if(!date.value)return;const current=++version;loading.value=true;error.value=false;try{const {data}=await apiClient.get('/auth/whatsapp-history',{params:{date:date.value,timezone,page:page.value}});if(current===version){rows.value=data.data;lastPage.value=data.last_page}}catch{if(current===version){error.value=true;rows.value=[]}}finally{if(current===version)loading.value=false}}
onMounted(load);onUnmounted(()=>version++)
</script>
<style scoped>
.wa-history{padding:20px;margin-top:24px}.wa-history header{display:flex;align-items:start;justify-content:space-between;gap:12px}.wa-history h2{font-size:1rem}.wa-history p,.wa-history small{font-size:.8125rem}.wa-history label{display:block;max-width:220px}.wa-history-list{list-style:none;padding:0;display:grid;gap:12px;margin:18px 0}.wa-history-list li{padding:14px;border:1px solid #d2ddd7;border-radius:12px;background:#fff}.wa-history-list li.outgoing{background:#f0f7f3}.wa-history-list p{white-space:pre-wrap;overflow-wrap:anywhere;margin:10px 0}.wa-history-list time,.wa-history-list small{color:#53675b;font-size:.75rem}.wa-history-pages{display:flex;align-items:center;gap:12px;margin-bottom:16px}.wa-history .btn{width:auto}
</style>
