<template>
  <div>
    <div class="flex flex-wrap gap-1 bg-gray-100 rounded-xl p-1 mb-4 w-fit">
      <button v-for="t in subTabs" :key="t.id" @click="switchSub(t.id)"
              :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                       activeSub === t.id ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
        {{ t.label }}
      </button>
    </div>

    <!-- ── Расход материалов за период ── -->
    <div v-show="activeSub === 'consumption'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
          <button v-for="d in dimensions" :key="d.key" @click="setDimension(d.key)"
                  :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                           dimension === d.key ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
            {{ d.label }}
          </button>
        </div>
        <a :href="exportUrl" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Экспорт CSV →</a>
      </div>

      <RangePicker :range="consumption" />

      <div v-if="selectedEntity" class="flex items-center gap-2 text-sm">
        <button @click="clearEntity" class="text-blue-600 hover:text-blue-800 font-medium">
          ← Ко всем {{ dimensionLabelPlural(dimension) }}
        </button>
        <span class="text-gray-300">/</span>
        <span class="text-gray-700 font-medium">{{ selectedEntity.label }}</span>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div v-if="consumption.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div class="overflow-x-auto" v-else>
        <table class="w-full text-sm">
          <thead v-if="showMaterialColumns">
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-center px-3 py-2.5 w-16">Код</th>
              <th class="text-left px-4 py-2.5">Материал</th>
              <th class="text-center px-3 py-2.5 w-16">Ед.</th>
              <th class="text-right px-4 py-2.5 w-28">Кол-во</th>
              <th class="text-right px-4 py-2.5 w-32">Сумма, ₽</th>
              <th class="text-right px-4 py-2.5 w-24">Δ к пред. периоду</th>
            </tr>
          </thead>
          <thead v-else-if="dimension === 'brigade'">
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">Бригада</th>
              <th class="text-right px-4 py-2.5 w-28">Кол-во</th>
              <th class="text-right px-4 py-2.5 w-32">Сумма, ₽</th>
              <th class="text-right px-4 py-2.5 w-24">Заявок</th>
              <th class="text-right px-4 py-2.5 w-32">₽ на заявку</th>
            </tr>
          </thead>
          <thead v-else>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">{{ dimension === 'service_type' ? 'Участок' : 'Территория' }}</th>
              <th class="text-right px-4 py-2.5 w-28">Кол-во</th>
              <th class="text-right px-4 py-2.5 w-32">Сумма, ₽</th>
              <th class="text-right px-4 py-2.5 w-24">Заявок</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!consumption.state.data.rows.length">
              <td :colspan="showMaterialColumns ? 6 : dimension === 'brigade' ? 5 : 4" class="text-center py-10 text-gray-400 text-xs">Нет данных за выбранный период</td>
            </tr>
            <template v-else-if="showMaterialColumns">
              <tr v-for="r in consumption.state.data.rows" :key="r.key" class="hover:bg-gray-50">
                <td class="px-3 py-1.5 text-center font-mono text-xs text-gray-400">{{ r.code || '—' }}</td>
                <td class="px-4 py-1.5 text-gray-800">{{ r.label }}</td>
                <td class="px-3 py-1.5 text-center text-gray-500 text-xs">{{ r.unit }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.qty }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(r.amount) }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">
                  <span v-if="r.change_pct === null" class="text-blue-500 text-xs">новый</span>
                  <span v-else :class="r.change_pct > 0 ? 'text-red-500' : r.change_pct < 0 ? 'text-green-600' : 'text-gray-400'">
                    {{ r.change_pct > 0 ? '+' : '' }}{{ r.change_pct }}%
                  </span>
                </td>
              </tr>
            </template>
            <template v-else-if="dimension === 'brigade'">
              <tr v-for="r in consumption.state.data.rows" :key="r.key" class="hover:bg-blue-50 cursor-pointer" @click="drillInto(r)">
                <td class="px-4 py-1.5 text-blue-600 hover:underline">{{ r.label }} →</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.qty }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(r.amount) }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.request_count }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(r.avg_amount_per_ticket) }}</td>
              </tr>
            </template>
            <template v-else>
              <tr v-for="r in consumption.state.data.rows" :key="r.key" class="hover:bg-blue-50 cursor-pointer" @click="drillInto(r)">
                <td class="px-4 py-1.5 text-blue-600 hover:underline">{{ r.label }} →</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.qty }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(r.amount) }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.request_count }}</td>
              </tr>
            </template>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- ── Поступления от абонентов ── -->
    <div v-show="activeSub === 'revenue'" class="space-y-4">
      <p class="text-xs text-gray-500 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2">
        Только акты типа «Обычный» — материалы по ним оплачивает абонент. Ремонтные акты
        (восстановление) сюда не входят, так как абонент их не оплачивает.
      </p>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
          <button v-for="d in dimensions" :key="d.key" @click="setRevenueDimension(d.key)"
                  :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                           revenueDimension === d.key ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
            {{ d.label }}
          </button>
        </div>
        <a :href="revenueExportUrl" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Экспорт CSV →</a>
      </div>

      <RangePicker :range="revenue" />

      <div v-if="revenueSelectedEntity" class="flex items-center gap-2 text-sm">
        <button @click="clearRevenueEntity" class="text-blue-600 hover:text-blue-800 font-medium">
          ← Ко всем {{ dimensionLabelPlural(revenueDimension) }}
        </button>
        <span class="text-gray-300">/</span>
        <span class="text-gray-700 font-medium">{{ revenueSelectedEntity.label }}</span>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div v-if="revenue.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div class="overflow-x-auto" v-else>
        <table class="w-full text-sm">
          <thead v-if="showRevenueMaterialColumns">
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-center px-3 py-2.5 w-16">Код</th>
              <th class="text-left px-4 py-2.5">Материал</th>
              <th class="text-center px-3 py-2.5 w-16">Ед.</th>
              <th class="text-right px-4 py-2.5 w-28">Кол-во</th>
              <th class="text-right px-4 py-2.5 w-32">Сумма, ₽</th>
              <th class="text-right px-4 py-2.5 w-24">Δ к пред. периоду</th>
            </tr>
          </thead>
          <thead v-else-if="revenueDimension === 'brigade'">
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">Бригада</th>
              <th class="text-right px-4 py-2.5 w-28">Кол-во</th>
              <th class="text-right px-4 py-2.5 w-32">Сумма, ₽</th>
              <th class="text-right px-4 py-2.5 w-24">Заявок</th>
              <th class="text-right px-4 py-2.5 w-32">₽ на заявку</th>
            </tr>
          </thead>
          <thead v-else>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">{{ revenueDimension === 'service_type' ? 'Участок' : 'Территория' }}</th>
              <th class="text-right px-4 py-2.5 w-28">Кол-во</th>
              <th class="text-right px-4 py-2.5 w-32">Сумма, ₽</th>
              <th class="text-right px-4 py-2.5 w-24">Заявок</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!revenue.state.data.rows.length">
              <td :colspan="showRevenueMaterialColumns ? 6 : revenueDimension === 'brigade' ? 5 : 4" class="text-center py-10 text-gray-400 text-xs">Нет данных за выбранный период</td>
            </tr>
            <template v-else-if="showRevenueMaterialColumns">
              <tr v-for="r in revenue.state.data.rows" :key="r.key" class="hover:bg-gray-50">
                <td class="px-3 py-1.5 text-center font-mono text-xs text-gray-400">{{ r.code || '—' }}</td>
                <td class="px-4 py-1.5 text-gray-800">{{ r.label }}</td>
                <td class="px-3 py-1.5 text-center text-gray-500 text-xs">{{ r.unit }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.qty }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(r.amount) }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">
                  <span v-if="r.change_pct === null" class="text-blue-500 text-xs">новый</span>
                  <span v-else :class="r.change_pct > 0 ? 'text-red-500' : r.change_pct < 0 ? 'text-green-600' : 'text-gray-400'">
                    {{ r.change_pct > 0 ? '+' : '' }}{{ r.change_pct }}%
                  </span>
                </td>
              </tr>
            </template>
            <template v-else-if="revenueDimension === 'brigade'">
              <tr v-for="r in revenue.state.data.rows" :key="r.key" class="hover:bg-blue-50 cursor-pointer" @click="drillIntoRevenue(r)">
                <td class="px-4 py-1.5 text-blue-600 hover:underline">{{ r.label }} →</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.qty }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(r.amount) }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.request_count }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(r.avg_amount_per_ticket) }}</td>
              </tr>
            </template>
            <template v-else>
              <tr v-for="r in revenue.state.data.rows" :key="r.key" class="hover:bg-blue-50 cursor-pointer" @click="drillIntoRevenue(r)">
                <td class="px-4 py-1.5 text-blue-600 hover:underline">{{ r.label }} →</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.qty }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ formatMoney(r.amount) }}</td>
                <td class="px-4 py-1.5 text-right font-mono tabular-nums">{{ r.request_count }}</td>
              </tr>
            </template>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- ── По месяцам ── -->
    <div v-show="activeSub === 'monthly'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
          <button v-for="p in monthsPeriods" :key="p.key" @click="setMonthsPeriod(p.key)"
                  :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                           monthsPeriod === p.key ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
            {{ p.label }}
          </button>
        </div>
        <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
          <button @click="monthlyMetric = 'qty'"
                  :class="['px-3 py-1 rounded-lg text-xs font-medium transition-colors', monthlyMetric === 'qty' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
            Кол-во
          </button>
          <button @click="monthlyMetric = 'amount'"
                  :class="['px-3 py-1 rounded-lg text-xs font-medium transition-colors', monthlyMetric === 'amount' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
            Сумма, ₽
          </button>
        </div>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div v-if="monthlyLoading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!monthlyData.materials.length" class="text-center py-10 text-gray-400 text-sm">Нет данных</div>
        <div v-else class="overflow-x-auto">
          <table class="text-sm border-collapse">
            <thead>
              <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
                <th class="text-left px-3 py-2.5 sticky left-0 bg-gray-50">Материал</th>
                <th v-for="mo in monthlyData.months" :key="mo" class="text-right px-3 py-2.5 whitespace-nowrap">{{ mo }}</th>
                <th class="text-right px-3 py-2.5 whitespace-nowrap font-semibold">Итого</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="m in monthlyData.materials" :key="m.key" class="hover:bg-gray-50">
                <td class="px-3 py-1.5 text-gray-800 whitespace-nowrap sticky left-0 bg-white">{{ m.name }}</td>
                <td v-for="(v, i) in (monthlyMetric === 'qty' ? m.qty : m.amount)" :key="i" class="px-3 py-1.5 text-right font-mono tabular-nums text-xs">
                  {{ v ? (monthlyMetric === 'amount' ? formatMoney(v) : v) : '—' }}
                </td>
                <td class="px-3 py-1.5 text-right font-mono tabular-nums font-semibold">
                  {{ formatMoney(monthlyMetric === 'qty' ? m.total_qty : m.total_amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Прогноз (скрыт из навигации — не удалять, см. память project-acts-feature) ── -->
    <div v-show="activeSub === 'forecast'" class="space-y-4">
      <div v-if="forecastLoading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
      <template v-else>
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-600">Итого по всем материалам</h2>
            <ForecastBadge :series="forecastData.aggregate" />
          </div>
          <div v-if="forecastData.aggregate.method === 'insufficient_data'" class="text-center py-6 text-gray-400 text-sm">
            Недостаточно истории для прогноза (нужно минимум 3 месяца данных)
          </div>
          <canvas v-else ref="aggregateCanvas" style="max-height:220px" />
        </div>

        <div v-for="m in forecastData.top" :key="m.key" class="bg-white rounded-2xl border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-600">{{ m.name }}</h2>
            <ForecastBadge :series="m" />
          </div>
          <div v-if="m.method === 'insufficient_data'" class="text-center py-6 text-gray-400 text-sm">
            Недостаточно истории для прогноза
          </div>
          <canvas v-else :ref="el => setForecastCanvasRef(el, m.key)" style="max-height:180px" />
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, onBeforeUnmount, h, defineComponent } from 'vue'
import axios from 'axios'
import Chart from 'chart.js/auto'
import RangePicker from '@/Components/Reports/RangePicker.vue'
import { useReportRange } from '@/Composables/useReportRange'

// ── Sub-tabs ──
// "Прогноз" сознательно убран из этого списка (не показывается кнопкой), но
// весь код и разметка вкладки ниже оставлены нетронутыми — см. память
// project-acts-feature, "Отчёты переехали из Материалов в Акты".
const subTabs = [
  { id: 'consumption', label: 'Расход материалов' },
  { id: 'revenue',     label: 'Поступления от абонентов' },
  { id: 'monthly',     label: 'По месяцам' },
]
const activeSub = ref('consumption')

function switchSub(id) {
  activeSub.value = id
  ensureSubLoaded(id)
  nextTick(() => buildForSub(id))
}

function ensureSubLoaded(id) {
  if (id === 'consumption') consumption.ensureLoaded()
  if (id === 'revenue')     revenue.ensureLoaded()
  if (id === 'monthly')     ensureMonthlyLoaded()
  if (id === 'forecast')    ensureForecastLoaded()
}

function buildForSub(id) {
  if (id === 'forecast') buildForecastCharts()
}

// ── Общее для "Расход материалов" и "Поступления от абонентов" ──
const dimensions = [
  { key: 'all',          label: 'Общий' },
  { key: 'brigade',      label: 'По бригадам' },
  { key: 'territory',    label: 'По территориям' },
  { key: 'service_type', label: 'По участкам' },
]

function dimensionLabelPlural(d) {
  return d === 'brigade' ? 'бригадам' : d === 'service_type' ? 'участкам' : 'территориям'
}

function formatMoney(val) {
  return Number(val).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Расход материалов за период ──
const dimension       = ref('all')
const selectedEntity  = ref(null) // { key, label } | null — drill-down в конкретную бригаду/территорию/участок

const showMaterialColumns = computed(() => dimension.value === 'all' || selectedEntity.value !== null)

const consumption = useReportRange(
  'acts.report.consumption',
  { rows: [], totals: { qty: 0, amount: 0 } },
  () => ({ dimension: dimension.value, entity_id: selectedEntity.value?.key ?? null })
)

function setDimension(d) {
  dimension.value = d
  selectedEntity.value = null
  consumption.refresh()
}

function drillInto(row) {
  selectedEntity.value = { key: row.key, label: row.label }
  consumption.refresh()
}

function clearEntity() {
  selectedEntity.value = null
  consumption.refresh()
}

const exportUrl = computed(() => {
  const { from, to } = consumption.currentRange()
  const params = new URLSearchParams({ dimension: dimension.value, from, to })
  if (selectedEntity.value) params.set('entity_id', selectedEntity.value.key)
  return route('acts.report.export') + '?' + params.toString()
})

// ── Поступления от абонентов (тот же движок, только only_billable=1 — только акты типа "Обычный") ──
const revenueDimension      = ref('all')
const revenueSelectedEntity = ref(null)

const showRevenueMaterialColumns = computed(() => revenueDimension.value === 'all' || revenueSelectedEntity.value !== null)

const revenue = useReportRange(
  'acts.report.consumption',
  { rows: [], totals: { qty: 0, amount: 0 } },
  () => ({ dimension: revenueDimension.value, entity_id: revenueSelectedEntity.value?.key ?? null, only_billable: 1 })
)

function setRevenueDimension(d) {
  revenueDimension.value = d
  revenueSelectedEntity.value = null
  revenue.refresh()
}

function drillIntoRevenue(row) {
  revenueSelectedEntity.value = { key: row.key, label: row.label }
  revenue.refresh()
}

function clearRevenueEntity() {
  revenueSelectedEntity.value = null
  revenue.refresh()
}

const revenueExportUrl = computed(() => {
  const { from, to } = revenue.currentRange()
  const params = new URLSearchParams({ dimension: revenueDimension.value, from, to, only_billable: 1 })
  if (revenueSelectedEntity.value) params.set('entity_id', revenueSelectedEntity.value.key)
  return route('acts.report.export') + '?' + params.toString()
})

const charts = {}
function destroy(key) { if (charts[key]) { charts[key].destroy(); delete charts[key] } }

// ── По месяцам ──
const monthsPeriods = [
  { key: '12',  label: '12 мес.' },
  { key: '24',  label: '24 мес.' },
  { key: 'all', label: 'Вся история' },
]
const monthsPeriod   = ref('12')
const monthlyMetric  = ref('qty')
const monthlyLoading = ref(false)
const monthlyLoaded  = ref(false)
const monthlyData    = reactive({ months: [], materials: [] })

async function fetchMonthly() {
  monthlyLoading.value = true
  try {
    const res = await axios.get(route('acts.report.monthly-matrix'), { params: { months: monthsPeriod.value } })
    monthlyData.months    = res.data.months
    monthlyData.materials = res.data.materials
  } finally {
    monthlyLoading.value = false
    monthlyLoaded.value = true
  }
}

function ensureMonthlyLoaded() { if (!monthlyLoaded.value) fetchMonthly() }
function setMonthsPeriod(p) { monthsPeriod.value = p; fetchMonthly() }

// ── Прогноз (недоступен из UI, код сохранён — см. комментарий у subTabs) ──
const forecastLoading = ref(false)
const forecastLoaded  = ref(false)
const forecastData    = reactive({ top: [], aggregate: { months: [], values: [], method: 'insufficient_data' } })
const aggregateCanvas = ref(null)
const forecastCanvasRefs = {}

function setForecastCanvasRef(el, key) {
  if (el) forecastCanvasRefs[key] = el
}

async function fetchForecast() {
  forecastLoading.value = true
  try {
    const res = await axios.get(route('acts.report.forecast'), { params: { top: 5 } })
    forecastData.top       = res.data.top
    forecastData.aggregate = res.data.aggregate
  } finally {
    forecastLoading.value = false
    forecastLoaded.value = true
    nextTick(buildForecastCharts)
  }
}

function ensureForecastLoaded() { if (!forecastLoaded.value) fetchForecast() }

function buildSeriesChart(canvas, key, series, color) {
  destroy('forecast_' + key)
  if (!canvas || series.method === 'insufficient_data') return
  const labels = [...series.months, series.forecast_month]
  const actual = [...series.values, null]
  const forecastLine = series.months.map(() => null)
  forecastLine.push(series.forecast_value)
  // соединяем последнюю фактическую точку с прогнозной для непрерывности линии
  if (series.values.length) forecastLine[series.values.length - 1] = series.values[series.values.length - 1]

  charts['forecast_' + key] = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Факт', data: actual, borderColor: color, backgroundColor: color + '22', tension: 0.3, spanGaps: false },
        { label: 'Прогноз', data: forecastLine, borderColor: color, borderDash: [6, 4], backgroundColor: 'transparent', tension: 0.3 },
      ],
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: true } },
    },
  })
}

function buildForecastCharts() {
  if (forecastData.aggregate.method !== 'insufficient_data') {
    buildSeriesChart(aggregateCanvas.value, '_total', forecastData.aggregate, 'rgba(59,130,246,0.9)')
  }
  forecastData.top.forEach((m, i) => {
    if (m.method === 'insufficient_data') return
    buildSeriesChart(forecastCanvasRefs[m.key], m.key, m, 'rgba(34,197,94,0.9)')
  })
}

// ── Значок метода прогноза ──
const ForecastBadge = defineComponent({
  props: { series: Object },
  setup(props) {
    return () => {
      const s = props.series
      if (!s || s.method === 'insufficient_data') return null
      const label = s.method === 'linear+seasonal' ? 'тренд + сезонность' : 'линейный тренд'
      return h('div', { class: 'text-right' }, [
        h('div', { class: 'text-lg font-bold text-gray-800' }, formatMoney(s.forecast_value) + ' ₽'),
        h('div', { class: 'text-xs text-gray-400' }, `прогноз на ${s.forecast_month} · ${label}`),
      ])
    }
  }
})

onMounted(() => {
  ensureSubLoaded(activeSub.value)
  nextTick(() => buildForSub(activeSub.value))
})

onBeforeUnmount(() => {
  Object.values(charts).forEach(c => c.destroy())
})
</script>
