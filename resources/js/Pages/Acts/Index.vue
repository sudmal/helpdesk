<template>
  <Head title="Акты" />
  <AppLayout title="Акты">

    <!-- Вкладки -->
    <div class="flex bg-gray-100 rounded-xl p-1 gap-0.5 w-fit mb-4">
      <button v-for="t in tabs" :key="t.id" @click="switchTab(t.id)"
              :class="['px-4 py-1.5 rounded-lg text-sm font-medium transition-colors',
                       tab === t.id ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
        {{ t.label }}
      </button>
    </div>

    <!-- Вкладка "Отчёты" -->
    <div v-if="tab === 'reports'">
      <div v-if="!canViewReports" class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-gray-400">
        Нет доступа к отчётам.
      </div>
      <div v-else class="space-y-4">
        <!-- Расход материалов (перенесено из общих Отчётов, 2026-07-15) -->
        <div class="flex items-center justify-between">
          <RangePicker :range="materials" />
          <a :href="route('materials.index') + '?tab=report'" class="text-sm text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap">
            Подробный отчёт →
          </a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-600 mb-4">Динамика расхода (ед.) по неделям</h2>
            <div v-if="materials.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
            <div v-else-if="!materials.state.data.weekly.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
            <canvas v-else ref="materialQtyCanvas" style="max-height:280px" />
          </div>
          <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-600 mb-4">Динамика суммы (₽) по неделям</h2>
            <div v-if="materials.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
            <div v-else-if="!materials.state.data.weekly.labels.length" class="text-center py-10 text-gray-400 text-sm">Нет данных за выбранный период</div>
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
              <tr v-if="!materials.state.data.top.length">
                <td colspan="5" class="text-center py-6 text-gray-400 text-xs">—</td>
              </tr>
              <tr v-for="(m, i) in materials.state.data.top" :key="i" class="hover:bg-gray-50">
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
    </div>

    <template v-else>
      <!-- Фильтры -->
      <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div v-if="tab === 'archive'" class="flex-1 min-w-48">
          <label class="block text-xs text-gray-500 mb-1">Поиск</label>
          <input v-model="f.search" @keydown.enter="apply"
                 placeholder="Номер акта, номер заявки, адрес..."
                 class="field-input w-full" />
        </div>
        <div v-if="tab === 'active'">
          <label class="block text-xs text-gray-500 mb-1">Статус</label>
          <select v-model="f.status" @change="apply" class="field-input">
            <option value="">Все</option>
            <option v-for="(label, key) in activeStatusLabels" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Тип</label>
          <select v-model="f.type" @change="apply" class="field-input">
            <option value="">Все</option>
            <option value="regular">Обычный</option>
            <option value="repair">Ремонт/Восстановление</option>
          </select>
        </div>
        <div v-if="tab === 'archive'">
          <label class="block text-xs text-gray-500 mb-1">Сортировка</label>
          <select v-model="f.sort" @change="apply" class="field-input">
            <option value="completed_at">По дате завершения</option>
            <option value="created_at">По дате создания</option>
            <option value="number">По номеру</option>
          </select>
        </div>
        <div v-if="tab === 'archive'">
          <button @click="toggleSortDir" class="btn-outline text-sm" :title="f.sort_dir === 'asc' ? 'По возрастанию' : 'По убыванию'">
            {{ f.sort_dir === 'asc' ? '↑' : '↓' }}
          </button>
        </div>
        <div v-if="tab === 'archive'">
          <button @click="apply" class="btn-primary text-sm">Найти</button>
        </div>
        <label v-if="tab === 'archive'" class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer select-none pb-1.5"
               title="Легаси-акты — старый бэкфилл из ticket_materials до появления workflow (номера вида legacy-*, старые заявки, без указания типа). Скрыты по умолчанию, не удалены.">
          <input type="checkbox" :checked="f.legacy === 'show'" @change="toggleLegacy" class="rounded w-4 h-4 text-blue-600" />
          Показывать легаси-акты (без типа)
        </label>
      </div>

      <!-- Таблица -->
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-2 border-b border-gray-100">
          <span class="text-sm text-gray-500">Всего: {{ acts.total }}</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-xs">
            <thead class="bg-gray-50 text-[11px] text-gray-400 uppercase tracking-wide">
              <tr v-if="tab === 'active'">
                <th class="px-2 py-1 text-left whitespace-nowrap">Создан</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Номер</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Заявка</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Тип</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Статус</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Бригадир</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">ПЭО</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Логистика</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Абонотдел</th>
              </tr>
              <tr v-else>
                <th class="px-2 py-1 text-left whitespace-nowrap">Номер</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Заявка</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Тип</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Территория / адрес</th>
                <th class="px-2 py-1 text-right whitespace-nowrap">Материалы</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Завершил</th>
                <th class="px-2 py-1 text-left whitespace-nowrap">Завершён</th>
              </tr>
            </thead>

            <!-- Активные: группировка по территории/бригаде -->
            <tbody v-if="tab === 'active'" class="divide-y divide-gray-100">
              <template v-for="row in groupedRows" :key="row.key">
                <tr v-if="row.isGroup" class="bg-gray-100">
                  <td colspan="9" class="px-2 py-1.5 text-center text-xs font-bold text-gray-700">
                    {{ row.territoryName }} <span class="text-gray-500 font-semibold">· {{ row.brigadeName }}</span>
                  </td>
                </tr>
                <tr v-else class="hover:bg-gray-50 cursor-pointer" :class="needsAck(row.act) ? 'bg-red-50/70' : ''"
                    @click="openAct(row.act.id)">
                  <td class="px-2 py-1 whitespace-nowrap text-gray-400">{{ fmtDate(row.act.created_at) }}</td>
                  <td class="px-2 py-1 whitespace-nowrap font-mono font-medium text-gray-800">
                    {{ row.act.number }}
                    <span v-if="needsAck(row.act)" class="ml-1 text-red-600 font-bold"
                          title="Бригадир изменил состав акта — есть неподтверждённые изменения">(!)</span>
                  </td>
                  <td class="px-2 py-1 whitespace-nowrap text-blue-600">{{ requestLabel(row.act) }}</td>
                  <td class="px-2 py-1 whitespace-nowrap">
                    <span class="px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 font-medium">{{ typeLabel(row.act.type) }}</span>
                  </td>
                  <td class="px-2 py-1 whitespace-nowrap">
                    <span :class="statusClass(row.act.status)" class="px-1.5 py-0.5 rounded font-medium">{{ statusLabels[row.act.status] || row.act.status }}</span>
                  </td>
                  <td class="px-2 py-1 whitespace-nowrap">
                    <span v-if="row.act.foreman_reviewed_at" class="px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-medium">✓</span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="px-2 py-1 whitespace-nowrap">
                    <span v-if="row.act.type === 'regular' && row.act.peo_processed_at" class="px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-medium">✓</span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="px-2 py-1 whitespace-nowrap">
                    <span v-if="row.act.logistics_processed_at" class="px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-medium">✓</span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="px-2 py-1 whitespace-nowrap">
                    <span v-if="row.act.subscriber_dept_completed_at" class="px-1.5 py-0.5 rounded bg-green-100 text-green-700 font-medium">✓</span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                </tr>
              </template>
              <tr v-if="!acts.data.length">
                <td colspan="9" class="px-4 py-8 text-center text-gray-400">Нет актов</td>
              </tr>
            </tbody>

            <!-- Архив: плоский список, без группировки -->
            <tbody v-else class="divide-y divide-gray-100">
              <tr v-for="act in acts.data" :key="act.id"
                  class="hover:bg-gray-50 cursor-pointer" @click="openAct(act.id)">
                <td class="px-2 py-1 whitespace-nowrap font-mono font-medium text-gray-800">{{ act.number }}</td>
                <td class="px-2 py-1 whitespace-nowrap text-blue-600">{{ requestLabel(act) }}</td>
                <td class="px-2 py-1 whitespace-nowrap">
                  <span class="px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 font-medium">{{ typeLabel(act.type) }}</span>
                </td>
                <td class="px-2 py-1 whitespace-nowrap">
                  <span v-if="requestTerritoryName(act)">{{ requestTerritoryName(act) }} · </span>{{ requestAddress(act) }}
                </td>
                <td class="px-2 py-1 whitespace-nowrap text-right font-medium">{{ materialsTotal(act) }} ₽</td>
                <td class="px-2 py-1 whitespace-nowrap">{{ act.subscriber_dept_completer?.name || '—' }}</td>
                <td class="px-2 py-1 whitespace-nowrap text-gray-400">{{ fmtDate(act.subscriber_dept_completed_at) }}</td>
              </tr>
              <tr v-if="!acts.data.length">
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">Ничего не найдено</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Пагинация -->
        <div v-if="acts.last_page > 1"
             class="px-4 py-2 border-t border-gray-100 flex items-center gap-2">
          <button v-for="link in acts.links" :key="link.label"
                  :disabled="!link.url || link.active"
                  @click="link.url && router.get(link.url, {}, { preserveState: true })"
                  v-html="link.label"
                  :class="['px-3 py-0.5 rounded-lg text-sm transition-colors',
                           link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600 disabled:opacity-40 disabled:cursor-default']" />
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { reactive, computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
Chart.defaults.animation = false
import AppLayout from '@/Components/Layout/AppLayout.vue'
import RangePicker from '@/Components/Reports/RangePicker.vue'
import { useReportRange } from '@/Composables/useReportRange'

const props = defineProps({
  tab:             { type: String, default: 'active' },
  acts:            { type: Object, default: null },
  filters:         { type: Object, default: () => ({}) },
  authUserId:      { type: Number, default: null },
  canViewReports:  { type: Boolean, default: false },
})

// "(!)" виден только тому, кто создал акт (обычно монтажник) — это к нему
// относятся неподтверждённые правки бригадира в составе акта.
function needsAck(act) {
  return !!act.materials_changed_at && act.created_by === props.authUserId
}

const tabs = [
  { id: 'active',  label: 'Активные' },
  { id: 'archive', label: 'Архив' },
  { id: 'reports', label: 'Отчёты' },
]

const statusLabels = {
  pending_foreman:          'Ждёт бригадира',
  approved:                 'Утверждён',
  processing:               'В обработке',
  pending_subscriber_dept:  'Ждёт Абонотдел',
  completed:                'Завершён',
}

// В "Активных" завершённые не показываются вовсе (они в Архиве) — статус
// "Завершён" в фильтре этой вкладки не нужен.
const activeStatusLabels = Object.fromEntries(
  Object.entries(statusLabels).filter(([key]) => key !== 'completed')
)

const f = reactive({
  status:   props.filters?.status   || '',
  type:     props.filters?.type     || '',
  search:   props.filters?.search   || '',
  sort:     props.filters?.sort     || 'completed_at',
  sort_dir: props.filters?.sort_dir || 'desc',
  legacy:   props.filters?.legacy   || '',
})

function apply() {
  router.get(route('acts.index'), { tab: props.tab, ...f }, { preserveState: true, replace: true })
}

function switchTab(id) {
  router.get(route('acts.index'), { tab: id }, { preserveState: false })
}

// Передаём текущий адрес списка (вкладка/фильтры/страница) актом через query —
// кнопка "Назад" на карточке акта возвращает именно сюда, а не на дефолтный
// /acts. Надёжнее, чем полагаться на history back — не зависит от того,
// сколько раз до этого меняли фильтры (replace/push вперемешку).
function openAct(id) {
  router.get(route('acts.show', id), { from: window.location.href })
}

function toggleSortDir() {
  f.sort_dir = f.sort_dir === 'asc' ? 'desc' : 'asc'
  apply()
}

function toggleLegacy(e) {
  f.legacy = e.target.checked ? 'show' : ''
  apply()
}

// Строки таблицы + вставленные заголовки групп по территории/бригаде (только для "Активных").
// Список уже отсортирован бэкендом (territory.sort_order, territory.name, brigade.name) —
// здесь только расставляем разделители при смене группы.
const groupedRows = computed(() => {
  if (!props.acts) return []
  const result = []
  let lastKey = null
  for (const act of props.acts.data) {
    const territoryName = requestTerritoryName(act) || 'Без территории'
    const brigadeName = act.ticket?.brigade?.name || act.connection_request?.brigade?.name || 'Без бригады'
    const key = territoryName + '|' + brigadeName
    if (key !== lastKey) {
      result.push({ isGroup: true, key: 'g-' + key, territoryName, brigadeName })
      lastKey = key
    }
    result.push({ isGroup: false, key: 'a-' + act.id, act })
  }
  return result
})

function materialsTotal(act) {
  return (act.materials ?? []).reduce((s, m) => s + m.price_at_time * m.quantity, 0).toFixed(2)
}

// Акт теперь бывает от заявки (ticket) ИЛИ от заявки на подключение
// (connection_request) — эти три хелпера достают общее поле из того
// источника, который реально заполнен у конкретного акта.
function requestLabel(act) {
  if (act.ticket) return '#' + act.ticket.number
  if (act.connection_request) return act.connection_request.name
  return '—'
}

function requestTerritoryName(act) {
  return act.ticket?.address?.territory?.name || act.connection_request?.territory?.name || ''
}

function requestAddress(act) {
  return act.ticket?.address?.full_address || act.connection_request?.address_string || '—'
}

function typeLabel(type) {
  return type === 'repair' ? 'Ремонт' : type === 'regular' ? 'Обычный' : '—'
}

function statusClass(status) {
  return {
    pending_foreman:         'bg-amber-100 text-amber-700',
    approved:                'bg-indigo-100 text-indigo-700',
    processing:              'bg-indigo-100 text-indigo-700',
    pending_subscriber_dept: 'bg-indigo-100 text-indigo-700',
    completed:               'bg-green-100 text-green-700',
  }[status] || 'bg-gray-100 text-gray-600'
}

function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('ru-RU') : '—'
}

// ── Вкладка "Отчёты": Расход материалов (перенесено из общих Отчётов, 2026-07-15) ──
const materials = useReportRange('reports.material-dynamics', { weekly: { labels: [], qty: [], amount: [] }, top: [] })
const materialQtyCanvas = ref(null)
const materialAmtCanvas = ref(null)
const reportCharts = {}

function formatMoney(val) {
  return Number(val).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function destroyReportChart(key) {
  if (reportCharts[key]) { reportCharts[key].destroy(); delete reportCharts[key] }
}

function buildMaterialsCharts() {
  destroyReportChart('qty')
  destroyReportChart('amt')
  const data = materials.state.data
  const labels = data.weekly.labels
  if (!labels.length) return
  if (materialQtyCanvas.value) {
    reportCharts.qty = new Chart(materialQtyCanvas.value, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Кол-во', data: data.weekly.qty,
        borderColor: 'rgba(59,130,246,0.85)', backgroundColor: 'rgba(59,130,246,0.15)', fill: true, tension: 0.35 }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    })
  }
  if (materialAmtCanvas.value) {
    reportCharts.amt = new Chart(materialAmtCanvas.value, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Сумма', data: data.weekly.amount,
        borderColor: 'rgba(34,197,94,0.85)', backgroundColor: 'rgba(34,197,94,0.15)', fill: true, tension: 0.35 }] },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    })
  }
}

watch(() => materials.state.data, () => nextTick(buildMaterialsCharts))

onMounted(() => {
  if (props.tab === 'reports' && props.canViewReports) {
    materials.ensureLoaded()
    nextTick(buildMaterialsCharts)
  }
})

onBeforeUnmount(() => {
  Object.values(reportCharts).forEach(c => c.destroy())
})
</script>
