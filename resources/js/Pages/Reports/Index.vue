<template>
  <Head title="Отчёты" />
  <AppLayout title="Отчёты">

    <!-- ── Выбор периода ── -->
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <!-- Быстрые режимы -->
      <div class="flex bg-gray-100 rounded-xl p-1 gap-0.5">
        <button v-for="m in periodModes" :key="m.key" @click="setMode(m.key)"
                :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                         periodMode === m.key
                           ? 'bg-white shadow text-gray-800'
                           : 'text-gray-500 hover:text-gray-700']">
          {{ m.label }}
        </button>
      </div>

      <!-- День: один пикер -->
      <template v-if="periodMode === 'day'">
        <input type="date" v-model="singleDay" @change="applyDay"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </template>

      <!-- Период: два пикера + кнопка -->
      <template v-if="periodMode === 'period'">
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
      </template>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="bg-gray-50 border-b border-gray-200 flex items-end gap-0.5 px-3 pt-2 flex-wrap">
        <button v-for="tab in tabs" :key="tab.id" @click="switchTab(tab.id)"
                :class="['px-4 py-2 rounded-t-xl text-sm font-medium transition-colors',
                         activeTab === tab.id
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          {{ tab.label }}
        </button>
      </div>

    <div v-show="activeTab === 'brigade'" class="p-4 space-y-4">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-600 mb-4">Количество заявок по бригадам</h2>
        <div v-if="!brigadeLoad.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="brigadeCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
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
    </div>

    <!-- Частота по территориям -->
    <div v-show="activeTab === 'territory'" class="p-4 space-y-4">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-600 mb-4">Частота обращений по территориям</h2>
        <div v-if="!territoryFrequency.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="territoryCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
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
    </div>

    <!-- Расход материалов -->
    <div v-show="activeTab === 'materials'" class="p-4 space-y-4">
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
        <div class="overflow-x-auto">
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
    </div>

    <!-- Соблюдение сроков -->
    <div v-show="activeTab === 'deadlines'" class="p-4 space-y-4">
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
    </div>

    <!-- Распределение по дням -->
    <div v-show="activeTab === 'distribution'" class="p-4 space-y-4">
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
    <div v-show="activeTab === 'callcenter'" class="p-4 space-y-4">
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-3 text-center"><p class="text-2xl font-bold text-gray-800">{{ callStats.summary?.total ?? 0 }}</p><p class="text-xs text-gray-500 mt-0.5">Всего звонков</p></div>
        <div class="bg-green-50 rounded-xl border border-green-200 p-3 text-center"><p class="text-2xl font-bold text-green-700">{{ callStats.summary?.answer_rate ?? 0 }}%</p><p class="text-xs text-gray-500 mt-0.5">Отвечено</p></div>
        <div class="bg-red-50 rounded-xl border border-red-200 p-3 text-center"><p class="text-2xl font-bold text-red-600">{{ callStats.summary?.missed ?? 0 }}</p><p class="text-xs text-gray-500 mt-0.5">Пропущено</p></div>
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-3 text-center"><p class="text-2xl font-bold text-blue-700">{{ callStats.summary?.peak_hour != null ? callStats.summary.peak_hour + ':00' : '—' }}</p><p class="text-xs text-gray-500 mt-0.5">Пиковый час</p></div>
        <div class="bg-orange-50 rounded-xl border border-orange-200 p-3 text-center"><p class="text-2xl font-bold text-orange-600">{{ callStats.summary?.worst_hour != null ? callStats.summary.worst_hour + ':00' : '—' }}</p><p class="text-xs text-gray-500 mt-0.5">Больше пропусков</p></div>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 p-4">
        <div v-if="!(callStats.hours ?? []).some(h => h.total > 0)" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <template v-else>
          <h2 class="text-sm font-semibold text-gray-600 mb-3">Отвечено / Пропущено</h2>
          <canvas ref="callcenterCanvas" style="max-height:240px" />
          <div class="mt-5 pt-4 border-t border-gray-100">
            <h2 class="text-sm font-semibold text-gray-600 mb-3">Очередь и операторы</h2>
            <canvas ref="callcenterCanvas2" style="max-height:180px" />
          </div>
        </template>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead><tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium"><th class="text-left px-3 py-2.5">Час</th><th class="text-right px-3 py-2.5">Всего</th><th class="text-right px-3 py-2.5">Отвечено</th><th class="text-right px-3 py-2.5">Пропущено</th><th class="text-right px-3 py-2.5">Пропуск %</th><th class="text-right px-3 py-2.5 hidden md:table-cell">Ср. ожидание</th><th class="text-right px-3 py-2.5 hidden lg:table-cell">Макс. очередь</th><th class="text-right px-3 py-2.5 hidden lg:table-cell">Ср. операторов</th></tr></thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!(callStats.hours ?? []).some(h => h.total > 0)"><td colspan="8" class="text-center py-6 text-gray-400 text-xs">—</td></tr>
            <tr v-for="h in (callStats.hours ?? []).filter(h => h.total > 0)" :key="h.hour" :class="[h.miss_rate >= 40 ? 'bg-red-50 hover:bg-red-100' : h.miss_rate >= 20 ? 'bg-orange-50 hover:bg-orange-100' : 'hover:bg-gray-50']">
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
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
Chart.defaults.animation = false
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  from: String,
  to: String,
  brigadeLoad: Object,
  territoryFrequency: Object,
  materialDynamics: Object,
  deadlineCompliance: Object,
  distribution: Object,
  callStats: { type: Object, default: () => ({ hours: [], summary: {} }) },
})

const tabs = [
  { id: 'brigade',      label: 'Нагрузка бригад' },
  { id: 'territory',    label: 'Территории' },
  { id: 'materials',    label: 'Расход материалов' },
  { id: 'deadlines',    label: 'Соблюдение сроков' },
  { id: 'distribution', label: 'Распределение по дням' },
  { id: 'callcenter',    label: 'Обработка звонков' },
]

const activeTab    = ref('brigade')
const localFrom    = ref(props.from)
const localTo      = ref(props.to)
const distMode     = ref('day') // 'day' | 'weekday'

// ── Выбор периода ──────────────────────────────────────────────────
const periodModes = [
  { key: 'day',    label: 'День' },
  { key: 'week',   label: 'Неделя' },
  { key: 'month',  label: 'Месяц' },
  { key: 'period', label: 'Период' },
]

function toIso(d) { return d.toISOString().split('T')[0] }

function getMondayOfWeek() {
  const d = new Date()
  const day = d.getDay()
  d.setDate(d.getDate() - (day === 0 ? 6 : day - 1))
  return toIso(d)
}

function detectMode(from, to) {
  const today = toIso(new Date())
  if (from === to) return 'day'
  const d = new Date()
  const monthStart = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-01`
  if (from === monthStart && to === today) return 'month'
  if (from === getMondayOfWeek() && to === today) return 'week'
  return 'period'
}

const periodMode = ref(detectMode(props.from, props.to))
const singleDay  = ref(props.from === props.to ? props.from : toIso(new Date()))

function setMode(m) {
  periodMode.value = m
  const today = toIso(new Date())
  if (m === 'day') {
    singleDay.value = today
    router.get(route('reports.index'), { from: today, to: today }, { preserveState: false })
  } else if (m === 'week') {
    router.get(route('reports.index'), { from: getMondayOfWeek(), to: today }, { preserveState: false })
  } else if (m === 'month') {
    const d = new Date()
    const from = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-01`
    router.get(route('reports.index'), { from, to: today }, { preserveState: false })
  }
  // 'period' — просто показываем пикеры, навигация по кнопке Применить
}

function applyDay() {
  router.get(route('reports.index'), { from: singleDay.value, to: singleDay.value }, { preserveState: false })
}

const brigadeCanvas      = ref(null)
const territoryCanvas    = ref(null)
const materialQtyCanvas  = ref(null)
const materialAmtCanvas  = ref(null)
const deadlineCanvas     = ref(null)
const distributionCanvas = ref(null)
const callcenterCanvas    = ref(null)
const callcenterCanvas2   = ref(null)

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


function buildCallcenter() {
  destroy('callcenter')
  destroy('callcenter2')
  if (!callcenterCanvas.value) return
  const hours = props.callStats?.hours ?? []
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
    if (tab === 'materials')    buildMaterials()
    if (tab === 'deadlines')    buildDeadline()
    if (tab === 'distribution') buildDistribution()
    if (tab === 'callcenter')   buildCallcenter()
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