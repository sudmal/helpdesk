<template>
  <Head title="Журнал звонков" />
  <AppLayout title="Журнал звонков">

    <!-- Фильтры -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-36">
        <label class="block text-xs text-gray-500 mb-1">Телефон</label>
        <input v-model="f.phone" @keydown.enter="apply"
               class="field-input" placeholder="+7..." />
      </div>
      <div class="flex-1 min-w-48">
        <label class="block text-xs text-gray-500 mb-1">Адрес (из биллинга)</label>
        <input v-model="f.address" @keydown.enter="apply"
               class="field-input" placeholder="Железнодорожный..." />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Дата с</label>
        <input v-model="f.date_from" type="date" class="field-input" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Дата по</label>
        <input v-model="f.date_to" type="date" class="field-input" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Адрес сматчен</label>
        <select v-model="f.matched" class="field-input">
          <option value="">Все</option>
          <option value="yes">Да</option>
          <option value="no">Нет</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button @click="apply" class="btn-primary text-sm">Найти</button>
        <button @click="reset" class="btn-outline text-sm">Сброс</button>
      </div>
    </div>

    <!-- Таблица -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <span class="text-sm text-gray-500">Всего: {{ calls.total }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-left">Время</th>
              <th class="px-4 py-3 text-left">Телефон</th>
              <th class="px-4 py-3 text-left">Адрес из биллинга</th>
              <th class="px-4 py-3 text-left">Адрес в базе</th>
              <th class="px-4 py-3 text-left">Заявки</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="c in calls.data" :key="c.id" class="hover:bg-gray-50">
              <td class="px-4 py-2.5 whitespace-nowrap text-gray-500 text-xs">
                {{ formatDate(c.called_at) }}
              </td>
              <td class="px-4 py-2.5 font-mono whitespace-nowrap">
                <a :href="route('tickets.index', { search: c.phone })"
                   class="text-blue-600 hover:underline">{{ c.phone }}</a>
              </td>
              <td class="px-4 py-2.5 text-gray-700">{{ c.address_string ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <span v-if="c.address" class="text-green-700">
                  ✓ {{ c.address.full_address }}
                </span>
                <span v-else class="text-gray-400 text-xs">не найден</span>
              </td>
              <td class="px-4 py-2.5">
                <a v-if="c.address"
                   :href="route('tickets.index', { address_id: c.address.id })"
                   class="text-xs text-blue-500 hover:underline">заявки →</a>
              </td>
            </tr>
            <tr v-if="!calls.data.length">
              <td colspan="5" class="px-4 py-8 text-center text-gray-400">Нет записей</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Пагинация -->
      <div v-if="calls.last_page > 1"
           class="px-5 py-3 border-t border-gray-100 flex items-center gap-2">
        <button v-for="link in calls.links" :key="link.label"
                :disabled="!link.url || link.active"
                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                v-html="link.label"
                :class="['px-3 py-1 rounded-lg text-sm transition-colors',
                         link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600 disabled:opacity-40 disabled:cursor-default']" />
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  calls:   Object,
  filters: Object,
})

const f = ref({
  phone:     props.filters?.phone     ?? '',
  address:   props.filters?.address   ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to:   props.filters?.date_to   ?? '',
  matched:   props.filters?.matched   ?? '',
})

function apply() {
  router.get(route('calls.index'), f.value, { preserveState: true })
}

function reset() {
  f.value = { phone: '', address: '', date_from: '', date_to: '', matched: '' }
  apply()
}

function formatDate(val) {
  if (!val) return '—'
  const d = new Date(val)
  return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}
</script>