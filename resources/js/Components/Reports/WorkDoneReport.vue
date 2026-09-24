<template>
  <div class="space-y-3">
    <RangePicker :range="range" />

    <p class="text-xs text-gray-400">
      Закрытые заявки за период по типам и бригадам. В скобках — сколько из них оформлено актом.
      Первая цифра открывает список заявок, цифра в скобках — список актов.
      Итоговая строка складывает обычные заявки и заявки на подключение, поэтому не кликается.
    </p>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div v-if="range.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
      <div v-else-if="!data.rows.length" class="text-center py-10 text-gray-400 text-sm">
        За выбранный период закрытых заявок нет
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5 whitespace-nowrap">Тип работ</th>
              <th class="text-right px-4 py-2.5 whitespace-nowrap">Всего (с актами)</th>
              <th v-for="b in data.brigades" :key="b.key"
                  class="text-right px-4 py-2.5 whitespace-nowrap">{{ b.name }} (с актами)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in data.rows" :key="r.key" class="border-b border-gray-50 hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ r.label }}</td>
              <td class="px-4 py-2 text-right font-semibold text-gray-800 whitespace-nowrap">
                <Cell :row="r" :cell="r" :period="data.period" :closed-status-id="data.closed_status_id" />
              </td>
              <td v-for="b in data.brigades" :key="b.key" class="px-4 py-2 text-right text-gray-600 whitespace-nowrap">
                <Cell v-if="r.by_brigade[b.key]" :row="r" :cell="r.by_brigade[b.key]" :brigade-key="b.key"
                      :period="data.period" :closed-status-id="data.closed_status_id" />
                <span v-else>—</span>
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
import { computed, defineComponent, h, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import RangePicker from '@/Components/Reports/RangePicker.vue'
import { useReportRange } from '@/Composables/useReportRange'

const range = useReportRange('reports.works-done', {
  rows: [], brigades: [], total: { all: 0, act: 0 }, period: null, closed_status_id: null,
})
const data = computed(() => range.state.data)

// "N (M)": N — все закрытые заявки, M — из них с актом
const fmt = (c) => `${c.all} (${c.act})`

// Ячейка "N (M)": N → список заявок (или заявок на подключение), M → список актов.
// brigadeKey не задан — колонка "Всего" по строке (без фильтра по бригаде);
// brigadeKey === 0 — "Без бригады".
const Cell = defineComponent({
  props: {
    row:            { type: Object, required: true },
    cell:           { type: Object, required: true },
    brigadeKey:     { type: [Number, String], default: undefined },
    period:         { type: Object, default: null },
    closedStatusId: { type: Number, default: null },
  },
  setup(props) {
    const linkCls = 'text-blue-600 hover:underline'

    const base = () => {
      const q = {}
      if (props.brigadeKey !== undefined) q.brigade = props.brigadeKey === 0 ? 'none' : props.brigadeKey
      if (props.period) { q.closed_from = props.period.from; q.closed_to = props.period.to }
      return q
    }

    const allUrl = () => props.row.source === 'ticket'
      ? route('tickets.index', { ...base(), status: props.closedStatusId, type: props.row.type_id })
      : route('connection-requests.index', { ...base(), status: 'closed', kind: props.row.kind })

    const actUrl = () => route('acts.index', {
      ...base(), tab: 'all',
      ...(props.row.source === 'ticket' ? { ticket_type: props.row.type_id } : { kind: props.row.kind }),
    })

    return () => h('span', [
      h(Link, { href: allUrl(), class: linkCls }, () => String(props.cell.all)),
      ' (',
      props.cell.act > 0
        ? h(Link, { href: actUrl(), class: linkCls }, () => String(props.cell.act))
        : '0',
      ')',
    ])
  },
})

onMounted(() => {
  range.state.periodMode = 'month'
  range.ensureLoaded()
})
</script>
