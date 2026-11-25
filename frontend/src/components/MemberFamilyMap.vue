<template>
  <section class="management-surface member-panel">
    <h2>{{ tr('Mapa de familias', 'Family map') }}</h2>
    <p v-if="loading" role="status">{{ tr('Cargando ubicaciones…', 'Loading locations…') }}</p>
    <p v-else-if="failed" role="alert">{{ tr('No se pudo cargar el mapa de familias.', 'Could not load family locations.') }}</p>
    <template v-else>
      <p v-if="locations.length">{{ locations.length }} {{ tr('ubicaciones familiares', 'family locations') }}</p>
      <p v-else>{{ tr('No hay ubicaciones familiares para mostrar.', 'There are no family locations to display.') }}</p>
      <div class="family-map-actions">
        <button v-if="pending.length" type="button" class="btn btn-outline-primary" aria-haspopup="dialog" @click="addressDialog.showModal()">
          {{ pendingLabel }}
        </button>
        <button v-if="directory.length" type="button" class="btn btn-outline-secondary" @click="downloadAddresses">
          {{ tr('Descargar direcciones (CSV)', 'Download addresses (CSV)') }}
        </button>
      </div>
      <dialog ref="addressDialog" class="family-address-dialog" aria-labelledby="family-address-title">
        <h2 id="family-address-title">{{ tr('Direcciones por revisar', 'Addresses to review') }}</h2>
        <form method="dialog"><button type="submit" class="btn btn-outline-secondary">{{ tr('Cerrar', 'Close') }}</button></form>
        <ul>
          <li v-for="member in pending" :key="member.id">
            <router-link :to="`/members/${member.id}`">{{ member.name || '#' + member.id }}</router-link>
            <small v-if="member.missing_address">{{ tr('Sin dirección completa', 'Missing street address') }}</small>
            <small v-else>{{ tr('Sin coordenadas familiares', 'Missing family coordinates') }}</small>
            <div v-if="member.address">{{ member.address }}</div>
          </li>
        </ul>
      </dialog>
      <div v-if="locations.length" ref="canvas" class="family-map" :aria-label="tr('Mapa de domicilios familiares', 'Family address map')"></div>
    </template>
  </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { membersApi } from '../services/membersApi'
import { useUiStore } from '../stores/uiStore'

const ui = useUiStore()
const tr = (es, en) => ui.locale === 'es' ? es : en
const canvas = ref(null)
const locations = ref([])
const missing = ref(0)
const directory = ref([])
const addressDialog = ref(null)
const pending = computed(() => directory.value.filter(member => member.missing_address || member.family_unmapped))
const pendingLabel = computed(() => {
  if (missing.value === 1) return tr('1 familia sin coordenadas', '1 family without coordinates')
  if (missing.value > 1) return tr(`${missing.value} familias sin coordenadas`, `${missing.value} families without coordinates`)
  return tr('Miembros sin dirección', 'Members without an address')
})
const loading = ref(true)
const failed = ref(false)
let map

function downloadAddresses() {
  const csvCell = value => {
    const text = String(value ?? '')
    const safe = /^(?:\s*[=+@-]|[\t\r\n])/.test(text) ? "'" + text : text
    return '"' + safe.replaceAll('"', '""') + '"'
  }
  const rows = [
    [tr('Miembro', 'Member'), tr('Dirección registrada', 'Recorded address')],
    ...directory.value.map(member => [member.name, member.address]),
  ]
  const csv = rows.map(row => row.map(csvCell).join(',')).join('\r\n')
  const url = URL.createObjectURL(new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = tr('direcciones-de-miembros.csv', 'member-addresses.csv')
  link.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

onMounted(async () => {
  try {
    const result = await membersApi.familyMap()
    locations.value = result.locations
    missing.value = result.missing
    directory.value = result.directory
    loading.value = false
    await nextTick()
    if (!canvas.value) return

    map = L.map(canvas.value, { scrollWheelZoom: false })
    const markers = L.featureGroup().addTo(map)
    const byPosition = new Map()
    for (const location of locations.value) {
      const position = [location.lat, location.lng]
      const key = position.join(',')
      if (!byPosition.has(key)) byPosition.set(key, { position, families: [] })
      byPosition.get(key).families.push(location)
    }
    for (const { position, families } of byPosition.values()) {
      const popup = document.createElement('div')
      for (const family of families) {
        const heading = document.createElement('strong')
        heading.textContent = family.names.join(', ') || tr('Familia', 'Family')
        const address = document.createElement('p')
        address.textContent = family.address
        popup.append(heading, address)
      }
      const names = families.flatMap(family => family.names).join(', ')
      L.marker(position, {
        icon: L.divIcon({ className: 'family-map-pin', iconSize: [18, 18] }),
        title: names || tr('Ubicación familiar', 'Family location'),
      }).bindPopup(popup).addTo(markers)
    }
    map.fitBounds(markers.getBounds(), { padding: [30, 30], maxZoom: 13 })
    L.tileLayer(import.meta.env.VITE_FAMILY_MAP_TILES || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>',
      maxZoom: 19,
    }).addTo(map)
  } catch {
    map?.remove()
    map = undefined
    failed.value = true
    loading.value = false
  }
})

onBeforeUnmount(() => map?.remove())
</script>

<style scoped>
.family-map-actions {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  margin-bottom: .75rem;
}
.family-address-dialog {
  width: min(36rem, calc(100% - 2rem));
  max-height: 80vh;
  padding: 1rem;
  border: 1px solid #d8e1dc;
  border-radius: .75rem;
}
.family-address-dialog::backdrop { background: #0006; }
.family-address-dialog ul { padding: 0; margin: .75rem 0 0; list-style: none; }
.family-address-dialog li { padding: .5rem 0; border-bottom: 1px solid #d8e1dc; overflow-wrap: anywhere; }
.family-address-dialog small { display: block; }
.family-map {
  height: clamp(320px, 62vh, 640px);
  isolation: isolate;
  border: 1px solid #d8e1dc;
  border-radius: .75rem;
}
.family-map :deep(.family-map-pin) {
  background: #206958;
  border: 2px solid white;
  border-radius: 50%;
  box-shadow: 0 1px 4px #0006;
}
</style>
