<template>
  <section class="calendar-review">
    <h3>{{ tr('Revisión del calendario', 'Calendar review') }}</h3>
    <p>{{ tr('Revisa los eventos que correspondan. No es obligatorio resolver todo el período.', 'Review the relevant events. You do not have to resolve the whole period.') }}</p>
    <form class="range" @submit.prevent="load"><label>{{ tr('Desde','From') }}<input v-model="from" type="date" required /></label><label>{{ tr('Hasta','Through') }}<input v-model="until" type="date" :min="from" required /></label><button class="btn btn-outline-primary" :disabled="loading">{{ tr('Cargar eventos','Load events') }}</button></form>
    <p v-if="error" role="alert">{{ error }}</p>
    <p v-if="loading">{{ tr('Cargando…','Loading…') }}</p>
    <p v-else-if="!events.length">{{ tr('No hay eventos en este período.','No events in this period.') }}</p>
    <article v-for="event in events" :key="event.id" class="event-row"><div><strong>{{ event.title }}</strong><p>{{ event.department?.name }} · {{ date(event.start_at) }}</p><span>{{ eventReviewLabel(event.review_status, locale) }}</span></div><button class="btn btn-outline-secondary" @click="selected=event">{{ tr('Revisar evento','Review event') }}</button></article>
    <EventDetailsModal :open="!!selected" :event="selected" :notes="selected?.notes || []" :histories="selected?.histories || []" :can-review="true" :can-reply-notes="true" :saving="saving" :error="detailError" :locale="locale" @close="selected=null" @review="review" @reply-note="addNote" />
  </section>
</template>
<script setup>
import {ref,onMounted} from 'vue'
import {calendarApi} from '../services/calendarApi'
import EventDetailsModal from './EventDetailsModal.vue'
import {eventReviewLabel} from '../utils/eventWorkflow'
const props=defineProps({point:{type:Object,required:true},locale:{type:String,default:'es'}})
const emit=defineEmits(['reviewed'])
const tr=(es,en)=>props.locale==='es'?es:en
const from=ref(props.point.calendar_from),until=ref(props.point.calendar_until),events=ref([]),selected=ref(null),loading=ref(false),saving=ref(false),error=ref(''),detailError=ref('')
const date=v=>new Date(v).toLocaleString(props.locale)
const message=e=>Object.values(e.response?.data?.errors||{}).flat().join(' ')||e.response?.data?.message||tr('No se pudo guardar.','Could not save.')
async function load(){loading.value=true;error.value='';try{const end=new Date(until.value+'T00:00:00');end.setDate(end.getDate()+1);events.value=(await calendarApi.fetchEvents({start:new Date(from.value+'T00:00:00').toISOString(),end:end.toISOString()})).filter(e=>e.status==='locked'&&!e.is_conference_event)}catch(e){error.value=message(e)}finally{loading.value=false}}
async function review({status,note}){saving.value=true;detailError.value='';try{selected.value=await calendarApi.reviewEvent(selected.value.id,{review_status:status,review_note:note,meeting_point_id:props.point.id});await load();emit('reviewed')}catch(e){detailError.value=message(e)}finally{saving.value=false}}
async function addNote(payload){saving.value=true;try{const note=await calendarApi.addNote(selected.value.id,{note:typeof payload==='string'?payload:payload.reply});selected.value.notes=[...(selected.value.notes||[]),note]}catch(e){detailError.value=message(e)}finally{saving.value=false}}
onMounted(load)
</script>
<style scoped>
.calendar-review{margin-top:14px;background:#f4f8f5;border:1px solid #dce7df;padding:16px;border-radius:8px;min-width:0}.range{display:flex;flex-wrap:wrap;gap:12px;align-items:end}.range label{flex:1;min-width:120px}.range input{width:100%;display:block}.event-row{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid #dce7df;overflow-wrap:anywhere}.event-row p{margin:5px 0}.event-row .btn{width:auto;flex-shrink:0}.calendar-review p{color:#617166;font-size:12px}@media(max-width:480px){.event-row{align-items:start;flex-direction:column}}
</style>
