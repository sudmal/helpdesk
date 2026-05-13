<template>
  <Head :title="`Расписание — ${brigade.name}`" />
  <AppLayout :title="`Расписание: ${brigade.name}`">

    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-3 mb-5">
      <div class="flex items-center gap-1">
        <button @click="changeMonth(-1)"
                class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <span class="text-sm font-semibold text-gray-700 min-w-[120px] text-center">{{ monthLabel }}</span>
        <button @click="changeMonth(1)"
                class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors">
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

      <button @click="runGenerate"
              :disabled="generating"
              class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors">
        <svg class="w-4 h-4" :class="generating && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        {{ generating ? 'Генерация...' : 'Сгенерировать' }}
      </button>

      <button @click="saveSchedule"
              :disabled="saving"
              class="flex items-center gap-1.5 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors">
        {{ saving ? 'Сохранение...' : 'Сохранить расписание' }}
      </button>

      <span v-if="savedMsg" class="text-sm text-green-600 font-medium">✓ Сохранено</span>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-4 mb-4 text-xs text-gray-500">
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-green-100 border border-green-200"></span>Выход</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-gray-100 border border-gray-300"></span>Выходной</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-amber-100 border border-amber-200"></span>Пожелание (до генерации)</span>
      <span class="flex items-center gap-1.5"><span class="inline-block w-4 h-4 rounded bg-purple-100 border border-purple-200"></span>Праздник</span>
      <span class="flex items-center gap-1.5 ml-4 text-gray-400">Клик по ячейке — переключить статус · Клик по числу дня — отметить праздник</span>
    </div>

    <!-- Grid -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="border-collapse" style="min-width: max-content">
          <thead>
            <tr>
              <th class="sticky left-0 z-10 bg-gray-50 border-b border-r border-gray-200 px-4 py-2 text-left text-xs font-medium text-gray-500 min-w-[160px]">
                Сотрудник
              </th>
              <th v-for="day in days" :key="day.date"
                  @click="toggleHoliday(day)"
                  :class="['border-b border-r border-gray-100 text-center cursor-pointer select-none transition-colors w-9 min-w-[36px] py-1',
                           day.isHoliday  ? 'bg-purple-50 hover:bg-purple-100'
                           : day.isWeekend ? 'bg-red-50 hover:bg-red-100'
                           :                'bg-gray-50 hover:bg-gray-100']"
                  :title="day.isHoliday ? (day.holidayName || 'Праздник — кликни чтобы снять') : 'Кликни чтобы отметить праздник'">
                <div class="text-xs font-bold text-gray-700">{{ day.day }}</div>
                <div :class="['text-[10px]', day.isWeekend ? 'text-red-400' : 'text-gray-400']">{{ day.dow }}</div>
              </th>
              <th class="border-b border-gray-200 bg-gray-50 px-3 py-2 text-center text-xs font-medium text-gray-500 min-w-[72px]">
                Выходов
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="member in members" :key="member.id"
                class="hover:bg-gray-50/50 transition-colors">
              <td class="sticky left-0 z-10 bg-white border-r border-b border-gray-100 px-4 py-1.5 text-sm text-gray-800 font-medium whitespace-nowrap">
                {{ member.name }}
              </td>
              <td v-for="day in days" :key="day.date"
                  @click="toggleCell(member.id, day)"
                  :class="['border-r border-b border-gray-100 text-center cursor-pointer select-none transition-colors w-9 h-9',
                           cellClass(member.id, day)]">
                <span class="text-xs">{{ cellIcon(member.id, day) }}</span>
              </td>
              <td class="border-b border-gray-100 px-3 text-center font-mono tabular-nums text-sm font-semibold text-gray-700">
                {{ workCount(member.id) }}
              </td>
            </tr>

            <!-- Summary row -->
            <tr class="bg-gray-50">
              <td class="sticky left-0 z-10 bg-gray-50 border-r border-t border-gray-200 px-4 py-1.5 text-xs font-semibold text-gray-500">
                На участке
              </td>
              <td v-for="day in days" :key="day.date"
                  :class="['border-r border-t border-gray-100 text-center w-9 py-1',
                           workerCountOnDay(day.date) < minWorkers && !day.isHoliday ? 'bg-red-50' : '']">
                <span :class="['text-[11px] font-semibold',
                               day.isHoliday ? 'text-gray-300'
                               : workerCountOnDay(day.date) < minWorkers ? 'text-red-500'
                               : 'text-gray-500']">
                  {{ day.isHoliday ? '—' : workerCountOnDay(day.date) }}
                </span>
              </td>
              <td class="border-t border-gray-200 px-3 text-center text-xs text-gray-400">
                мин {{ minWorkers }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Constraint warning -->
    <div v-if="hasConflicts" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-600">
      ⚠ Есть дни с меньше {{ minWorkers }} работающих — выделены красным. Скорректируй расписание или уменьши выходные.
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

const mode      = ref('mark')  // 'mark' | 'edit'
const generating = ref(false)
const saving     = ref(false)
const savedMsg   = ref(false)

// Mutable copy of schedule: cells[userId][date] = 'work'|'off'|'requested'
const cells = reactive({})
for (const m of props.members) {
  cells[m.id] = {}
  for (const day of props.days) {
    cells[m.id][day.date] = props.schedule[m.id]?.[day.date] ?? 'work'
  }
}

// Holiday overrides (client-side pending save)
const localHolidays = reactive({})
for (const day of props.days) {
  localHolidays[day.date] = { isHoliday: day.isHoliday, name: day.holidayName }
}

const minWorkers = computed(() => Math.min(2, props.members.length))

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
  if (s === 'holiday')   return 'bg-purple-50 cursor-default'
  if (s === 'off')       return 'bg-gray-100 hover:bg-gray-200'
  if (s === 'requested') return 'bg-amber-100 hover:bg-amber-200'
  // work
  if (day.isWeekend)     return 'bg-red-50 hover:bg-red-100'
  return 'bg-green-50 hover:bg-green-100'
}

function cellIcon(userId, day) {
  const s = cellStatus(userId, day.date)
  if (s === 'holiday')   return '—'
  if (s === 'off')       return ''
  if (s === 'requested') return '?'
  return ''
}

function toggleCell(userId, day) {
  if (localHolidays[day.date]?.isHoliday) return
  const current = cells[userId][day.date] ?? 'work'
  if (mode.value === 'mark') {
    cells[userId][day.date] = current === 'requested' ? 'work' : 'requested'
  } else {
    cells[userId][day.date] = current === 'off' ? 'work' : 'off'
  }
}

async function toggleHoliday(day) {
  const date = day.date
  const wasHoliday = localHolidays[date]?.isHoliday
  let name = null
  if (!wasHoliday) {
    name = prompt('Название праздника (необязательно):') ?? ''
  }

  try {
    const res = await axios.post(route('brigades.schedule.holiday', props.brigade.id), { date, name })
    localHolidays[date] = { isHoliday: res.data.isHoliday, name: name || null }
  } catch {}
}

function workCount(userId) {
  return props.days.filter(day => {
    if (localHolidays[day.date]?.isHoliday) return false
    const s = cells[userId]?.[day.date] ?? 'work'
    return s === 'work'
  }).length
}

function workerCountOnDay(date) {
  return props.members.filter(m => {
    const s = cells[m.id]?.[date] ?? 'work'
    return s === 'work'
  }).length
}

const hasConflicts = computed(() =>
  props.days.some(day => {
    if (localHolidays[day.date]?.isHoliday) return false
    return workerCountOnDay(day.date) < minWorkers.value
  })
)

async function runGenerate() {
  generating.value = true
  try {
    const preMark = {}
    for (const m of props.members) {
      preMark[m.id] = {}
      for (const day of props.days) {
        const s = cells[m.id][day.date]
        if (s === 'requested' || s === 'off') {
          preMark[m.id][day.date] = s
        }
      }
    }
    const res = await axios.post(
      route('brigades.schedule.generate', props.brigade.id),
      { month: props.month, pre_marks: preMark }
    )
    const newSchedule = res.data.schedule
    for (const m of props.members) {
      for (const day of props.days) {
        cells[m.id][day.date] = newSchedule[m.id]?.[day.date] ?? 'work'
      }
    }
    mode.value = 'edit'
  } catch (e) {
    alert('Ошибка генерации')
  } finally {
    generating.value = false
  }
}

async function saveSchedule() {
  saving.value = true
  savedMsg.value = false
  try {
    const scheduleToSave = {}
    for (const m of props.members) {
      scheduleToSave[m.id] = {}
      for (const day of props.days) {
        scheduleToSave[m.id][day.date] = cells[m.id][day.date] ?? 'work'
      }
    }
    await axios.post(
      route('brigades.schedule.save', props.brigade.id),
      { month: props.month, schedule: scheduleToSave }
    )
    savedMsg.value = true
    setTimeout(() => { savedMsg.value = false }, 3000)
  } catch {
    alert('Ошибка сохранения')
  } finally {
    saving.value = false
  }
}

function changeMonth(delta) {
  const [y, m] = props.month.split('-').map(Number)
  const d = new Date(y, m - 1 + delta, 1)
  const newMonth = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
  router.get(route('brigades.schedule.show', props.brigade.id), { month: newMonth })
}
</script>
