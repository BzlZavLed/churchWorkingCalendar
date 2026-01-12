<template>
  <div v-if="open" class="modal-backdrop" @click.self="$emit('close')">
    <div class="modal-panel">
      <header class="modal-header">
        <h2>{{ resolvedLabels.createEvent }}</h2>
        <button type="button" class="modal-close" @click="$emit('close')">×</button>
      </header>
      <p class="event-date">{{ formattedDate }}</p>

      <label>
        {{ resolvedLabels.title }}
        <input class="form-control" :value="title" type="text" @input="emitUpdate('title', $event.target.value)" />
      </label>

      <label>
        {{ resolvedLabels.description }}
        <textarea
          class="form-control"
          :value="description"
          rows="3"
          @input="emitUpdate('description', $event.target.value)"
        ></textarea>
      </label>

      <label>
        {{ resolvedLabels.location }}
        <input class="form-control" :value="location" type="text" @input="emitUpdate('location', $event.target.value)" />
      </label>

      <label>
        {{ resolvedLabels.objective }}
        <select class="form-select" :value="objectiveId" @change="emitUpdate('objectiveId', $event.target.value)">
          <option value="">{{ objectivePlaceholder }}</option>
          <option v-for="objective in objectives" :key="objective.id" :value="objective.id">
            {{ objective.display_name || objective.name }}
          </option>
        </select>
      </label>

      <div class="time-row">
      <label>
        {{ resolvedLabels.start }}
        <input
          class="form-control"
          :value="startTime"
          type="time"
          @input="emitUpdate('startTime', $event.target.value)"
        />
      </label>
      <label>
        {{ resolvedLabels.end }}
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
        <template v-if="isEditing">
          <button type="button" @click="$emit('save-edit')">{{ resolvedLabels.saveEvent }}</button>
        </template>
        <template v-else>
          <button type="button" @click="$emit('create-hold')">{{ resolvedLabels.createHold }}</button>
          <button type="button" :disabled="!activeHoldId" @click="$emit('lock-hold')">
            {{ resolvedLabels.lockEvent }}
          </button>
        </template>
        <button type="button" @click="$emit('close')">{{ resolvedLabels.close }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { storeToRefs } from 'pinia'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'

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
  isEditing: {
    type: Boolean,
    default: false,
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

const emit = defineEmits([
  'update:title',
  'update:description',
  'update:location',
  'update:startTime',
  'update:endTime',
  'update:objectiveId',
  'create-hold',
  'lock-hold',
  'save-edit',
  'close',
])

const uiStore = useUiStore()
const { locale } = storeToRefs(uiStore)
const fallbackLabels = computed(() => translations[locale.value].calendar.drawer)
const common = computed(() => translations[locale.value].common)
const resolvedLabels = computed(() => props.labels || fallbackLabels.value)
const objectivePlaceholder = computed(() => resolvedLabels.value.selectObjective || common.value.select)

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
