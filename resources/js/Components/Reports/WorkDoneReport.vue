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

    <!-- Соотношение работ с актами и без актов по бригадам -->
    <div v-if="!range.state.loading && data.brigades.length" class="grid lg:grid-cols-2 gap-3">
      <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <h3 class="text-sm font-medium text-gray-700 mb-2">Работы с актами и без актов — по бригадам</h3>
        <div class="relative h-72"><canvas ref="absCanvas"></canvas></div>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <h3 class="text-sm font-medium text-gray-700 mb-2">Доля работ с актами, %</h3>
        <div class="relative h-72"><canvas ref="pctCanvas"></canvas></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'
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

// ── Графики: с актом / без акта по бригадам ──
const absCanvas = ref(null)
const pctCanvas = ref(null)
let absChart = null
let pctChart = null
const COLOR_ACT = '#22c55e'
const COLOR_NO_ACT = '#f97316'

function destroyCharts() {
  absChart?.destroy(); absChart = null
  pctChart?.destroy(); pctChart = null
}

function buildCharts() {
  destroyCharts()
  const bs = data.value.brigades
  if (!bs.length || !absCanvas.value || !pctCanvas.value) return

  const labels  = bs.map(b => b.name)
  const withAct = bs.map(b => b.act)
  const without = bs.map(b => b.all - b.act)
  const pctAct  = bs.map(b => b.all ? (b.act / b.all) * 100 : 0)
  const pctNo   = bs.map(b => b.all ? ((b.all - b.act) / b.all) * 100 : 0)

  const common = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
  }

  absChart = new Chart(absCanvas.value, {
    type: 'bar',
    data: { labels, datasets: [
      { label: 'С актом',   data: withAct, backgroundColor: COLOR_ACT },
      { label: 'Без акта',  data: without, backgroundColor: COLOR_NO_ACT },
    ] },
    options: { ...common, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
  })

  pctChart = new Chart(pctCanvas.value, {
    type: 'bar',
    data: { labels, datasets: [
      { label: 'С актом',  data: pctAct, backgroundColor: COLOR_ACT },
      { label: 'Без акта', data: pctNo,  backgroundColor: COLOR_NO_ACT },
    ] },
    options: {
      ...common,
      scales: { x: { stacked: true }, y: { stacked: true, min: 0, max: 100, ticks: { callback: v => v + '%' } } },
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: (ctx) => {
          const b = bs[ctx.dataIndex]
          const cnt = ctx.datasetIndex === 0 ? b.act : b.all - b.act
          return `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)}% (${cnt} из ${b.all})`
        } } },
      },
    },
  })
}

watch([() => range.state.data, () => range.state.loading], () => nextTick(buildCharts))

onMounted(() => {
  Chart.defaults.animation = false
  range.state.periodMode = 'month'
  range.ensureLoaded()
})

onBeforeUnmount(destroyCharts)
</script>
