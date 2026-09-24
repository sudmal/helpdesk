<template>
  <div class="space-y-4">
    <RangePicker :range="range" />

    <p class="text-xs text-gray-400">
      Считаются акты, созданные за выбранный период: один акт — одна выполненная работа.
      Заявки на подключение учтены как «Подключение».
    </p>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div v-if="range.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
      <div v-else-if="!data.types.length" class="text-center py-10 text-gray-400 text-sm">
        За выбранный период работ нет
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5 whitespace-nowrap">Тип работ</th>
              <th class="text-right px-4 py-2.5 w-24 whitespace-nowrap">Всего</th>
              <th v-for="b in data.brigades" :key="b.key"
                  class="text-right px-4 py-2.5 whitespace-nowrap">{{ b.name }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in data.types" :key="t.name" class="border-b border-gray-50 hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ t.name }}</td>
              <td class="px-4 py-2 text-right font-semibold text-gray-800">{{ t.total }}</td>
              <td v-for="b in data.brigades" :key="b.key" class="px-4 py-2 text-right text-gray-600">
                {{ t.by_brigade[b.key] || '—' }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-50 border-t border-gray-200 font-semibold text-gray-800">
              <td class="px-4 py-2.5">Итого</td>
              <td class="px-4 py-2.5 text-right">{{ data.total }}</td>
              <td v-for="b in data.brigades" :key="b.key" class="px-4 py-2.5 text-right">{{ b.total }}</td>
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

const range = useReportRange('acts.report.works-done', { types: [], brigades: [], total: 0 })
const data  = computed(() => range.state.data)

onMounted(() => {
  range.state.periodMode = 'month'
  range.ensureLoaded()
})
</script>
