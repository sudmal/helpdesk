<template>
  <Head :title="`Расписание — ${brigade.name}`" />
  <AppLayout :title="`Расписание: ${brigade.name}`">

    <!-- Toolbar (скрыт при печати) -->
    <div class="flex flex-wrap items-center gap-3 mb-5 print:hidden">
      <div class="flex items-center gap-1">
        <button @click="changeMonth(-1)" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <span class="text-sm font-semibold text-gray-700 min-w-[120px] text-center">{{ monthLabel }}</span>
        <button @click="changeMonth(1)" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>

      <div class="flex gap-1 bg-gray-100 rounded-xl p-1 text-xs">
        <button @click="mode = 'mark'"
                :class="['px-3 py-1 rounded-lg font-medium transition-colors',
                         mode === 'mark' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
          Отметить пожелания
        </button>
        <button @click="mode = 'edit'"
                :class="['px-3 py-1 rounded-lg font-medium transition-colors',
                         mode === 'edit' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
          Редактировать итог
        </button>
      </div>

      <div class="flex items-center gap-1.5">
        <label class="text-xs text-gray-500 whitespace-nowrap">Выходов макс.</label>
        <input type="number" v-model.number="targetDays" min="1" :max="days.length"
               class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:outline-none focus:ring-2 focus:ring-blue-500" />
        <span class="text-xs text-gray-400">из {{ days.length }}</span>
      </div>

      <div class="flex items-center gap-1.5">
        <label class="text-xs text-gray-500 whitespace-nowrap">Мин. на участке</label>
        <input type="number" v-model.number="minWorkers" min="1" :max="members.length"
               @change="saveMinWorkers"
               class="w-16 border border-gray-300 rounded-lg px-2 py-1 text-sm text-center focus:outline-none focus:ring-2 focus:ring-blue-500" />
        <span class="text-xs text-gray-400">чел.</span>
      </div>

      <button @click="runGenerate" :disabled="generating || scheduleIsSaved"
              :title="scheduleIsSaved ? 'Расписание сохранено — измените ячейку для разблокировки' : ''"
              class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors">
        <svg v-if="!scheduleIsSaved" class="w-4 h-4" :class="generating && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
        {{ generating ? 'Генерация...' : (scheduleIsSaved ? 'Сохранено' : 'Сгенерировать') }}
      </button>

      <button @click="saveSchedule" :disabled="saving"
              class="flex items-center gap-1.5 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors">
        {{ saving ? 'Сохранение...' : 'Сохранить расписание' }}
      </button>

      <button @click="printPage"
              class="flex items-center gap-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-1.5 rounded-lg text-sm font-medium transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Печать
      </button>

      <span v-if="savedMsg" class="text-sm text-green-600 font-medium">✓ Сохранено</span>
    </div>

    <!-- Легенда (скрыта при печати) -->
    <div class="flex flex-wrap gap-4 mb-4 text-xs text-gray-600 print:hidden">
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-green-200 border border-green-300"></span>Рабочий день</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-gray-300 border border-gray-400"></span>Выходной</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-amber-300 border border-amber-400"></span>Пожелание</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-purple-200 border border-purple-300"></span>Праздник</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-blue-100 border border-blue-200"></span>Сб (заголовок)</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-red-100 border border-red-200"></span>Вс (заголовок)</span>
      <span class="flex items-center gap-1.5 ml-4 text-gray-400">Клик по ячейке — статус · Клик по числу — праздник</span>
    </div>

    <!-- Заголовок для печати -->
    <div class="hidden print:block mb-4">
      <h1 class="text-lg font-bold">Расписание бригады: {{ brigade.name }}</h1>
      <p class="text-sm text-gray-600">{{ monthLabel }} · Выходов: {{ targetDays }} из {{ days.length }}</p>
    </div>

    <!-- Сетка -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden schedule-grid">
      <div class="overflow-x-auto">
        <table class="border-collapse" style="min-width: max-content">
          <thead>
            <tr>
              <th class="sched-name-col border-b border-r border-gray-200 bg-gray-100 px-4 py-2 text-left text-xs font-semibold text-gray-600 min-w-[160px]">
                Сотрудник
              </th>
              <th v-for="day in days" :key="day.date"
                  @click="toggleHoliday(day)"
                  :class="['border-b border-r border-gray-200 text-center cursor-pointer select-none transition-colors w-9 min-w-[36px] py-1',
                           localHolidays[day.date]?.isHoliday ? 'bg-purple-200 hover:bg-purple-300'
                           : day.dow === 'Сб'                 ? 'bg-blue-100 hover:bg-blue-200'
                           : day.isWeekend                    ? 'bg-red-100 hover:bg-red-200'
                           :                                    'bg-gray-100 hover:bg-gray-200']"
                  :title="localHolidays[day.date]?.isHoliday ? (localHolidays[day.date]?.name || 'Праздник — клик чтобы снять') : 'Клик — отметить праздник'">
                <div class="text-xs font-bold text-gray-800">{{ day.day }}</div>
                <div :class="['text-[10px] font-medium', day.dow === 'Сб' ? 'text-blue-500' : day.isWeekend ? 'text-red-500' : 'text-gray-500']">{{ day.dow }}</div>
              </th>
              <th class="sched-count-col border-b border-gray-200 bg-gray-100 px-3 py-2 text-center text-xs font-semibold text-gray-600 min-w-[64px]">
                Выходов
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in members" :key="member.id" class="hover:brightness-95 transition-all">
              <td class="sched-name-col bg-white border-r border-b border-gray-200 px-4 py-1.5 text-sm text-gray-800 font-semibold whitespace-nowrap">
                {{ member.name }}
              </td>
              <td v-for="day in days" :key="day.date"
                  @click="toggleCell(member.id, day)"
                  :class="['border-r border-b border-gray-200 text-center cursor-pointer select-none transition-all w-9 h-9 sched-cell',
                           cellClass(member.id, day)]"
                  :data-status="cellStatus(member.id, day.date)">
                <span class="text-[11px] font-bold select-none cell-label">{{ cellLabel(member.id, day) }}</span>
              </td>
              <td class="sched-count-col border-b border-gray-200 px-3 text-center font-mono tabular-nums text-sm font-bold"
                  :class="workCount(member.id) >= targetDays ? 'text-green-700' : 'text-orange-600'">
                {{ workCount(member.id) }}
              </td>
            </tr>

            <!-- Строка "на участке" -->
            <tr class="bg-gray-50">
              <td class="sched-name-col bg-gray-50 border-r border-t border-gray-300 px-4 py-1.5 text-xs font-bold text-gray-500">
                На участке
              </td>
              <td v-for="day in days" :key="day.date"
                  :class="['border-r border-t border-gray-200 text-center w-9 py-1',
                           !localHolidays[day.date]?.isHoliday && workerCountOnDay(day.date) < minWorkers ? 'bg-red-200' : '']">
                <span :class="['text-[11px] font-bold',
                               localHolidays[day.date]?.isHoliday ? 'text-gray-300'
                               : workerCountOnDay(day.date) < minWorkers ? 'text-red-700'
                               : 'text-gray-600']">
                  {{ localHolidays[day.date]?.isHoliday ? '—' : workerCountOnDay(day.date) }}
                </span>
              </td>
              <td class="sched-count-col border-t border-gray-200 px-3 text-center text-xs text-gray-400 font-medium">
                мин {{ minWorkers }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Предупреждение (скрыто при печати) -->
    <div v-if="hasConflicts" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-medium print:hidden">
      ⚠ Есть дни с нарушением минимума ({{ minWorkers }} чел.) — выделены красным.
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  brigade:  Object,
  members:  Array,
  month:    String,
  days:     Array,
  schedule: Object,
})

const mode            = ref('mark')
const targetDays      = ref(24)
const minWorkers      = ref(props.brigade.min_workers ?? 2)
const generating      = ref(false)
const saving          = ref(false)
const savingMinWorkers = ref(false)
const scheduleIsSaved = ref(
  props.members.some(m => Object.keys(props.schedule[m.id] ?? {}).length > 0)
)
const savedMsg   = ref(false)

const cells = reactive({})
for (const m of props.members) {
  cells[m.id] = {}
  for (const day of props.days) {
    cells[m.id][day.date] = props.schedule[m.id]?.[day.date] ?? 'work'
  }
}

const localHolidays = reactive({})
for (const day of props.days) {
  localHolidays[day.date] = { isHoliday: day.isHoliday, name: day.holidayName }
}

const monthLabel = computed(() => {
  const [y, m] = props.month.split('-')
  const names = ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь']
  return `${names[parseInt(m) - 1]} ${y}`
})

function cellStatus(userId, date) {
  if (localHolidays[date]?.isHoliday) return 'holiday'
  return cells[userId]?.[date] ?? 'work'
}

function cellClass(userId, day) {
  const s = cellStatus(userId, day.date)
  if (s === 'holiday')   return 'bg-purple-200 cursor-default'
  if (s === 'off')       return 'bg-gray-300 hover:bg-gray-400'
  if (s === 'requested') return 'bg-amber-300 hover:bg-amber-400'
  return 'bg-green-200 hover:bg-green-300'
}

// Метка: пустая на экране (цвет достаточен), текст в печати через CSS
function cellLabel(userId, day) {
  const s = cellStatus(userId, day.date)
  if (s === 'holiday')   return 'П'
  if (s === 'off')       return 'В'
  if (s === 'requested') return '?'
  return 'Р'
}

function toggleCell(userId, day) {
  if (localHolidays[day.date]?.isHoliday) return
  const current = cells[userId][day.date] ?? 'work'
  if (mode.value === 'mark') {
    cells[userId][day.date] = current === 'requested' ? 'work' : 'requested'
  } else {
    cells[userId][day.date] = current === 'off' ? 'work' : 'off'
  }
  scheduleIsSaved.value = false
}

async function toggleHoliday(day) {
  const date = day.date
  const wasHoliday = localHolidays[date]?.isHoliday
  let name = null
  if (!wasHoliday) name = prompt('Название праздника (необязательно):') ?? ''
  try {
    const res = await axios.post(route('brigades.schedule.holiday', props.brigade.id), { date, name })
    localHolidays[date] = { isHoliday: res.data.isHoliday, name: name || null }
  } catch {}
}

function workCount(userId) {
  return props.days.filter(day => {
    if (localHolidays[day.date]?.isHoliday) return false
    return (cells[userId]?.[day.date] ?? 'work') === 'work'
  }).length
}

function workerCountOnDay(date) {
  if (localHolidays[date]?.isHoliday) return props.members.length
  return props.members.filter(m => (cells[m.id]?.[date] ?? 'work') === 'work').length
}

const hasConflicts = computed(() =>
  props.days.some(day => {
    if (localHolidays[day.date]?.isHoliday) return false
    return workerCountOnDay(day.date) < minWorkers.value
  })
)

async function saveMinWorkers() {
  savingMinWorkers.value = true
  try {
    await axios.patch(route('brigades.min-workers', props.brigade.id), { min_workers: minWorkers.value })
  } catch { /* silent */ } finally {
    savingMinWorkers.value = false
  }
}

async function runGenerate() {
  generating.value = true
  try {
    const preMark = {}
    for (const m of props.members) {
      preMark[m.id] = {}
      for (const day of props.days) {
        const s = cells[m.id][day.date]
        if (s === 'requested' || s === 'off') preMark[m.id][day.date] = s
      }
    }
    const res = await axios.post(
      route('brigades.schedule.generate', props.brigade.id),
      { month: props.month, pre_marks: preMark, target_days: targetDays.value, min_workers: minWorkers.value }
    )
    for (const m of props.members) {
      for (const day of props.days) {
        cells[m.id][day.date] = res.data.schedule[m.id]?.[day.date] ?? 'work'
      }
    }
    mode.value = 'edit'
    scheduleIsSaved.value = false
  } catch {
    alert('Ошибка генерации')
  } finally {
    generating.value = false
  }
}

async function saveSchedule() {
  saving.value = true
  savedMsg.value = false
  try {
    const s = {}
    for (const m of props.members) {
      s[m.id] = {}
      for (const day of props.days) s[m.id][day.date] = cells[m.id][day.date] ?? 'work'
    }
    await axios.post(route('brigades.schedule.save', props.brigade.id), { month: props.month, schedule: s })
    savedMsg.value = true
    scheduleIsSaved.value = true
    setTimeout(() => { savedMsg.value = false }, 3000)
  } catch {
    alert('Ошибка сохранения')
  } finally {
    saving.value = false
  }
}

function printPage() { window.print() }

function changeMonth(delta) {
  const [y, m] = props.month.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  router.get(route('brigades.schedule.show', props.brigade.id),
    { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` })
}
</script>

<style>
/* Ячейки: метка скрыта на экране, видна при печати */
.sched-cell .cell-label { opacity: 0; }

/* @page должен быть на верхнем уровне — вне @media print */
@page { size: A4 landscape; margin: 8mm; }

@media print {
  .print\:hidden { display: none !important; }
  html, body { background: white !important; }

  /* Уменьшаем ячейки чтобы 31 день влез в A4 landscape */
  .schedule-grid { border: 0.5pt solid #000 !important; border-radius: 0 !important; overflow: visible !important; }
  .schedule-grid .overflow-x-auto { overflow: visible !important; }
  .schedule-grid table { font-size: 7pt !important; }

  .sched-cell,
  .schedule-grid thead th,
  .schedule-grid tbody td { border: 0.5pt solid #aaa !important; padding: 0 !important; }

  /* Ширина колонок дней */
  .sched-cell,
  .schedule-grid thead th:not(.sched-name-col),
  .schedule-grid tbody td:not(.sched-name-col) {
    width: 26px !important;
    min-width: 26px !important;
    max-width: 26px !important;
  }

  /* Колонка с именем */
  .sched-name-col {
    position: static !important;
    background: #f0f0f0 !important;
    min-width: 110px !important;
    max-width: 110px !important;
    white-space: nowrap;
    font-size: 8pt !important;
  }

  /* Колонка "Выходов" */
  .sched-count-col {
    width: 30px !important;
    min-width: 30px !important;
    max-width: 30px !important;
    padding: 0 2px !important;
    font-size: 7pt !important;
    overflow: hidden;
    white-space: nowrap;
  }

  /* Буквы скрыты — статус передаётся только заливкой */
  .sched-cell .cell-label { opacity: 0 !important; }

  /* Статусы: оттенки серого для Ч/Б */
  .sched-cell[data-status="off"]       { background: #bbb !important; }
  .sched-cell[data-status="requested"] { background: #ddd !important; }
  .sched-cell[data-status="holiday"]   { background: #999 !important; }
  .sched-cell[data-status="work"]      { background: #fff !important; }
}
</style>
