<template>
  <Head title="Акты" />
  <AppLayout title="Акты">

    <!-- Фильтры -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Статус</label>
        <select v-model="f.status" @change="apply" class="field-input">
          <option value="">Все</option>
          <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Тип</label>
        <select v-model="f.type" @change="apply" class="field-input">
          <option value="">Все</option>
          <option value="regular">Обычный</option>
          <option value="repair">Ремонт/Восстановление</option>
        </select>
      </div>
    </div>

    <!-- Таблица -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100">
        <span class="text-sm text-gray-500">Всего: {{ acts.total }}</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-50 text-[11px] text-gray-400 uppercase tracking-wide">
            <tr>
              <th class="px-3 py-2 text-left whitespace-nowrap">Номер</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Заявка</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Тип</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Статус</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Бригадир</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">ПЭО</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Логистика</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Абонотдел</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Создан</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="a in acts.data" :key="a.id" class="hover:bg-gray-50 cursor-pointer"
                @click="router.get(route('acts.show', a.id))">
              <td class="px-3 py-2 whitespace-nowrap font-mono font-medium text-gray-800">{{ a.number }}</td>
              <td class="px-3 py-2 whitespace-nowrap text-blue-600">#{{ a.ticket?.number }}</td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span class="px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 font-medium">{{ typeLabel(a.type) }}</span>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span :class="statusClass(a.status)" class="px-1.5 py-0.5 rounded font-medium">{{ statusLabels[a.status] || a.status }}</span>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">{{ stageMark(a.foreman_reviewed_at) }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ a.type === 'regular' ? stageMark(a.peo_processed_at) : '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ stageMark(a.logistics_processed_at) }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ stageMark(a.subscriber_dept_completed_at) }}</td>
              <td class="px-3 py-2 whitespace-nowrap text-gray-400">{{ fmtDate(a.created_at) }}</td>
            </tr>
            <tr v-if="!acts.data.length">
              <td colspan="9" class="px-4 py-8 text-center text-gray-400">Нет актов</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Пагинация -->
      <div v-if="acts.last_page > 1"
           class="px-5 py-3 border-t border-gray-100 flex items-center gap-2">
        <button v-for="link in acts.links" :key="link.label"
                :disabled="!link.url || link.active"
                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                v-html="link.label"
                :class="['px-3 py-0.5 rounded-lg text-sm transition-colors',
                         link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600 disabled:opacity-40 disabled:cursor-default']" />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  acts: Object,
  filters: Object,
})

const statusLabels = {
  pending_foreman:          'Ждёт бригадира',
  returned:                 'Возвращён',
  approved:                 'Утверждён',
  processing:               'В обработке',
  pending_subscriber_dept:  'Ждёт Абонотдел',
  completed:                'Завершён',
}

const f = reactive({
  status: props.filters?.status || '',
  type:   props.filters?.type   || '',
})

function apply() {
  router.get(route('acts.index'), { ...f }, { preserveState: true, replace: true })
}

function typeLabel(type) {
  return type === 'repair' ? 'Ремонт' : type === 'regular' ? 'Обычный' : '—'
}

function statusClass(status) {
  return {
    pending_foreman:         'bg-amber-100 text-amber-700',
    returned:                'bg-red-100 text-red-700',
    approved:                'bg-indigo-100 text-indigo-700',
    processing:              'bg-indigo-100 text-indigo-700',
    pending_subscriber_dept: 'bg-indigo-100 text-indigo-700',
    completed:               'bg-green-100 text-green-700',
  }[status] || 'bg-gray-100 text-gray-600'
}

function stageMark(at) {
  return at ? '✓' : '—'
}

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('ru-RU') : '—'
}
</script>
