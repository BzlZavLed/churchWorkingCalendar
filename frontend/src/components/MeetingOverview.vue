<template>
  <div class="meeting-overview">
    <p><strong>{{ tr('Inicio', 'Started') }}:</strong> {{ date(meeting.start_at) }} · <strong>{{ tr('Cierre', 'Closed') }}:</strong> {{ date(meeting.end_at) }}</p>
    <p><strong>{{ tr('Oración inicial', 'Opening prayer') }}:</strong> {{ meeting.opening_prayer || '—' }} <small>{{ stamp(meeting.opening_prayer_recorded_at) }}</small></p>
    <p><strong>{{ tr('Observaciones iniciales', 'Opening remarks') }}:</strong> {{ meeting.opening_remarks || '—' }} <small>{{ stamp(meeting.opening_remarks_recorded_at) }}</small></p>
    <strong>{{ tr('Asistencia', 'Attendance') }}</strong>
    <ul><li v-for="(person, index) in meeting.attendance || []" :key="index">{{ person.name }} · {{ ({present:tr('Presente','Present'),absent:tr('Ausente','Absent'),online:tr('En línea','Online')})[person.status] }} <small>{{ stamp(person.recorded_at) }}</small></li></ul>
    <p v-if="meeting.closing_notes"><strong>{{ tr('Notas de cierre', 'Closing notes') }}:</strong> {{ meeting.closing_notes }} <small>{{ date(meeting.end_at) }}</small></p>
    <section v-for="review in meeting.calendar_reviews || []" :key="review.id" class="calendar-decisions">
      <strong>{{ review.title }} · {{ tr('Revisión del calendario','Calendar review') }}</strong>
      <p v-if="!review.calendar_decisions?.length">{{ tr('Sin decisiones de eventos registradas.','No event decisions recorded.') }}</p>
      <p v-for="(decision,i) in review.calendar_decisions" :key="i"><strong>{{ decision.title }}</strong> · {{ decision.department }}<br />{{ date(decision.start_at) }} · {{ eventReviewLabel(decision.status,locale) }}<br /><span v-if="decision.note">{{ decision.note }}<br /></span><small>{{ date(decision.at) }} · {{ decision.actor }}</small></p>
    </section>
  </div>
</template>
<script setup>
import {eventReviewLabel} from '../utils/eventWorkflow'
const props = defineProps({ meeting: { type: Object, required: true }, locale: { type: String, default: 'es' } })
const tr = (es,en) => props.locale === 'es' ? es : en
const date = value => value ? new Intl.DateTimeFormat(props.locale === 'es' ? 'es-US' : 'en-US', {dateStyle:'medium',timeStyle:'short'}).format(new Date(value)) : '—'
const stamp = value => value ? date(value) : tr('Fecha de registro no disponible', 'Recording time unavailable')
</script>
<style scoped>
.meeting-overview { padding: 14px; margin: 12px 0; border-radius: 8px; background: #f8faf9; font-size: 12px; overflow-wrap: anywhere; white-space: pre-wrap; }
.meeting-overview p { margin: 0 0 10px; }
.meeting-overview small { color: #68766e; }
.meeting-overview ul { padding-left: 20px; margin-top: 8px; }
</style>
