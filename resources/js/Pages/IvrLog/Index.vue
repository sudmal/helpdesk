<template>
  <Head title="Журнал IVR" />
  <AppLayout title="Журнал IVR">
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

      <!-- Фильтры -->
      <div class="p-4 border-b border-gray-100 flex flex-wrap gap-3 items-end bg-gray-50">
        <div class="flex-1 min-w-36">
          <label class="block text-xs text-gray-500 mb-1">Телефон</label>
          <input v-model="f.phone" @keydown.enter="apply" class="field-input" placeholder="+7..." />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Действие</label>
          <select v-model="f.action" class="field-input">
            <option value="">Все</option>
            <option v-for="(label, key) in actionLabels" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Дата с</label>
          <input v-model="f.date_from" type="date" class="field-input" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Дата по</label>
          <input v-model="f.date_to" type="date" class="field-input" />
        </div>
        <div class="flex gap-2">
          <button @click="apply" class="btn-primary text-sm">Найти</button>
          <button @click="reset" class="btn-outline text-sm">Сброс</button>
        </div>
      </div>

      <!-- Таблица -->
      <div class="px-4 py-2 border-b border-gray-100">
        <span class="text-sm text-gray-500">Всего: {{ logs.total }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-left">Время</th>
              <th class="px-4 py-3 text-left">Телефон</th>
              <th class="px-4 py-3 text-left">Абонент</th>
              <th class="px-4 py-3 text-left">Договор</th>
              <th class="px-4 py-3 text-right">Баланс</th>
              <th class="px-4 py-3 text-left">Действие</th>
              <th class="px-4 py-3 text-left">Детали</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-xs">
            <tr v-for="row in logs.data" :key="row.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ formatDate(row.created_at) }}</td>
              <td class="px-4 py-2 font-mono">{{ row.phone }}</td>
              <td class="px-4 py-2 text-gray-700">{{ row.subscriber_name ?? '—' }}</td>
              <td class="px-4 py-2 font-mono text-gray-600">{{ row.agreement_num ?? '—' }}</td>
              <td class="px-4 py-2 text-right tabular-nums" :class="row.balance < 0 ? 'text-red-600' : 'text-gray-700'">
                <span v-if="row.balance !== null">{{ row.balance }} ₽</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-2">
                <span :class="actionBadge(row.action)"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap">
                  {{ actionLabels[row.action] ?? row.action }}
                </span>
              </td>
              <td class="px-4 py-2 text-gray-500">{{ row.details ?? '' }}</td>
            </tr>
            <tr v-if="!logs.data.length">
              <td colspan="7" class="px-4 py-8 text-center text-gray-400">Нет записей</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Пагинация -->
      <div v-if="logs.last_page > 1" class="px-5 py-3 border-t border-gray-100 flex items-center gap-2">
        <button v-for="link in logs.links" :key="link.label"
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
import { ref } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  logs:         Object,
  actionLabels: Object,
  filters:      Object,
})

const f = ref({
  phone:     props.filters?.phone     ?? '',
  action:    props.filters?.action    ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to:   props.filters?.date_to   ?? '',
})

function apply() {
  router.get(route('ivr-log.index'), f.value, { preserveState: true })
}
function reset() {
  f.value = { phone: '', action: '', date_from: '', date_to: '' }
  apply()
}

function formatDate(val) {
  if (!val) return '—'
  const d = new Date(val)
  return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

const ACTION_COLORS = {
  balance_check:      'bg-blue-100 text-blue-700',
  pp_offered:         'bg-yellow-100 text-yellow-700',
  pp_activated:       'bg-green-100 text-green-700',
  pp_declined:        'bg-gray-100 text-gray-500',
  transfer_to_support:'bg-purple-100 text-purple-700',
  not_found:          'bg-orange-100 text-orange-600',
  api_error:          'bg-red-100 text-red-600',
}
function actionBadge(action) {
  return ACTION_COLORS[action] ?? 'bg-gray-100 text-gray-500'
}
</script>
