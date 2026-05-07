<template>
  <Head title="Дашборд" />
  <AppLayout title="Дашборд">

    <!-- ── Статистика ── -->
    <div class="flex flex-wrap gap-2 mb-3">
      <div class="flex items-center gap-2 bg-blue-50 border border-blue-100 rounded-xl px-3 py-1.5">
        <span class="text-sm font-bold text-blue-600">{{ stats.open }}</span>
        <span class="text-xs text-gray-500">открытых</span>
      </div>
      <div class="flex items-center gap-2 bg-amber-50 border border-amber-100 rounded-xl px-3 py-1.5">
        <span class="text-sm font-bold text-amber-600">{{ stats.today }}</span>
        <span class="text-xs text-gray-500">на день</span>
      </div>
      <a :href="closedTodayLink('manual')"
         class="flex items-center gap-2 bg-green-50 border border-green-100 rounded-xl px-3 py-1.5 hover:border-green-300 transition-colors">
        <span class="text-sm font-bold text-green-600">{{ stats.closed_today }}</span>
        <span class="text-xs text-gray-500">закрыто</span>
      </a>
      <a :href="closedTodayLink('auto')"
         class="flex items-center gap-2 bg-orange-50 border border-orange-100 rounded-xl px-3 py-1.5 hover:border-orange-300 transition-colors">
        <span class="text-sm font-bold text-orange-500">{{ stats.closed_today_auto }}</span>
        <span class="text-xs text-gray-500">просрочено авт.</span>
      </a>
      <div :class="['flex items-center gap-2 rounded-xl px-3 py-1.5 cursor-pointer transition-colors',
                    stats.overdue > 0 ? 'bg-red-50 border border-red-200 hover:bg-red-100' : 'bg-gray-50 border border-gray-200']"
           @click="scrollToOverdue">
        <span :class="['text-sm font-bold', stats.overdue > 0 ? 'text-red-600' : 'text-gray-400']">{{ stats.overdue }}</span>
        <span :class="['text-xs', stats.overdue > 0 ? 'text-red-500' : 'text-gray-400']">просроченных ↓</span>
      </div>
    </div>

    <!-- ── Переключатель участков + дата ── -->
    <div class="bg-white rounded-2xl border border-gray-200 px-4 py-2.5 mb-2 flex items-center gap-2 flex-wrap">
      <span class="text-xs text-gray-400 font-medium">Участок:</span>
      <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
        <button v-for="st in serviceTypes" :key="st.id"
                @click="navigate({ service_type: st.id })"
                :class="['px-3 py-1 rounded-lg text-sm font-medium transition-colors flex items-center gap-1',
                         serviceType === st.id
                           ? 'bg-white shadow-sm text-gray-800'
                           : 'text-gray-500 hover:text-gray-700']">
          {{ serviceIcon(st.name) }} {{ st.name }}
          <span v-if="st.has_open" class="text-orange-500 font-bold text-sm leading-none">✱</span>
        </button>
        <button @click="navigate({ service_type: null })"
                :class="['px-3 py-1 rounded-lg text-sm font-medium transition-colors',
                         !serviceType
                           ? 'bg-white shadow-sm text-gray-800'
                           : 'text-gray-500 hover:text-gray-700']">
          Все
        </button>
      </div>
      <!-- Дата справа -->
      <div class="flex items-center gap-1 ml-auto">
        <button @click="changeDate(-1)"
                class="px-1.5 py-1 rounded-lg hover:bg-gray-100 transition-colors text-gray-500">‹</button>
        <input type="date" :value="selectedDate" @change="changeDate(0, $event.target.value)"
               class="border border-gray-200 rounded-lg px-2 py-1 text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        <button @click="changeDate(1)"
                class="px-1.5 py-1 rounded-lg hover:bg-gray-100 transition-colors text-gray-500">›</button>
        <button @click="changeDate(0, today)"
                class="text-xs text-blue-600 hover:text-blue-800 font-medium px-1.5">Сег.</button>
      </div>
    </div>

    <!-- ── ПРОСРОЧЕННЫЕ (вверху!) ── -->
    <div v-if="overdue?.length" ref="overdueSection"
         class="bg-red-50 border border-red-200 rounded-2xl overflow-hidden mb-4">
      <div class="px-4 py-3 border-b border-red-200 flex items-center justify-between">
        <h2 class="font-semibold text-red-700 text-sm flex items-center gap-2">
          ⚠ Требуют внимания — просроченные
          <span class="bg-red-600 text-white text-xs px-2 py-0.5 rounded-full">{{ overdue?.length }}</span>
        </h2>
        <a :href="route('tickets.index', { overdue: 1, service_type: serviceType, territory: selectedTerritory })"
           class="text-xs text-red-600 hover:text-red-800 font-medium">Открыть список →</a>
      </div>
      <table class="w-full text-xs">
        <tbody class="divide-y divide-red-100">
          <tr v-for="t in (overdue ?? [])" :key="t.id"
              class="hover:bg-red-100/50 cursor-pointer transition-colors"
              @click="router.visit(route('tickets.show', t.id))">
            <td class="pl-3 pr-1 py-2 text-center w-6">{{ serviceIcon(t.service_type?.name) }}</td>
            <td class="px-3 py-2 w-20">
              <span class="font-mono text-red-700 font-medium">{{ t.number }}</span>
            </td>
            <td class="px-3 py-2">
              <p class="font-medium text-gray-800 truncate max-w-[180px]">{{ fullAddress(t) }}</p>
              <p class="text-gray-500 truncate max-w-[180px]">
                {{ t.description?.slice(0, 50) }}{{ t.description?.length > 50 ? '…' : '' }}
              </p>
            </td>
            <td class="px-3 py-2 hidden sm:table-cell">
              <Badge v-if="t.type" :color="t.type.color" :label="t.type.name" small />
            </td>
            <td class="px-3 py-2">
              <Badge v-if="t.status" :color="t.status.color" :label="t.status.name" small />
            </td>
            <td class="px-3 py-2 hidden md:table-cell text-gray-500">{{ t.phone ?? '—' }}</td>
            <td class="px-3 py-2 text-red-600 font-medium whitespace-nowrap text-right pr-4">
              {{ formatDateTime(t.scheduled_at) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Вкладки территорий + выбор даты + таблица ── -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 flex-wrap gap-3">
        <!-- Вкладки территорий -->
        <div class="flex gap-1 flex-wrap">
          <button v-for="t in territories" :key="t.id"
                  @click="selectTerritory(t.id)"
                  :class="['px-3 py-1.5 rounded-xl text-sm font-medium transition-colors flex items-center gap-1',
                           selectedTerritory === t.id
                             ? 'bg-blue-600 text-white'
                             : 'text-gray-600 hover:bg-gray-100']">
            {{ t.name }}
            <span v-if="t.open_count > 0"
                  :class="['text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none',
                           selectedTerritory === t.id ? 'bg-red-500 text-white' : 'bg-red-100 text-red-700']">
              {{ t.open_count }}
            </span>
            <span v-if="t.closed_count > 0"
                  :class="['text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none',
                           selectedTerritory === t.id ? 'bg-green-400 text-white' : 'bg-green-100 text-green-700']">
              {{ t.closed_count }}
            </span>
          </button>
        </div>
      </div>

      <!-- Таблица заявок на день -->
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/50 text-gray-500 font-medium">
              <th class="w-6 px-2 py-2.5"></th>
              <th class="px-3 py-2.5 text-left cursor-pointer hover:bg-gray-100 w-20"
                  @click="sortBy('scheduled_at')">
                Время {{ sortIcon('scheduled_at') }}
              </th>
              <th class="px-2 py-1.5 text-left cursor-pointer hover:bg-gray-100 w-20"
                  @click="sortBy('number')">
                № {{ sortIcon('number') }}
              </th>
              <th class="px-2 py-1.5 text-left">Адрес / Описание</th>
              <th class="px-2 py-1.5 text-left hidden md:table-cell">Тип</th>
              <th class="px-2 py-1.5 text-left hidden lg:table-cell">Телефон</th>
              <th class="px-2 py-1.5 text-left cursor-pointer hover:bg-gray-100"
                  @click="sortBy('status_id')">
                Статус {{ sortIcon('status_id') }}
              </th>
              <th class="px-2 py-1.5 text-left text-gray-500 w-14">Акт</th>
              <th class="px-2 py-1.5 text-left text-gray-500">Комментарий</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!todayTickets?.length">
              <td colspan="7" class="text-center py-10 text-gray-400">
                Заявок на {{ formatDateLabel(selectedDate) }} нет
              </td>
            </tr>
            <tr v-for="t in (todayTickets ?? [])" :key="t.id"
                class="cursor-pointer transition-all"
                :style="{ backgroundColor: (t.status?.color ?? '#6b7280') + '1a' }"
                @mouseover="e => e.currentTarget.style.filter='brightness(0.93)'"
                @mouseout="e => e.currentTarget.style.filter=''"
                @click="router.visit(route('tickets.show', t.id))">
              <td class="pl-1.5 pr-1 py-0.5 text-center text-sm leading-none">{{ serviceIcon(t.service_type?.name) }}</td>
              <td class="px-2 py-0.5 font-medium tabular-nums text-gray-700 whitespace-nowrap text-xs">
                {{ formatTime(t.scheduled_at) }}
              </td>
              <td class="px-2 py-0.5">
                <span class="font-mono text-blue-600 font-medium text-xs">{{ t.number }}</span>
              </td>
              <td class="px-2 py-0.5 max-w-[240px]">
                <p class="font-medium text-gray-800 truncate text-xs leading-tight">{{ fullAddress(t) }}</p>
                <p class="text-gray-400 truncate text-xs leading-tight">
                  {{ t.description?.slice(0, 60) }}{{ t.description?.length > 60 ? '…' : '' }}
                </p>
              </td>
              <td class="px-2 py-0.5 hidden md:table-cell">
                <Badge v-if="t.type" :color="t.type.color" :label="t.type.name" small />
              </td>
              <td class="px-2 py-0.5 hidden lg:table-cell text-gray-600 text-xs">{{ t.phone ?? '—' }}</td>
              <td class="px-2 py-0.5">
                <Badge v-if="t.status" :color="t.status.color" :label="t.status.name" small />
              </td>
              <!-- Акт (фиксированная ширина) -->
              <td class="px-2 py-0.5 w-14">
                <span v-if="t.status?.is_final"
                      class="text-xs font-medium text-green-700 bg-green-100 px-1.5 py-0.5 rounded whitespace-nowrap">
                  {{ t.act_number || 'б/а' }}
                </span>
              </td>
              <!-- Комментарий (вся оставшаяся ширина) -->
              <td class="px-2 pr-3 py-0.5 max-w-0">
                <p v-if="t.status?.is_final && t.close_notes"
                   class="text-xs text-gray-400 truncate leading-tight" :title="t.close_notes">
                  {{ t.close_notes }}
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import 'dayjs/locale/ru'
dayjs.locale('ru')
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'

const props = defineProps({
  stats:             { type: Object, required: true },
  todayTickets:      { type: Array,  default: () => [] },
  overdue:           { type: Array,  default: () => [] },
  territories:       { type: Array,  default: () => [] },
  serviceTypes:      { type: Array,  default: () => [] },
  selectedDate:      String,
  selectedTerritory: Number,
  serviceType:       Number,
  sort:              { type: String, default: 'scheduled_at' },
  sortDir:           { type: String, default: 'asc' },
})

const overdueSection = ref(null)
const today = dayjs().format('YYYY-MM-DD')

function navigate(extra = {}) {
  router.get(route('dashboard'), {
    date:         props.selectedDate,
    territory:    props.selectedTerritory,
    service_type: props.serviceType,
    sort:         props.sort,
    dir:          props.sortDir,
    ...extra,
  }, { preserveState: true, replace: true })
}

function selectTerritory(id) { navigate({ territory: id }) }

function changeDate(delta, value = null) {
  const d = value ?? dayjs(props.selectedDate).add(delta, 'day').format('YYYY-MM-DD')
  navigate({ date: d })
}

function sortBy(field) {
  const dir = props.sort === field && props.sortDir === 'asc' ? 'desc' : 'asc'
  navigate({ sort: field, dir })
}

function sortIcon(field) {
  if (props.sort !== field) return '↕'
  return props.sortDir === 'asc' ? '↑' : '↓'
}

function scrollToOverdue() {
  overdueSection.value?.scrollIntoView({ behavior: 'smooth' })
}

function closedTodayLink(type) {
  return route('tickets.index', {
    closed_today:  type,
    service_type:  props.serviceType,
    territory:     props.selectedTerritory,
  })
}

function formatTime(d)     { return d ? dayjs(d).format('HH:mm') : '—' }
function formatDateTime(d) { return d ? dayjs(d).format('DD MMM HH:mm') : '—' }
function formatDateLabel(d) {
  const dt = dayjs(d)
  if (dt.isSame(dayjs(), 'day'))             return 'сегодня'
  if (dt.isSame(dayjs().add(1,'day'), 'day')) return 'завтра'
  return dt.format('DD.MM.YYYY')
}

const SERVICE_ICONS = { 'интернет': '🌐', 'inet': '🌐', 'ктв': '📺', 'ctv': '📺', 'волс': '🔆' }
function serviceIcon(name) {
  if (!name) return '📋'
  const k = name.toLowerCase()
  for (const [key, icon] of Object.entries(SERVICE_ICONS)) {
    if (k.includes(key)) return icon
  }
  return '📋'
}

// Подсветка строки по статусу
function rowClass(t) {
  return 'cursor-pointer transition-colors'
}
function rowStyle(t) {
  if (!t.status?.color) return {}
  return { backgroundColor: t.status.color + '36' } // ~21% opacity
}

function fullAddress(t) {
  const a = t.address
  if (!a) return '—'
  const apt = t.apartment || a.apartment
  return [a.street, a.building ? 'д.'+a.building : null, apt ? 'кв.'+apt : null]
    .filter(Boolean).join(' ')
}
</script>
