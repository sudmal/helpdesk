<template>
  <Head title="Отчёты" />
  <AppLayout title="Отчёты">

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="bg-gray-50 border-b border-gray-200 flex items-end gap-0.5 px-3 pt-2 flex-wrap">
        <button v-for="tab in tabs" :key="tab.id" @click="switchTab(tab.id)"
                :class="['px-4 py-2 rounded-t-xl text-sm font-medium transition-colors',
                         activeTab === tab.id
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          {{ tab.label }}
        </button>
      </div>

    <div v-show="activeTab === 'brigade'" class="p-4 space-y-3">
      <RangePicker :range="brigade" />
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-600 mb-3">Количество заявок по бригадам</h2>
        <div v-if="brigade.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!brigade.state.data.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="brigadeCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
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
            <tr v-if="!brigade.state.data.labels.length">
              <td colspan="4" class="text-center py-4 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="(label, i) in brigade.state.data.labels" :key="i" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800">{{ label }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">{{ brigade.state.data.total[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-green-600">{{ brigade.state.data.closed[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-orange-500">{{ brigade.state.data.total[i] - brigade.state.data.closed[i] }}</td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- Частота по территориям -->
    <div v-show="activeTab === 'territory'" class="p-4 space-y-3">
      <RangePicker :range="territory" />
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-600 mb-3">Частота обращений по территориям</h2>
        <div v-if="territory.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!territory.state.data.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="territoryCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">Территория</th>
              <th class="text-right px-4 py-2.5 w-28">Заявок</th>
              <th class="text-right px-4 py-2.5 w-28">% от общего</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!territory.state.data.labels.length">
              <td colspan="3" class="text-center py-4 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="(label, i) in territory.state.data.labels" :key="i" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800">{{ label }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">{{ territory.state.data.values[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-500">
                {{ totalTerritory ? (territory.state.data.values[i] / totalTerritory * 100).toFixed(1) + '%' : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- Соблюдение сроков -->
    <div v-show="activeTab === 'deadlines'" class="p-4 space-y-3">
      <RangePicker :range="deadlines" />
      <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
          <div class="text-3xl font-bold text-gray-800">{{ deadlines.state.data.summary.total }}</div>
          <div class="text-xs text-gray-500 mt-1">Закрыто заявок</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
          <div class="text-3xl font-bold text-green-600">{{ deadlines.state.data.summary.on_time }}</div>
          <div class="text-xs text-gray-500 mt-1">Закрыто в срок</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
          <div :class="['text-3xl font-bold',
                        deadlines.state.data.summary.pct >= 80 ? 'text-green-600'
                        : deadlines.state.data.summary.pct >= 60 ? 'text-yellow-500'
                        : 'text-red-500']">
            {{ deadlines.state.data.summary.pct }}%
          </div>
          <div class="text-xs text-gray-500 mt-1">Соблюдение сроков</div>
        </div>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-600 mb-3">Соблюдение сроков по бригадам</h2>
        <div v-if="deadlines.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!deadlines.state.data.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет закрытых заявок за выбранный период</div>
        <canvas v-else ref="deadlineCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
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
            <tr v-if="!deadlines.state.data.labels.length">
              <td colspan="5" class="text-center py-4 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="(label, i) in deadlines.state.data.labels" :key="i" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800">{{ label }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">
                {{ deadlines.state.data.on_time[i] + deadlines.state.data.overdue[i] }}
              </td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-green-600">{{ deadlines.state.data.on_time[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-red-500">{{ deadlines.state.data.overdue[i] }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">
                {{ rowPct(deadlines.state.data.on_time[i], deadlines.state.data.overdue[i]) }}
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- Распределение по дням -->
    <div v-show="activeTab === 'distribution'" class="p-4 space-y-3">
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-3">
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
        <div v-if="!distributionState.loaded" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!hasDistData" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="distributionCanvas" style="max-height:380px" />
      </div>

      <!-- Легенда / итоговая таблица -->
      <div v-if="hasDistData" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 text-sm font-semibold text-gray-700">Итого за текущий месяц</div>
        <div class="overflow-x-auto">
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
    </div>

    <!-- Работа ТП -->
    <div v-show="activeTab === 'callcenter'" class="p-4 space-y-3">
      <RangePicker :range="callcenter" />
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center"><p class="text-2xl font-bold text-gray-800">{{ callcenter.state.data.summary?.total ?? 0 }}</p><p class="text-xs text-gray-500 mt-0.5">Всего звонков</p></div>
        <div class="bg-green-50 rounded-xl border border-green-200 p-3 text-center"><p class="text-2xl font-bold text-green-700">{{ callcenter.state.data.summary?.answer_rate ?? 0 }}%</p><p class="text-xs text-gray-500 mt-0.5">Отвечено</p></div>
        <div class="bg-red-50 rounded-xl border border-red-200 p-3 text-center"><p class="text-2xl font-bold text-red-600">{{ callcenter.state.data.summary?.missed ?? 0 }}</p><p class="text-xs text-gray-500 mt-0.5">Пропущено</p></div>
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-3 text-center"><p class="text-2xl font-bold text-blue-700">{{ callcenter.state.data.summary?.peak_hour != null ? callcenter.state.data.summary.peak_hour + ':00' : '—' }}</p><p class="text-xs text-gray-500 mt-0.5">Пиковый час</p></div>
        <div class="bg-orange-50 rounded-xl border border-orange-200 p-3 text-center"><p class="text-2xl font-bold text-orange-600">{{ callcenter.state.data.summary?.worst_hour != null ? callcenter.state.data.summary.worst_hour + ':00' : '—' }}</p><p class="text-xs text-gray-500 mt-0.5">Больше пропусков</p></div>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div v-if="callcenter.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!(callcenter.state.data.hours ?? []).some(h => h.total > 0)" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <template v-else>
          <h2 class="text-sm font-semibold text-gray-600 mb-3">Отвечено / Пропущено</h2>
          <canvas ref="callcenterCanvas" style="max-height:240px" />
          <div class="mt-5 pt-3 border-t border-gray-100">
            <h2 class="text-sm font-semibold text-gray-600 mb-3">Очередь и операторы</h2>
            <canvas ref="callcenterCanvas2" style="max-height:180px" />
          </div>
        </template>
      </div>
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium"><th class="text-left px-3 py-2.5">Час</th><th class="text-right px-3 py-2.5">Всего</th><th class="text-right px-3 py-2.5">Отвечено</th><th class="text-right px-3 py-2.5">Пропущено</th><th class="text-right px-3 py-2.5">Пропуск %</th><th class="text-right px-3 py-2.5 hidden md:table-cell">Ср. ожидание</th><th class="text-right px-3 py-2.5 hidden lg:table-cell">Макс. очередь</th><th class="text-right px-3 py-2.5 hidden lg:table-cell">Ср. операторов</th></tr></thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!(callcenter.state.data.hours ?? []).some(h => h.total > 0)"><td colspan="8" class="text-center py-4 text-gray-400 text-xs">—</td></tr>
            <tr v-for="h in (callcenter.state.data.hours ?? []).filter(h => h.total > 0)" :key="h.hour" :class="[h.miss_rate >= 40 ? 'bg-red-50 hover:bg-red-100' : h.miss_rate >= 20 ? 'bg-orange-50 hover:bg-orange-100' : 'hover:bg-gray-50']">
              <td class="px-3 py-1.5 font-medium text-gray-700 tabular-nums">{{ String(h.hour).padStart(2,'0') }}:00</td>
              <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ h.total }}</td>
              <td class="px-3 py-1.5 text-right tabular-nums text-green-700">{{ h.answered }}</td>
              <td class="px-3 py-1.5 text-right tabular-nums text-red-600">{{ h.missed }}</td>
              <td class="px-3 py-1.5 text-right tabular-nums"><span :class="['px-1.5 py-0.5 rounded text-xs font-medium', h.miss_rate >= 40 ? 'bg-red-100 text-red-700' : h.miss_rate >= 20 ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600']">{{ h.miss_rate }}%</span></td>
              <td class="px-3 py-1.5 text-right tabular-nums text-gray-500 hidden md:table-cell">{{ h.avg_wait != null ? Math.round(h.avg_wait) + 'c' : '—' }}</td>
              <td class="px-3 py-1.5 text-right tabular-nums hidden lg:table-cell"><span v-if="h.max_queue != null" :class="['font-medium', h.max_queue > (h.avg_operators ?? 999) ? 'text-red-600' : 'text-gray-700']">{{ h.max_queue }}</span><span v-else class="text-gray-400">—</span></td>
              <td class="px-3 py-1.5 text-right tabular-nums text-gray-500 hidden lg:table-cell">{{ h.avg_operators != null ? h.avg_operators : '—' }}</td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    </div><!-- end reports card -->

  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Head } from '@inertiajs/vue3'
import axios from 'axios'
import Chart from 'chart.js/auto'
Chart.defaults.animation = false
import AppLayout from '@/Components/Layout/AppLayout.vue'
import RangePicker from '@/Components/Reports/RangePicker.vue'
import { useReportRange } from '@/Composables/useReportRange'

const tabs = [
  { id: 'brigade',      label: 'Нагрузка бригад' },
  { id: 'territory',    label: 'Территории' },
  { id: 'deadlines',    label: 'Соблюдение сроков' },
  { id: 'distribution', label: 'Распределение по дням' },
  { id: 'callcenter',   label: 'Обработка звонков' },
]

const activeTab = ref('brigade')

// ── Каждая вкладка — независимый диапазон дат + свой запрос данных ──
// "Расход материалов" перенесён во вкладку "Отчёты" раздела Акты (2026-07-15,
// см. память project-acts-feature) — здесь больше не запрашивается.
const brigade    = useReportRange('reports.brigade-load',        { labels: [], total: [], closed: [] })
const territory  = useReportRange('reports.territory-frequency', { labels: [], values: [] })
const deadlines  = useReportRange('reports.deadline-compliance', { labels: [], on_time: [], overdue: [], summary: { total: 0, on_time: 0, pct: 0 } })
const callcenter = useReportRange('reports.call-stats',           { hours: [], summary: {} })

// ── Распределение по дням: без выбора диапазона, фиксированный период (текущий месяц) ──
const distMode = ref('day') // 'day' | 'weekday'
const distributionState = reactive({
  data: { byDay: { labels: [], datasets: [] }, byWeekday: { labels: [], datasets: [] } },
  loaded: false,
})

function toIso(d) { return d.toISOString().split('T')[0] }

async function ensureDistributionLoaded() {
  if (distributionState.loaded) return
  const now = new Date()
  const from = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`
  const to = toIso(now)
  const res = await axios.get(route('reports.distribution'), { params: { from, to } })
  distributionState.data = res.data
  distributionState.loaded = true
}

const brigadeCanvas      = ref(null)
const territoryCanvas    = ref(null)
const deadlineCanvas     = ref(null)
const distributionCanvas = ref(null)
const callcenterCanvas   = ref(null)
const callcenterCanvas2  = ref(null)

const charts = {}

const totalTerritory = computed(() =>
  territory.state.data.values.reduce((a, b) => a + b, 0)
)

const currentDistData = computed(() =>
  distMode.value === 'day' ? distributionState.data.byDay : distributionState.data.byWeekday
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

function rowPct(onTime, overdue) {
  const total = onTime + overdue
  return total ? (onTime / total * 100).toFixed(1) + '%' : '—'
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
  const data = brigade.state.data
  if (!brigadeCanvas.value || !data.labels.length) return
  charts.brigade = new Chart(brigadeCanvas.value, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [
        { label: 'Всего',   data: data.total,  backgroundColor: C.blue },
        { label: 'Закрыто', data: data.closed, backgroundColor: C.green },
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
  const data = territory.state.data
  if (!territoryCanvas.value || !data.labels.length) return
  charts.territory = new Chart(territoryCanvas.value, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [{ label: 'Заявок', data: data.values, backgroundColor: C.blue }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
    },
  })
}

function buildDeadline() {
  destroy('deadline')
  const data = deadlines.state.data
  if (!deadlineCanvas.value || !data.labels.length) return
  charts.deadline = new Chart(deadlineCanvas.value, {
    type: 'bar',
    data: {
      labels: data.labels,
      datasets: [
        { label: 'В срок',     data: data.on_time, backgroundColor: C.green, stack: 'a' },
        { label: 'Просрочено', data: data.overdue, backgroundColor: C.red,   stack: 'a' },
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

function buildCallcenter() {
  destroy('callcenter')
  destroy('callcenter2')
  if (!callcenterCanvas.value) return
  const hours = callcenter.state.data?.hours ?? []
  if (!hours.some(h => h.total > 0)) return
  const labels  = hours.map(h => h.hour + ':00')
  const answered = hours.map(h => h.answered)
  const missed   = hours.map(h => h.missed)
  const maxQ     = hours.map(h => h.max_queue ?? null)
  const avgOps   = hours.map(h => h.avg_operators ?? null)

  // График 1: столбцы звонков
  charts.callcenter = new Chart(callcenterCanvas.value, {
    type: 'bar',
    data: { labels, datasets: [
      { label: 'Отвечено',  data: answered, backgroundColor: '#22c55e', stack: 'a' },
      { label: 'Пропущено', data: missed,   backgroundColor: '#ef4444', stack: 'a' },
    ]},
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'top' } },
      scales: {
        x: { stacked: true, ticks: { maxRotation: 0 } },
        y: { beginAtZero: true, stacked: true, ticks: { precision: 0 }, title: { display: true, text: 'Звонки' } },
      },
    },
  })


  // График 2: среднее очереди = бары, пик = T-усик; операторы = ступени на единой оси
  const avgQData  = hours.map(h => h.avg_queue ?? null)
  const spikeData = hours.map(h => {
    const avg = h.avg_queue ?? null
    const max = h.max_queue ?? null
    if (max == null || max === 0) return null
    if (avg == null) return [0, max]
    if (max > avg)   return [avg, max]
    return null
  })

  if (callcenterCanvas2.value && (maxQ.some(v => v != null) || avgOps.some(v => v != null))) {
    const whiskerCap = {
      id: 'whiskerCap',
      afterDatasetsDraw(chart) {
        const dsIdx = chart.data.datasets.findIndex(d => d.label === 'Макс. очередь')
        if (dsIdx < 0) return
        const meta = chart.getDatasetMeta(dsIdx)
        const ds   = chart.data.datasets[dsIdx]
        const ctx2 = chart.ctx
        ctx2.save()
        ctx2.strokeStyle = 'rgba(249,115,22,0.95)'
        ctx2.lineWidth = 2
        meta.data.forEach((bar, i) => {
          if (ds.data[i] == null) return
          const capW = Math.max((bar.width || 4) * 3, 10)
          ctx2.beginPath()
          ctx2.moveTo(bar.x - capW / 2, bar.y)
          ctx2.lineTo(bar.x + capW / 2, bar.y)
          ctx2.stroke()
        })
        ctx2.restore()
      }
    }
    charts.callcenter2 = new Chart(callcenterCanvas2.value, {
      plugins: [whiskerCap],
      type: 'bar',
      data: { labels, datasets: [
        { label: 'Ср. очередь',   data: avgQData,  type: 'bar',  backgroundColor: 'rgba(34,197,94,0.55)',  borderColor: 'rgba(34,197,94,0.8)',  borderWidth: 1, order: 3 },
        { label: 'Макс. очередь', data: spikeData, type: 'bar',  backgroundColor: 'rgba(249,115,22,0.65)', borderColor: 'rgba(249,115,22,0.9)', borderWidth: 1, barThickness: 4, order: 2 },
        { label: 'Операторов',    data: avgOps,    type: 'line', borderColor: '#6366f1', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 4, stepped: 'before', fill: false, spanGaps: true, order: 1 },
      ]},
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top' },
          tooltip: {
            callbacks: {
              label: ctx => {
                const i = ctx.dataIndex
                if (ctx.dataset.label === 'Ср. очередь')   return 'Ср. очередь: '   + (hours[i].avg_queue ?? '—')
                if (ctx.dataset.label === 'Макс. очередь') return 'Макс. очередь: ' + (hours[i].max_queue ?? '—')
                return ctx.dataset.label + ': ' + ctx.parsed.y
              }
            }
          }
        },
        scales: {
          x: { ticks: { maxRotation: 0 } },
          y: { beginAtZero: true, ticks: { precision: 0 } },
        },
      },
    })
  }
}

function buildForTab(tab) {
  nextTick(() => {
    if (tab === 'brigade')      buildBrigade()
    if (tab === 'territory')    buildTerritory()
    if (tab === 'deadlines')    buildDeadline()
    if (tab === 'distribution') buildDistribution()
    if (tab === 'callcenter')   buildCallcenter()
  })
}

function ensureLoadedForTab(tab) {
  if (tab === 'brigade')      brigade.ensureLoaded()
  if (tab === 'territory')    territory.ensureLoaded()
  if (tab === 'deadlines')    deadlines.ensureLoaded()
  if (tab === 'callcenter')   callcenter.ensureLoaded()
  if (tab === 'distribution') ensureDistributionLoaded()
}

function switchTab(id) {
  activeTab.value = id
  ensureLoadedForTab(id)
  buildForTab(id)
}

// Перестраивать график вкладки при каждом новом ответе сервера (смена диапазона)
watch(() => brigade.state.data,    () => nextTick(buildBrigade))
watch(() => territory.state.data,  () => nextTick(buildTerritory))
watch(() => deadlines.state.data,  () => nextTick(buildDeadline))
watch(() => callcenter.state.data, () => nextTick(buildCallcenter))
watch(() => distributionState.data, () => nextTick(buildDistribution))

onMounted(() => {
  ensureLoadedForTab(activeTab.value)
  buildForTab(activeTab.value)
})

onBeforeUnmount(() => {
  Object.values(charts).forEach(c => c.destroy())
})
</script>
