<template>
  <section class="management-surface birthday-panel" aria-labelledby="birthday-heading">
    <header><div><h2 id="birthday-heading">{{ tr('Cumpleaños','Birthdays') }}</h2><p>{{ tr('Elige un período, revisa la lista y prepara una felicitación para el grupo de la iglesia.','Choose a period, review the list and prepare a greeting for your church group.') }}</p></div></header>
    <div class="birthday-filters">
      <label>{{ tr('Frecuencia','Frequency') }}<select v-model="frequency" class="form-select"><option value="daily">{{ tr('Diaria','Daily') }}</option><option value="weekly">{{ tr('Semanal · 7 días','Weekly · 7 days') }}</option><option value="biweekly">{{ tr('Quincenal · 14 días','Biweekly · 14 days') }}</option><option value="monthly">{{ tr('Mensual','Monthly') }}</option></select></label>
      <label>{{ tr('Fecha de referencia','Reference date') }}<input v-model="date" type="date" class="form-control" required /></label>
      <label v-if="fields.length > 1">{{ tr('Campo de nacimiento','Date-of-birth field') }}<select v-model="fieldId" class="form-select"><option value="">{{ tr('Seleccionar campo','Select a field') }}</option><option v-for="field in fields" :key="field.id" :value="field.id">{{ field.label }}</option></select></label>
    </div>
    <p v-if="error" class="alert alert-danger mt-3" role="alert">{{ error }}</p>
    <p v-if="loading" role="status">{{ tr('Buscando cumpleaños…','Loading birthdays…') }}</p>
    <template v-else-if="roster">
      <p class="birthday-range">{{ roster.from }} — {{ roster.to }} · {{ roster.members.length }} {{ tr('cumpleaños','birthdays') }}</p>
      <p v-if="!fields.length" class="alert alert-info">{{ tr('Agrega un campo de tipo Fecha de nacimiento a la plantilla y completa ese dato en los miembros.','Add a Date of birth field to the member template and fill it in for members.') }}</p>
      <p v-else-if="!roster.field_id" class="alert alert-info">{{ tr('Selecciona cuál campo representa el cumpleaños.','Choose which field represents the birthday.') }}</p>
      <template v-else>
        <p v-if="!roster.members.length">{{ tr('No hay cumpleaños registrados en este período.','No recorded birthdays in this period.') }}</p>
        <ul v-else class="birthday-list"><li v-for="member in roster.members" :key="member.id"><span>🎂 {{ member.name }}</span><time :datetime="member.birthday">{{ displayDate(member.birthday) }}</time></li></ul>
        <p class="small text-muted">{{ roster.missing_dates }} {{ tr('miembros sin una fecha válida en el campo seleccionado. Los cumpleaños del 29 de febrero se incluyen el 28 en años no bisiestos.','members without a valid date in the selected field. February 29 birthdays are included on February 28 in non-leap years.') }}</p>
      </template>
      <div v-if="roster.members.length" class="birthday-message">
        <div class="birthday-actions"><h3>{{ tr('Felicitación para compartir','Greeting to share') }}</h3><button class="btn btn-outline-secondary" :disabled="generating" @click="makeGreeting">{{ tr('Otra versión sin IA','Another version without AI') }}</button><button v-if="auth.can('members.manage')" class="btn btn-outline-primary" :disabled="generating || !roster.ai_available" @click="generate">{{ generating ? tr('Redactando…','Writing…') : tr('Crear versión con IA','Create AI version') }}</button></div>
        <p class="small">{{ tr('La IA solo recibe el idioma y la cantidad de personas. Los nombres se agregan aquí. Generar con IA utiliza la API de pago; no envía mensajes a WhatsApp.','AI receives only the language and number of people. Names are added here. AI generation uses the paid API; it does not send messages to WhatsApp.') }}</p>
        <p v-if="!roster.ai_available" class="small">{{ tr('La IA no está configurada en este servidor. Puedes usar y editar el mensaje sin IA.','AI is not configured on this server. You can use and edit the greeting without AI.') }}</p>
        <label>{{ tr('Revisar mensaje','Review message') }}<textarea v-model="message" rows="8" class="form-control" :disabled="generating" /></label>
        <p class="small">{{ tr('Selecciona el grupo existente en WhatsApp y confirma el envío allí. No se publica automáticamente. En computadora, copia el mensaje y pégalo en el grupo.','Choose your existing group in WhatsApp and confirm sending there. Nothing is posted automatically. On a computer, copy the message and paste it into the group.') }}</p>
        <div class="birthday-actions"><button class="btn btn-primary" :disabled="!message.trim() || generating" @click="share">{{ tr('Compartir','Share') }}</button><button class="btn btn-outline-secondary" :disabled="!message.trim() || generating" @click="copy">{{ tr('Copiar mensaje','Copy message') }}</button><a href="https://web.whatsapp.com/" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary">{{ tr('Abrir WhatsApp Web','Open WhatsApp Web') }}</a></div>
        <p v-if="notice" role="status" class="mt-2">{{ notice }}</p>
      </div>
    </template>
  </section>
</template>
<script setup>
import { ref, watch, onUnmounted } from 'vue'
import { apiClient } from '../services/authApi'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
const auth=useAuthStore(),ui=useUiStore(),tr=(es,en)=>ui.locale==='es'?es:en
const frequency=ref('monthly'),date=ref(new Date().toLocaleDateString('en-CA')),fieldId=ref(''),fields=ref([]),roster=ref(null),loading=ref(false),generating=ref(false),error=ref(''),message=ref(''),notice=ref('')
const timezone=Intl.DateTimeFormat().resolvedOptions().timeZone
let version=0,alive=true,variant=0
const params=()=>({frequency:frequency.value,date:date.value,timezone,field_id:fieldId.value||undefined,locale:ui.locale})
const displayDate=value=>new Date(value+'T12:00:00').toLocaleDateString(ui.locale==='es'?'es':'en',{month:'long',day:'numeric'})
function makeGreeting(){
 const greetings=ui.locale==='es'?['¡Hoy celebramos el regalo de sus vidas! 🎉 Que este nuevo año llegue con alegría, buenas sorpresas y muchas bendiciones. ¡Un abrazo de toda nuestra familia de la iglesia! 💛','¡Que suenen las felicitaciones! 🥳 Celebramos a personas muy especiales de nuestra iglesia. Que Dios les acompañe en un año lleno de esperanza, sonrisas y momentos para recordar. 🎈','¡Tenemos motivos para celebrar! 🎂 Gracias por ser parte de nuestra familia de fe. Les deseamos un cumpleaños lleno de cariño y un nuevo año con grandes alegrías. ✨']:['Let the birthday celebrations begin! 🎉 Wishing you joy, wonderful surprises and many blessings in the year ahead. With love from your church family! 💛','Birthday cheers for some very special people! 🥳 May God guide you through a year full of hope, smiles and beautiful memories. 🎈','We have something wonderful to celebrate! 🎂 Thank you for being part of our church family. Wishing you a birthday full of love and a joyful year ahead. ✨']
 message.value=greetings[variant++%greetings.length]+'\n\n'+roster.value.members.map(m=>'🎂 '+m.name).join('\n');notice.value=''
}
watch([frequency,date,fieldId,()=>ui.locale],async()=>{
 const current=++version;roster.value=null;message.value='';notice.value='';error.value='';loading.value=true
 if(!date.value){loading.value=false;return}
 try{const {data}=await apiClient.get('/members/birthdays',{params:params()});if(!alive||version!==current)return;roster.value=data;fields.value=data.fields;if(data.members.length)makeGreeting()}
 catch(e){if(alive&&version===current)error.value=e.response?.data?.message||tr('No se pudo cargar la lista.','Unable to load birthdays.')}
 finally{if(alive&&version===current)loading.value=false}
},{immediate:true})
async function generate(){const current=version;generating.value=true;error.value='';notice.value='';try{const {data}=await apiClient.post('/members/birthdays/message',params());if(alive&&current===version)message.value=data.message}catch(e){if(alive&&current===version)error.value=e.response?.data?.message||tr('No se pudo generar el mensaje.','Unable to generate the greeting.')}finally{if(alive)generating.value=false}}
async function copy(){try{await navigator.clipboard.writeText(message.value);notice.value=tr('Mensaje copiado. Pégalo en el grupo de WhatsApp.','Message copied. Paste it into your WhatsApp group.')}catch{notice.value=tr('Selecciona el texto del mensaje y cópialo manualmente.','Select the message text and copy it manually.')}}
async function share(){if(navigator.share){try{await navigator.share({text:message.value});notice.value=tr('Compartido con la aplicación seleccionada; confirma el envío en WhatsApp.','Shared with the selected app; confirm sending in WhatsApp.')}catch(e){if(e.name!=='AbortError')await copy()}}else await copy()}
onUnmounted(()=>{alive=false;version++})
</script>
<style scoped>
.birthday-panel{padding:20px;margin:20px 0;min-width:0}.birthday-panel h2{font-size:18px}.birthday-panel h3{font-size:15px;margin:0}.birthday-panel p,.birthday-panel label{font-size:13px;color:#52645a}.birthday-filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}.birthday-filters label{min-width:0}.birthday-filters input,.birthday-filters select{max-width:100%;margin-top:6px;font-size:13px}.birthday-range{margin-top:18px;font-weight:500}.birthday-list{padding:0;list-style:none}.birthday-list li{display:flex;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid #e1e8e3;overflow-wrap:anywhere}.birthday-list time{flex-shrink:0;color:#52645a;font-size:13px}.birthday-message{border-top:1px solid #dce5df;margin-top:20px;padding-top:20px}.birthday-message label{display:block}.birthday-message textarea{margin:8px 0 12px;font-size:14px;white-space:pre-wrap}.birthday-actions{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px}.birthday-panel .btn{font-size:13px;white-space:normal}.birthday-panel .btn-primary{background:#1f6f5c;border-color:#1f6f5c;color:#fff}.birthday-panel .btn-outline-primary{background:#fff;color:#1f6f5c;border-color:#1f6f5c}.birthday-panel .btn-outline-secondary{background:#fff;color:#52645a;border-color:#8da998}@media(max-width:500px){.birthday-panel{padding:14px}.birthday-filters{grid-template-columns:1fr}.birthday-list li{align-items:flex-start;flex-direction:column;gap:4px}}
</style>
