<template>
  <Head title="Отчёты" />
  <AppLayout title="Отчёты">

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-500">С</label>
        <input type="date" v-model="localFrom"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-500">По</label>
        <input type="date" v-model="localTo"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <button @click="applyFilter"
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors">
        Применить
      </button>
    </div>

    <div class="flex gap-1 mb-6 bg-white border border-gray-200 rounded-2xl p-1 w-fit flex-wrap">
      <button v-for="tab in tabs" :key="tab.id" @click="switchTab(tab.id)"
              :class="['px-4 py-2 rounded-xl text-sm font-medium transition-colors',
                       activeTab === tab.id
                         ? 'bg-blue-600 text-white shadow-sm'
                         : 'text-gray-500 hover:text-gray-800 hover:bg-gray-100']">
        {{ tab.label }}
      </button>
    </div>

    <!-- Нагрузка на бригады -->
    <div v-show="activeTab === 'brigade'" class="space-y-4">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-600 mb-4">Количество заявок по бригадам</h2>
        <div v-if="!brigadeLoad.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="brigadeCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">Бригада</th>
              <th class="text-right px-4 py-2.5 w-28">Всего</th>
              <th class="text-right px-4 py-2.5 w-28">Закрыто</th>
              <th class="text-right px-4 py-2.5 w-28">Открыто</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!brigadeLoad.labels.length">
              <td colspan="4" class="text-center py-6 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="(label, i) in brigadeLoad.labels" :key="i" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800">{{ label }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">{{ brigadeLoad.total[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-green-600">{{ brigadeLoad.closed[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-orange-500">{{ brigadeLoad.total[i] - brigadeLoad.closed[i] }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Частота по территориям -->
    <div v-show="activeTab === 'territory'" class="space-y-4">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-600 mb-4">Частота обращений по территориям</h2>
        <div v-if="!territoryFrequency.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="territoryCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">Территория</th>
              <th class="text-right px-4 py-2.5 w-28">Заявок</th>
              <th class="text-right px-4 py-2.5 w-28">% от общего</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!territoryFrequency.labels.length">
              <td colspan="3" class="text-center py-6 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="(label, i) in territoryFrequency.labels" :key="i" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800">{{ label }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">{{ territoryFrequency.values[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-500">
                {{ totalTerritory ? (territoryFrequency.values[i] / totalTerritory * 100).toFixed(1) + '%' : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Расход материалов -->
    <div v-show="activeTab === 'materials'" class="space-y-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-600 mb-4">Динамика расхода (ед.) по неделям</h2>
          <div v-if="!materialDynamics.weekly.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
          <canvas v-else ref="materialQtyCanvas" style="max-height:280px" />
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <h2 class="text-sm font-semibold text-gray-600 mb-4">Динамика суммы (₽) по неделям</h2>
          <div v-if="!materialDynamics.weekly.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
          <canvas v-else ref="materialAmtCanvas" style="max-height:280px" />
        </div>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 text-sm font-semibold text-gray-700">Топ-10 материалов по сумме</div>
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-center px-4 py-2.5 w-20">Код</th>
              <th class="text-left px-4 py-2.5">Наименование</th>
              <th class="text-center px-4 py-2.5 w-20">Ед.</th>
              <th class="text-right px-4 py-2.5 w-28">Кол-во</th>
              <th class="text-right px-4 py-2.5 w-36">Сумма, ₽</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!materialDynamics.top.length">
              <td colspan="5" class="text-center py-6 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="(m, i) in materialDynamics.top" :key="i" class="hover:bg-gray-50">
              <td class="px-4 py-1.5 text-center text-gray-400 font-mono text-xs">{{ m.code || '—' }}</td>
              <td class="px-4 py-1.5 text-gray-800">{{ m.name }}</td>
              <td class="px-4 py-1.5 text-center text-gray-500 text-xs">{{ m.unit }}</td>
              <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ m.qty }}</td>
              <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(m.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Соблюдение сроков -->
    <div v-show="activeTab === 'deadlines'" class="space-y-4">
      <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-5 text-center">
          <div class="text-3xl font-bold text-gray-800">{{ deadlineCompliance.summary.total }}</div>
          <div class="text-xs text-gray-500 mt-1">Закрыто заявок</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 text-center">
          <div class="text-3xl font-bold text-green-600">{{ deadlineCompliance.summary.on_time }}</div>
          <div class="text-xs text-gray-500 mt-1">Закрыто в срок</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-5 text-center">
          <div :class="['text-3xl font-bold',
                        deadlineCompliance.summary.pct >= 80 ? 'text-green-600'
                        : deadlineCompliance.summary.pct >= 60 ? 'text-yellow-500'
                        : 'text-red-500']">
            {{ deadlineCompliance.summary.pct }}%
          </div>
          <div class="text-xs text-gray-500 mt-1">Соблюдение сроков</div>
        </div>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-600 mb-4">Соблюдение сроков по бригадам</h2>
        <div v-if="!deadlineCompliance.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет закрытых заявок за выбранный период</div>
        <canvas v-else ref="deadlineCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">Бригада</th>
              <th class="text-right px-4 py-2.5 w-24">Всего</th>
              <th class="text-right px-4 py-2.5 w-24">В срок</th>
              <th class="text-right px-4 py-2.5 w-24">Просрочено</th>
              <th class="text-right px-4 py-2.5 w-28">% в срок</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!deadlineCompliance.labels.length">
              <td colspan="5" class="text-center py-6 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="(label, i) in deadlineCompliance.labels" :key="i" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800">{{ label }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">
                {{ deadlineCompliance.on_time[i] + deadlineCompliance.overdue[i] }}
              </td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-green-600">{{ deadlineCompliance.on_time[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-red-500">{{ deadlineCompliance.overdue[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">
                {{ rowPct(deadlineCompliance.on_time[i], deadlineCompliance.overdue[i]) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Распределение по дням -->
    <div v-show="activeTab === 'distribution'" class="space-y-4">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-sm font-semibold text-gray-600">Распределение заявок по типу обращения</h2>
          <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
            <button @click="switchDistMode('day')"
                    :class="['px-3 py-1 rounded-lg text-xs font-medium transition-colors',
                             distMode === 'day' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
              По дням месяца
            </button>
            <button @click="switchDistMode('weekday')"
                    :class="['px-3 py-1 rounded-lg text-xs font-medium transition-colors',
                             distMode === 'weekday' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
              По дням недели
            </button>
          </div>
        </div>
        <div v-if="!hasDistData" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="distributionCanvas" style="max-height:380px" />
      </div>

      <!-- Легенда / итоговая таблица -->
      <div v-if="hasDistData" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 text-sm font-semibold text-gray-700">Итого за период</div>
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">Тип обращения</th>
              <th class="text-right px-4 py-2.5 w-32">Всего заявок</th>
              <th class="text-right px-4 py-2.5 w-28">% от общего</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="ds in distTotals" :key="ds.name" class="hover:bg-gray-50">
              <td class="px-4 py-2">
                <span class="inline-block w-3 h-3 rounded-full mr-2 align-middle"
                      :style="{ backgroundColor: ds.color }"></span>
                <span class="text-gray-800">{{ ds.name }}</span>
              </td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">{{ ds.total }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-500">
                {{ distGrandTotal ? (ds.total / distGrandTotal * 100).toFixed(1) + '%' : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  from: String,
  to: String,
  brigadeLoad: Object,
  territoryFrequency: Object,
  materialDynamics: Object,
  deadlineCompliance: Object,
  distribution: Object,
})

const tabs = [
  { id: 'brigade',      label: 'Нагрузка бригад' },
  { id: 'territory',    label: 'Территории' },
  { id: 'materials',    label: 'Расход материалов' },
  { id: 'deadlines',    label: 'Соблюдение сроков' },
  { id: 'distribution', label: 'Распределение по дням' },
]

const activeTab    = ref('brigade')
const localFrom    = ref(props.from)
const localTo      = ref(props.to)
const distMode     = ref('day') // 'day' | 'weekday'

const brigadeCanvas      = ref(null)
const territoryCanvas    = ref(null)
const materialQtyCanvas  = ref(null)
const materialAmtCanvas  = ref(null)
const deadlineCanvas     = ref(null)
const distributionCanvas = ref(null)

const charts = {}

const totalTerritory = computed(() =>
  props.territoryFrequency.values.reduce((a, b) => a + b, 0)
)

const currentDistData = computed(() =>
  distMode.value === 'day' ? props.distribution.byDay : props.distribution.byWeekday
)

const hasDistData = computed(() =>
  currentDistData.value.datasets.some(ds => ds.data.some(v => v > 0))
)

const distTotals = computed(() =>
  currentDistData.value.datasets.map(ds => ({
    name:  ds.name,
    color: ds.color,
    total: ds.data.reduce((a, b) => a + b, 0),
  })).filter(ds => ds.total > 0)
)

const distGrandTotal = computed(() =>
  distTotals.value.reduce((a, b) => a + b.total, 0)
)

function formatMoney(val) {
  return Number(val).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function rowPct(onTime, overdue) {
  const total = onTime + overdue
  return total ? (onTime / total * 100).toFixed(1) + '%' : '—'
}

function applyFilter() {
  router.get(route('reports.index'), { from: localFrom.value, to: localTo.value }, { preserveState: false })
}

const C = {
  blue:       'rgba(59,130,246,0.85)',
  blueAlpha:  'rgba(59,130,246,0.15)',
  green:      'rgba(34,197,94,0.85)',
  greenAlpha: 'rgba(34,197,94,0.15)',
  red:        'rgba(239,68,68,0.85)',
  orange:     'rgba(249,115,22,0.85)',
}

function destroy(key) {
  if (charts[key]) { charts[key].destroy(); delete charts[key] }
}

function buildBrigade() {
  destroy('brigade')
  if (!brigadeCanvas.value || !props.brigadeLoad.labels.length) return
  charts.brigade = new Chart(brigadeCanvas.value, {
    type: 'bar',
    data: {
      labels: props.brigadeLoad.labels,
      datasets: [
        { label: 'Всего',   data: props.brigadeLoad.total,  backgroundColor: C.blue },
        { label: 'Закрыто', data: props.brigadeLoad.closed, backgroundColor: C.green },
      ],
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  })
}

function buildTerritory() {
  destroy('territory')
  if (!territoryCanvas.value || !props.territoryFrequency.labels.length) return
  charts.territory = new Chart(territoryCanvas.value, {
    type: 'bar',
    data: {
      labels: props.territoryFrequency.labels,
      datasets: [{ label: 'Заявок', data: props.territoryFrequency.values, backgroundColor: C.blue }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  })
}

function buildMaterials() {
  destroy('materialQty')
  destroy('materialAmt')
  const labels = props.materialDynamics.weekly.labels
  if (!labels.length) return
  if (materialQtyCanvas.value) {
    charts.materialQty = new Chart(materialQtyCanvas.value, {
      type: 'line',
      data: {
        labels,
        datasets: [{ label: 'Кол-во', data: props.materialDynamics.weekly.qty,
          borderColor: C.blue, backgroundColor: C.blueAlpha, fill: true, tension: 0.35 }],
      },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    })
  }
  if (materialAmtCanvas.value) {
    charts.materialAmt = new Chart(materialAmtCanvas.value, {
      type: 'line',
      data: {
        labels,
        datasets: [{ label: 'Сумма', data: props.materialDynamics.weekly.amount,
          borderColor: C.green, backgroundColor: C.greenAlpha, fill: true, tension: 0.35 }],
      },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    })
  }
}

function buildDeadline() {
  destroy('deadline')
  if (!deadlineCanvas.value || !props.deadlineCompliance.labels.length) return
  charts.deadline = new Chart(deadlineCanvas.value, {
    type: 'bar',
    data: {
      labels: props.deadlineCompliance.labels,
      datasets: [
        { label: 'В срок',     data: props.deadlineCompliance.on_time, backgroundColor: C.green, stack: 'a' },
        { label: 'Просрочено', data: props.deadlineCompliance.overdue, backgroundColor: C.red,   stack: 'a' },
      ],
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { stacked: true } },
    },
  })
}

function buildDistribution() {
  destroy('distribution')
  if (!distributionCanvas.value || !hasDistData.value) return
  const src = currentDistData.value
  charts.distribution = new Chart(distributionCanvas.value, {
    type: 'line',
    data: {
      labels: src.labels,
      datasets: src.datasets.map(ds => ({
        label:           ds.name,
        data:            ds.data,
        borderColor:     ds.color,
        backgroundColor: ds.color + '22',
        borderWidth:     2,
        pointRadius:     3,
        pointHoverRadius: 5,
        tension:         0.35,
        fill:            false,
      })),
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { position: 'top' },
        tooltip: { mode: 'index' },
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } },
      },
    },
  })
}

function switchDistMode(mode) {
  distMode.value = mode
  nextTick(() => buildDistribution())
}

function buildForTab(tab) {
  nextTick(() => {
    if (tab === 'brigade')      buildBrigade()
    if (tab === 'territory')    buildTerritory()
    if (tab === 'materials')    buildMaterials()
    if (tab === 'deadlines')    buildDeadline()
    if (tab === 'distribution') buildDistribution()
  })
}

function switchTab(id) {
  activeTab.value = id
  buildForTab(id)
}

onMounted(() => buildForTab(activeTab.value))

onBeforeUnmount(() => {
  Object.values(charts).forEach(c => c.destroy())
})
</script>
