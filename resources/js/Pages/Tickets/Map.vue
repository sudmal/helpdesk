<template>
  <Head title="Карта заявок" />
  <div class="fixed inset-0 flex flex-col bg-white">

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

    <div ref="mapEl" class="flex-1" />

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'

defineOptions({ layout: null })

const mapEl      = ref(null)
const points     = ref([])
const groupCount = ref(0)
const loading    = ref(false)
const period     = ref('today')

const periods = [
  { value: 'today', label: 'Сегодня' },
  { value: 'week',  label: 'Неделя'  },
  { value: 'month', label: 'Месяц'   },
]

let ymapInstance = null

function loadYmaps() {
  if (window.ymaps) return Promise.resolve(window.ymaps)
  return new Promise((resolve, reject) => {
    const s = document.createElement('script')
    s.src = 'https://api-maps.yandex.ru/2.1/?apikey=1bda077f-7f7e-45a9-9a3c-75bf6a3ded73&lang=ru_RU'
    s.onerror = reject
    document.head.appendChild(s)
    s.onload = () => window.ymaps.ready(() => resolve(window.ymaps))
  })
}

onMounted(async () => {
  const ym = await loadYmaps()
  ymapInstance = new ym.Map(mapEl.value, {
    center: [48.0835, 37.9742],
    zoom: 12,
    controls: ['zoomControl', 'fullscreenControl'],
  })
  await load()
})

onUnmounted(() => {
  if (ymapInstance) { ymapInstance.destroy(); ymapInstance = null }
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
  if (!ymapInstance || !window.ymaps) return
  ymapInstance.geoObjects.removeAll()
  if (!points.value.length) return

  const ym = window.ymaps

  // Группируем по координатам
  const groups = new Map()
  for (const pt of points.value) {
    const key = `${pt.lat},${pt.lng}`
    if (!groups.has(key)) groups.set(key, [])
    groups.get(key).push(pt)
  }
  groupCount.value = groups.size

  for (const [key, pts] of groups) {
    const [lat, lng] = key.split(',').map(Number)
    const count = pts.length

    if (count === 1) {
      const pt = pts[0]
      // Одна заявка: hover = подсказка, click = открыть заявку
      const pm = new ym.Placemark([lat, lng], {
        hintContent:
          `<b>${pt.num}</b>&nbsp;<span style="color:#9ca3af;font-size:11px">${pt.type}</span><br>` +
          `<span style="color:#374151">${pt.addr}</span><br>` +
          `<span style="color:#9ca3af;font-size:11px">${pt.date} &nbsp;·&nbsp; ${pt.status}</span>`,
      }, {
        preset: 'islands#violetDotIcon',
      })
      pm.events.add('click', () => window.open(route('tickets.show', pt.id), '_blank'))
      ymapInstance.geoObjects.add(pm)

    } else {
      // Несколько заявок: hover = кол-во, click = балун со списком
      const col = clusterColor(count)
      const sz  = clusterSize(count)

      const iconLayout = ym.templateLayoutFactory.createClass(
        `<div style="` +
          `width:${sz}px;height:${sz}px;line-height:${sz}px;` +
          `background:${col.fill};border:2px solid ${col.stroke};` +
          `border-radius:50%;text-align:center;color:#fff;` +
          `font-size:${sz >= 30 ? 12 : 11}px;font-weight:700;` +
          `box-shadow:0 1px 4px rgba(0,0,0,.35);cursor:pointer` +
        `">${count}</div>`
      )

      const listHtml = pts.map(p =>
        `<div style="padding:4px 0;border-bottom:1px solid #f3f4f6">` +
        `<a href="${route('tickets.show', p.id)}" target="_blank" ` +
        `style="color:#3b82f6;font-weight:600;text-decoration:none">${p.num}</a>` +
        `<span style="color:#6b7280;font-size:11px;margin-left:8px">${p.type}</span>` +
        `<span style="color:#9ca3af;font-size:11px;margin-left:6px">${p.date}</span>` +
        `<span style="color:#6b7280;font-size:11px;margin-left:6px">${p.status}</span>` +
        `</div>`
      ).join('')

      const pm = new ym.Placemark([lat, lng], {
        hintContent:
          `<b>${pts[0].addr}</b><br>` +
          `<span style="color:#9ca3af">Заявок: ${count}</span>`,
        balloonContentHeader:
          `<b>${pts[0].addr}</b>&nbsp;` +
          `<span style="color:#9ca3af;font-size:12px">${count} заявки</span>`,
        balloonContentBody:
          `<div style="min-width:280px;max-height:320px;overflow-y:auto">${listHtml}</div>`,
      }, {
        iconLayout,
        iconShape: { type: 'Circle', coordinates: [0, 0], radius: sz / 2 },
      })

      ymapInstance.geoObjects.add(pm)
    }
  }

  const bounds = ymapInstance.geoObjects.getBounds()
  if (bounds) {
    ymapInstance.setBounds(bounds, { checkZoomRange: true, zoomMargin: 50 })
  }
}

function clusterColor(count) {
  if (count <= 3)  return { fill: '#a855f7', stroke: '#7e22ce' }
  if (count <= 7)  return { fill: '#ec4899', stroke: '#be185d' }
  if (count <= 15) return { fill: '#f97316', stroke: '#c2410c' }
  return                  { fill: '#ef4444', stroke: '#b91c1c' }
}

function clusterSize(count) {
  if (count < 5)  return 24
  if (count < 10) return 28
  if (count < 20) return 32
  return 36
}

function setPeriod(p) {
  period.value = p
  load()
}
</script>
