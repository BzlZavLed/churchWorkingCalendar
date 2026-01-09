<template>
  <div v-if="open" class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-panel">
      <header class="modal-header">
        <h2>{{ labels.createEvent }}</h2>
        <button type="button" class="modal-close" @click="$emit('close')">×</button>
      </header>
      <p class="event-date">{{ formattedDate }}</p>

      <label>
        {{ labels.title }}
        <input class="form-control" :value="title" type="text" @input="emitUpdate('title', $event.target.value)" />
      </label>

      <label>
        {{ labels.description }}
        <textarea
          class="form-control"
          :value="description"
          rows="3"
          @input="emitUpdate('description', $event.target.value)"
        ></textarea>
      </label>

      <label>
        {{ labels.location }}
        <input class="form-control" :value="location" type="text" @input="emitUpdate('location', $event.target.value)" />
      </label>

      <label>
        {{ labels.objective }}
        <select class="form-select" :value="objectiveId" @change="emitUpdate('objectiveId', $event.target.value)">
          <option value="">Select...</option>
          <option v-for="objective in objectives" :key="objective.id" :value="objective.id">
            {{ objective.display_name || objective.name }}
          </option>
        </select>
      </label>

      <div class="time-row">
      <label>
        {{ labels.start }}
        <input
          class="form-control"
          :value="startTime"
          type="time"
          @input="emitUpdate('startTime', $event.target.value)"
        />
      </label>
      <label>
        {{ labels.end }}
        <input
          class="form-control"
          :value="endTime"
          type="time"
          @input="emitUpdate('endTime', $event.target.value)"
        />
      </label>
      </div>

      <p v-if="error" class="event-error">{{ error }}</p>
      <p v-if="notice" class="event-notice">{{ notice }}</p>

      <div class="action-row">
        <button type="button" @click="$emit('create-hold')">{{ labels.createHold }}</button>
        <button type="button" :disabled="!activeHoldId" @click="$emit('lock-hold')">
          {{ labels.lockEvent }}
        </button>
        <button type="button" @click="$emit('close')">{{ labels.close }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
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
  location: {
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
  objectiveId: {
    type: [String, Number],
    default: '',
  },
  objectives: {
    type: Array,
    default: () => [],
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
    default: () => ({
      createEvent: 'Create Event',
      title: 'Title',
      description: 'Description',
      location: 'Location',
      start: 'Start',
      end: 'End',
      createHold: 'Create Hold',
      lockEvent: 'Lock Event',
      close: 'Close',
      objective: 'Objective',
    }),
  },
})

const emit = defineEmits([
  'update:title',
  'update:description',
  'update:location',
  'update:startTime',
  'update:endTime',
  'update:objectiveId',
  'create-hold',
  'lock-hold',
  'close',
])

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
