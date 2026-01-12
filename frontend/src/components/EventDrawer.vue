<template>
  <aside v-if="selectedDate" class="event-drawer">
    <h2>{{ resolvedLabels.createEvent }}</h2>
    <p class="event-date">{{ formattedDate }}</p>

    <label>
      {{ resolvedLabels.title }}
      <input :value="title" type="text" @input="emitUpdate('title', $event.target.value)" />
    </label>

    <label>
      {{ resolvedLabels.description }}
      <textarea :value="description" rows="3" @input="emitUpdate('description', $event.target.value)"></textarea>
    </label>

    <div class="time-row">
      <label>
        {{ resolvedLabels.start }}
        <input :value="startTime" type="time" @input="emitUpdate('startTime', $event.target.value)" />
      </label>
      <label>
        {{ resolvedLabels.end }}
        <input :value="endTime" type="time" @input="emitUpdate('endTime', $event.target.value)" />
      </label>
    </div>

    <p v-if="error" class="event-error">{{ error }}</p>
    <p v-if="notice" class="event-notice">{{ notice }}</p>

    <div class="action-row">
      <button type="button" @click="$emit('create-hold')">{{ resolvedLabels.createHold }}</button>
      <button type="button" :disabled="!activeHoldId" @click="$emit('lock-hold')">
        {{ resolvedLabels.lockEvent }}
      </button>
      <button type="button" @click="$emit('close')">{{ resolvedLabels.close }}</button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'

const props = defineProps({
  selectedDate: {
    type: Date,
    default: null,
  },
  title: {
    type: String,
    default: '',
  },
  description: {
    type: String,
    default: '',
  },
  startTime: {
    type: String,
    default: '',
  },
  endTime: {
    type: String,
    default: '',
  },
  activeHoldId: {
    type: Number,
    default: null,
  },
  error: {
    type: String,
    default: '',
  },
  notice: {
    type: String,
    default: '',
  },
  labels: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:title', 'update:description', 'update:startTime', 'update:endTime', 'create-hold', 'lock-hold', 'close'])

const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const fallbackLabels = computed(() => translations[locale.value].calendar.drawer)
const resolvedLabels = computed(() => props.labels || fallbackLabels.value)

const formattedDate = computed(() => {
  if (!props.selectedDate) {
    return ''
  }
  return props.selectedDate.toLocaleDateString()
})

const emitUpdate = (field, value) => {
  emit(`update:${field}`, value)
}
</script>
