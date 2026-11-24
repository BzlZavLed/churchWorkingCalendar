<template>
  <div class="chart-canvas"><canvas ref="canvas" role="img" :aria-label="label"></canvas></div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Chart, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend)
const props = defineProps({ points: { type: Array, required: true }, series: { type: Array, required: true }, label: { type: String, required: true }, selected: { type: String, default: '' } })
const emit = defineEmits(['select'])
const canvas = ref(null)
let chart
function update() {
  if (!chart) return
  chart.data.labels = props.points.map(point => point.date)
  const colors = ['#1f795a', '#2563a6', '#b35b1d', '#874cb0', '#bb4063', '#807000', '#16888b', '#855a45']
  chart.data.datasets = props.series.map((series, index) => ({ label: series.label, data: series.values, borderColor: colors[index % colors.length], backgroundColor: '#fff', pointBorderColor: colors[index % colors.length], pointBorderWidth: 3, pointRadius: props.points.map(point => point.date === props.selected ? 7 : 5), pointHoverRadius: 8, spanGaps: true, tension: 0, borderWidth: 2 }))
  chart.update()
}
onMounted(() => {
  chart = new Chart(canvas.value, {
    type: 'line',
    data: { labels: [], datasets: [] },
    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'nearest', intersect: false }, plugins: { legend: { display: true, position: 'bottom' } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 10 } } }, onClick: (_event, elements) => { if (elements.length) emit('select', props.points[elements[0].index].date) } },
  })
  update()
})
watch(() => [props.points, props.series, props.selected], update, { deep: true })
onBeforeUnmount(() => chart?.destroy())
</script>

<style scoped>
.chart-canvas{position:relative;height:260px;min-width:0}
</style>
