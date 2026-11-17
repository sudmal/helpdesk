<template>
  <div class="space-y-3">
    <RangePicker :range="range" />

    <p class="text-xs text-gray-400">
      Ответы абонентов на вопрос «Откуда вы о нас узнали?», заданный оператором при создании заявки на подключение.
      Конверсия — доля заявок, завершившихся подключением (статус «Выполнено»), от всех заявок с этим источником за период
      (по дате создания заявки). Заявки с сайта фирмы создаются без участия оператора — источник у них не указан.
    </p>

    <div class="grid grid-cols-3 gap-3">
      <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
        <div class="text-2xl font-bold text-gray-800">{{ data.total.total }}</div>
        <div class="text-xs text-gray-500 mt-1">Всего заявок</div>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
        <div class="text-2xl font-bold text-gray-800">{{ data.total.closed }}</div>
        <div class="text-xs text-gray-500 mt-1">Подключено</div>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
        <div class="text-2xl font-bold" :class="cls(data.total.conversion)">{{ fmtPct(data.total.conversion) }}</div>
        <div class="text-xs text-gray-500 mt-1">Общая конверсия</div>
      </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div v-if="range.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
      <div v-else-if="!data.rows.length" class="text-center py-10 text-gray-400 text-sm">За выбранный период заявок на подключение нет</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5 whitespace-nowrap">Источник</th>
              <th class="text-right px-3 py-2.5 whitespace-nowrap">Заявок</th>
              <th class="text-right px-3 py-2.5 whitespace-nowrap">Подключено</th>
              <th class="text-right px-3 py-2.5 whitespace-nowrap">Отказ/отклонено</th>
              <th class="text-left px-3 py-2.5 min-w-[160px]">Конверсия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in data.rows" :key="r.key" class="border-b border-gray-50 hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ r.label }}</td>
              <td class="px-3 py-2 text-right text-gray-600">{{ r.total }}</td>
              <td class="px-3 py-2 text-right text-gray-600">{{ r.closed }}</td>
              <td class="px-3 py-2 text-right text-gray-600">{{ r.lost }}</td>
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                  <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                    <div class="h-full rounded-full" :class="barCls(r.conversion)" :style="{ width: (r.conversion ?? 0) + '%' }"></div>
                  </div>
                  <span class="text-xs font-semibold w-11 text-right shrink-0" :class="cls(r.conversion)">{{ fmtPct(r.conversion) }}</span>
                </div>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-50 border-t border-gray-200 font-semibold text-gray-800">
              <td class="px-4 py-2.5">Итого</td>
              <td class="px-3 py-2.5 text-right">{{ data.total.total }}</td>
              <td class="px-3 py-2.5 text-right">{{ data.total.closed }}</td>
              <td class="px-3 py-2.5 text-right"></td>
              <td class="px-3 py-2.5" :class="cls(data.total.conversion)">{{ fmtPct(data.total.conversion) }}</td>
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

const range = useReportRange('reports.referral-source', { rows: [], total: { total: 0, closed: 0, conversion: null }, period: null })
const data  = computed(() => range.state.data)

const fmtPct = (v) => v == null ? '—' : `${v}%`
const cls    = (v) => v == null ? 'text-gray-400' : v >= 50 ? 'text-green-600' : v >= 25 ? 'text-lime-600' : v >= 10 ? 'text-yellow-600' : 'text-red-600'
const barCls = (v) => v == null ? 'bg-gray-300'   : v >= 50 ? 'bg-green-500'   : v >= 25 ? 'bg-lime-500'   : v >= 10 ? 'bg-yellow-500'   : 'bg-red-500'

onMounted(() => {
  range.state.periodMode = 'month'
  range.ensureLoaded()
})
</script>
