<template>
  <Head title="Отчёты" />
  <AppLayout title="Отчёты" help-tab="admin" help-section="reports">

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

    <!-- Эффективность бригад -->
    <div v-show="activeTab === 'brigade'" class="p-4 space-y-3">
      <RangePicker :range="brigade" />
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
          <div class="text-2xl font-bold text-gray-800">{{ brigade.state.data.summary.closed }}</div>
          <div class="text-xs text-gray-500 mt-1">Закрыто заявок</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
          <div :class="['text-2xl font-bold', pctColor(brigade.state.data.summary.pct_on_time)]">
            {{ brigade.state.data.summary.pct_on_time != null ? brigade.state.data.summary.pct_on_time + '%' : '—' }}
          </div>
          <div class="text-xs text-gray-500 mt-1">Соблюдение сроков</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
          <div class="text-2xl font-bold text-gray-800">
            {{ brigade.state.data.summary.per_man_day ?? '—' }}
          </div>
          <div class="text-xs text-gray-500 mt-1">Заявок на человеко-день</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3.5 text-center">
          <div class="text-2xl font-bold text-gray-800">{{ formatMoney(brigade.state.data.summary.material_cost) }}</div>
          <div class="text-xs text-gray-500 mt-1">Расход материалов, ₽</div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-600 mb-3">Соблюдение сроков по бригадам</h2>
        <div v-if="brigade.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!brigade.state.data.rows.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="brigadeCanvas" style="max-height:280px" />
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-600 mb-3">Заявок на человеко-день</h2>
        <p class="text-xs text-gray-400 mb-3">Нагрузка, нормализованная на реальную явку бригады по графику — так бригады разного состава сравнимы честно.</p>
        <div v-if="brigade.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!brigade.state.data.rows.some(r => r.man_days > 0)" class="text-center py-10 text-gray-400 text-sm">Нет данных графика за выбранный период</div>
        <canvas v-else ref="perManDayCanvas" style="max-height:240px" />
      </div>

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-3 py-2.5">Бригада</th>
              <th class="text-right px-3 py-2.5">Закрыто</th>
              <th class="text-right px-3 py-2.5">В срок</th>
              <th class="text-right px-3 py-2.5">Просрочено</th>
              <th class="text-right px-3 py-2.5">% в срок</th>
              <th class="text-right px-3 py-2.5">Человеко-дней</th>
              <th class="text-right px-3 py-2.5">Заявок/чел-день</th>
              <th class="text-right px-3 py-2.5">Ср. время, ч</th>
              <th class="text-right px-3 py-2.5">Материалы, ₽</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!brigade.state.data.rows.length">
              <td colspan="9" class="text-center py-4 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="r in brigade.state.data.rows" :key="r.brigade_id" class="hover:bg-gray-50">
              <td class="px-3 py-2 text-gray-800">{{ r.brigade }}</td>
              <td class="px-3 py-2 text-right font-mono tabular-nums">{{ r.closed }}</td>
              <td class="px-3 py-2 text-right font-mono tabular-nums text-green-600">{{ r.on_time }}</td>
              <td class="px-3 py-2 text-right font-mono tabular-nums text-red-500">{{ r.overdue }}</td>
              <td class="px-3 py-2 text-right font-mono tabular-nums">{{ r.pct_on_time != null ? r.pct_on_time + '%' : '—' }}</td>
              <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500">{{ r.man_days || '—' }}</td>
              <td class="px-3 py-2 text-right font-mono tabular-nums font-medium">{{ r.per_man_day ?? '—' }}</td>
              <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500">{{ r.avg_hours ?? '—' }}</td>
              <td class="px-3 py-2 text-right font-mono tabular-nums text-gray-500">{{ formatMoney(r.material_cost) }}</td>
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
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-sm font-semibold text-gray-600">Частота обращений по территориям</h2>
          <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
            <button @click="territoryMode = 'total'"
                    :class="['px-3 py-1 rounded-lg text-xs font-medium transition-colors',
                             territoryMode === 'total' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
              По количеству
            </button>
            <button @click="territoryMode = 'per100'"
                    :class="['px-3 py-1 rounded-lg text-xs font-medium transition-colors',
                             territoryMode === 'per100' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
              На 100 адресов
            </button>
          </div>
        </div>
        <p class="text-xs text-gray-400 mb-3">«На 100 адресов» показывает реальную проблемность территории — крупная территория не выглядит «хуже» просто из-за размера.</p>
        <div v-if="territory.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!territoryRows.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="territoryCanvas" style="max-height:320px" />
      </div>
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5">Территория</th>
              <th class="text-right px-4 py-2.5 w-28">Заявок</th>
              <th class="text-right px-4 py-2.5 w-28">Адресов</th>
              <th class="text-right px-4 py-2.5 w-32">На 100 адресов</th>
              <th class="text-right px-4 py-2.5 w-28">% от общего</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!territoryRows.length">
              <td colspan="5" class="text-center py-4 text-gray-400 text-xs">—</td>
            </tr>
            <tr v-for="row in territoryRows" :key="row.label" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800">{{ row.label }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums">{{ row.total }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-500">{{ row.addresses || '—' }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums font-medium">{{ row.per100 ?? '—' }}</td>
              <td class="px-4 py-2 text-right font-mono tabular-nums text-gray-500">
                {{ totalTerritory ? (row.total / totalTerritory * 100).toFixed(1) + '%' : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- Распределение по дням -->
    <div v-show="activeTab === 'distribution'" class="p-4 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <RangePicker :range="distribution" :modes="['month', 'quarter', 'period']" />
        <select v-model="distTerritory"
                class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Все территории</option>
          <option v-for="t in territories" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </div>
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
        <div v-if="!distribution.state.loaded" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
        <div v-else-if="!hasDistData" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
        <canvas v-else ref="distributionCanvas" style="max-height:380px" />
      </div>

      <!-- Легенда / итоговая таблица -->
      <div v-if="hasDistData" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
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

      <!-- Оптимизация расписания операторов (2026-09-12) — отдельный блок со
           своим диапазоном дат, не связан с таблицей выше ни данными, ни кодом. -->
      <div class="pt-2 border-t border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700 mb-1">Оптимизация расписания операторов</h2>
        <p class="text-xs text-gray-400 mb-3">Типичная нагрузка на оператора по часам суток за длительный период — где стоит усилить смену, а где людей обычно больше, чем нужно. Это эвристика по фактическим звонкам за период, не готовое решение — финальное слово за вами.</p>
        <RangePicker :range="staffing" :modes="['month', 'quarter', 'period']" />

        <div v-if="staffing.state.data.understaffed.length || staffing.state.data.overstaffed.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
          <div class="bg-red-50 border border-red-200 rounded-xl p-3">
            <div class="text-xs font-semibold text-red-700 mb-1">Не хватает операторов</div>
            <div class="text-sm text-red-600">{{ staffing.state.data.understaffed.length ? staffing.state.data.understaffed.map(h => String(h.hour).padStart(2,'0') + ':00').join(', ') : '—' }}</div>
          </div>
          <div class="bg-blue-50 border border-blue-200 rounded-xl p-3">
            <div class="text-xs font-semibold text-blue-700 mb-1">Возможен избыток</div>
            <div class="text-sm text-blue-600">{{ staffing.state.data.overstaffed.length ? staffing.state.data.overstaffed.map(h => String(h.hour).padStart(2,'0') + ':00').join(', ') : '—' }}</div>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <div v-if="staffing.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
          <div v-else-if="!staffing.state.data.hours.some(h => h.days > 0)" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
          <template v-else>
            <h3 class="text-xs font-semibold text-gray-500 mb-3">Звонков на оператора по часам</h3>
            <canvas ref="staffingCanvas" style="max-height:240px" />
          </template>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mt-3">
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-3 py-2.5">Час</th>
              <th class="text-right px-3 py-2.5">Дней с данными</th>
              <th class="text-right px-3 py-2.5">Ср. операторов</th>
              <th class="text-right px-3 py-2.5">Ср. звонков</th>
              <th class="text-right px-3 py-2.5">Звонков/оператора</th>
              <th class="text-right px-3 py-2.5">Пропуск %</th>
              <th class="text-right px-3 py-2.5">% дней с перегрузкой</th>
              <th class="text-left px-3 py-2.5">Вывод</th>
            </tr></thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-if="!staffing.state.data.hours.some(h => h.days > 0)"><td colspan="8" class="text-center py-4 text-gray-400 text-xs">—</td></tr>
              <tr v-for="h in staffing.state.data.hours.filter(h => h.days > 0)" :key="h.hour" class="hover:bg-gray-50">
                <td class="px-3 py-1.5 font-medium text-gray-700 tabular-nums">{{ String(h.hour).padStart(2,'0') }}:00</td>
                <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ h.days }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ h.avg_operators }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ h.avg_calls }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums font-medium">{{ h.calls_per_operator ?? '—' }}</td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ h.miss_rate }}%</td>
                <td class="px-3 py-1.5 text-right tabular-nums">{{ h.overload_pct }}%</td>
                <td class="px-3 py-1.5">
                  <span :class="['px-1.5 py-0.5 rounded text-xs font-medium', verdictClass(h.verdict)]">{{ verdictLabel(h.verdict) }}</span>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>

    </div><!-- end reports card -->

  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { Head } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
Chart.defaults.animation = false
import AppLayout from '@/Components/Layout/AppLayout.vue'
import RangePicker from '@/Components/Reports/RangePicker.vue'
import { useReportRange } from '@/Composables/useReportRange'

defineProps({
  territories: { type: Array, default: () => [] },
})

const tabs = [
  { id: 'brigade',      label: 'Эффективность бригад' },
  { id: 'territory',    label: 'Территории' },
  { id: 'distribution', label: 'Распределение по дням' },
  { id: 'callcenter',   label: 'Обработка звонков' },
]

const activeTab = ref('brigade')

// ── Каждая вкладка — независимый диапазон дат + свой запрос данных ──
// "Расход материалов" перенесён во вкладку "Отчёты" раздела Акты (2026-07-15,
// см. память project-acts-feature) — здесь больше не запрашивается.
const brigade    = useReportRange('reports.brigade-efficiency',  { rows: [], summary: { closed: 0, pct_on_time: null, material_cost: 0, per_man_day: null } })
const territory  = useReportRange('reports.territory-frequency', { labels: [], values: [], addresses: [], per100: [] })
const callcenter = useReportRange('reports.call-stats',          { hours: [], summary: {} })

// ── Оптимизация расписания операторов: отдельный от callcenter источник —
// не трогает существующий отчёт по звонкам ни данными, ни диапазоном дат.
// По умолчанию квартал — на дне/неделе картина по часам суток статистически
// не показательна для планирования смен.
const staffing = useReportRange('reports.operator-load', {
  hours: Array.from({ length: 24 }, (_, h) => ({
    hour: h, days: 0, avg_operators: null, avg_calls: null,
    calls_per_operator: null, miss_rate: null, overload_pct: null, verdict: 'no_data',
  })),
  baseline: null, understaffed: [], overstaffed: [],
})
staffing.state.periodMode = 'quarter'

function verdictLabel(v) {
  return { understaffed: 'Не хватает', overstaffed: 'Возможен избыток', balanced: 'Норма', no_data: 'Нет данных' }[v] || '—'
}

function verdictClass(v) {
  return {
    understaffed: 'bg-red-100 text-red-700',
    overstaffed:  'bg-blue-100 text-blue-700',
    balanced:     'bg-green-100 text-green-700',
    no_data:      'bg-gray-100 text-gray-500',
  }[v] || 'bg-gray-100 text-gray-500'
}

const territoryMode = ref('total') // 'total' | 'per100'

// ── Распределение по дням: свой диапазон дат (2026-09-11, раньше был жёстко
// зашит текущий месяц) + необязательный фильтр по территории. По умолчанию —
// квартал, чтобы "По дням месяца" сразу показывал честную агрегацию по
// числам месяца, а не по сути посуточный график одного месяца.
const distMode = ref('day') // 'day' | 'weekday'
const distTerritory = ref('') // '' = все территории
const distribution = useReportRange(
  'reports.distribution',
  { byDay: { labels: [], datasets: [] }, byWeekday: { labels: [], datasets: [] } },
  () => (distTerritory.value ? { territory_id: distTerritory.value } : {})
)
distribution.state.periodMode = 'quarter'

function formatMoney(v) {
  return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(v || 0)
}

function pctColor(pct) {
  if (pct == null) return 'text-gray-400'
  return pct >= 80 ? 'text-green-600' : pct >= 60 ? 'text-yellow-500' : 'text-red-500'
}

const brigadeCanvas      = ref(null)
const perManDayCanvas    = ref(null)
const territoryCanvas    = ref(null)
const distributionCanvas = ref(null)
const callcenterCanvas   = ref(null)
const staffingCanvas     = ref(null)
const callcenterCanvas2  = ref(null)

const charts = {}

// Строки территорий, отсортированные под текущий режим отображения (по
// количеству или на 100 адресов) — сырой ответ сервера всегда отсортирован
// по количеству, для второго режима пересортировываем на клиенте.
const territoryRows = computed(() => {
  const d = territory.state.data
  const rows = d.labels.map((label, i) => ({
    label,
    total: d.values[i],
    addresses: d.addresses[i],
    per100: d.per100[i],
  }))
  if (territoryMode.value === 'per100') {
    return [...rows].sort((a, b) => (b.per100 ?? -1) - (a.per100 ?? -1))
  }
  return rows
})

const totalTerritory = computed(() =>
  territory.state.data.values.reduce((a, b) => a + b, 0)
)

const currentDistData = computed(() =>
  distMode.value === 'day' ? distribution.state.data.byDay : distribution.state.data.byWeekday
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
  const rows = brigade.state.data.rows
  if (!brigadeCanvas.value || !rows.length) return
  charts.brigade = new Chart(brigadeCanvas.value, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.brigade),
      datasets: [
        { label: 'В срок',     data: rows.map(r => r.on_time), backgroundColor: C.green, stack: 'a' },
        { label: 'Просрочено', data: rows.map(r => r.overdue), backgroundColor: C.red,   stack: 'a' },
      ],
    },
    options: {
      responsive: true,
      plugins: { legend: { position: 'top' } },
      scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { stacked: true } },
    },
  })
}

function buildPerManDay() {
  destroy('perManDay')
  const rows = brigade.state.data.rows.filter(r => r.man_days > 0)
  if (!perManDayCanvas.value || !rows.length) return
  const sorted = [...rows].sort((a, b) => (b.per_man_day ?? 0) - (a.per_man_day ?? 0))
  charts.perManDay = new Chart(perManDayCanvas.value, {
    type: 'bar',
    data: {
      labels: sorted.map(r => r.brigade),
      datasets: [{ label: 'Заявок на человеко-день', data: sorted.map(r => r.per_man_day), backgroundColor: C.blue }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true } },
    },
  })
}

function buildTerritory() {
  destroy('territory')
  const rows = territoryRows.value
  if (!territoryCanvas.value || !rows.length) return
  const useTotal = territoryMode.value === 'total'
  charts.territory = new Chart(territoryCanvas.value, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: [{
        label: useTotal ? 'Заявок' : 'Заявок на 100 адресов',
        data: rows.map(r => useTotal ? r.total : (r.per100 ?? 0)),
        backgroundColor: C.blue,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true } },
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

function buildStaffing() {
  destroy('staffing')
  const hours = staffing.state.data.hours.filter(h => h.days > 0)
  if (!staffingCanvas.value || !hours.length) return
  const verdictColor = { understaffed: '#ef4444', overstaffed: '#3b82f6', balanced: '#22c55e', no_data: '#d1d5db' }
  charts.staffing = new Chart(staffingCanvas.value, {
    type: 'bar',
    data: {
      labels: hours.map(h => String(h.hour).padStart(2, '0') + ':00'),
      datasets: [{
        label: 'Звонков на оператора',
        data: hours.map(h => h.calls_per_operator ?? 0),
        backgroundColor: hours.map(h => verdictColor[h.verdict] || '#9ca3af'),
      }],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => {
              const h = hours[ctx.dataIndex]
              return [
                `Звонков/оператора: ${h.calls_per_operator ?? '—'}`,
                `Ср. операторов: ${h.avg_operators}`,
                `Перегрузка: ${h.overload_pct}% дней`,
              ]
            },
          },
        },
      },
      scales: { x: { ticks: { maxRotation: 0 } }, y: { beginAtZero: true } },
    },
  })
}

function buildForTab(tab) {
  nextTick(() => {
    if (tab === 'brigade')      { buildBrigade(); buildPerManDay() }
    if (tab === 'territory')    buildTerritory()
    if (tab === 'distribution') buildDistribution()
    if (tab === 'callcenter')   { buildCallcenter(); buildStaffing() }
  })
}

function ensureLoadedForTab(tab) {
  if (tab === 'brigade')      brigade.ensureLoaded()
  if (tab === 'territory')    territory.ensureLoaded()
  if (tab === 'callcenter')   { callcenter.ensureLoaded(); staffing.ensureLoaded() }
  if (tab === 'distribution') distribution.ensureLoaded()
}

function switchTab(id) {
  activeTab.value = id
  ensureLoadedForTab(id)
  buildForTab(id)
}

// Перестраивать график вкладки при каждом новом ответе сервера (смена диапазона)
watch(() => brigade.state.data,    () => nextTick(() => { buildBrigade(); buildPerManDay() }))
watch(() => territory.state.data,  () => nextTick(buildTerritory))
watch(territoryMode,               () => nextTick(buildTerritory))
watch(() => callcenter.state.data, () => nextTick(buildCallcenter))
watch(() => staffing.state.data,   () => nextTick(buildStaffing))
watch(() => distribution.state.data, () => nextTick(buildDistribution))
watch(distTerritory, () => distribution.refresh())

onMounted(() => {
  ensureLoadedForTab(activeTab.value)
  buildForTab(activeTab.value)
})

onBeforeUnmount(() => {
  Object.values(charts).forEach(c => c.destroy())
})
</script>
