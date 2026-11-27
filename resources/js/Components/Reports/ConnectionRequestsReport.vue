<template>
  <div class="space-y-3">
    <RangePicker :range="range" :modes="['month', 'quarter', 'period']" />

    <p class="text-xs text-gray-400">
      Заявки на подключение по территориям и статусам за период (по дате создания заявки).
      Заявки с сайта фирмы, которым оператор ещё не проставил территорию, попадают в строку «Без территории».
    </p>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div v-if="range.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
      <div v-else-if="!data.rows.length" class="text-center py-10 text-gray-400 text-sm">За выбранный период заявок на подключение нет</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5 whitespace-nowrap">Территория</th>
              <th v-for="s in data.statuses" :key="s" :title="statusHint(s)" class="text-right px-3 py-2.5 whitespace-nowrap">
                {{ statusLabel(s) }}
                <span v-if="statusHint(s)" class="text-gray-300">ⓘ</span>
              </th>
              <th class="text-right px-4 py-2.5 whitespace-nowrap">Всего</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in data.rows" :key="r.territory_id ?? 'none'"
                :class="['border-b border-gray-50 hover:bg-gray-50', !r.territory_id ? 'text-gray-500 italic' : '']">
              <td class="px-4 py-2 whitespace-nowrap">{{ r.label }}</td>
              <td v-for="s in data.statuses" :key="s" class="px-3 py-2 text-right font-mono tabular-nums">
                <span v-if="r[s]" :class="statusTextCls(s)">{{ r[s] }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-2 text-right font-mono tabular-nums font-semibold text-gray-800">{{ r.total }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-50 border-t border-gray-200 font-semibold text-gray-800">
              <td class="px-4 py-2.5">Итого</td>
              <td v-for="s in data.statuses" :key="s" class="px-3 py-2.5 text-right font-mono tabular-nums">{{ data.totals[s] }}</td>
              <td class="px-4 py-2.5 text-right font-mono tabular-nums">{{ data.totals.total }}</td>
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

// "pending" разбит на pending_tech/pending_callback -- это разные этапы работы с
// заявкой (техвозможность ещё не оценена / оценена, ждём прозвона абонента), не
// одно "Ожидает" -- та же логика, что и в бейдже/фильтре на странице Подключений
// (см. statusLabel()/STATUS_OPTIONS в ConnectionRequests/Index.vue).
const range = useReportRange('reports.connections', {
  rows: [], totals: { total: 0 },
  statuses: ['pending_tech', 'pending_callback', 'scheduled', 'closed', 'rejected', 'cancelled'],
  period: null,
})
const data = computed(() => range.state.data)

function statusLabel(s) {
  return {
    pending_tech:     'Ожидает техвозм.',
    pending_callback: 'Ожидает прозвон',
    scheduled:        'Назначено',
    closed:           'Выполнено',
    rejected:         'Отклонено',
    cancelled:        'Самоотказ',
  }[s] ?? s
}
function statusHint(s) {
  return {
    pending_tech:     'Заявка создана, монтажник ещё не оценил техническую возможность подключения по этому адресу.',
    pending_callback: 'Техвозможность подтверждена монтажником — осталось дозвониться до абонента и согласовать дату.',
    scheduled:        'Согласована дата и время выезда бригады (подключение или переключение на ПОН).',
    closed:           'Подключение/переключение выполнено бригадой, по заявке создан акт.',
    rejected:         'Подключение технически невозможно — ставится автоматически, когда монтажник после прозвона отмечает «невозможно».',
    cancelled:        'Абонент сам отказался от подключения после того, как оставил заявку — технически подключить было можно.',
  }[s] ?? null
}
function statusTextCls(s) {
  return {
    pending_tech:     'text-amber-700',
    pending_callback: 'text-yellow-700',
    scheduled:        'text-blue-700',
    closed:           'text-green-700',
    rejected:         'text-red-600',
    cancelled:        'text-gray-500',
  }[s] ?? 'text-gray-700'
}

onMounted(() => {
  range.state.periodMode = 'month'
  range.ensureLoaded()
})
</script>
