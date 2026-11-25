<template>
  <details class="management-surface member-panel">
    <summary>{{ tr('Corregir fechas de nacimiento importadas', 'Correct imported birth dates') }}</summary>
    <p>{{ tr('Carga el CSV original. Se aceptan fechas AAAA-DD-MM, M/D/AAAA y M/D/AA. Se usa PersonId para identificar a cada miembro y se corrigen solo fechas con mes y día invertidos respecto al archivo.', 'Upload the original CSV with YYYY-DD-MM, M/D/YYYY, or M/D/YY dates. Members are matched by PersonId, and only dates with the month and day reversed relative to the file are corrected.') }}</p>
    <form class="member-toolbar" @submit.prevent="submit(false)">
      <label>{{ tr('CSV original', 'Original CSV') }}
        <input type="file" accept=".csv,text/csv" class="form-control" :disabled="busy" @change="chooseFile" />
      </label>
      <button class="btn btn-outline-primary" :disabled="!file || busy">{{ tr('Revisar cambios', 'Preview changes') }}</button>
    </form>
    <p v-if="error" class="alert alert-danger mt-3" role="alert">{{ error }}</p>
    <p v-if="updated !== null" class="alert alert-success mt-3" role="status">{{ updated }} {{ tr('fechas corregidas', 'dates corrected') }}</p>
    <template v-if="review">
      <p class="mt-3" role="status">{{ readyCount }} {{ tr('fechas se corregirán. Las demás filas se omitirán.', 'dates will be corrected. Other rows will be skipped.') }}</p>
      <div class="correction-preview">
        <table class="table">
          <thead><tr><th>{{ tr('Miembro', 'Member') }}</th><th>{{ tr('Fecha actual', 'Current date') }}</th><th>{{ tr('Fecha corregida', 'Corrected date') }}</th><th>{{ tr('Resultado', 'Result') }}</th></tr></thead>
          <tbody><tr v-for="row in review.rows" :key="row.person_id">
            <td>{{ row.name || row.person_id }}</td><td>{{ row.current || '—' }}</td><td>{{ row.corrected || '—' }}</td><td>{{ statusLabel(row.status) }}</td>
          </tr></tbody>
        </table>
      </div>
      <button v-if="readyCount" type="button" class="btn btn-primary mt-2" :disabled="busy" @click="confirmation.showModal()">
        {{ tr('Aplicar corrección', 'Apply correction') }}
      </button>
    </template>
    <dialog ref="confirmation" class="rounded border p-3" aria-labelledby="birthdate-confirm-title">
      <h2 id="birthdate-confirm-title">{{ tr('Confirmar corrección', 'Confirm correction') }}</h2>
      <p>{{ tr('Se actualizarán únicamente las fechas de nacimiento indicadas en la revisión.', 'Only the birth dates marked for correction in the preview will be updated.') }}</p>
      <form method="dialog" class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" autofocus>{{ tr('Cancelar', 'Cancel') }}</button>
        <button type="button" class="btn btn-primary" @click="confirmation.close(); submit(true)">{{ tr('Confirmar', 'Confirm') }}</button>
      </form>
    </dialog>
  </details>
</template>

<script setup>
import { computed, ref } from 'vue'
import { membersApi } from '../services/membersApi'
import { useUiStore } from '../stores/uiStore'

const emit = defineEmits(['corrected'])
const ui = useUiStore()
const tr = (es, en) => ui.locale === 'es' ? es : en
const file = ref(null)
const confirmation = ref(null)
const review = ref(null)
const updated = ref(null)
const busy = ref(false)
const error = ref('')
const readyCount = computed(() => review.value?.rows.filter(row => row.status === 'ready').length ?? 0)
const statusLabel = status => ({
  ready: tr('Por corregir', 'Ready to correct'),
  unchanged: tr('Ya coincide', 'Already correct'),
  invalid: tr('Fecha inválida o vacía', 'Invalid or empty date'),
  different: tr('La fecha actual difiere del CSV', 'Current date differs from CSV'),
  unmatched: tr('PersonId no encontrado', 'PersonId not found'),
})[status]

function chooseFile(event) {
  file.value = event.target.files[0] ?? null
  review.value = null
  updated.value = null
  error.value = ''
}

async function submit(apply) {
  if (busy.value || !file.value || (apply && !readyCount.value)) return
  busy.value = true
  error.value = ''
  updated.value = null
  const token = apply ? review.value.preview_token : null
  review.value = null
  try {
    const result = await membersApi.correctBirthDates(file.value, token)
    if (apply) {
      updated.value = result.updated
      emit('corrected')
    } else {
      review.value = result
    }
  } catch (failure) {
    error.value = failure.response?.data?.errors?.file?.[0] || failure.response?.data?.message ||
      tr('No se pudieron revisar o guardar las fechas.', 'Unable to preview or save the dates.')
  } finally {
    busy.value = false
  }
}
</script>

<style scoped>
.correction-preview { max-height: 28rem; overflow: auto; }
.correction-preview th { position: sticky; top: 0; background: white; }
dialog::backdrop { background: #0006; }
</style>
