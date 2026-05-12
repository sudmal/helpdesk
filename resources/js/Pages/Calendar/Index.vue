<template>
  <Head title="Календарь заявок" />
  <AppLayout title="Календарь заявок">

    <!-- Участки -->
    <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 mb-2 flex items-center gap-2 flex-wrap">
      <span class="text-xs text-gray-400 font-medium">Участок:</span>
      <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
        <button @click="selectedServiceType = null; reload()"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                         !selectedServiceType ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700']">
          Все
        </button>
        <button v-for="st in serviceTypes" :key="st.id"
                @click="selectedServiceType = st.id; reload()"
                :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                         selectedServiceType === st.id ? 'bg-white shadow-sm text-gray-800' : 'text-gray-500 hover:text-gray-700']">
          {{ serviceIcon(st.name) }} {{ st.name }}
        </button>
      </div>
    </div>

    <!-- Территории + бригада -->
    <div class="bg-white rounded-2xl border border-gray-200 px-4 py-3 mb-4 flex flex-wrap items-center gap-3">
      <div class="flex gap-1 flex-wrap">
        <button @click="selectedTerritory = null; reload()"
                :class="['px-3 py-1.5 rounded-xl text-sm font-medium transition-colors',
                         !selectedTerritory ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100']">
          Все
        </button>
        <button v-for="t in territories" :key="t.id"
                @click="selectedTerritory = t.id; reload()"
                :class="['px-3 py-1.5 rounded-xl text-sm font-medium transition-colors',
                         selectedTerritory === t.id ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100']">
          {{ t.name }}
        </button>
      </div>
      <div class="h-5 border-l border-gray-200 hidden md:block"></div>
      <select v-model="selectedBrigade" @change="reload()"
              class="border border-gray-200 rounded-xl px-3 py-1.5 text-sm bg-white
                     focus:outline-none focus:ring-2 focus:ring-blue-500/30">
        <option :value="null">Все бригады</option>
        <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
    </div>

    <!-- Календарь -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden p-4">
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
import { ref, reactive, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import ruLocale from '@fullcalendar/core/locales/ru'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  brigades:    { type: Array, default: () => [] },
  territories: { type: Array, default: () => [] },
  serviceTypes: { type: Array, default: () => [] },
})

const calRef            = ref(null)
const selectedBrigade   = ref(null)
const selectedTerritory = ref(null)
const calKey            = ref(0)
const selectedServiceType = ref(null)

function reload() {
  calKey.value++
}

// Tooltip
const tooltip = reactive({
  show: false, x: 0, y: 0,
  number: '', address: '', scheduled: '', type: '',
  phone: '', description: '',
})

// Попап при клике
const popup = reactive({
  show: false, number: '', address: '',
  status: '', statusColor: '', type: '', typeColor: '',
  brigade: '', scheduled: '', phone: '', description: '', url: '',
})

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
  plugins:     [dayGridPlugin, timeGridPlugin, interactionPlugin],
  locale:      ruLocale,
  initialView: 'dayGridMonth',
  timeZone:    'local',
  headerToolbar: {
    left:   'prev,next today',
    center: 'title',
    right:  'dayGridMonth,timeGridWeek,timeGridDay',
  },
  displayEventTime: true,
  eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

  events(fetchInfo, successCallback, failureCallback) {
    const params = new URLSearchParams({
      start:           fetchInfo.startStr,
      end:             fetchInfo.endStr,
      brigade_id:      selectedBrigade.value    ?? '',
      territory_id:    selectedTerritory.value  ?? '',
      service_type_id: selectedServiceType.value ?? '',
    })
    fetch(`/calendar/events?${params}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
      }
    })
      .then(r => r.ok ? r.json() : Promise.reject(r.status))
      .then(successCallback)
      .catch(e => { console.error('Calendar load failed:', e); failureCallback(e) })
  },

  // Клик — открываем попап
  eventClick(info) {
    info.jsEvent.preventDefault()
    const p = info.event.extendedProps
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
  },

  // Наведение — показываем tooltip
  eventMouseEnter(info) {
    const p = info.event.extendedProps
    const rect = info.el.getBoundingClientRect()
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
  },

  eventMouseLeave() {
    tooltip.show = false
  },

  eventContent(arg) {
    const time  = arg.timeText
    const title = arg.event.title
    return {
      html: `<div style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;padding:1px 4px;font-size:0.75rem;cursor:pointer">
               <b>${time}</b> ${title}
             </div>`
    }
  },
}))
</script>
