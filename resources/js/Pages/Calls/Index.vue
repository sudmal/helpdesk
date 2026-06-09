<template>
  <Head :title="activeTab === 'queue' ? 'Очередь АТС' : 'Журнал звонков'" />
  <AppLayout :title="activeTab === 'queue' ? 'Очередь АТС' : 'Журнал звонков'">

    <div class="flex gap-1 mb-4 bg-white border border-gray-200 rounded-2xl p-1 w-fit">
      <button @click="activeTab = 'calls'"
              :class="['px-4 py-1.5 rounded-xl text-sm font-medium transition-colors',
                       activeTab === 'calls' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100']">
        Журнал звонков
      </button>
      <button @click="activeTab = 'queue'"
              :class="['px-4 py-1.5 rounded-xl text-sm font-medium transition-colors',
                       activeTab === 'queue' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:bg-gray-100']">
        Очередь АТС
      </button>
    </div>

    <template v-if="activeTab === 'calls'">
      <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-36">
          <label class="block text-xs text-gray-500 mb-1">Телефон</label>
          <input v-model="f.phone" @keydown.enter="apply" class="field-input" placeholder="+7..." />
        </div>
        <div class="flex-1 min-w-48">
          <label class="block text-xs text-gray-500 mb-1">Адрес (из биллинга)</label>
          <input v-model="f.address" @keydown.enter="apply" class="field-input" placeholder="Железнодорожный..." />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Дата с</label>
          <input v-model="f.date_from" type="date" class="field-input" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Дата по</label>
          <input v-model="f.date_to" type="date" class="field-input" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Адрес сматчен</label>
          <select v-model="f.matched" class="field-input">
            <option value="">Все</option>
            <option value="yes">Да</option>
            <option value="no">Нет</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button @click="apply" class="btn-primary text-sm">Найти</button>
          <button @click="reset" class="btn-outline text-sm">Сброс</button>
        </div>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
          <span class="text-sm text-gray-500">Всего: {{ calls.total }}</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
              <tr>
                <th class="px-4 py-3 text-left">Время</th>
                <th class="px-4 py-3 text-left"></th>
                <th class="px-4 py-3 text-left">Телефон</th>
                <th class="px-4 py-3 text-left">Адрес из биллинга</th>
                <th class="px-4 py-3 text-left">Адрес в базе</th>
                <th class="px-4 py-3 text-left">Кв.</th>
                <th class="px-4 py-3 text-left">Заявки</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-xs">
              <tr v-for="c in calls.data" :key="c.id" class="hover:bg-gray-50">
                <td class="px-3 py-0.5 whitespace-nowrap text-gray-500">{{ formatDate(c.called_at) }}</td>
                <td class="px-3 py-0.5">
                  <a :href="createTicketUrl(c)" class="text-xs text-green-600 hover:underline whitespace-nowrap">+ заявка</a>
                </td>
                <td class="px-3 py-0.5 font-mono">{{ c.phone }}</td>
                <td class="px-3 py-0.5 text-gray-700">{{ c.address_string ?? '—' }}</td>
                <td class="px-3 py-0.5">
                  <span v-if="c.address" class="text-green-700">{{ c.address.full_address }}</span>
                  <span v-else class="text-gray-400">не найден</span>
                </td>
                <td class="px-3 py-0.5 text-gray-600">{{ c.apartment ?? '—' }}</td>
                <td class="px-3 py-0.5">
                  <a v-if="c.address"
                     :href="route('tickets.index', { address_id: c.address.id, apartment: c.apartment })"
                     class="text-xs text-blue-500 hover:underline">заявки →</a>
                </td>
              </tr>
              <tr v-if="!calls.data.length">
                <td colspan="7" class="px-4 py-8 text-center text-gray-400">Нет записей</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="calls.last_page > 1" class="px-5 py-3 border-t border-gray-100 flex items-center gap-2">
          <button v-for="link in calls.links" :key="link.label"
                  :disabled="!link.url || link.active"
                  @click="link.url && router.get(link.url, {}, { preserveState: true })"
                  v-html="link.label"
                  :class="['px-3 py-0.5 rounded-lg text-sm transition-colors',
                           link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600 disabled:opacity-40 disabled:cursor-default']" />
        </div>
      </div>
    </template>

    <template v-if="activeTab === 'queue'">
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
          <div class="text-3xl font-bold" :class="qLatest?.waiting > 0 ? 'text-amber-500' : 'text-gray-300'">
            {{ qLatest?.waiting ?? '—' }}
          </div>
          <div class="text-xs text-gray-500 mt-1">Ожидают в очереди</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
          <div class="text-3xl font-bold text-blue-500">{{ qLatest?.talking ?? '—' }}</div>
          <div class="text-xs text-gray-500 mt-1">Разговаривают</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
          <div class="text-3xl font-bold text-green-500">{{ qLatest?.active_members ?? '—' }}</div>
          <div class="text-xs text-gray-500 mt-1">Активных операторов</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 text-center">
          <div class="text-3xl font-bold text-gray-400">{{ qLatest?.total_members ?? '—' }}</div>
          <div class="text-xs text-gray-500 mt-1">Всего операторов</div>
        </div>
      </div>

      <!-- Очередь + Операторы -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden min-w-0">
          <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-700">В очереди</span>
            <span v-if="qDetail.callers.length"
                  class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">
              {{ qDetail.callers.length }}
            </span>
          </div>
          <div v-if="!qDetail.callers.length" class="px-4 py-4 text-sm text-gray-400 text-center">Пусто</div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="c in qDetail.callers" :key="c.pos"
                 class="px-3 py-1.5">
              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 w-5 shrink-0">#{{ c.pos }}</span>
                <span class="text-xs font-mono font-semibold text-gray-800 flex-1">{{ c.phone ?? '—' }}</span>
                <span class="text-xs font-bold font-mono text-amber-600 tabular-nums shrink-0">{{ c.wait }}</span>
              </div>
              <div v-if="c.address" class="ml-7 text-xs text-gray-500 leading-tight mt-0.5">{{ c.address }}</div>
            </div>
          </div>
        </div>
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
          <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50">
            <span class="text-sm font-semibold text-gray-700">Операторы</span>
          </div>
          <div v-if="!qDetail.members.length" class="px-4 py-6 text-sm text-gray-400 text-center">
            Нет данных от АТС
          </div>
          <table v-else class="w-full text-sm">
            <thead class="text-xs text-gray-400 uppercase bg-gray-50 border-b border-gray-100">
              <tr>
                <th class="px-3 py-1.5 text-left w-14">Доб.</th>
                <th class="px-3 py-1.5 text-left">Статус</th>
                <th class="px-3 py-1.5 text-right">Время</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="m in sortedMembers" :key="m.ext" class="hover:bg-gray-50">
                <td class="px-3 py-1 font-mono font-bold text-gray-800">{{ m.ext }}</td>
                <td class="px-3 py-1">
                  <span :class="statusBadge(m.status)"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap">
                    <span :class="statusDot(m.status)" class="w-1.5 h-1.5 rounded-full flex-shrink-0"></span>
                    {{ statusLabel(m.status) }}
                  </span>
                </td>
                <td class="px-3 py-1 text-right">
                  <template v-if="m.secs > 0">
                    <span v-if="m.status === 'in_call'"
                          class="text-xs font-mono font-semibold text-red-600 tabular-nums">{{ formatSecs(m.secs) }}</span>
                    <span v-else class="text-xs text-gray-400 font-mono tabular-nums">{{ formatSecs(m.secs) }} назад</span>
                  </template>
                  <span v-else class="text-xs text-gray-300">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
        <div class="flex gap-1 bg-white border border-gray-200 rounded-xl p-1">
          <button v-for="h in [1, 3, 6, 12, 24]" :key="h"
                  @click="qHours = h; loadQueue()"
                  :class="['px-3 py-1 rounded-lg text-xs font-medium transition-colors',
                           qHours === h ? 'bg-blue-600 text-white' : 'text-gray-500 hover:bg-gray-100']">
            {{ h }}ч
          </button>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs text-gray-400">
            {{ qLatest ? 'Обновлено: ' + formatDate(qLatest.recorded_at) : 'Нет данных от АТС' }}
          </span>
          <button @click="loadQueue" class="btn-outline text-xs py-1">Обновить</button>
        </div>
      </div>
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div v-if="qHistory.length === 0" class="flex items-center justify-center h-48 text-gray-400 text-sm">
          {{ qLoading ? 'Загрузка...' : 'Нет данных за выбранный период' }}
        </div>
        <canvas v-else ref="queueCanvas" style="max-height:300px"></canvas>
      </div>
    </template>

  </AppLayout>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  calls:   Object,
  filters: Object,
})

const activeTab = ref('calls')
const f = ref({
  phone:     props.filters?.phone     ?? '',
  address:   props.filters?.address   ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to:   props.filters?.date_to   ?? '',
  matched:   props.filters?.matched   ?? '',
})
function apply() {
  router.get(route('calls.index'), f.value, { preserveState: true })
}
function reset() {
  f.value = { phone: '', address: '', date_from: '', date_to: '', matched: '' }
  apply()
}
function createTicketUrl(c) {
  const params = { phone: c.phone }
  if (c.address) {
    params.address_id = c.address.id
    if (c.apartment) params.apartment = c.apartment
  }
  return route('tickets.create', params)
}
function formatDate(val) {
  if (!val) return '—'
  const d = new Date(val)
  return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}
const qLatest     = ref(null)
const qHistory    = ref([])
const qDetail     = ref({ members: [], callers: [] })
const qHours      = ref(3)
const qLoading    = ref(false)
const queueCanvas = ref(null)
let qChart = null
let qRefreshTimer = null
let callsRefreshTimer = null

async function loadQueue() {
  qLoading.value = true
  try {
    const res  = await fetch(route('pbx.queue-history') + '?hours=' + qHours.value)
    const data = await res.json()
    qLatest.value  = data.latest
    qHistory.value = data.history
    qDetail.value  = data.detail ?? { members: [], callers: [] }
  } catch (e) {}
  qLoading.value = false
}

const STATUS_ORDER = { in_call: 0, ringing: 1, idle: 2, unavailable: 3 }
const sortedMembers = computed(() =>
  [...qDetail.value.members].sort((a, b) => (STATUS_ORDER[a.status] ?? 9) - (STATUS_ORDER[b.status] ?? 9))
)
function statusLabel(s) {
  return { in_call: "В разговоре", ringing: "Звонит", idle: "Свободен", unavailable: "Недоступен" }[s] ?? s
}
function statusBadge(s) {
  return { in_call: "bg-red-50 text-red-700", ringing: "bg-yellow-50 text-yellow-700", idle: "bg-green-50 text-green-700", unavailable: "bg-gray-100 text-gray-500" }[s] ?? "bg-gray-100 text-gray-500"
}
function statusDot(s) {
  return { in_call: "bg-red-500", ringing: "bg-yellow-400 animate-pulse", idle: "bg-green-500", unavailable: "bg-gray-300" }[s] ?? "bg-gray-300"
}
function formatSecs(secs) {
  if (!secs || secs <= 0) return "—"
  if (secs < 60) return secs + " с"
  if (secs < 3600) return Math.floor(secs / 60) + " мин"
  if (secs < 86400) return Math.floor(secs / 3600) + " ч " + Math.floor((secs % 3600) / 60) + " мин"
  return Math.floor(secs / 86400) + " дн"
}

function renderChart() {
  if (!queueCanvas.value || qHistory.value.length === 0) return
  if (qChart) { qChart.destroy(); qChart = null }
  const labels = qHistory.value.map(r => {
    const d = new Date(r.recorded_at)
    return d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  })
  const few = qHistory.value.length <= 60
  qChart = new Chart(queueCanvas.value, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label: 'Ожидают в очереди',  data: qHistory.value.map(r => r.waiting),        borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.3, fill: true, pointRadius: few ? 3 : 0 },
        { label: 'Разговаривают',       data: qHistory.value.map(r => r.talking),        borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.08)', tension: 0.3, fill: true, pointRadius: few ? 3 : 0 },
        { label: 'Активных операторов', data: qHistory.value.map(r => r.active_members), borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.08)',  tension: 0.3, fill: true, pointRadius: few ? 3 : 0 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'top', labels: { font: { size: 12 } } } },
      scales: {
        x: { ticks: { maxTicksLimit: 12, font: { size: 11 } } },
        y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } } },
      },
    },
  })
}
watch(activeTab, val => { if (val === 'queue') loadQueue() })
watch(qHistory, async () => { await nextTick(); renderChart() }, { deep: true })
onMounted(() => {
  callsRefreshTimer = setInterval(() => {
    if (activeTab.value === 'calls') router.reload({ only: ['calls'], preserveState: true })
  }, 10000)
  qRefreshTimer = setInterval(() => {
    if (activeTab.value === 'queue') loadQueue()
  }, 60000)
})
onUnmounted(() => {
  clearInterval(callsRefreshTimer)
  clearInterval(qRefreshTimer)
  if (qChart) qChart.destroy()
})
</script>
