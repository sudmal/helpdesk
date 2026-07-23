<template>
  <Head title="Заявки" />
  <AppLayout title="Заявки">
    <template #actions>

      <a :href="route('tickets.map')" target="_blank"
         class="inline-flex items-center gap-1.5 bg-white hover:bg-gray-50 border border-gray-200
                text-gray-600 px-3 py-0.5 rounded-lg text-sm font-medium transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
        </svg>
        Карта
      </a>
      <a v-if="canCreate" :href="route('tickets.create')"
         class="inline-flex items-center gap-1 bg-blue-600 hover:bg-blue-700
                text-white px-3 py-0.5 rounded-lg text-sm font-medium transition-colors">
        + Новая
      </a>
    </template>

    <!-- Плашки активных фильтров -->
    <div v-if="localFilters.overdue || localFilters.closed_today || localFilters.address_id || localFilters.street || localFilters.apartment"
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
        📍 {{ addressFilterLabel ? addressFilterLabel + (localFilters.apartment ? ' кв.' + localFilters.apartment : '') : ([localFilters.city, localFilters.street, localFilters.building ? 'д.'+localFilters.building : null, localFilters.apartment ? 'кв.'+localFilters.apartment : null].filter(Boolean).join(' ') || 'Адрес') }}
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
      <button @click="openAddrModal"
              :class="['border rounded-lg px-2 py-0.5 text-xs transition-colors whitespace-nowrap',
                       (localFilters.city || localFilters.address_id)
                         ? 'bg-blue-50 border-blue-300 text-blue-700'
                         : 'border-gray-200 text-gray-600 hover:bg-gray-50']">
        📍 Адрес
      </button>
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
              <th class="w-8 px-1.5 py-0.5 text-center">
                <input type="checkbox" :checked="selectAll" @change="toggleSelectAll" class="rounded border-gray-300 cursor-pointer" />
              </th>
              <th class="w-5 px-1 py-0.5"></th>
              <th class="text-left px-2 py-0.5 w-28 cursor-pointer hover:text-gray-800 select-none"
                  @click="sortBy('created_at')">
                Добавлена <span class="text-gray-400">{{ sortIcon('created_at') }}</span>
              </th>
              <th class="text-left px-2 py-0.5 w-20 cursor-pointer hover:text-gray-800 select-none"
                  @click="sortBy('number')">
                № <span class="text-gray-400">{{ sortIcon('number') }}</span>
              </th>
              <th class="text-left px-2 py-0.5 hidden sm:table-cell w-16">Автор</th>
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

              <td class="px-1.5 py-px text-center" @click.stop>
                <input type="checkbox" :checked="selected.has(t.id)" @change="toggleSelect(t.id)" class="rounded border-gray-300 cursor-pointer" />
              </td>
              <!-- Иконка участка -->
              <td class="px-1.5 py-px text-center text-sm leading-none" :title="t.service_type?.name">
                {{ serviceIcon(t.service_type?.name) }}
              </td>
              <!-- Дата -->
              <td class="px-2 py-px whitespace-nowrap text-xs tabular-nums text-gray-400">
                {{ formatDate(t.created_at) }}
              </td>
              <!-- Номер + приоритет -->
              <td class="px-2 py-px whitespace-nowrap">
                <div class="flex items-center gap-1">
                  <span v-if="t.priority === 'urgent'" class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0" />
                  <span v-else-if="t.priority === 'high'" class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
                  <span class="font-mono text-blue-600 font-medium text-xs">{{ t.number }}</span>
                </div>
              </td>

              <!-- Автор -->
              <td class="px-2 py-px hidden sm:table-cell text-xs text-gray-500 truncate max-w-[72px]">
                {{ t.creator?.login ?? '—' }}
              </td>
              <!-- Адрес + телефон + описание -->
              <td class="px-2 py-px min-w-0">
                <p class="font-medium text-gray-800 truncate text-xs leading-tight">{{ fullAddress(t) }}</p>
                <p class="text-gray-600 text-xs leading-tight" :class="expandedDesc.has(t.id) ? 'whitespace-normal' : 'truncate'">
                  <span v-if="t.phone" class="text-gray-600 mr-1.5">{{ t.phone }}</span>
                  <span>{{ expandedDesc.has(t.id) ? t.description : t.description?.slice(0, 100) }}</span>
                  <button v-if="(t.description?.length ?? 0) > 100" @click.stop="toggleDesc(t.id)"
                          class="ml-0.5 text-blue-400 hover:text-blue-600 font-medium text-[10px] leading-none align-middle">
                    {{ expandedDesc.has(t.id) ? '[↑]' : '[…]' }}
                  </button>
                  <span v-if="t.comments_count" class="ml-1 text-blue-400">💬{{ t.comments_count }}</span>
                </p>
              </td>

              <!-- Тип -->
              <td class="px-2 py-px hidden md:table-cell">
                <Badge v-if="t.type" :color="t.type.color" :label="t.type.name" small />
              </td>

              <!-- Бригада -->
              <td class="px-2 py-px hidden lg:table-cell text-xs text-gray-500 whitespace-nowrap">
                {{ t.brigade?.name ?? '—' }}
              </td>

              <!-- Статус -->
              <td class="px-2 py-px">
                <Badge v-if="t.status" :color="t.status.color" :label="t.status.name" small />
              </td>

              <!-- Выезд -->
              <td class="px-2 pr-3 py-px hidden sm:table-cell text-xs whitespace-nowrap"
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
    <!-- Фильтр по адресу -->
    <Modal v-if="showAddrModal" title="Фильтр по адресу" @close="showAddrModal = false">
      <div class="space-y-3 w-72">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Город</label>
          <select v-model="addrF.city" @change="onAddrCityChange"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
            <option value="">— Выбрать город —</option>
            <option v-for="c in addrCities" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div v-if="addrF.city">
          <label class="block text-xs text-gray-500 mb-1">Улица</label>
          <select v-model="addrF.street" @change="onAddrStreetChange"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
            <option value="">— Все улицы —</option>
            <option v-for="s in addrStreets" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div v-if="addrF.street">
          <label class="block text-xs text-gray-500 mb-1">Дом</label>
          <select v-model="addrF.building" @change="onAddrBuildingChange"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
            <option value="">— Все дома —</option>
            <option v-for="b in addrBuildings" :key="b" :value="b">{{ b }}</option>
          </select>
        </div>
        <div v-if="addrF.building && addrApartments.length">
          <label class="block text-xs text-gray-500 mb-1">Квартира</label>
          <select v-model="addrF.apartment"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30">
            <option value="">— Все квартиры —</option>
            <option v-for="a in addrApartments" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
        <div class="flex gap-2 justify-end pt-2 border-t border-gray-100">
          <button type="button" @click="clearAddrModal" class="btn-outline text-sm">Очистить</button>
          <button type="button" @click="applyAddrModal" :disabled="!addrF.city"
                  class="btn-primary text-sm disabled:opacity-50">Найти</button>
        </div>
      </div>
    </Modal>
    <!-- Массовые операции: панель -->
    <Teleport to="body">
      <div v-if="selected.size > 0"
           class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-gray-900 text-white rounded-2xl shadow-2xl px-5 py-3 flex items-center gap-4 text-sm whitespace-nowrap">
        <span>Выбрано: <b>{{ selected.size }}</b></span>
        <button @click="bulkCloseModal = true"
                class="bg-green-600 hover:bg-green-700 px-4 py-1.5 rounded-lg font-medium transition-colors">
          ✓ Закрыть
        </button>
        <button @click="bulkRescheduleModal = true"
                class="bg-blue-600 hover:bg-blue-700 px-4 py-1.5 rounded-lg font-medium transition-colors">
          📅 Перенести
        </button>
        <button @click="selected = new Set()" class="text-white/50 hover:text-white ml-1">✕</button>
      </div>
    </Teleport>

    <!-- Модалка: массовое закрытие -->
    <Modal v-if="bulkCloseModal" title="Закрыть заявки" @close="bulkCloseModal = false">
      <div class="w-80 space-y-3">
        <p class="text-sm text-gray-600">Выбрано заявок: <b>{{ selected.size }}</b></p>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Номер акта <span class="text-gray-400">(если нет — будет «б/а»)</span></label>
          <input v-model="bulkCloseForm.act_number" type="text" placeholder="б/а"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Комментарий</label>
          <textarea v-model="bulkCloseForm.comment" rows="3" placeholder="Что было сделано..."
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30"></textarea>
        </div>
        <div class="flex gap-2 justify-end pt-1 border-t border-gray-100">
          <button @click="bulkCloseModal = false" class="btn-outline text-sm">Отмена</button>
          <button @click="doBulkClose" :disabled="bulkLoading"
                  class="btn-primary text-sm disabled:opacity-50">
            {{ bulkLoading ? 'Закрываем...' : 'Закрыть ' + selected.size + ' заявок' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Модалка: массовый перенос -->
    <Modal v-if="bulkRescheduleModal" title="Перенести заявки" @close="bulkRescheduleModal = false">
      <div class="w-80 space-y-3">
        <p class="text-sm text-gray-600">Выбрано заявок: <b>{{ selected.size }}</b></p>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Новая дата и время <span class="text-red-400">*</span></label>
          <input v-model="bulkRescheduleForm.scheduled_at" type="datetime-local"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Комментарий</label>
          <textarea v-model="bulkRescheduleForm.comment" rows="3" placeholder="Причина переноса..."
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30"></textarea>
        </div>
        <div class="flex gap-2 justify-end pt-1 border-t border-gray-100">
          <button @click="bulkRescheduleModal = false" class="btn-outline text-sm">Отмена</button>
          <button @click="doBulkReschedule" :disabled="!bulkRescheduleForm.scheduled_at || bulkLoading"
                  class="btn-primary text-sm disabled:opacity-50">
            {{ bulkLoading ? 'Переносим...' : 'Перенести ' + selected.size + ' заявок' }}
          </button>
        </div>
      </div>
    </Modal>

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
import Modal from '@/Components/UI/Modal.vue'
import axios from 'axios'

const props = defineProps({
  tickets:      { type: Object, default: () => ({ data: [], total: 0, current_page: 1, last_page: 1 }) },
  filters:      Object,
  addressFilterLabel: { type: String, default: null },
  statuses:     Array,
  types:        Array,
  serviceTypes: { type: Array, default: () => [] },
  brigades:     Array,
  overdueCount: { type: Number, default: 0 },
  sort:         { type: String, default: 'created_at' },
  sortDir:      { type: String, default: 'desc' },
  canCreate:    { type: Boolean, default: false },
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
  apartment:    props.filters?.apartment    ?? '',
})

// Автообновление каждые 60 сек
let refreshTimer = null
onMounted(() => {
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
  localFilters.value.apartment  = ''
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
  return [a.city, a.street, a.building ? 'д.'+a.building : null, (apt && apt !== '0') ? 'кв.'+apt : null]
    .filter(Boolean).join(' ')
}

function rowStyle(t) {
  if (!t.status?.color) return {}
  return { backgroundColor: t.status.color + '18' }
}

// ── Фильтр по адресу (модалка) ──
const showAddrModal   = ref(false)
const addrCities      = ref([])
const addrStreets     = ref([])
const addrBuildings   = ref([])
const addrApartments  = ref([])
const addrF           = ref({ city: '', street: '', building: '', apartment: '' })

async function openAddrModal() {
  addrF.value = {
    city:      localFilters.value.city      ?? '',
    street:    localFilters.value.street    ?? '',
    building:  localFilters.value.building  ?? '',
    apartment: localFilters.value.apartment ?? '',
  }
  showAddrModal.value = true
  if (!addrCities.value.length) {
    const { data } = await axios.get(route('addresses.hierarchy'))
    addrCities.value = data
  }
  if (addrF.value.city && !addrStreets.value.length) {
    const { data } = await axios.get(route('addresses.hierarchy'), { params: { city: addrF.value.city } })
    addrStreets.value = data
  }
  if (addrF.value.street && !addrBuildings.value.length) {
    const { data } = await axios.get(route('addresses.hierarchy'), { params: { city: addrF.value.city, street: addrF.value.street } })
    addrBuildings.value = data
  }
  if (addrF.value.building && !addrApartments.value.length) {
    const { data } = await axios.get(route('addresses.hierarchy'), { params: { city: addrF.value.city, street: addrF.value.street, building: addrF.value.building } })
    addrApartments.value = data
  }
}
async function onAddrCityChange() {
  addrF.value.street = ''; addrF.value.building = ''; addrF.value.apartment = ''
  addrStreets.value = []; addrBuildings.value = []; addrApartments.value = []
  if (addrF.value.city) {
    const { data } = await axios.get(route('addresses.hierarchy'), { params: { city: addrF.value.city } })
    addrStreets.value = data
  }
}
async function onAddrStreetChange() {
  addrF.value.building = ''; addrF.value.apartment = ''
  addrBuildings.value = []; addrApartments.value = []
  if (addrF.value.street) {
    const { data } = await axios.get(route('addresses.hierarchy'), { params: { city: addrF.value.city, street: addrF.value.street } })
    addrBuildings.value = data
  }
}
async function onAddrBuildingChange() {
  addrF.value.apartment = ''; addrApartments.value = []
  if (addrF.value.building) {
    const { data } = await axios.get(route('addresses.hierarchy'), { params: { city: addrF.value.city, street: addrF.value.street, building: addrF.value.building } })
    addrApartments.value = data
  }
}
function clearAddrModal() {
  addrF.value = { city: '', street: '', building: '', apartment: '' }
  addrStreets.value = []; addrBuildings.value = []; addrApartments.value = []
}
function applyAddrModal() {
  localFilters.value.address_id = ''
  localFilters.value.city       = addrF.value.city
  localFilters.value.street     = addrF.value.street
  localFilters.value.building   = addrF.value.building
  localFilters.value.apartment  = addrF.value.apartment
  showAddrModal.value = false
  applyFilters()
}

function isOverdue(t) {
  return t.scheduled_at && !t.status?.is_final && dayjs(t.scheduled_at).isBefore(dayjs().startOf('day'))
}

const expandedDesc = ref(new Set())
function toggleDesc(id) {
  const s = new Set(expandedDesc.value)
  if (s.has(id)) s.delete(id); else s.add(id)
  expandedDesc.value = s
}

function formatDate(d) {
  if (!d) return '—'
  return dayjs(d).format('DD MMM HH:mm')
}

// Массовые операции
const selected = ref(new Set())
const bulkCloseModal = ref(false)
const bulkRescheduleModal = ref(false)
const bulkLoading = ref(false)
const bulkCloseForm = ref({ comment: '', act_number: '' })
const bulkRescheduleForm = ref({ scheduled_at: '', comment: '' })

const selectAll = computed(() => {
  const ids = props.tickets?.data?.map(t => t.id) ?? []
  return ids.length > 0 && ids.every(id => selected.value.has(id))
})

function toggleSelect(id) {
  const s = new Set(selected.value)
  if (s.has(id)) s.delete(id); else s.add(id)
  selected.value = s
}

function toggleSelectAll() {
  const ids = props.tickets?.data?.map(t => t.id) ?? []
  selected.value = selectAll.value ? new Set() : new Set(ids)
}

async function doBulkClose() {
  bulkLoading.value = true
  try {
    await axios.post(route('tickets.bulk.close'), {
      ids: [...selected.value],
      comment: bulkCloseForm.value.comment,
      act_number: bulkCloseForm.value.act_number,
    })
    bulkCloseModal.value = false
    selected.value = new Set()
    bulkCloseForm.value = { comment: '', act_number: '' }
    router.reload({ only: ['tickets'], preserveState: true })
  } finally { bulkLoading.value = false }
}

async function doBulkReschedule() {
  bulkLoading.value = true
  try {
    await axios.post(route('tickets.bulk.reschedule'), {
      ids: [...selected.value],
      scheduled_at: bulkRescheduleForm.value.scheduled_at,
      comment: bulkRescheduleForm.value.comment,
    })
    bulkRescheduleModal.value = false
    selected.value = new Set()
    bulkRescheduleForm.value = { scheduled_at: '', comment: '' }
    router.reload({ only: ['tickets'], preserveState: true })
  } finally { bulkLoading.value = false }
}
</script>
