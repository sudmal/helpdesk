<template>
  <Head title="Календарь" />
  <AppLayout title="Календарь заявок">
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <!-- Toolbar -->
      <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3 flex-wrap">
        <div class="flex gap-1 bg-gray-100 p-1 rounded-xl">
          <button v-for="v in views" :key="v.key"
                  @click="currentView = v.key"
                  :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                           currentView === v.key ? 'bg-white shadow text-blue-600' : 'text-gray-600 hover:text-gray-800']">
            {{ v.label }}
          </button>
        </div>

        <select v-model="selectedBrigade" @change="refetchEvents"
                class="border border-gray-200 rounded-xl px-3 py-1.5 text-sm">
          <option value="">Все бригады</option>
          <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>

        <div class="ml-auto flex items-center gap-2">
          <button @click="prev" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors">‹</button>
          <button @click="goToday" class="px-3 py-1.5 text-sm border border-gray-200 rounded-xl hover:bg-gray-50">Сегодня</button>
          <button @click="next" class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors">›</button>
          <span class="text-sm font-medium text-gray-700 min-w-[160px] text-center">{{ calendarTitle }}</span>
        </div>
      </div>

      <!-- FullCalendar -->
      <FullCalendar ref="calRef" :options="calOptions" class="helpdesk-calendar" />
    </div>

    <!-- Popup при клике на событие -->
    <div v-if="selectedEvent"
         class="fixed inset-0 z-50 flex items-end justify-center sm:items-center p-4"
         @click.self="selectedEvent = null">
      <div class="bg-white rounded-2xl border border-gray-200 shadow-xl p-5 max-w-sm w-full">
        <div class="flex items-center justify-between mb-3">
          <span class="font-mono text-sm text-blue-600">{{ selectedEvent.extendedProps.ticketNumber }}</span>
          <button @click="selectedEvent = null" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <p class="font-medium text-gray-800 mb-1">{{ selectedEvent.extendedProps.address }}</p>
        <div class="flex gap-2 mb-3">
          <Badge :color="selectedEvent.backgroundColor" :label="selectedEvent.extendedProps.status" />
          <Badge :color="selectedEvent.borderColor" :label="selectedEvent.extendedProps.type" />
        </div>
        <p class="text-xs text-gray-500 mb-1">Бригада: {{ selectedEvent.extendedProps.brigade ?? '—' }}</p>
        <p class="text-xs text-gray-500 mb-4">
          Выезд: {{ formatEventDate(selectedEvent.start) }}
        </p>
        <a :href="selectedEvent.extendedProps.url"
           class="block text-center bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl text-sm font-medium transition-colors">
          Открыть заявку
        </a>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'
import ruLocale from '@fullcalendar/core/locales/ru'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'
import dayjs from 'dayjs'

defineProps({ brigades: Array })

const calRef         = ref(null)
const currentView    = ref('dayGridMonth')
const selectedBrigade = ref('')
const selectedEvent  = ref(null)
const calendarTitle  = ref('')

const views = [
  { key: 'dayGridMonth', label: 'Месяц' },
  { key: 'timeGridWeek',  label: 'Неделя' },
  { key: 'timeGridDay',   label: 'День' },
]

watch(currentView, (v) => calRef.value?.getApi().changeView(v))

const calOptions = {
  plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
  locale: ruLocale,
  initialView: 'dayGridMonth',
  headerToolbar: false,
  height: 'auto',
  events: fetchEvents,
  eventClick: ({ event }) => { selectedEvent.value = event },
  datesSet: ({ view }) => { calendarTitle.value = view.title },
  eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
}

async function fetchEvents({ start, end }, successCb) {
  const { data } = await axios.get(route('calendar.events'), {
    params: {
      start: dayjs(start).toISOString(),
      end: dayjs(end).toISOString(),
      brigade_id: selectedBrigade.value || undefined,
    }
  })
  successCb(data)
}

function refetchEvents() { calRef.value?.getApi().refetchEvents() }
function prev()    { calRef.value?.getApi().prev() }
function next()    { calRef.value?.getApi().next() }
function goToday() { calRef.value?.getApi().today() }
function formatEventDate(d) { return dayjs(d).format('DD.MM.YYYY HH:mm') }
</script>

<style>
.helpdesk-calendar .fc-event { border-radius: 6px; font-size: 12px; padding: 1px 4px; cursor: pointer; }
.helpdesk-calendar .fc-daygrid-day-number { font-size: 13px; }
.helpdesk-calendar .fc-col-header-cell { font-size: 12px; font-weight: 500; }
</style>
