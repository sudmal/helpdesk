<template>
  <div class="space-y-3">
    <RangePicker :range="range" />

    <p class="text-xs text-gray-400">
      Закрытые заявки за период по типам и бригадам. В скобках — сколько из них
      оформлено актом. Заявки на подключение учтены по типу «Подключение» / «Перекл. на PON».
    </p>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div v-if="range.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
      <div v-else-if="!data.types.length" class="text-center py-10 text-gray-400 text-sm">
        За выбранный период закрытых заявок нет
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5 whitespace-nowrap">Тип работ</th>
              <th class="text-right px-4 py-2.5 whitespace-nowrap">Всего</th>
              <th v-for="b in data.brigades" :key="b.key"
                  class="text-right px-4 py-2.5 whitespace-nowrap">{{ b.name }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in data.types" :key="t.name" class="border-b border-gray-50 hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ t.name }}</td>
              <td class="px-4 py-2 text-right font-semibold text-gray-800 whitespace-nowrap">{{ fmt(t) }}</td>
              <td v-for="b in data.brigades" :key="b.key" class="px-4 py-2 text-right text-gray-600 whitespace-nowrap">
                {{ t.by_brigade[b.key] ? fmt(t.by_brigade[b.key]) : '—' }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-50 border-t border-gray-200 font-semibold text-gray-800">
              <td class="px-4 py-2.5">Итого</td>
              <td class="px-4 py-2.5 text-right whitespace-nowrap">{{ fmt(data.total) }}</td>
              <td v-for="b in data.brigades" :key="b.key" class="px-4 py-2.5 text-right whitespace-nowrap">{{ fmt(b) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import RangePicker from '@/Components/Reports/RangePicker.vue'
import { useReportRange } from '@/Composables/useReportRange'

const range = useReportRange('reports.works-done', { types: [], brigades: [], total: { all: 0, act: 0 } })
const data  = computed(() => range.state.data)

// "N (M)": N — все закрытые заявки, M — из них с актом
const fmt = (c) => `${c.all} (${c.act})`

onMounted(() => {
  range.state.periodMode = 'month'
  range.ensureLoaded()
})
</script>
