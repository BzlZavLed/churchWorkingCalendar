<template>
 <section class="provider-usage" aria-labelledby="provider-usage-title">
  <h3 id="provider-usage-title">{{ tr('Uso reportado por proveedores','Provider-reported usage') }}</h3>
  <p>{{ tr('Totales de las cuentas compartidas, no de esta iglesia. Pueden incluir otros proyectos y números. Se consultan cada 30 minutos mientras esta ventana está abierta; los proveedores pueden demorar en reportar.','Shared account totals, not this church alone. May include other projects and numbers. Checked every 30 minutes while this window is open; provider reporting can be delayed.') }}</p>
  <p v-if="loading" role="status">{{ tr('Consultando proveedores…','Checking providers…') }}</p>
  <p v-if="error" role="alert">{{ tr('No se pudo consultar el reporte. Los contadores locales siguen disponibles.','Unable to load reports. Local counters remain available.') }}</p>
  <article v-for="(report, provider) in reports" :key="provider">
   <h4>{{ provider === 'meta' ? 'Meta · WhatsApp' : 'OpenAI' }}</h4>
   <template v-if="report.status === 'ok'">
    <template v-if="provider === 'meta'">
     <strong>{{ tr('Mensajes','Messages') }}: {{ report.messages.toLocaleString() }}</strong>
     <span>{{ tr('Costo reportado','Reported cost') }}: {{ report.cost === null ? tr('No disponible','Unavailable') : report.cost.toLocaleString(undefined,{maximumFractionDigits:6}) }}</span>
     <small>{{ tr('Importe en la moneda de facturación de tu cuenta Meta.','Amount in your Meta account billing currency.') }}</small>
    </template>
    <template v-else>
     <strong>{{ tr('Solicitudes de modelos','Model requests') }}: {{ report.requests.toLocaleString() }}</strong>
     <span>{{ tr('Tokens de entrada / salida','Input / output tokens') }}: {{ report.input_tokens.toLocaleString() }} / {{ report.output_tokens.toLocaleString() }}</span>
     <span v-for="(amount,currency) in report.costs" :key="currency">{{ tr('Costo total de organización','Organization total cost') }}: {{ amount.toLocaleString(undefined,{maximumFractionDigits:6}) }} {{ currency.toUpperCase() }}</span>
     <span v-if="!Object.keys(report.costs).length">{{ tr('Sin costos reportados.','No costs reported.') }}</span>
    </template>
   </template>
   <p v-else>{{ statusLabel(report.status) }}</p>
   <small v-if="report.updated_at">{{ tr('Actualizado','Updated') }}: {{ new Date(report.updated_at).toLocaleString() }}</small>
   <small v-else-if="report.checked_at">{{ tr('Última consulta','Last checked') }}: {{ new Date(report.checked_at).toLocaleString() }}</small>
  </article>
 </section>
</template>
<script setup>
import {ref,watch,onUnmounted} from 'vue'
import {apiClient} from '../services/authApi'
import {useUiStore} from '../stores/uiStore'
const props=defineProps({church:{type:String,required:true},month:{type:String,required:true}})
const ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const reports=ref({}),loading=ref(false),error=ref(false)
let version=0
const statusLabel=status=>({
 admin_key_required:tr('La facturación de OpenAI requiere OPENAI_ADMIN_KEY en el servidor. Los tokens locales de abajo siguen disponibles con la clave actual.','OpenAI billing requires OPENAI_ADMIN_KEY on the server. Local tokens below remain available with the current key.'),
 not_configured:tr('Falta configurar la cuenta, versión de API o token del proveedor.','Provider account, API version or token is not configured.'),
 permission_required:tr('El proveedor rechazó el acceso. Revisa vigencia del token y permisos de analítica.','Provider denied access. Check token validity and analytics permissions.'),
 unavailable:tr('El proveedor no devolvió un reporte completo. Revisa acceso, versión de API o disponibilidad; se reintentará en la próxima consulta.','Provider did not return a complete report. Check access, API version or availability; it will retry on the next check.'),
 future:tr('El mes seleccionado todavía no comienza.','The selected month has not started.')
})[status]||tr('Reporte no disponible.','Report unavailable.')
async function load(){
 const current=++version;loading.value=true;error.value=false
 try{const {data}=await apiClient.get(`/superadmin/churches/${props.church}/integrations/providers`,{params:{month:props.month}});if(current===version)reports.value=data}
 catch{if(current===version)error.value=true}
 finally{if(current===version)loading.value=false}
}
watch(()=>[props.church,props.month],()=>{reports.value={};load()},{immediate:true})
const timer=setInterval(load,30*60*1000)
onUnmounted(()=>{version++;clearInterval(timer)})
</script>
<style scoped>
.provider-usage{margin:20px 0;border-top:1px solid #dce6df;padding-top:18px}
.provider-usage>p{font-size:12px;color:#53675b}
.provider-usage article{display:grid;gap:7px;border:1px solid #dce6df;border-radius:10px;padding:14px;margin:12px 0;font-size:13px;overflow-wrap:anywhere}
.provider-usage h3{font-size:15px}.provider-usage h4{font-size:14px;margin:0}
.provider-usage small{color:#53675b}
</style>
