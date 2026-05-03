<template>
  <Head title="Заявки" />
  <AppLayout title="Заявки">
    <template #actions>
      <Link :href="route('tickets.create')"
            class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white
                   px-4 py-2 rounded-xl text-sm font-medium transition-colors">
        <Icon name="plus" class="w-4 h-4" /> Новая заявка
      </Link>
    </template>

    <!-- Фильтры -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3">
      <!-- Поиск -->
      <div class="relative flex-1 min-w-[220px]">
        <Icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
        <input v-model="form.search" @input="debouncedSearch"
               placeholder="Поиск по адресу, номеру, телефону..."
               class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-xl text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400" />
      </div>

      <select v-model="form.status" @change="applyFilters"
              class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none
                     focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
        <option value="">Все статусы</option>
        <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>

      <select v-model="form.type" @change="applyFilters"
              class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none
                     focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
        <option value="">Все типы</option>
        <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>

      <select v-model="form.brigade" @change="applyFilters"
              class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none
                     focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white">
        <option value="">Все бригады</option>
        <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>

      <!-- Дата от/до -->
      <input v-model="form.date_from" @change="applyFilters" type="date"
             class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none
                    focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white" />
      <input v-model="form.date_to" @change="applyFilters" type="date"
             class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none
                    focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white" />

      <button v-if="hasFilters" @click="resetFilters"
              class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <Icon name="x" class="w-4 h-4" /> Сбросить
      </button>
    </div>

    <!-- Таблица -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="px-6 py-3 border-b border-gray-100 text-sm text-gray-500">
        Всего: {{ tickets.total }}
      </div>

      <div v-if="tickets.data.length === 0"
           class="py-16 text-center text-gray-400">
        Заявки не найдены
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/50">
              <th class="text-left px-6 py-3 text-xs text-gray-500 font-medium">№</th>
              <th class="text-left px-6 py-3 text-xs text-gray-500 font-medium">Адрес</th>
              <th class="text-left px-6 py-3 text-xs text-gray-500 font-medium">Телефон</th>
              <th class="text-left px-6 py-3 text-xs text-gray-500 font-medium">Тип</th>
              <th class="text-left px-6 py-3 text-xs text-gray-500 font-medium">Статус</th>
              <th class="text-left px-6 py-3 text-xs text-gray-500 font-medium">Бригада</th>
              <th class="text-left px-6 py-3 text-xs text-gray-500 font-medium">Приоритет</th>
              <th class="text-left px-6 py-3 text-xs text-gray-500 font-medium">Дата выезда</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="ticket in tickets.data" :key="ticket.id"
                class="hover:bg-gray-50/50 transition-colors">
              <td class="px-6 py-3">
                <Link :href="route('tickets.show', ticket.id)"
                      class="text-blue-600 hover:underline font-medium">
                  {{ ticket.number }}
                </Link>
              </td>
              <td class="px-6 py-3 text-gray-700 max-w-[200px]">
                <div class="truncate">{{ ticket.address?.full_address ?? '—' }}</div>
              </td>
              <td class="px-6 py-3 text-gray-500">{{ ticket.phone ?? '—' }}</td>
              <td class="px-6 py-3">
                <Badge :color="ticket.type.color">{{ ticket.type.name }}</Badge>
              </td>
              <td class="px-6 py-3">
                <Badge :color="ticket.status.color">{{ ticket.status.name }}</Badge>
              </td>
              <td class="px-6 py-3 text-gray-500">{{ ticket.brigade?.name ?? '—' }}</td>
              <td class="px-6 py-3">
                <PriorityBadge :priority="ticket.priority" />
              </td>
              <td class="px-6 py-3 text-gray-400 text-xs whitespace-nowrap">
                {{ formatDate(ticket.scheduled_at) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Пагинация -->
      <div v-if="tickets.last_page > 1"
           class="flex items-center justify-between px-6 py-3 border-t border-gray-100">
        <span class="text-sm text-gray-500">
          Страница {{ tickets.current_page }} из {{ tickets.last_page }}
        </span>
        <div class="flex gap-2">
          <Link v-if="tickets.prev_page_url" :href="tickets.prev_page_url"
                class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm hover:bg-gray-50">
            ← Назад
          </Link>
          <Link v-if="tickets.next_page_url" :href="tickets.next_page_url"
                class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm hover:bg-gray-50">
            Вперёд →
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Icon from '@/Components/UI/Icon.vue'
import Badge from '@/Components/UI/Badge.vue'
import PriorityBadge from '@/Components/Tickets/PriorityBadge.vue'

function formatDate(d) {
  return d ? dayjs(d).format('DD.MM.YY HH:mm') : '—'
}

const props = defineProps({
  tickets:  { type: Object, required: true },
  filters:  { type: Object, default: () => ({}) },
  statuses: { type: Array,  default: () => [] },
  types:    { type: Array,  default: () => [] },
  brigades: { type: Array,  default: () => [] },
})

const form = ref({
  search:    props.filters.search    ?? '',
  status:    props.filters.status    ?? '',
  type:      props.filters.type      ?? '',
  brigade:   props.filters.brigade   ?? '',
  date_from: props.filters.date_from ?? '',
  date_to:   props.filters.date_to   ?? '',
})

const hasFilters = computed(() => Object.values(form.value).some(v => v !== ''))

function applyFilters() {
  router.get(route('tickets.index'), form.value, { preserveState: true, replace: true })
}

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

function resetFilters() {
  form.value = { search: '', status: '', type: '', brigade: '', date_from: '', date_to: '' }
  applyFilters()
}
</script>
