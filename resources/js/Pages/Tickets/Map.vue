<template>
  <Head title="Карта заявок" />
  <div class="fixed inset-0 flex flex-col bg-white">

    <!-- Панель управления -->
    <div class="flex items-center gap-3 px-4 h-12 bg-white border-b border-gray-200 shrink-0">
      <a :href="route('tickets.index')"
         class="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Заявки
      </a>
      <span class="text-gray-300 select-none">|</span>
      <span class="font-semibold text-gray-700 text-sm">Карта</span>

      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 text-xs ml-2">
        <button v-for="p in periods" :key="p.value"
                @click="setPeriod(p.value)"
                :class="['px-3 py-1 rounded-lg font-medium transition-colors',
                         period === p.value
                           ? 'bg-white shadow text-gray-800'
                           : 'text-gray-500 hover:text-gray-700']">
          {{ p.label }}
        </button>
      </div>

      <span class="text-xs text-gray-400 ml-1">
        {{ loading ? 'Загрузка...' : points.length + ' заявок, ' + groupCount + ' адресов' }}
      </span>
    </div>

    <!-- Карта -->
    <div ref="mapEl" class="flex-1" />

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

defineOptions({ layout: null })

const mapEl  = ref(null)
const points = ref([])
const groupCount = ref(0)
const loading = ref(false)
const period  = ref('week')

const periods = [
  { value: 'today', label: 'Сегодня' },
  { value: 'week',  label: 'Неделя'  },
  { value: 'month', label: 'Месяц'   },
]

let map     = null
let markers = null

onMounted(async () => {
  map = L.map(mapEl.value, { center: [48.0835, 37.9742], zoom: 12 })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '\u00a9 OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)
  markers = L.layerGroup().addTo(map)
  await load()
})

onUnmounted(() => {
  if (map) { map.remove(); map = null }
})

async function load() {
  loading.value = true
  try {
    const res = await fetch(`${route('tickets.map-data')}?period=${period.value}`)
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    points.value = await res.json()
    render()
  } catch (e) {
    console.error('map load error:', e)
  } finally {
    loading.value = false
  }
}

function render() {
  markers.clearLayers()
  if (!points.value.length) return

  // Группируем точки с одинаковыми координатами
  const groups = new Map()
  for (const pt of points.value) {
    const key = `${pt.lat},${pt.lng}`
    if (!groups.has(key)) groups.set(key, [])
    groups.get(key).push(pt)
  }

  groupCount.value = groups.size
  const latlngs = []
  for (const [key, pts] of groups) {
    const [lat, lng] = key.split(',').map(Number)
    const count = pts.length
    latlngs.push([lat, lng])

    if (count === 1) {
      // Одна заявка — маленький кружок
      const col1 = ticketColor(1)
      const pt = pts[0]
      L.circleMarker([lat, lng], {
        radius: 6,
        fillColor: col1.fill,
        color: col1.stroke,
        weight: 1,
        opacity: 1,
        fillOpacity: 0.8,
      })
        .bindTooltip(
          `<b style="font-size:13px">${pt.num}</b> <span style="color:#6b7280;font-size:11px">${pt.type}</span><br>`
          + `<span style="color:#374151">${pt.addr}</span><br>`
          + `<span style="color:#6b7280;font-size:11px">${pt.date} &nbsp;·&nbsp; ${pt.status}</span><br>`
          + `<span style="color:#3b82f6;font-size:11px">&#8599; открыть заявку</span>`,
          { sticky: true, className: 'map-ticket-tooltip' }
        )
        .on('click', () => window.open(route('tickets.show', pt.id), '_blank'))
        .addTo(markers)
    } else {
      // Несколько заявок на одном адресе — иконка с числом
      const col = ticketColor(count)
      const sz  = clusterSize(count)
      const icon = L.divIcon({
        className: '',
        html: `<div class="map-cluster" style="width:${sz}px;height:${sz}px;line-height:${sz}px;background:${col.fill};border-color:${col.stroke}">${count}</div>`,
        iconSize: [sz, sz],
        iconAnchor: [sz / 2, sz / 2],
      })

      const listHtml = pts.slice(0, 8).map(p =>
        `<div style="padding:1px 0;border-bottom:1px solid #f0f0f0">`
        + `<b style="font-size:12px">${p.num}</b> <span style="color:#6b7280;font-size:11px">${p.type}</span> `
        + `<span style="color:#6b7280;font-size:11px">${p.date}</span>`
        + `</div>`
      ).join('')
      const more = pts.length > 8 ? `<div style="color:#6b7280;font-size:11px;padding-top:2px">+${pts.length - 8} ещё</div>` : ''

      L.marker([lat, lng], { icon })
        .bindTooltip(
          `<div style="min-width:200px">`
          + `<b style="font-size:13px">${pts[0].addr}</b><br>`
          + `<span style="color:#6b7280;font-size:11px">Заявок: ${count}</span>`
          + `<div style="margin-top:4px">${listHtml}${more}</div>`
          + `<span style="color:#3b82f6;font-size:11px">&#8599; кликните чтобы открыть список</span>`
          + `</div>`,
          { sticky: true, className: 'map-ticket-tooltip' }
        )
        .on('click', () => {
          // Открываем первые 5 заявок в отдельных вкладках
          pts.slice(0, 5).forEach(p => window.open(route('tickets.show', p.id), '_blank'))
        })
        .addTo(markers)
    }
  }

  map.fitBounds(L.latLngBounds(latlngs).pad(0.1))
}

function ticketColor(count) {
  if (count === 1) return { fill: '#8b5cf6', stroke: '#6d28d9' } // violet
  if (count <= 3)  return { fill: '#a855f7', stroke: '#7e22ce' } // purple
  if (count <= 7)  return { fill: '#ec4899', stroke: '#be185d' } // pink-red
  if (count <= 15) return { fill: '#f97316', stroke: '#c2410c' } // orange
  return                  { fill: '#ef4444', stroke: '#b91c1c' } // red
}

function clusterSize(count) {
  if (count < 5)  return 22
  if (count < 10) return 26
  if (count < 20) return 30
  return 34
}

function setPeriod(p) {
  period.value = p
  load()
}
</script>

<style>
.map-ticket-tooltip {
  padding: 6px 8px;
  line-height: 1.6;
  white-space: nowrap;
}
.map-cluster {
  background: #3b82f6;
  color: white;
  border-radius: 50%;
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  border: 2px solid #1e40af;
  box-shadow: 0 1px 4px rgba(0,0,0,0.3);
}
</style>
