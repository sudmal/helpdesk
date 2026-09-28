<template>
  <Head :title="TAB_TITLES[activeTab]" />
  <AppLayout :title="TAB_TITLES[activeTab]" help-tab="dispatcher"
             :help-section="activeTab === 'calls' ? 'call-log' : 'call-queue'">

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="bg-gray-50 border-b border-gray-200 flex items-end gap-0.5 px-3 pt-2">
        <button @click="activeTab = 'calls'"
                :class="['px-4 py-2 rounded-t-xl text-sm font-medium transition-colors',
                         activeTab === 'calls'
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          Журнал звонков
        </button>
        <button @click="activeTab = 'techsupport'"
                :class="['px-4 py-2 rounded-t-xl text-sm font-medium transition-colors',
                         activeTab === 'techsupport'
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          Техподдержка
        </button>
        <button @click="activeTab = 'abonotdel'"
                :class="['px-4 py-2 rounded-t-xl text-sm font-medium transition-colors',
                         activeTab === 'abonotdel'
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          Абонотдел
        </button>
        <Link v-if="canViewReports" :href="route('reports.index', { tab: 'callcenter' })"
              class="px-4 py-2 rounded-t-xl text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-white/60 transition-colors ml-auto mb-1">
          Отчёты →
        </Link>
      </div>

    <div v-if="activeTab === 'calls'" class="p-4 space-y-3">

      <TrunkStatusWidget />

      <div class="bg-white rounded-xl border border-gray-200 p-4 mb-3 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-36">
          <label class="block text-xs text-gray-500 mb-1">Телефон</label>
          <input v-model="f.phone" @keydown.enter="apply" class="field-input" placeholder="+7..." />
        </div>
        <div class="flex-1 min-w-48">
          <label class="block text-xs text-gray-500 mb-1">Адрес (из биллинга)</label>
          <input v-model="f.address" @keydown.enter="apply" class="field-input" placeholder="Шахтерский 38 71" />
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
        <div>
          <label class="block text-xs text-gray-500 mb-1">Статус</label>
          <select v-model="f.queue_status" class="field-input">
            <option value="">Все</option>
            <option value="answered">Принят</option>
            <option value="missed">Упущен</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">IVR действие</label>
          <select v-model="f.ivr_action" class="field-input">
            <option value="">Все</option>
            <option v-for="(label, key) in actionLabels" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button @click="apply" class="btn-primary text-sm">Найти</button>
          <button @click="reset" class="btn-outline text-sm">Сброс</button>
        </div>
      </div>
      <div v-if="stats" class="flex flex-wrap items-center gap-3">
        <div class="flex gap-1 bg-white border border-gray-200 rounded-xl p-1 shrink-0">
          <button v-for="p in [{k:'day',l:'Сегодня'},{k:'week',l:'Неделя'},{k:'month',l:'Месяц'}]"
                  :key="p.k" @click="applyPeriod(p.k)"
                  :class="['px-3 py-1 rounded-lg text-xs font-medium transition-colors',
                           activePeriod === p.k ? 'bg-blue-600 text-white' : 'text-gray-500 hover:bg-gray-100']">
            {{ p.l }}
          </button>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm flex-1">
          <canvas ref="pieCanvas" width="44" height="44" class="shrink-0"></canvas>
          <div class="flex items-baseline gap-1.5">
            <span class="font-semibold text-gray-700">{{ stats.total }}</span>
            <span class="text-xs text-gray-400">всего</span>
          </div>
          <div class="w-px h-4 bg-gray-200"></div>
          <div class="flex items-baseline gap-1.5">
            <span class="font-semibold text-green-600">{{ stats.answered }}</span>
            <span class="text-xs text-gray-400">принято</span>
            <span v-if="stats.answered + stats.missed > 0" class="text-xs text-green-500">
              ({{ Math.round(stats.answered / (stats.answered + stats.missed) * 100) }}%)
            </span>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="font-semibold text-red-500">{{ stats.missed }}</span>
            <span class="text-xs text-gray-400">упущено</span>
            <span v-if="stats.answered + stats.missed > 0" class="text-xs text-red-400">
              ({{ Math.round(stats.missed / (stats.answered + stats.missed) * 100) }}%)
            </span>
          </div>
          <div class="w-px h-4 bg-gray-200"></div>
          <div class="flex items-baseline gap-1.5">
            <span class="font-semibold text-gray-300">{{ stats.no_status }}</span>
            <span class="text-xs text-gray-300">без статуса</span>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
          <span class="text-sm text-gray-500 shrink-0">Всего: {{ calls.total }}</span>
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <div v-for="item in BLOCK_LEGEND" :key="item.label" class="flex items-center gap-1">
              <span :class="item.dot" class="w-2.5 h-2.5 rounded-full border border-black/10 shrink-0"></span>
              <span class="text-xs text-gray-500 whitespace-nowrap">{{ item.label }}</span>
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
              <tr>
                <th class="px-2 py-2 text-left">Время</th>
                <th class="px-2 py-2 text-left"></th>
                <th class="px-2 py-2 text-left">Статус</th>
                <th class="px-2 py-2 text-left">Телефон</th>
                <th class="px-2 py-2 text-left">Абонент</th>
                <th class="px-2 py-2 text-left">Договор</th>
                <th class="px-2 py-2 text-left">IVR</th>
                <th class="px-2 py-2 text-right">Баланс</th>
                <th class="px-2 py-2 text-left">Ожидание</th>
                <th class="px-2 py-2 text-left">Оператор</th>
                <th class="px-2 py-2 text-left">Адрес</th>
                <th class="px-2 py-2 text-left">Кв.</th>
                <th class="px-2 py-2 text-left">Заявки</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-xs">
              <tr v-for="c in calls.data" :key="c.id" class="hover:bg-gray-50">
                <td class="px-2 py-0.5 whitespace-nowrap text-gray-500">{{ formatDate(c.called_at) }}</td>
                <td class="px-2 py-0.5">
                  <a :href="createTicketUrl(c)" class="text-xs text-green-600 hover:underline whitespace-nowrap">+ заявка</a>
                </td>
                <td class="px-2 py-0.5">
                  <span v-if="c.queue_status === 'answered'" class="inline-flex items-center px-1.5 py-px rounded-full text-xs font-medium bg-green-100 text-green-700">Принят</span>
                  <span v-else-if="c.queue_status === 'missed'" class="inline-flex items-center px-1.5 py-px rounded-full text-xs font-medium bg-red-100 text-red-600">Упущен</span>
                  <span v-else class="inline-flex items-center px-1.5 py-px rounded-full text-xs font-medium bg-gray-100 text-gray-400">Не в очереди</span>
                </td>
                <td class="px-2 py-0.5 font-mono text-xs">{{ c.phone }}</td>
                <td class="px-2 py-0.5 text-gray-700">
                  <a v-if="c.lanbilling_uid && (c.ivr_subscriber_name || c.lanbilling_name)"
                     :href="lanUserUrl(c.lanbilling_uid)" target="_blank" rel="noopener"
                     class="text-blue-600 hover:underline">{{ c.ivr_subscriber_name || c.lanbilling_name }}</a>
                  <span v-else>{{ c.ivr_subscriber_name || c.lanbilling_name || '—' }}</span>
                </td>
                <td class="px-2 py-0.5 font-mono text-gray-500 text-xs">
                  <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                    <span :class="blockedDotClass(c.ivr_blocked ?? c.lanbilling_blocked)"
                          :title="blockedDotTitle(c.ivr_blocked ?? c.lanbilling_blocked)"
                          class="inline-block w-2 h-2 rounded-full shrink-0"></span>
                    <span v-if="showSessionIcon && sessState(c)" :class="SESS_CLS[sessState(c)]" :title="sessTitle(c)"
                          class="inline-flex items-center shrink-0">
                      <svg v-if="sessState(c) === 'off'" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="2" x2="22" y2="22"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 4.17-2.65"/><path d="M10.66 5c4.01-.36 8.14.9 11.34 3.76"/><path d="M16.85 11.25a10 10 0 0 1 2.22 1.68"/><path d="M5 13a10 10 0 0 1 5.24-2.76"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                      <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13a10 10 0 0 1 14 0"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 20 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                    </span>
                    <span>{{ c.ivr_agreement_num ?? '—' }}</span>
                  </span>
                </td>
                <td class="px-2 py-0.5">
                  <span v-if="c.ivr_action" :class="ivrActionBadge(c.ivr_action)"
                        class="inline-flex items-center px-1.5 py-px rounded-full text-xs font-medium whitespace-nowrap">
                    {{ actionLabels[c.ivr_action] ?? c.ivr_action }}
                  </span>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <td class="px-2 py-0.5 text-right tabular-nums text-xs"
                    :class="(c.ivr_balance ?? 0) < 0 ? 'text-red-600' : 'text-gray-600'">
                  <span v-if="c.ivr_balance !== null && c.ivr_balance !== undefined">{{ c.ivr_balance }} ₽</span>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <td class="px-2 py-0.5 tabular-nums text-gray-500">
                  <span v-if="c.wait_seconds">{{ Math.floor(c.wait_seconds / 60) + ':' + String(c.wait_seconds % 60).padStart(2, '0') }}</span>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <td class="px-2 py-0.5 text-gray-600 font-mono text-xs">{{ c.operator_ext ?? '—' }}</td>
                <td class="px-2 py-0.5 text-gray-700">{{ callAddressLabel(c) }}</td>
                <td class="px-2 py-0.5 text-gray-600">{{ c.apartment ?? '—' }}</td>
                <td class="px-2 py-0.5">
                  <a v-if="c.address"
                     :href="route('tickets.index', { address_id: c.address.id, apartment: c.apartment })"
                     class="text-xs text-blue-500 hover:underline">заявки →</a>
                </td>
              </tr>
              <tr v-if="!calls.data.length">
                <td colspan="13" class="px-2 py-4 text-center text-gray-400">Нет записей</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="calls.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex items-center gap-2">
          <button v-for="link in calls.links" :key="link.label"
                  :disabled="!link.url || link.active"
                  @click="link.url && router.get(link.url, {}, { preserveState: true })"
                  v-html="link.label"
                  :class="['px-3 py-0.5 rounded-lg text-sm transition-colors',
                           link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600 disabled:opacity-40 disabled:cursor-default']" />
        </div>
      </div>
    </div>

    <QueueBoard v-if="activeTab === 'techsupport'" queue-key="techsupport"
                :show-session-icon="showSessionIcon" :blocked-labels="blockedLabels" />
    <QueueBoard v-if="activeTab === 'abonotdel'" queue-key="abonotdel"
                :show-session-icon="showSessionIcon" :blocked-labels="blockedLabels" />

    </div><!-- end main card -->

  </AppLayout>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import QueueBoard from '@/Components/Calls/QueueBoard.vue'
import TrunkStatusWidget from '@/Components/Calls/TrunkStatusWidget.vue'

const props = defineProps({
  calls:         Object,
  filters:       Object,
  stats:         Object,
  actionLabels:  { type: Object, default: () => ({}) },
  blockedLabels: { type: Object, default: () => ({}) },
  showSessionIcon: { type: Boolean, default: true },
  canViewReports:  { type: Boolean, default: false },
})

const TAB_TITLES = { calls: 'Журнал звонков', techsupport: 'Техподдержка', abonotdel: 'Абонотдел' }
const activeTab = ref('calls')
const f = ref({
  phone:        props.filters?.phone        ?? '',
  address:      props.filters?.address      ?? '',
  date_from:    props.filters?.date_from    ?? '',
  date_to:      props.filters?.date_to      ?? '',
  matched:      props.filters?.matched      ?? '',
  queue_status: props.filters?.queue_status ?? '',
  ivr_action:   props.filters?.ivr_action   ?? '',
})
function ivrActionBadge(action) {
  const map = {
    balance_check:       'bg-yellow-100 text-yellow-800',
    pp_offered:          'bg-orange-100 text-orange-700',
    pp_activated:        'bg-green-100 text-green-700',
    pp_declined:         'bg-red-100 text-red-700',
    transfer_to_support: 'bg-blue-100 text-blue-700',
    transfer_to_abon_dept: 'bg-indigo-100 text-indigo-700',
    not_found:           'bg-gray-100 text-gray-500',
    api_error:           'bg-red-200 text-red-800',
  }
  return map[action] ?? 'bg-gray-100 text-gray-500'
}

function apply() {
  router.get(route('calls.index'), f.value, { preserveState: true })
}
function reset() {
  f.value = { phone: '', address: '', date_from: '', date_to: '', matched: '', queue_status: '' }
  activePeriod.value = null
  apply()
}
function applyPeriod(key) {
  activePeriod.value = key
  const fmt = fmtD
  const today = new Date()
  f.value.date_to = fmt(today)
  if (key === 'day')   { const s = new Date(); s.setHours(0,0,0,0);      f.value.date_from = fmt(s) }
  if (key === 'week')  { const s = new Date(); s.setDate(s.getDate()-7);  f.value.date_from = fmt(s) }
  if (key === 'month') { const s = new Date(); s.setDate(s.getDate()-30); f.value.date_from = fmt(s) }
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
function lanUserUrl(uid) {
  return uid ? `https://lan.sputnik-tele.com/#users/${uid}` : null
}
const BLOCK_DOT = {
  0:  'bg-green-400',
  1:  'bg-red-400',
  2:  'bg-amber-400',
  3:  'bg-purple-400',
  4:  'bg-red-400',
  5:  'bg-orange-400',
  10: 'bg-gray-400',
}
function blockedDotClass(code) {
  if (code === null || code === undefined) return 'bg-white border border-gray-300'
  return BLOCK_DOT[code] ?? 'bg-gray-400'
}
function blockedDotTitle(code) {
  if (code === null || code === undefined) return 'Нет данных'
  return props.blockedLabels[code] ?? `Код ${code}`
}
function sessRead(o) {
  return {
    uid:      o?.lanbilling_uid     ?? o?.caller_uid              ?? null,
    online:   o?.session_online     ?? o?.caller_session_online   ?? null,
    redirect: o?.session_redirect   ?? o?.caller_session_redirect ?? null,
    ip:       o?.session_ip         ?? o?.caller_session_ip       ?? null,
  }
}
function sessState(o) {
  const { uid, online, redirect } = sessRead(o)
  if (!uid || online === null || online === undefined) return null
  if (!online) return 'off'
  return redirect ? 'redir' : 'on'
}
function sessTitle(o) {
  const st = sessState(o)
  if (!st) return ''
  if (st === 'off') return 'Оффлайн — активной интернет-сессии нет'
  const { ip } = sessRead(o)
  return `Онлайн${ip ? ' · ' + ip : ''}` + (st === 'redir' ? ' · в редиректе (портал блокировки)' : ' · сессия активна')
}
const SESS_CLS = { on: 'text-green-500', redir: 'text-amber-500', off: 'text-gray-300' }
const BLOCK_LEGEND = [
  { label: 'Нет данных',                 dot: 'bg-white border border-gray-300' },
  { label: 'Активна',                    dot: 'bg-green-400' },
  { label: 'Блок.: баланс',              dot: 'bg-red-400' },
  { label: 'Блок.: абонентом',           dot: 'bg-amber-400' },
  { label: 'Блок.: администратором',     dot: 'bg-purple-400' },
  { label: 'Блок.: лимит трафика',       dot: 'bg-orange-400' },
  { label: 'Отключена',                  dot: 'bg-gray-400' },
]
function callAddressLabel(c) {
  if (c.address) {
    const parts = [c.address.city, c.address.street, c.address.building].filter(Boolean)
    if (parts.length) return parts.join(', ')
  }
  return c.ivr_address || c.address_string || '—'
}

const fmtD = d => {
  const pad = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`
}
function initPeriod() {
  const today = fmtD(new Date())
  const df = props.filters?.date_from
  const dt = props.filters?.date_to
  if (df === today && dt === today) return 'day'
  const weekAgo = new Date(); weekAgo.setDate(weekAgo.getDate() - 7)
  const monthAgo = new Date(); monthAgo.setDate(monthAgo.getDate() - 30)
  if (dt === today && df === fmtD(weekAgo))  return 'week'
  if (dt === today && df === fmtD(monthAgo)) return 'month'
  return null
}
const activePeriod = ref(initPeriod())
const pieCanvas = ref(null)
let pieChart = null
let callsRefreshTimer = null

function renderPie() {
  if (!pieCanvas.value || !props.stats) return
  if (pieChart) { pieChart.destroy(); pieChart = null }
  const { answered, missed, no_status } = props.stats
  if (answered + missed + no_status === 0) return
  pieChart = new Chart(pieCanvas.value, {
    type: 'doughnut',
    data: {
      labels: ['Принято', 'Упущено', 'Не в очереди'],
      datasets: [{ data: [answered, missed, no_status], backgroundColor: ['#22c55e', '#ef4444', '#d1d5db'], borderWidth: 0 }],
    },
    options: {
      responsive: false,
      cutout: '65%',
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } } },
    },
  })
}

watch(() => props.stats, async () => { await nextTick(); renderPie() }, { deep: true })
onMounted(() => {
  nextTick().then(renderPie)
  callsRefreshTimer = setInterval(() => {
    if (activeTab.value === 'calls') router.reload({ only: ['calls'], preserveState: true })
  }, 10000)
})
onUnmounted(() => {
  clearInterval(callsRefreshTimer)
  if (pieChart) pieChart.destroy()
})
</script>
