<template>
  <Head :title="activeTab === 'queue' ? 'Очередь АТС' : 'Журнал звонков'" />
  <AppLayout :title="activeTab === 'queue' ? 'Очередь АТС' : 'Журнал звонков'">

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="bg-gray-50 border-b border-gray-200 flex items-end gap-0.5 px-3 pt-2">
        <button @click="activeTab = 'calls'"
                :class="['px-4 py-2 rounded-t-xl text-sm font-medium transition-colors',
                         activeTab === 'calls'
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          Журнал звонков
        </button>
        <button @click="activeTab = 'queue'"
                :class="['px-4 py-2 rounded-t-xl text-sm font-medium transition-colors',
                         activeTab === 'queue'
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          Очередь АТС
        </button>

      </div>
    <div v-if="activeTab === 'calls'" class="p-4 space-y-4">

      <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
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
        <div class="bg-white rounded-2xl border border-gray-200 px-4 py-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm flex-1">
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

      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
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
                <td colspan="13" class="px-2 py-6 text-center text-gray-400">Нет записей</td>
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
    </div>
    <div v-if="activeTab === 'queue'" class="p-4">
      <div class="bg-white rounded-2xl border border-gray-200 px-5 py-2.5 flex items-start gap-4 text-sm mb-4">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1.5 min-w-0">
          <div class="flex items-baseline gap-1.5">
            <span class="text-lg font-bold" :class="qLatest?.waiting > 0 ? 'text-amber-500' : 'text-gray-300'">{{ qLatest?.waiting ?? '—' }}</span>
            <span class="text-xs text-gray-400">ожидают</span>
          </div>
          <div class="w-px h-4 bg-gray-200"></div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-blue-500">{{ qLatest?.talking ?? '—' }}</span>
            <span class="text-xs text-gray-400">разговаривают</span>
          </div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-green-500">{{ qLatest?.active_members ?? '—' }}</span>
            <span class="text-xs text-gray-400">активных операторов</span>
          </div>
          <div class="w-px h-4 bg-gray-200"></div>
          <div class="flex items-baseline gap-1.5">
            <span class="text-lg font-bold text-gray-400">{{ qLatest?.total_members ?? '—' }}</span>
            <span class="text-xs text-gray-400">всего</span>
          </div>
          <div class="w-px h-4 bg-gray-200"></div>
          <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1 min-w-0">
            <div v-for="item in BLOCK_LEGEND_SHORT" :key="item.label" class="flex items-center gap-0.5" :title="item.title">
              <span :class="item.dot" class="w-2 h-2 rounded-full border border-black/10 shrink-0"></span>
              <span class="text-xs text-gray-500 whitespace-nowrap">{{ item.label }}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-3 shrink-0 pt-0.5">
          <div class="flex items-center gap-1.5"
               :title="trunkTitle(trunkStatus, qDetail.trunk?.rtt_ms)">
            <span :class="['w-2.5 h-2.5 rounded-full flex-shrink-0', trunkDotClass(trunkStatus)]"></span>
            <span class="text-xs text-gray-500 font-medium whitespace-nowrap">PHOENIX SIP</span>
          </div>
          <button @click="sendCmd('fix_dialing')" :disabled="cmdSending !== null"
                  :class="['flex items-center justify-center w-6 h-6 rounded-full border-2 transition-colors flex-shrink-0 text-xs font-bold leading-none',
                           cmdSending === 'fix_dialing' ? 'bg-orange-500 border-orange-500 text-white' : 'bg-white border-orange-400 text-orange-600 hover:bg-orange-100']"
                  title="Починить дозвон: pjsip reload + dialplan reload + queue reload + qualify all">
            {{ cmdSending === 'fix_dialing' ? '…' : '↻' }}
          </button>
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
                <span class="inline-flex items-center gap-1.5 flex-1 min-w-0 whitespace-nowrap">
                  <span :class="blockedDotClass(c.lanbilling_blocked)"
                        :title="blockedDotTitle(c.lanbilling_blocked)"
                        class="inline-block w-2 h-2 rounded-full shrink-0"></span>
                  <span class="text-xs font-mono font-semibold text-gray-800">{{ c.phone ?? '—' }}</span>
                </span>
                <span class="text-xs font-bold font-mono text-amber-600 tabular-nums shrink-0">{{ c.wait }}</span>
              </div>
              <a v-if="c.address && c.lanbilling_uid" :href="lanUserUrl(c.lanbilling_uid)" target="_blank" rel="noopener"
                 class="block ml-7 text-xs text-blue-600 hover:underline leading-tight mt-0.5">{{ c.address }}</a>
              <div v-else-if="c.address" class="ml-7 text-xs text-gray-500 leading-tight mt-0.5">{{ c.address }}</div>
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
                <th class="px-3 py-1.5 text-left w-full">Статус</th>
                <th class="px-3 py-1.5 text-right w-24 whitespace-nowrap">Время</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="m in sortedMembers" :key="m.ext" class="hover:bg-gray-50">
                <td class="px-3 py-1 font-mono font-bold text-gray-800">
                  <div class="flex items-center gap-1.5">
                    {{ m.ext }}
                    <span :class="['w-2 h-2 rounded-full flex-shrink-0', sipDotClass(m.ext)]"
                          :title="sipTitle(m.ext)"></span>
                  </div>
                </td>
                <td class="px-3 py-1 w-full min-w-0">
                  <div class="flex items-center gap-2 flex-wrap min-w-0">
                    <span :class="statusBadge(m.status)"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap flex-shrink-0">
                      <span :class="statusDot(m.status)" class="w-1.5 h-1.5 rounded-full flex-shrink-0"></span>
                      {{ statusLabel(m.status) }}
                    </span>
                    <span v-if="m.dnd"
                          :title="m.dnd_since ? 'В DND с ' + formatDate(m.dnd_since) : 'В DND'"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap flex-shrink-0 bg-purple-100 text-purple-700">
                      DND{{ m.dnd_since ? ' · ' + dndDuration(m.dnd_since) : '' }}
                    </span>
                    <span v-if="m.dnd_missed_since && m.status !== 'in_call'"
                          :title="'DND по звонку -- начало ' + formatDate(m.dnd_missed_since) + ', обновлено ' + formatDate(m.dnd_missed_at)"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap flex-shrink-0 bg-purple-100 text-purple-700">
                      ⚠ DND (по звонку) · начало {{ shortTime(m.dnd_missed_since) }} · обновлено {{ shortTime(m.dnd_missed_at) }} · {{ dndDuration(m.dnd_missed_since) }}
                    </span>
                    <span v-if="m.caller_phone" class="inline-flex items-center gap-1.5 whitespace-nowrap">
                      <span :class="blockedDotClass(m.caller_blocked)"
                            :title="blockedDotTitle(m.caller_blocked)"
                            class="inline-block w-2 h-2 rounded-full shrink-0"></span>
                      <span class="text-xs font-mono text-gray-700">{{ m.caller_phone }}</span>
                    </span>
                    <a v-if="m.caller_address && m.caller_uid" :href="lanUserUrl(m.caller_uid)" target="_blank" rel="noopener"
                       class="text-xs text-blue-600 hover:underline truncate">{{ m.caller_address }}</a>
                    <span v-else-if="m.caller_address" class="text-xs text-gray-400 truncate">{{ m.caller_address }}</span>
                  </div>
                </td>
                <td class="px-3 py-1 text-right whitespace-nowrap">
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
        <template v-else>
          <div class="flex flex-wrap gap-x-4 gap-y-1 justify-center mb-2 text-xs text-gray-500">
            <div v-for="item in TIMELINE_LEGEND" :key="item.label" class="flex items-center gap-1.5">
              <span class="inline-block w-2.5 h-2.5 rounded-full" :style="{background: item.color}"></span>{{ item.label }}
            </div>
          </div>
          <div :style="{height: Math.max(120, 28 * qTimeline.extensions.length + 10) + 'px'}">
            <canvas ref="timelineCanvas"></canvas>
          </div>
          <div class="border-t border-gray-100 my-3"></div>
          <div class="flex flex-wrap gap-x-4 gap-y-1 justify-center mb-3 text-xs text-gray-500">
            <div class="flex items-center gap-1.5">
              <span class="inline-block w-5 h-0.5 rounded" style="background:#f59e0b"></span>Ожидают в очереди
            </div>
          </div>
          <canvas ref="queueCanvas" style="max-height:220px"></canvas>
        </template>
      </div>
    </div>



    </div><!-- end main card -->

  </AppLayout>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import axios from 'axios'
import Chart from 'chart.js/auto'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  calls:         Object,
  filters:       Object,
  stats:         Object,
  actionLabels:  { type: Object, default: () => ({}) },
  blockedLabels: { type: Object, default: () => ({}) },
})

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
  const pad = n => String(n).padStart(2, '0')
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
function shortTime(val) {
  if (!val) return '—'
  return new Date(val).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}
function lanUserUrl(uid) {
  return uid ? `https://lan.sputnik-tele.com/#users/${uid}` : null
}
// Код блокировки ЛС LanBilling -- маленький кружок (те же цвета, что и в
// легенде), а не бейдж/раскраска строки. Null/undefined (код не пришёл) --
// пустой кружок с рамкой, чтобы отличать "нет данных" от кода 0 (активна).
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
const BLOCK_LEGEND = [
  { label: 'Нет данных',                 dot: 'bg-white border border-gray-300' },
  { label: 'Активна',                    dot: 'bg-green-400' },
  { label: 'Блок.: баланс',              dot: 'bg-red-400' },
  { label: 'Блок.: абонентом',           dot: 'bg-amber-400' },
  { label: 'Блок.: администратором',     dot: 'bg-purple-400' },
  { label: 'Блок.: лимит трафика',       dot: 'bg-orange-400' },
  { label: 'Отключена',                  dot: 'bg-gray-400' },
]
// Компактная версия для узкой строки статистики очереди -- те же цвета,
// короткие подписи (полный текст -- в title при наведении), чтобы влезло
// в одну строку рядом со статистикой, не переносясь под неё.
const BLOCK_LEGEND_SHORT = [
  { label: 'Нет данных',       title: 'Нет данных',                        dot: 'bg-white border border-gray-300' },
  { label: 'Активна',          title: 'Активна',                           dot: 'bg-green-400' },
  { label: 'Баланс',           title: 'Блок.: отрицательный баланс',       dot: 'bg-red-400' },
  { label: 'Абонентом',        title: 'Блок. абонентом',                   dot: 'bg-amber-400' },
  { label: 'Администратором',  title: 'Блок. администратором',             dot: 'bg-purple-400' },
  { label: 'Лимит трафика',    title: 'Блок.: превышен лимит трафика',     dot: 'bg-orange-400' },
  { label: 'Отключена',        title: 'Отключена',                         dot: 'bg-gray-400' },
]
function callAddressLabel(c) {
  // Если есть привязанный Address (значит есть и ссылка на заявки) --
  // показываем город+улицу+дом из него (город в сырых текстах ivr_address/
  // address_string никогда не встречается). Квартиру из Address НЕ берём --
  // у звонка она своя, отдельным полем (Address.apartment общий на дом и
  // может относиться к другой заявке/типу услуги).
  if (c.address) {
    const parts = [c.address.city, c.address.street, c.address.building].filter(Boolean)
    if (parts.length) return parts.join(', ')
  }
  return c.ivr_address || c.address_string || '—'
}
function dndDuration(since) {
  const secs = Math.max(0, Math.floor((Date.now() - new Date(since).getTime()) / 1000))
  const h = Math.floor(secs / 3600)
  const m = Math.floor((secs % 3600) / 60)
  return h > 0 ? `${h}ч ${m}м` : `${m}м`
}
const cmdSending  = ref(null)
const qLatest     = ref(null)
const qHistory    = ref([])
const qDetail     = ref({ members: [], callers: [], phones: [], trunk: null })
const qHours      = ref(3)
const qLoading    = ref(false)
const qMissedCalls = ref([])
// Chart.js сам подбирает "круглый" шаг для линейной оси по значению в мс,
// не по смыслу времени -- получаются деления вида 08:13, 09:13 вместо
// ровных часов. Подменяем тики на ровные часовые границы вручную.
function hourAlignedTicks(scale) {
  const { min, max } = scale
  if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min) return
  const start = new Date(min)
  start.setMinutes(0, 0, 0)
  if (start.getTime() < min) start.setHours(start.getHours() + 1)
  const ticks = []
  for (let t = start.getTime(); t <= max; t += 3600000) ticks.push({ value: t })
  if (ticks.length) scale.ticks = ticks
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
const queueCanvas    = ref(null)
const timelineCanvas = ref(null)
const pieCanvas    = ref(null)
let qChart = null
let qTimelineChart = null
let pieChart = null
let qRefreshTimer = null
let callsRefreshTimer = null
const qTimeline = ref({ extensions: [], segments: {} })
const qWindow   = ref({ from: null, to: null })
const TIMELINE_COLORS = { offline: '#9ca3af', idle: '#22c55e', in_call: '#3b82f6', dnd: '#a855f7' }
const TIMELINE_LABELS = { offline: 'Офлайн', idle: 'На линии', in_call: 'В разговоре', dnd: 'DND' }
const TIMELINE_LEGEND = Object.keys(TIMELINE_COLORS).map(status => ({ label: TIMELINE_LABELS[status], color: TIMELINE_COLORS[status] }))
const TIMELINE_Y_AXIS_WIDTH = 60 // должна совпадать в renderChart() и renderTimeline(), иначе графики разъедутся по горизонтали

async function loadQueue() {
  qLoading.value = true
  try {
    const res  = await fetch(route('pbx.queue-history') + '?hours=' + qHours.value)
    const data = await res.json()
    qLatest.value  = data.latest
    qHistory.value = data.history
    qDetail.value    = data.detail ?? { members: [], callers: [] }
    qMissedCalls.value = data.missed_calls ?? []
    qTimeline.value = data.operator_timeline ?? { extensions: [], segments: {} }
    qWindow.value   = data.window ?? { from: null, to: null }
  } catch (e) {}
  qLoading.value = false
}
async function sendCmd(cmd) {
  cmdSending.value = cmd
  try {
    await axios.post(route('pbx.trigger-cmd'), { cmd })
  } catch (e) {}
  setTimeout(() => { cmdSending.value = null }, 800)
}

const sipByExt = computed(() => {
  const map = {}
  for (const p of (qDetail.value.phones ?? [])) map[p.extension] = p
  return map
})
function sipDotClass(ext) {
  const s = sipByExt.value[ext]?.status
  if (s === 'Avail')       return 'bg-green-400'
  if (s === 'Unreachable') return 'bg-orange-400'
  return 'bg-gray-300'
}
function sipTitle(ext) {
  const p = sipByExt.value[ext]
  if (!p) return 'SIP: нет данных'
  if (p.status === 'Avail') return `SIP: Зарегистрирован (${p.rtt_ms} мс)`
  if (p.status === 'Unreachable') return 'SIP: Недоступен'
  return 'SIP: Неизвестно'
}
const trunkStatus = computed(() => qDetail.value.trunk?.status ?? null)
function trunkDotClass(s) {
  if (s === 'Avail')       return 'bg-green-400'
  if (s === 'Unreachable') return 'bg-red-400'
  return 'bg-gray-300'
}
function trunkLabel(s) {
  if (s === 'Avail')       return 'Авail'
  if (s === 'Unreachable') return 'Недоступен'
  return 'Нет данных'
}
function trunkTitle(s, rtt) {
  if (s === 'Avail')       return `PHOENIX SIP: подключён (${rtt} мс)`
  if (s === 'Unreachable') return 'PHOENIX SIP: недоступен'
  return 'PHOENIX SIP: нет данных'
}
const sortedMembers = computed(() =>
  [...qDetail.value.members].sort((a, b) => a.ext.localeCompare(b.ext, undefined, { numeric: true }))
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
// Ожидание в очереди -- единственная линия (шумные "Разговаривают"/
// "Активных операторов"/"В DND" переехали в отдельный таймлайн операторов
// renderTimeline() ниже, там же где они и понятнее видны). Ось X -- реальное
// время (линейная шкала в мс), чтобы пиксель-в-пиксель совпадать с таймлайном.
function renderChart() {
  if (!queueCanvas.value || qHistory.value.length === 0) return
  if (qChart) { qChart.destroy(); qChart = null }
  const few = qHistory.value.length <= 60
  const minMs = qWindow.value.from ? new Date(qWindow.value.from).getTime() : undefined
  const maxMs = qWindow.value.to   ? new Date(qWindow.value.to).getTime()   : undefined
  const points = qHistory.value.map(r => ({ x: new Date(r.recorded_at).getTime(), y: r.waiting, row: r }))

  const missedPlugin = {
    id: 'missedMarkers',
    afterDatasetsDraw(chart) {
      const xScale = chart.scales.x
      const area   = chart.chartArea
      if (!xScale || !area) return
      const ctx = chart.ctx
      const y0  = area.bottom
      ctx.save()
      for (const mt of (qMissedCalls.value ?? [])) {
        const ms = new Date(mt).getTime()
        if (ms < xScale.min || ms > xScale.max) continue
        const x = xScale.getPixelForValue(ms)
        ctx.fillStyle = '#ef4444'
        ctx.beginPath()
        ctx.moveTo(x,     y0)
        ctx.lineTo(x - 6, y0 - 11)
        ctx.lineTo(x + 6, y0 - 11)
        ctx.closePath()
        ctx.fill()
      }
      ctx.restore()
    },
  }

  qChart = new Chart(queueCanvas.value, {
    type: 'line',
    data: {
      datasets: [
        { label: 'Ожидают в очереди', data: points, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.3, fill: true, pointRadius: few ? 3 : 0 },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: true, animation: false,
      interaction: { mode: 'nearest', intersect: false, axis: 'x' },
      plugins: {
        legend: { display: false },
        tooltip: {
          // Сохраняем ту же информативность, что была при 4 линиях -- просто
          // достаём остальные метрики из той же строки qHistory вручную,
          // раз они больше не отдельные датасеты на графике.
          callbacks: {
            title(items) {
              if (!items.length) return ''
              return new Date(items[0].parsed.x).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
            },
            label(item) {
              const row = item.raw.row
              return [
                `Ожидают в очереди: ${row.waiting}`,
                `Разговаривают: ${row.talking}`,
                `Активных операторов: ${row.active_members}`,
                `В DND: ${row.dnd_active ?? 0}`,
              ]
            },
            afterBody(items) {
              if (!items.length) return []
              const exts = items[0].raw.row?.dnd_extensions
              return (exts && exts.length) ? ['Добавочные в DND: ' + exts.join(', ')] : []
            },
          },
        },
      },
      scales: {
        x: {
          type: 'linear', min: minMs, max: maxMs,
          afterBuildTicks: hourAlignedTicks,
          ticks: {
            maxTicksLimit: 12, font: { size: 11 },
            callback: v => new Date(v).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }),
          },
        },
        y: {
          beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } },
          afterFit: scale => { scale.width = TIMELINE_Y_AXIS_WIDTH },
        },
      },
    },
    plugins: [missedPlugin],
  })
}

// Горизонтальный таймлайн операторов -- по строке на добавочный, floating
// bar на каждый отрезок статуса. Список добавочных динамический (растёт/
// сужается сам, см. buildOperatorTimeline() на бэкенде) -- новые/пропавшие
// добавочные появляются/исчезают между обновлениями без доп. кода тут.
//
// ВАЖНО: один датасет на ВСЕ отрезки (а не по датасету на статус) -- раньше
// было 4 датасета (offline/idle/in_call/dnd), и Chart.js либо раскладывал их
// параллельными суб-полосками на категорию (дефолт для сгруппированных
// баров), либо при stacked/stack-группировке ломал позиционирование
// floating-баров ([start,end]) для всех, кроме первого датасета -- реально
// рисовался только "offline". Один датасет с цветом на каждую ТОЧКУ данных
// (через backgroundColor-колбэк) полностью убирает эту путаницу: Chart.js
// просто кладёт каждый сегмент в свою строку по `y`, без группировки.
function renderTimeline() {
  if (!timelineCanvas.value || !qTimeline.value.extensions.length) return
  if (qTimelineChart) { qTimelineChart.destroy(); qTimelineChart = null }

  const exts  = qTimeline.value.extensions
  const minMs = qWindow.value.from ? new Date(qWindow.value.from).getTime() : undefined
  const maxMs = qWindow.value.to   ? new Date(qWindow.value.to).getTime()   : undefined

  const data = exts.flatMap(ext =>
    (qTimeline.value.segments[ext] ?? []).map(s => ({
      x: [new Date(s.start).getTime(), new Date(s.end).getTime()],
      y: ext,
      status: s.status,
      start: s.start,
      end: s.end,
    }))
  )

  // Фон таймлайна красится по длине очереди (qHistory.waiting) в тот же
  // момент времени -- рисуем ДО баров (beforeDatasetsDraw), поэтому цвет
  // проступает только в белых промежутках между/вокруг полос статусов, сами
  // бары остаются полностью непрозрачными поверх. 0 -- прозрачно, 14+ --
  // максимально красный.
  const MAX_QUEUE_FOR_HEAT = 14
  const queueHeatPlugin = {
    id: 'queueHeat',
    beforeDatasetsDraw(chart) {
      const xScale = chart.scales.x
      const area   = chart.chartArea
      const rows   = qHistory.value
      if (!xScale || !area || !rows.length) return
      const ctx = chart.ctx
      ctx.save()
      for (let i = 0; i < rows.length; i++) {
        const waiting = rows[i].waiting ?? 0
        if (waiting <= 0) continue
        const startMs = new Date(rows[i].recorded_at).getTime()
        const endMs   = i + 1 < rows.length ? new Date(rows[i + 1].recorded_at).getTime() : xScale.max
        const x0 = Math.max(area.left,  xScale.getPixelForValue(startMs))
        const x1 = Math.min(area.right, xScale.getPixelForValue(endMs))
        if (x1 <= x0) continue
        // Минимум 0.35 непрозрачности для любой непустой очереди (иначе
        // небольшие значения почти не видны на глаз), дальше растёт до 1.0 к 14.
        const alpha = Math.min(1, 0.35 + (waiting / MAX_QUEUE_FOR_HEAT) * 0.65)
        ctx.fillStyle = `rgba(220, 38, 38, ${alpha})`
        ctx.fillRect(x0, area.top, x1 - x0, area.bottom - area.top)
      }
      ctx.restore()
    },
  }

  qTimelineChart = new Chart(timelineCanvas.value, {
    type: 'bar',
    data: {
      labels: exts,
      datasets: [{
        data,
        backgroundColor: ctx => TIMELINE_COLORS[ctx.raw?.status] ?? '#d1d5db',
        borderSkipped: false,
        barPercentage: 1,
        categoryPercentage: 0.85,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false, animation: false,
      scales: {
        x: {
          type: 'linear', min: minMs, max: maxMs,
          afterBuildTicks: hourAlignedTicks,
          ticks: {
            maxTicksLimit: 12, font: { size: 11 },
            callback: v => new Date(v).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' }),
          },
        },
        y: {
          grid: { display: false },
          ticks: { font: { size: 11 } },
          afterFit: scale => { scale.width = TIMELINE_Y_AXIS_WIDTH },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            title: () => '',
            label(ctx) {
              const r    = ctx.raw
              const from = new Date(r.start).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
              const to   = new Date(r.end).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
              return `${ctx.label}: ${TIMELINE_LABELS[r.status] ?? r.status} (${from} – ${to})`
            },
          },
        },
      },
    },
    plugins: [queueHeatPlugin],
  })
}

watch(activeTab, val => { if (val === 'queue') loadQueue() })
watch(qHistory, async () => { await nextTick(); renderChart() }, { deep: true })
watch(qTimeline, async () => { await nextTick(); renderTimeline() }, { deep: true })
watch(() => props.stats, async () => { await nextTick(); renderPie() }, { deep: true })
onMounted(() => {
  nextTick().then(renderPie)
  callsRefreshTimer = setInterval(() => {
    if (activeTab.value === 'calls') router.reload({ only: ['calls'], preserveState: true })
  }, 10000)
  qRefreshTimer = setInterval(() => {
    if (activeTab.value === 'queue') loadQueue()
  }, 15000)
})
onUnmounted(() => {
  clearInterval(callsRefreshTimer)
  clearInterval(qRefreshTimer)
  if (qChart) qChart.destroy()
  if (qTimelineChart) qTimelineChart.destroy()
  if (pieChart) pieChart.destroy()
})
</script>
