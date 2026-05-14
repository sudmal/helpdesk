<template>
  <Head title="Календарь заявок" />
  <AppLayout title="Календарь заявок">

    <!-- Участки -->
    <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 mb-2 flex items-center gap-2 flex-wrap">
      <span class="text-xs text-gray-400 font-medium">Участок:</span>
      <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1 overflow-x-auto">
        <button @click="selectedServiceType = null; onFilterChange()"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                         !selectedServiceType ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700']">
          Все
        </button>
        <button v-for="st in serviceTypes" :key="st.id"
                @click="selectedServiceType = st.id; onFilterChange()"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                         selectedServiceType === st.id ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700']">
          {{ serviceIcon(st.name) }} {{ st.name }}
        </button>
      </div>
    </div>

    <!-- Территории + бригада -->
    <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 mb-3 flex flex-wrap items-center gap-3">
      <div class="flex gap-1 flex-wrap">
        <button @click="selectedTerritory = null; onFilterChange()"
                :class="['px-3 py-1.5 rounded-xl text-sm font-medium transition-colors',
                         !selectedTerritory ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100']">
          Все
        </button>
        <button v-for="t in territories" :key="t.id"
                @click="selectedTerritory = t.id; onFilterChange()"
                :class="['px-3 py-1.5 rounded-xl text-sm font-medium transition-colors',
                         selectedTerritory === t.id ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100']">
          {{ t.name }}
        </button>
      </div>
      <div class="h-5 border-l border-gray-200 hidden md:block"></div>
      <select v-model="selectedBrigade" @change="onFilterChange()"
              class="border border-gray-200 rounded-xl px-3 py-1.5 text-sm bg-white
                     focus:outline-none focus:ring-2 focus:ring-blue-500/30">
        <option :value="null">Все бригады</option>
        <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
    </div>

    <!-- Переключатель вида -->
    <div class="flex gap-1 mb-4 bg-gray-100 p-1 rounded-xl w-fit">
      <button @click="view = 'overview'"
              :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                       view === 'overview' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700']">
        Обзор
      </button>
      <button @click="view = 'month'"
              :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                       view === 'month' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700']">
        Месяц
      </button>
    </div>

    <!-- ОБЗОР -->
    <template v-if="view === 'overview'">
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

        <div class="overflow-y-auto" style="max-height: 680px">
          <!-- Sticky header -->
          <div class="flex sticky top-0 bg-white z-10 border-b border-gray-200">
            <div class="flex-shrink-0 border-r border-gray-100" style="width: 60px"></div>
            <div v-for="col in columns" :key="col.key"
                 class="flex-1 px-3 py-3 border-r border-gray-100 last:border-r-0"
                 :class="col.key === 'overdue' ? 'bg-red-50' : 'bg-gray-50'">
              <div class="text-sm font-semibold"
                   :class="col.key === 'overdue' ? 'text-red-700' : 'text-gray-700'">
                {{ col.title }}
                <span class="ml-1 text-xs font-normal opacity-60">
                  ({{ (overviewEvents[col.key] ?? []).length }})
                </span>
              </div>
              <div class="text-xs text-gray-400 mt-0.5">{{ col.subtitle }}</div>
            </div>
          </div>

          <!-- Loading -->
          <div v-if="loading" class="py-10 text-center text-sm text-gray-400">Загрузка...</div>

          <!-- Grid body -->
          <div v-else class="flex items-start">

            <!-- Time labels -->
            <div class="flex-shrink-0 border-r border-gray-100" style="width: 60px">
              <div v-for="(slot, i) in timeSlots" :key="slot.minutes"
                   class="flex items-start justify-end pr-2 pt-0.5 border-b border-gray-100 text-xs text-gray-400"
                   :style="{ height: slotHeights[i] + 'px' }">
                {{ slot.label }}
              </div>
            </div>

            <!-- Overdue column — simple list -->
            <div class="flex-1 border-r border-gray-100 p-1.5 flex flex-col gap-0.5 self-stretch">
              <p v-if="!sortedOverdue.length" class="text-xs text-gray-400 px-2 py-2">Нет просроченных</p>
              <div v-for="ev in sortedOverdue" :key="ev.id"
                   class="rounded overflow-hidden cursor-pointer hover:opacity-80 transition-opacity select-none flex-shrink-0"
                   :style="{
                     height:          EVENT_H + 'px',
                     backgroundColor: ev.backgroundColor,
                     borderLeft:      '3px solid ' + ev.borderColor,
                   }"
                   @mouseenter="onEventEnter($event.currentTarget, ev.extendedProps)"
                   @mouseleave="tooltip.show = false"
                   @click="openPopup(ev.extendedProps)">
                <div class="px-1.5 flex items-center gap-1 overflow-hidden h-full">
                  <span v-if="titleParts(ev).icon" class="flex-shrink-0 leading-none">{{ titleParts(ev).icon }}</span>
                  <span class="text-gray-400 font-normal lowercase whitespace-nowrap flex-shrink-0" style="font-size: 10px">{{ titleParts(ev).type }}</span>
                  <span v-if="titleParts(ev).type" class="text-gray-300 flex-shrink-0" style="font-size: 10px">·</span>
                  <span class="truncate text-xs font-medium text-gray-800 min-w-0">{{ titleParts(ev).address }}</span>
                </div>
              </div>
            </div>

            <!-- Today column -->
            <div class="flex-1 relative border-r border-gray-100"
                 :style="{ height: totalGridH + 'px' }">
              <div v-for="(_, i) in timeSlots" :key="i"
                   class="absolute w-full border-b border-gray-100"
                   :style="{ top: slotTops[i] + 'px', height: slotHeights[i] + 'px' }"></div>
              <div v-for="ev in positionedEvents.today" :key="ev.id"
                   class="absolute rounded overflow-hidden cursor-pointer hover:opacity-80 transition-opacity select-none"
                   :style="{
                     top:             ev.top + 'px',
                     left:            '2px',
                     right:           '2px',
                     height:          EVENT_H + 'px',
                     backgroundColor: ev.backgroundColor,
                     borderLeft:      '3px solid ' + ev.borderColor,
                   }"
                   @mouseenter="onEventEnter($event.currentTarget, ev.extendedProps)"
                   @mouseleave="tooltip.show = false"
                   @click="openPopup(ev.extendedProps)">
                <div class="px-1.5 flex items-center gap-1 overflow-hidden h-full">
                  <span v-if="titleParts(ev).icon" class="flex-shrink-0 leading-none">{{ titleParts(ev).icon }}</span>
                  <span class="text-gray-400 font-normal lowercase whitespace-nowrap flex-shrink-0" style="font-size: 10px">{{ titleParts(ev).type }}</span>
                  <span v-if="titleParts(ev).type" class="text-gray-300 flex-shrink-0" style="font-size: 10px">·</span>
                  <span class="truncate text-xs font-medium text-gray-800 min-w-0">{{ titleParts(ev).address }}</span>
                </div>
              </div>
            </div>

            <!-- Tomorrow column -->
            <div class="flex-1 relative"
                 :style="{ height: totalGridH + 'px' }">
              <div v-for="(_, i) in timeSlots" :key="i"
                   class="absolute w-full border-b border-gray-100"
                   :style="{ top: slotTops[i] + 'px', height: slotHeights[i] + 'px' }"></div>
              <div v-for="ev in positionedEvents.tomorrow" :key="ev.id"
                   class="absolute rounded overflow-hidden cursor-pointer hover:opacity-80 transition-opacity select-none"
                   :style="{
                     top:             ev.top + 'px',
                     left:            '2px',
                     right:           '2px',
                     height:          EVENT_H + 'px',
                     backgroundColor: ev.backgroundColor,
                     borderLeft:      '3px solid ' + ev.borderColor,
                   }"
                   @mouseenter="onEventEnter($event.currentTarget, ev.extendedProps)"
                   @mouseleave="tooltip.show = false"
                   @click="openPopup(ev.extendedProps)">
                <div class="px-1.5 flex items-center gap-1 overflow-hidden h-full">
                  <span v-if="titleParts(ev).icon" class="flex-shrink-0 leading-none">{{ titleParts(ev).icon }}</span>
                  <span class="text-gray-400 font-normal lowercase whitespace-nowrap flex-shrink-0" style="font-size: 10px">{{ titleParts(ev).type }}</span>
                  <span v-if="titleParts(ev).type" class="text-gray-300 flex-shrink-0" style="font-size: 10px">·</span>
                  <span class="truncate text-xs font-medium text-gray-800 min-w-0">{{ titleParts(ev).address }}</span>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </template>

    <!-- МЕСЯЦ -->
    <div v-if="view === 'month'" class="bg-white rounded-2xl border border-gray-200 p-4">
      <FullCalendar :key="calKey" ref="calRef" :options="calOptions" />
    </div>

    <!-- Tooltip при наведении -->
    <Teleport to="body">
      <div v-if="tooltip.show"
           :style="{ position: 'fixed', left: tooltip.x + 'px', top: tooltip.y + 'px', zIndex: 9999 }"
           class="bg-gray-900 text-white rounded-xl shadow-2xl p-3 max-w-xs pointer-events-none">
        <p class="font-mono text-xs text-gray-400 mb-1">{{ tooltip.number }}</p>
        <p class="font-semibold text-sm mb-1">{{ tooltip.address }}</p>
        <p class="text-xs text-gray-300 mb-1">🕐 {{ tooltip.scheduled }} · {{ tooltip.type }}</p>
        <p v-if="tooltip.phone" class="text-xs text-gray-300 mb-1">📞 {{ tooltip.phone }}</p>
        <p v-if="tooltip.description" class="text-xs text-gray-400 border-t border-gray-700 pt-1 mt-1">
          {{ tooltip.description.slice(0, 100) }}{{ tooltip.description.length > 100 ? '…' : '' }}
        </p>
      </div>
    </Teleport>

    <!-- Попап при клике -->
    <Teleport to="body">
      <div v-if="popup.show"
           class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
           @click.self="popup.show = false">
        <div class="bg-white rounded-2xl shadow-2xl w-96 overflow-hidden">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
              <p class="text-xs text-gray-400 font-mono">{{ popup.number }}</p>
              <h3 class="font-semibold text-gray-800">{{ popup.address }}</h3>
            </div>
            <button @click="popup.show = false"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
          </div>
          <div class="px-5 py-4 space-y-2 text-sm">
            <div class="flex gap-2 flex-wrap">
              <span class="px-2.5 py-1 rounded-full text-xs font-medium text-gray-800 border"
                    :style="{ backgroundColor: popup.statusColor + '25', borderColor: popup.statusColor + '50' }">
                ● {{ popup.status }}
              </span>
              <span class="px-2.5 py-1 rounded-full text-xs font-medium text-gray-800 border"
                    :style="{ backgroundColor: popup.typeColor + '25', borderColor: popup.typeColor + '50' }">
                {{ popup.type }}
              </span>
            </div>
            <p class="text-gray-600">📅 {{ popup.scheduled }}</p>
            <p v-if="popup.phone" class="text-gray-600">📞 {{ popup.phone }}</p>
            <p class="text-gray-500">Бригада: {{ popup.brigade || '—' }}</p>
            <p v-if="popup.description"
               class="text-gray-500 text-xs bg-gray-50 rounded-lg p-2 leading-relaxed">
              {{ popup.description }}
            </p>
          </div>
          <div class="px-5 pb-4">
            <a :href="popup.url"
               class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white
                      py-2.5 rounded-xl font-medium text-sm transition-colors">
              Открыть заявку →
            </a>
          </div>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import ruLocale from '@fullcalendar/core/locales/ru'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  brigades:     { type: Array,  default: () => [] },
  territories:  { type: Array,  default: () => [] },
  serviceTypes: { type: Array,  default: () => [] },
  workSettings: { type: Object, default: () => ({ start: '09:00', end: '17:00', step: 30 }) },
})

const BASE_H  = 22
const EVENT_H = 20
const PAD     = 2

const view                = ref('overview')
const calKey              = ref(0)
const calRef              = ref(null)
const selectedBrigade     = ref(null)
const selectedTerritory   = ref(null)
const selectedServiceType = ref(null)
const loading             = ref(false)
const overviewEvents      = ref({ overdue: [], today: [], tomorrow: [] })

const tooltip = reactive({
  show: false, x: 0, y: 0,
  number: '', address: '', scheduled: '', type: '', phone: '', description: '',
})

const popup = reactive({
  show: false, number: '', address: '',
  status: '', statusColor: '', type: '', typeColor: '',
  brigade: '', scheduled: '', phone: '', description: '', url: '',
})

// Split event title into { icon, type, address } for styled rendering
function titleParts(ev) {
  const type   = ev.extendedProps?.type ?? ''
  const dotIdx = ev.title.indexOf(' · ')
  if (!type || dotIdx === -1) return { icon: '', type: '', address: ev.title }
  const iconType = ev.title.slice(0, dotIdx).trim()
  const address  = ev.title.slice(dotIdx + 3)
  const icon     = iconType.replace(type, '').trim()
  return { icon, type, address }
}

const timeSlots = computed(() => {
  const [sh, sm] = props.workSettings.start.split(':').map(Number)
  const [eh, em] = props.workSettings.end.split(':').map(Number)
  const startM = sh * 60 + sm
  const endM   = eh * 60 + em
  const step   = props.workSettings.step
  const slots  = []
  for (let m = startM; m < endM; m += step) {
    slots.push({
      minutes: m,
      label:   `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`,
    })
  }
  return slots
})

function getSlotIdx(startStr) {
  const timePart = (startStr || '').split('T')[1] || '00:00:00'
  const [h, m]   = timePart.split(':').map(Number)
  const [sh, sm] = props.workSettings.start.split(':').map(Number)
  return Math.floor(((h * 60 + m) - (sh * 60 + sm)) / props.workSettings.step)
}

const slotHeights = computed(() => {
  const heights = timeSlots.value.map(() => BASE_H)
  for (const col of ['today', 'tomorrow']) {
    const bySlot = {}
    for (const ev of (overviewEvents.value[col] ?? [])) {
      const si = Math.max(0, Math.min(getSlotIdx(ev.start), timeSlots.value.length - 1))
      bySlot[si] = (bySlot[si] ?? 0) + 1
    }
    for (const [si, count] of Object.entries(bySlot)) {
      heights[Number(si)] = Math.max(heights[Number(si)], count * EVENT_H + PAD * 2)
    }
  }
  return heights
})

const slotTops = computed(() => {
  const tops = []
  let acc = 0
  for (const h of slotHeights.value) { tops.push(acc); acc += h }
  return tops
})

const totalGridH = computed(() =>
  slotTops.value.length
    ? slotTops.value[slotTops.value.length - 1] + slotHeights.value[slotHeights.value.length - 1]
    : BASE_H
)

const sortedOverdue = computed(() =>
  [...(overviewEvents.value.overdue ?? [])].sort((a, b) => b.start.localeCompare(a.start))
)

const positionedEvents = computed(() => {
  const result = {}
  for (const col of ['today', 'tomorrow']) {
    const bySlot = {}
    for (const ev of (overviewEvents.value[col] ?? [])) {
      const si = Math.max(0, Math.min(getSlotIdx(ev.start), timeSlots.value.length - 1))
      if (!bySlot[si]) bySlot[si] = []
      bySlot[si].push(ev)
    }
    const positioned = []
    for (const [siStr, slotEvs] of Object.entries(bySlot)) {
      const si  = Number(siStr)
      const top = slotTops.value[si]
      slotEvs.forEach((ev, i) => positioned.push({ ...ev, top: top + PAD + i * EVENT_H }))
    }
    result[col] = positioned
  }
  return result
})

function fmtDate(d) {
  return `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}`
}

const columns = computed(() => {
  const today    = new Date()
  const tomorrow = new Date(today)
  tomorrow.setDate(today.getDate() + 1)
  return [
    { key: 'overdue',  title: 'Просроченные', subtitle: `до ${fmtDate(today)}` },
    { key: 'today',    title: 'Сегодня',       subtitle: fmtDate(today) },
    { key: 'tomorrow', title: 'Завтра',         subtitle: fmtDate(tomorrow) },
  ]
})

function isoDate(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function fetchJson(params) {
  return fetch(`/calendar/events?${new URLSearchParams(params)}`, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept':           'application/json',
      'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    },
  }).then(r => r.ok ? r.json() : Promise.reject(r.status))
}

async function fetchOverview() {
  loading.value = true
  const now  = new Date()
  now.setHours(0, 0, 0, 0)
  const tom  = new Date(now)
  tom.setDate(tom.getDate() + 1)
  const todayStart = isoDate(now) + 'T00:00:00'
  const todayEnd   = isoDate(now) + 'T23:59:59'
  const tomStart   = isoDate(tom) + 'T00:00:00'
  const tomEnd     = isoDate(tom) + 'T23:59:59'
  const common = {
    brigade_id:      selectedBrigade.value     ?? '',
    territory_id:    selectedTerritory.value   ?? '',
    service_type_id: selectedServiceType.value ?? '',
  }
  try {
    const [overdue, today, tomorrow] = await Promise.all([
      fetchJson({ ...common, start: '2000-01-01T00:00:00', end: todayStart, overdue: '1' }),
      fetchJson({ ...common, start: todayStart, end: todayEnd }),
      fetchJson({ ...common, start: tomStart,   end: tomEnd }),
    ])
    overviewEvents.value = { overdue, today, tomorrow }
  } catch (e) {
    console.error('Overview fetch failed:', e)
  } finally {
    loading.value = false
  }
}

function onFilterChange() {
  if (view.value === 'overview') fetchOverview()
  else calKey.value++
}

watch(view, v => { if (v === 'overview') fetchOverview() })
onMounted(fetchOverview)

function onEventEnter(el, p) {
  const rect = el.getBoundingClientRect()
  Object.assign(tooltip, {
    show:        true,
    x:           Math.min(rect.right + 8, window.innerWidth - 320),
    y:           Math.max(rect.top, 8),
    number:      p.ticketNumber,
    address:     p.address,
    scheduled:   p.scheduled,
    type:        p.type,
    phone:       p.phone,
    description: p.description,
  })
}

function openPopup(p) {
  Object.assign(popup, {
    show:        true,
    number:      p.ticketNumber,
    address:     p.address,
    status:      p.status,
    statusColor: p.statusColor,
    type:        p.type,
    typeColor:   p.typeColor,
    brigade:     p.brigade,
    scheduled:   p.scheduled,
    phone:       p.phone,
    description: p.description,
    url:         p.url,
  })
}

const SERVICE_ICONS = { 'интернет': '🌐', 'inet': '🌐', 'ктв': '📺', 'ctv': '📺', 'волс': '🔆', 'подключ': '🟢' }
function serviceIcon(name) {
  if (!name) return '📋'
  const k = name.toLowerCase()
  for (const [key, icon] of Object.entries(SERVICE_ICONS)) {
    if (k.includes(key)) return icon
  }
  return '📋'
}

const calOptions = computed(() => ({
  plugins:       [dayGridPlugin, interactionPlugin],
  locale:        ruLocale,
  initialView:   'dayGridMonth',
  timeZone:      'local',
  contentHeight: 'auto',
  headerToolbar: {
    left:   'prev,next today',
    center: 'title',
    right:  '',
  },
  displayEventTime: true,
  eventTimeFormat:  { hour: '2-digit', minute: '2-digit', hour12: false },

  events(fetchInfo, successCallback, failureCallback) {
    const params = new URLSearchParams({
      start:           fetchInfo.startStr,
      end:             fetchInfo.endStr,
      brigade_id:      selectedBrigade.value     ?? '',
      territory_id:    selectedTerritory.value   ?? '',
      service_type_id: selectedServiceType.value ?? '',
    })
    fetch(`/calendar/events?${params}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept':           'application/json',
        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      },
    })
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(successCallback)
      .catch(e => { console.error('Calendar load failed:', e); failureCallback(e) })
  },

  eventClick(info) {
    info.jsEvent.preventDefault()
    openPopup(info.event.extendedProps)
  },

  eventMouseEnter(info) {
    onEventEnter(info.el, info.event.extendedProps)
  },

  eventMouseLeave() {
    tooltip.show = false
  },

  eventContent(arg) {
    const p      = arg.event.extendedProps
    const time   = arg.timeText
    const type   = p.type || ''
    const dotIdx = arg.event.title.indexOf(' · ')
    let body
    if (type && dotIdx !== -1) {
      const iconType = arg.event.title.slice(0, dotIdx).trim()
      const address  = arg.event.title.slice(dotIdx + 3)
      const icon     = iconType.replace(type, '').trim()
      body = `${icon} <span style="font-size:10px;font-weight:400;color:#9ca3af;text-transform:lowercase">${type}</span> · ${address}`
    } else {
      body = arg.event.title
    }
    return {
      html: `<div style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;padding:1px 4px;font-size:0.75rem;cursor:pointer"><b>${time}</b> ${body}</div>`,
    }
  },
}))
</script>
