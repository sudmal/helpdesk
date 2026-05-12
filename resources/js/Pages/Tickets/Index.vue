<template>
  <Head title="Заявки" />
  <AppLayout title="Заявки">
    <template #actions>
      <a :href="route('tickets.create')"
         class="inline-flex items-center gap-1 bg-blue-600 hover:bg-blue-700
                text-white px-3 py-0.5 rounded-lg text-sm font-medium transition-colors">
        + Новая
      </a>
    </template>

    <!-- Плашки активных фильтров -->
    <div v-if="localFilters.overdue || localFilters.closed_today || localFilters.address_id || localFilters.street"
         class="flex flex-wrap gap-1.5 mb-2">
      <span v-if="localFilters.overdue"
            class="inline-flex items-center gap-1 bg-red-100 border border-red-200 text-red-700
                   text-xs px-2 py-0.5 rounded-full">
        🔴 Просроченные
        <button @click="localFilters.overdue=''; applyFilters()" class="hover:text-red-900">✕</button>
      </span>
      <span v-if="localFilters.closed_today"
            class="inline-flex items-center gap-1 bg-green-100 border border-green-200 text-green-700
                   text-xs px-2 py-0.5 rounded-full">
        {{ localFilters.closed_today === 'auto' ? '🟠 Просрочено сегодня' : '✅ Закрыто сегодня' }}
        <button @click="localFilters.closed_today=''; applyFilters()" class="hover:text-green-900">✕</button>
      </span>
      <span v-if="localFilters.address_id || localFilters.street"
            class="inline-flex items-center gap-1 bg-blue-100 border border-blue-200 text-blue-700
                   text-xs px-2 py-0.5 rounded-full">
        📍 {{ [localFilters.city, localFilters.street, localFilters.building ? 'д.'+localFilters.building : null].filter(Boolean).join(' ') || 'Адрес' }}
        <button @click="clearAddressFilter" class="hover:text-blue-900">✕</button>
      </span>
    </div>

    <!-- Фильтры — компактная строка -->
    <div class="bg-white rounded-xl border border-gray-200 px-3 py-2 mb-2 flex gap-2 flex-wrap items-center">
      <div class="relative flex-1 min-w-[160px]">
        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input v-model="localFilters.search" @input="debouncedApply"
               placeholder="Номер, адрес, телефон..."
               class="w-full pl-8 pr-2 py-1 border border-gray-200 rounded-lg text-xs
                      focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
      </div>
      <select v-model="localFilters.status" @change="applyFilters"
              class="border border-gray-200 rounded-lg px-2 py-0.5 text-xs bg-white min-w-[110px]">
        <option value="">Все статусы</option>
        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="localFilters.type" @change="applyFilters"
              class="border border-gray-200 rounded-lg px-2 py-0.5 text-xs bg-white min-w-[110px]">
        <option value="">Все типы</option>
        <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
      <select v-model="localFilters.service_type" @change="applyFilters"
              class="border border-gray-200 rounded-lg px-2 py-0.5 text-xs bg-white min-w-[90px]">
        <option value="">Участок</option>
        <option v-for="s in serviceTypes" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model="localFilters.brigade" @change="applyFilters"
              class="border border-gray-200 rounded-lg px-2 py-0.5 text-xs bg-white min-w-[110px]">
        <option value="">Все бригады</option>
        <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <select v-model="localFilters.priority" @change="applyFilters"
              class="border border-gray-200 rounded-lg px-2 py-0.5 text-xs bg-white min-w-[90px]">
        <option value="">Приоритет</option>
        <option value="urgent">🔴 Срочный</option>
        <option value="high">🟠 Высокий</option>
        <option value="normal">🔵 Обычный</option>
        <option value="low">⚪ Низкий</option>
      </select>
    </div>

    <!-- Пагинация + счётчик сверху -->
    <div class="flex items-center justify-between mb-1 px-1">
      <span class="text-xs text-gray-400">Всего: {{ tickets?.total ?? 0 }}</span>
      <div v-if="tickets?.last_page ?? 1 > 1" class="flex gap-1">
        <button v-if="tickets?.current_page ?? 1 > 1"
                @click="goPage(tickets?.current_page ?? 1 - 1)"
                class="px-2 py-0.5 border border-gray-200 rounded text-xs hover:bg-gray-50">‹</button>
        <span class="text-xs text-gray-400 px-1">{{ tickets?.current_page ?? 1 }} / {{ tickets?.last_page ?? 1 }}</span>
        <button v-if="tickets?.current_page ?? 1 < tickets?.last_page ?? 1"
                @click="goPage(tickets?.current_page ?? 1 + 1)"
                class="px-2 py-0.5 border border-gray-200 rounded text-xs hover:bg-gray-50">›</button>
      </div>
    </div>

    <!-- Таблица -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/80 text-xs text-gray-500 font-medium">
              <th class="w-6 px-1.5 py-0.5"></th>
              <th class="text-left px-2 py-0.5 w-28 cursor-pointer hover:text-gray-800 select-none"
                  @click="sortBy('created_at')">
                Дата <span class="text-gray-400">{{ sortIcon('created_at') }}</span>
              </th>
              <th class="text-left px-2 py-0.5 w-20 cursor-pointer hover:text-gray-800 select-none"
                  @click="sortBy('number')">
                № <span class="text-gray-400">{{ sortIcon('number') }}</span>
              </th>
              <th class="text-left px-2 py-0.5 hidden sm:table-cell w-24">Автор</th>
              <th class="text-left px-2 py-0.5">Адрес / Описание</th>
              <th class="text-left px-2 py-0.5 hidden md:table-cell w-32">Тип</th>
              <th class="text-left px-2 py-0.5 hidden lg:table-cell w-24">Бригада</th>
              <th class="text-left px-2 py-0.5 w-24 cursor-pointer hover:text-gray-800 select-none"
                  @click="sortBy('status_id')">
                Статус <span class="text-gray-400">{{ sortIcon('status_id') }}</span>
              </th>
              <th class="text-left px-2 py-0.5 hidden sm:table-cell w-24 cursor-pointer hover:text-gray-800 select-none"
                  @click="sortBy('scheduled_at')">
                Выезд <span class="text-gray-400">{{ sortIcon('scheduled_at') }}</span>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!tickets.data?.length">
              <td colspan="7" class="text-center py-8 text-xs text-gray-400">Заявок не найдено</td>
            </tr>
            <tr v-for="t in tickets.data ?? []" :key="t.id"
                class="cursor-pointer hover:brightness-95 transition-all"
                :style="rowStyle(t)"
                @click="router.visit(route('tickets.show', t.id))">

              <!-- Иконка участка -->
              <td class="px-1.5 py-0.5 text-center text-sm leading-none" :title="t.service_type?.name">
                {{ serviceIcon(t.service_type?.name) }}
              </td>
              <!-- Дата -->
              <td class="px-2 py-0.5 whitespace-nowrap text-xs tabular-nums text-gray-400">
                {{ formatDate(t.created_at) }}
              </td>
              <!-- Номер + приоритет -->
              <td class="px-2 py-0.5 whitespace-nowrap">
                <div class="flex items-center gap-1">
                  <span v-if="t.priority === 'urgent'" class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0" />
                  <span v-else-if="t.priority === 'high'" class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
                  <span class="font-mono text-blue-600 font-medium text-xs">{{ t.number }}</span>
                </div>
              </td>

              <!-- Автор -->
              <td class="px-2 py-0.5 hidden sm:table-cell text-xs text-gray-500 whitespace-nowrap truncate max-w-[80px]">
                {{ t.creator?.name?.split(' ')[0] ?? '—' }}
              </td>
              <!-- Адрес + телефон + описание -->
              <td class="px-2 py-0.5 min-w-0">
                <p class="font-medium text-gray-800 truncate text-xs leading-tight">{{ fullAddress(t) }}</p>
                <p class="text-gray-400 truncate text-xs leading-tight">
                  <span v-if="t.phone" class="text-gray-600 mr-1.5">{{ t.phone }}</span>
                  {{ t.description?.slice(0, 50) }}{{ t.description?.length > 50 ? '…' : '' }}
                  <span v-if="t.comments_count" class="ml-1 text-blue-400">💬{{ t.comments_count }}</span>
                </p>
              </td>

              <!-- Тип -->
              <td class="px-2 py-0.5 hidden md:table-cell">
                <Badge v-if="t.type" :color="t.type.color" :label="t.type.name" small />
              </td>

              <!-- Бригада -->
              <td class="px-2 py-0.5 hidden lg:table-cell text-xs text-gray-500 whitespace-nowrap">
                {{ t.brigade?.name ?? '—' }}
              </td>

              <!-- Статус -->
              <td class="px-2 py-0.5">
                <Badge v-if="t.status" :color="t.status.color" :label="t.status.name" small />
              </td>

              <!-- Выезд -->
              <td class="px-2 pr-3 py-0.5 hidden sm:table-cell text-xs whitespace-nowrap"
                  :class="isOverdue(t) ? 'text-red-500 font-medium' : 'text-gray-400'">
                {{ formatDate(t.scheduled_at) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Пагинация -->
      <div class="px-3 py-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
        <span>Стр. {{ tickets?.current_page ?? 1 }} из {{ tickets?.last_page ?? 1 }} ({{ tickets?.total ?? 0 }})</span>
        <div class="flex gap-1">
          <button v-if="tickets?.current_page ?? 1 > 1"
                  @click="goPage(tickets?.current_page ?? 1 - 1)"
                  class="px-2 py-0.5 border border-gray-200 rounded hover:bg-gray-50">‹</button>
          <template v-for="p in pageRange" :key="p">
            <span v-if="p === '...'" class="px-1.5 py-0.5 text-gray-300">…</span>
            <button v-else @click="goPage(p)"
                    :class="['px-2 py-0.5 border rounded',
                             p === tickets?.current_page ?? 1
                               ? 'bg-blue-600 text-white border-blue-600'
                               : 'border-gray-200 hover:bg-gray-50']">{{ p }}</button>
          </template>
          <button v-if="tickets?.current_page ?? 1 < tickets?.last_page ?? 1"
                  @click="goPage(tickets?.current_page ?? 1 + 1)"
                  class="px-2 py-0.5 border border-gray-200 rounded hover:bg-gray-50">›</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import 'dayjs/locale/ru'
dayjs.locale('ru')
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'

const props = defineProps({
  tickets:      { type: Object, default: () => ({ data: [], total: 0, current_page: 1, last_page: 1 }) },
  filters:      Object,
  statuses:     Array,
  types:        Array,
  serviceTypes: { type: Array, default: () => [] },
  brigades:     Array,
  overdueCount: { type: Number, default: 0 },
  sort:         { type: String, default: 'created_at' },
  sortDir:      { type: String, default: 'desc' },
})

const localFilters = ref({
  search:       props.filters?.search       ?? '',
  status:       props.filters?.status       ?? '',
  type:         props.filters?.type         ?? '',
  service_type: props.filters?.service_type ?? '',
  brigade:      props.filters?.brigade      ?? '',
  priority:     props.filters?.priority     ?? '',
  sort:         props.filters?.sort         ?? 'created_at',
  sortDir:      props.filters?.sortDir      ?? 'desc',
  overdue:      props.filters?.overdue      ?? '',
  closed_today: props.filters?.closed_today ?? '',
  address_id:   props.filters?.address_id   ?? '',
  city:         props.filters?.city         ?? '',
  street:       props.filters?.street       ?? '',
  building:     props.filters?.building     ?? '',
})

// Автообновление каждые 60 сек
let refreshTimer = null
onMounted(() => {
  console.log('tickets prop:', props.tickets)
  console.log('serviceTypes:', props.serviceTypes)
  console.log('statuses:', props.statuses)
  console.log('types:', props.types)
  refreshTimer = setInterval(() => {
    router.reload({ only: ['tickets'], preserveState: true, preserveScroll: true })
  }, 60000)
})
onUnmounted(() => clearInterval(refreshTimer))

let debounceTimer = null
function debouncedApply() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(applyFilters, 400)
}

function applyFilters() {
  const params = Object.fromEntries(
    Object.entries(localFilters.value).filter(([, v]) => v !== '' && v != null)
  )
  router.get(route('tickets.index'), params, { preserveState: true, replace: true })
}

function sortBy(field) {
  const dir = localFilters.value.sort === field && localFilters.value.sortDir === 'asc' ? 'desc' : 'asc'
  localFilters.value.sort    = field
  localFilters.value.sortDir = dir
  applyFilters()
}

function sortIcon(field) {
  if (localFilters.value.sort !== field) return '↕'
  return localFilters.value.sortDir === 'asc' ? '↑' : '↓'
}

function clearAddressFilter() {
  localFilters.value.address_id = ''
  localFilters.value.city       = ''
  localFilters.value.street     = ''
  localFilters.value.building   = ''
  applyFilters()
}

function goPage(page) {
  const params = Object.fromEntries(
    Object.entries(localFilters.value).filter(([, v]) => v !== '' && v != null)
  )
  params.page = page
  router.get(route('tickets.index'), params, { preserveState: true, replace: true })
}

const pageRange = computed(() => {
  const cur  = props.tickets?.current_page ?? 1
  const last = props.tickets?.last_page    ?? 1
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)
  const pages = new Set([1, last, cur, cur-1, cur+1].filter(p => p >= 1 && p <= last))
  const sorted = [...pages].sort((a, b) => a - b)
  const result = []
  let prev = 0
  for (const p of sorted) {
    if (p - prev > 1) result.push('...')
    result.push(p)
    prev = p
  }
  return result
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

function fullAddress(t) {
  const a = t.address
  if (!a) return '—'
  const apt = t.apartment || a.apartment
  return [a.street, a.building ? 'д.'+a.building : null, apt ? 'кв.'+apt : null]
    .filter(Boolean).join(' ')
}

function rowStyle(t) {
  if (!t.status?.color) return {}
  return { backgroundColor: t.status.color + '18' }
}

function isOverdue(t) {
  return t.scheduled_at && !t.status?.is_final && dayjs(t.scheduled_at).isBefore(dayjs().startOf('day'))
}

function formatDate(d) {
  if (!d) return '—'
  return dayjs(d).format('DD MMM HH:mm')
}
</script>
