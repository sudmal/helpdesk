<template>
  <div class="p-4 space-y-3">
    <div class="flex bg-gray-100 rounded-lg p-1 gap-0.5 w-fit">
      <button @click="subTab = 'queue'"
              :class="['px-3 py-1 rounded-md text-sm font-medium transition-colors',
                       subTab === 'queue' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
        Очередь
      </button>
      <button @click="subTab = 'shifts'"
              :class="['px-3 py-1 rounded-md text-sm font-medium transition-colors',
                       subTab === 'shifts' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
        Смены
      </button>
    </div>

    <div v-if="subTab === 'queue'">
      <div class="bg-white rounded-xl border border-gray-200 px-4 py-2.5 flex items-start gap-3 text-sm mb-3">
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
          <button @click="sendCmd('fix_dialing')" :disabled="cmdSending !== null"
                  :class="['flex items-center justify-center w-6 h-6 rounded-full border-2 transition-colors flex-shrink-0 text-xs font-bold leading-none',
                           cmdSending === 'fix_dialing' ? 'bg-orange-500 border-orange-500 text-white' : 'bg-white border-orange-400 text-orange-600 hover:bg-orange-100']"
                  title="Починить дозвон: pjsip reload + dialplan reload + queue reload + qualify all">
            {{ cmdSending === 'fix_dialing' ? '…' : '↻' }}
          </button>
        </div>
      </div>

      <!-- Очередь + Операторы -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-3">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden min-w-0">
          <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-700">В очереди</span>
            <span v-if="qDetail.callers.length"
                  class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">
              {{ qDetail.callers.length }}
            </span>
          </div>
          <div v-if="!qDetail.callers.length" class="px-4 py-3 text-sm text-gray-400 text-center">Пусто</div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="c in qDetail.callers" :key="c.pos"
                 class="px-3 py-1.5">
              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400 w-5 shrink-0">#{{ c.pos }}</span>
                <span class="inline-flex items-center gap-1.5 flex-1 min-w-0 whitespace-nowrap">
                  <span :class="blockedDotClass(c.lanbilling_blocked)"
                        :title="blockedDotTitle(c.lanbilling_blocked)"
                        class="inline-block w-2 h-2 rounded-full shrink-0"></span>
                    <span v-if="showSessionIcon && sessState(c)" :class="SESS_CLS[sessState(c)]" :title="sessTitle(c)"
                          class="inline-flex items-center shrink-0">
                      <svg v-if="sessState(c) === 'off'" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="2" x2="22" y2="22"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 4.17-2.65"/><path d="M10.66 5c4.01-.36 8.14.9 11.34 3.76"/><path d="M16.85 11.25a10 10 0 0 1 2.22 1.68"/><path d="M5 13a10 10 0 0 1 5.24-2.76"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                      <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13a10 10 0 0 1 14 0"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 20 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                    </span>
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
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50">
            <span class="text-sm font-semibold text-gray-700">Операторы</span>
          </div>
          <div v-if="!qDetail.members.length" class="px-4 py-4 text-sm text-gray-400 text-center">
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
                          :title="dndBadgeTitle(m)"
                          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap flex-shrink-0 bg-purple-100 text-purple-700">
                      DND · {{ dndSourceLabel(m) }} · {{ dndDuration(m.dnd_since) }}
                    </span>
                    <span v-if="m.caller_phone" class="inline-flex items-center gap-1.5 whitespace-nowrap">
                      <span :class="blockedDotClass(m.caller_blocked)"
                            :title="blockedDotTitle(m.caller_blocked)"
                            class="inline-block w-2 h-2 rounded-full shrink-0"></span>
                    <span v-if="showSessionIcon && sessState(m)" :class="SESS_CLS[sessState(m)]" :title="sessTitle(m)"
                          class="inline-flex items-center shrink-0">
                      <svg v-if="sessState(m) === 'off'" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="2" y1="2" x2="22" y2="22"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 4.17-2.65"/><path d="M10.66 5c4.01-.36 8.14.9 11.34 3.76"/><path d="M16.85 11.25a10 10 0 0 1 2.22 1.68"/><path d="M5 13a10 10 0 0 1 5.24-2.76"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                      <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13a10 10 0 0 1 14 0"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 20 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
                    </span>
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
      <div class="bg-white rounded-xl border border-gray-200 p-3.5">
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

    <div v-if="subTab === 'shifts'" class="space-y-3">
      <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-wrap gap-3 items-end justify-between">
        <div class="flex flex-wrap gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Дата с</label>
            <input v-model="shiftFilter.date_from" type="date" class="field-input" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Дата по</label>
            <input v-model="shiftFilter.date_to" type="date" class="field-input" />
          </div>
          <button @click="loadShiftReports" class="btn-primary text-sm">Найти</button>
        </div>
        <button @click="toggleShiftSettings" class="btn-outline text-sm">⚙ Настроить смены</button>
      </div>

      <div v-if="currentShift" class="bg-white rounded-xl border-2 border-blue-400 overflow-hidden">
        <div class="px-4 py-2.5 bg-blue-50 border-b border-blue-100 flex flex-wrap items-center gap-3">
          <span class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-700 uppercase tracking-wide">
            <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>Текущая
          </span>
          <span class="font-semibold text-gray-800">{{ currentShift.shift_name }}</span>
          <span class="text-xs text-gray-400">
            {{ shortTime(currentShift.shift_start_at) }} – {{ shortTime(currentShift.shift_end_at) }}
            (данные по {{ shortTime(currentShift.as_of) }})
          </span>
          <button @click="loadCurrentShift" class="ml-auto btn-outline text-xs py-1">Обновить</button>
        </div>
        <div class="px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-1 border-b border-gray-100">
          <span class="text-sm"><b>{{ currentShift.total_calls }}</b> <span class="text-gray-400 text-xs">всего</span></span>
          <span class="text-sm text-green-600"><b>{{ currentShift.answered_calls }}</b> <span class="text-gray-400 text-xs">принято</span></span>
          <span class="text-sm text-red-500"><b>{{ currentShift.missed_calls }}</b> <span class="text-gray-400 text-xs">потеряно ({{ currentShift.missed_percent ?? 0 }}%)</span></span>
          <span class="text-sm"><b>{{ currentShift.sla_percent ?? '—' }}%</b> <span class="text-gray-400 text-xs">SLA ≤{{ currentShift.sla_threshold_sec }}с</span></span>
          <span class="text-sm"><b>{{ formatDur(currentShift.avg_wait_sec) }}</b> <span class="text-gray-400 text-xs">ср. ожидание</span></span>
          <span class="text-sm"><b>{{ formatDur(currentShift.max_wait_sec) }}</b> <span class="text-gray-400 text-xs">макс. ожидание</span></span>
        </div>
        <div class="px-4 py-3 overflow-x-auto">
          <table class="w-full text-xs min-w-[720px]">
            <thead class="text-gray-400 uppercase border-b border-gray-100">
              <tr>
                <th class="text-left py-1.5 pr-2">Доб.</th>
                <th class="text-right py-1.5 px-2">DND</th>
                <th class="text-right py-1.5 px-2">Офлайн</th>
                <th class="text-right py-1.5 px-2">Ожидание</th>
                <th class="text-right py-1.5 px-2">Разговор</th>
                <th class="text-right py-1.5 px-2">Звонков</th>
                <th class="text-right py-1.5 px-2">Мин</th>
                <th class="text-right py-1.5 px-2">Сред</th>
                <th class="text-right py-1.5 px-2">Макс</th>
                <th class="text-right py-1.5 pl-2">Уник. номеров</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <template v-for="e in currentShift.extensions" :key="e.extension">
                <tr>
                  <td class="py-1.5 pr-2 font-mono font-semibold text-gray-800">
                    <button @click="toggleAudit('current:' + e.extension, { extension: e.extension, current: 1 })"
                            class="hover:underline decoration-dotted" title="Разбор по секундам">{{ e.extension }}</button>
                  </td>
                  <td class="text-right px-2 tabular-nums text-purple-600">{{ formatDur(e.seconds_dnd) }}</td>
                  <td class="text-right px-2 tabular-nums text-gray-400">{{ formatDur(e.seconds_offline) }}</td>
                  <td class="text-right px-2 tabular-nums text-green-600">{{ formatDur(e.seconds_idle) }}</td>
                  <td class="text-right px-2 tabular-nums text-blue-600">{{ formatDur(e.seconds_in_call) }}</td>
                  <td class="text-right px-2 tabular-nums font-semibold">{{ e.calls_answered }}</td>
                  <td class="text-right px-2 tabular-nums">{{ e.call_duration_min_sec !== null ? formatDur(e.call_duration_min_sec) : '—' }}</td>
                  <td class="text-right px-2 tabular-nums">{{ e.call_duration_avg_sec !== null ? formatDur(Math.round(e.call_duration_avg_sec)) : '—' }}</td>
                  <td class="text-right px-2 tabular-nums">{{ e.call_duration_max_sec !== null ? formatDur(e.call_duration_max_sec) : '—' }}</td>
                  <td class="text-right pl-2 tabular-nums">{{ e.unique_numbers }}</td>
                </tr>
                <tr v-if="auditKey === 'current:' + e.extension">
                  <td colspan="10" class="bg-gray-50 px-3 py-2">
                    <div v-if="auditLoading" class="text-gray-400 text-center py-2">Загрузка...</div>
                    <div v-else-if="!auditSegments.length" class="text-gray-400 text-center py-2">Нет данных</div>
                    <template v-else>
                      <div class="relative h-6 rounded overflow-hidden bg-gray-200">
                        <div v-for="(s, i) in auditSegments" :key="i"
                             class="absolute top-0 h-full"
                             :class="s.counted ? '' : 'ring-2 ring-inset ring-red-500'"
                             :style="segmentStyle(s)"
                             :title="segmentTitle(s)"></div>
                      </div>
                      <div class="flex justify-between text-[10px] text-gray-400 mt-0.5">
                        <span>{{ formatAuditTime(auditWindow.from) }}</span>
                        <span>{{ formatAuditTime(auditWindow.to) }}</span>
                      </div>
                      <div class="flex flex-wrap gap-x-3 gap-y-1 justify-center mt-1.5 text-[11px] text-gray-500">
                        <div v-for="item in TIMELINE_LEGEND" :key="item.label" class="flex items-center gap-1">
                          <span class="inline-block w-2 h-2 rounded-full" :style="{background: item.color}"></span>{{ item.label }}
                        </div>
                        <div class="flex items-center gap-1">
                          <span class="inline-block w-2 h-2 rounded-full border-2 border-red-500"></span>не засчитано (шум)
                        </div>
                      </div>
                    </template>
                  </td>
                </tr>
              </template>
              <tr v-if="!currentShift.extensions.length">
                <td colspan="10" class="text-center text-gray-400 py-3">Нет данных по операторам</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="showShiftSettings" class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-semibold text-gray-700">Смены</span>
          <button @click="showShiftSettings = false" class="text-gray-400 hover:text-gray-600 text-sm">Закрыть</button>
        </div>
        <p class="text-xs text-gray-400 mb-2">Список смен общий для всех очередей (Техподдержка/Абонотдел) — меняется здесь один раз.</p>
        <table class="w-full text-sm mb-3">
          <thead class="text-xs text-gray-400 uppercase">
            <tr>
              <th class="text-left py-1">Название</th>
              <th class="text-left py-1">Начало</th>
              <th class="text-left py-1">Конец</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in shiftDefs" :key="d.id" class="border-t border-gray-100">
              <td class="py-1.5 pr-2"><input v-model="d.name" class="field-input text-xs py-1" /></td>
              <td class="py-1.5 pr-2"><input v-model="d.start_time" type="time" class="field-input text-xs py-1" /></td>
              <td class="py-1.5 pr-2"><input v-model="d.end_time" type="time" class="field-input text-xs py-1" /></td>
              <td class="py-1.5 text-right whitespace-nowrap">
                <button @click="saveShiftDef(d)" class="text-xs text-blue-600 hover:underline mr-3">Сохранить</button>
                <button @click="deleteShiftDef(d)" class="text-xs text-red-500 hover:underline">Удалить</button>
              </td>
            </tr>
            <tr v-if="!shiftDefs.length">
              <td colspan="4" class="text-center text-gray-400 py-3">Смены не настроены</td>
            </tr>
          </tbody>
        </table>
        <div class="flex gap-2 items-end border-t border-gray-100 pt-3 flex-wrap">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Название</label>
            <input v-model="newShiftDef.name" class="field-input text-xs" placeholder="4 смена" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Начало</label>
            <input v-model="newShiftDef.start_time" type="time" class="field-input text-xs" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Конец</label>
            <input v-model="newShiftDef.end_time" type="time" class="field-input text-xs" />
          </div>
          <button @click="addShiftDef" class="btn-primary text-xs">Добавить смену</button>
        </div>
      </div>

      <div v-if="shiftLoading" class="text-center text-gray-400 py-10 text-sm">Загрузка...</div>
      <div v-else-if="!shiftDates.length" class="text-center text-gray-400 py-10 text-sm">Нет отчётов за выбранный период</div>
      <div v-else class="space-y-3">
        <div v-for="date in shiftDates" :key="date" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-100 text-sm font-semibold text-gray-700 capitalize">
            {{ formatShiftDate(date) }}
          </div>
          <div class="divide-y divide-gray-100">
            <div v-for="r in shiftReportsGrouped[date]" :key="r.id">
              <div class="px-4 py-3 flex flex-wrap items-center gap-x-6 gap-y-1 cursor-pointer hover:bg-gray-50"
                   @click="toggleShiftReport(r)">
                <span class="font-semibold text-gray-800 w-24 shrink-0">{{ r.shift_name }}</span>
                <span class="text-xs text-gray-400 w-28 shrink-0">{{ shortTime(r.shift_start_at) }} – {{ shortTime(r.shift_end_at) }}</span>
                <span class="text-sm"><b>{{ r.total_calls }}</b> <span class="text-gray-400 text-xs">всего</span></span>
                <span class="text-sm text-green-600"><b>{{ r.answered_calls }}</b> <span class="text-gray-400 text-xs">принято</span></span>
                <span class="text-sm text-red-500"><b>{{ r.missed_calls }}</b> <span class="text-gray-400 text-xs">потеряно ({{ r.missed_percent ?? 0 }}%)</span></span>
                <span class="text-sm"><b>{{ r.sla_percent ?? '—' }}%</b> <span class="text-gray-400 text-xs">SLA ≤{{ r.sla_threshold_sec }}с</span></span>
                <span class="text-sm"><b>{{ formatDur(r.avg_wait_sec) }}</b> <span class="text-gray-400 text-xs">ср. ожидание</span></span>
                <span class="text-sm"><b>{{ formatDur(r.max_wait_sec) }}</b> <span class="text-gray-400 text-xs">макс. ожидание</span></span>
                <span class="ml-auto text-gray-400 text-xs">{{ expandedShiftId === r.id ? '▲' : '▼' }}</span>
              </div>
              <div v-if="expandedShiftId === r.id" class="px-4 pb-4 overflow-x-auto">
                <div v-if="shiftDetailLoading" class="text-center text-gray-400 py-3 text-sm">Загрузка...</div>
                <table v-else-if="shiftDetail" class="w-full text-xs min-w-[720px]">
                  <thead class="text-gray-400 uppercase border-b border-gray-100">
                    <tr>
                      <th class="text-left py-1.5 pr-2">Доб.</th>
                      <th class="text-right py-1.5 px-2">DND</th>
                      <th class="text-right py-1.5 px-2">Офлайн</th>
                      <th class="text-right py-1.5 px-2">Ожидание</th>
                      <th class="text-right py-1.5 px-2">Разговор</th>
                      <th class="text-right py-1.5 px-2">Звонков</th>
                      <th class="text-right py-1.5 px-2">Мин</th>
                      <th class="text-right py-1.5 px-2">Сред</th>
                      <th class="text-right py-1.5 px-2">Макс</th>
                      <th class="text-right py-1.5 pl-2">Уник. номеров</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-50">
                    <template v-for="e in shiftDetail.extensions" :key="e.id">
                      <tr>
                        <td class="py-1.5 pr-2 font-mono font-semibold text-gray-800">
                          <button @click="toggleAudit('report:' + shiftDetail.id + ':' + e.extension, { extension: e.extension, shift_report_id: shiftDetail.id })"
                                  class="hover:underline decoration-dotted" title="Разбор по секундам">{{ e.extension }}</button>
                        </td>
                        <td class="text-right px-2 tabular-nums text-purple-600">{{ formatDur(e.seconds_dnd) }}</td>
                        <td class="text-right px-2 tabular-nums text-gray-400">{{ formatDur(e.seconds_offline) }}</td>
                        <td class="text-right px-2 tabular-nums text-green-600">{{ formatDur(e.seconds_idle) }}</td>
                        <td class="text-right px-2 tabular-nums text-blue-600">{{ formatDur(e.seconds_in_call) }}</td>
                        <td class="text-right px-2 tabular-nums font-semibold">{{ e.calls_answered }}</td>
                        <td class="text-right px-2 tabular-nums">{{ e.call_duration_min_sec !== null ? formatDur(e.call_duration_min_sec) : '—' }}</td>
                        <td class="text-right px-2 tabular-nums">{{ e.call_duration_avg_sec !== null ? formatDur(Math.round(e.call_duration_avg_sec)) : '—' }}</td>
                        <td class="text-right px-2 tabular-nums">{{ e.call_duration_max_sec !== null ? formatDur(e.call_duration_max_sec) : '—' }}</td>
                        <td class="text-right pl-2 tabular-nums">{{ e.unique_numbers }}</td>
                      </tr>
                      <tr v-if="auditKey === 'report:' + shiftDetail.id + ':' + e.extension">
                        <td colspan="10" class="bg-gray-50 px-3 py-2">
                          <div v-if="auditLoading" class="text-gray-400 text-center py-2">Загрузка...</div>
                          <div v-else-if="!auditSegments.length" class="text-gray-400 text-center py-2">Нет данных</div>
                          <template v-else>
                            <div class="relative h-6 rounded overflow-hidden bg-gray-200">
                              <div v-for="(s, i) in auditSegments" :key="i"
                                   class="absolute top-0 h-full"
                                   :class="s.counted ? '' : 'ring-2 ring-inset ring-red-500'"
                                   :style="segmentStyle(s)"
                                   :title="segmentTitle(s)"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-gray-400 mt-0.5">
                              <span>{{ formatAuditTime(auditWindow.from) }}</span>
                              <span>{{ formatAuditTime(auditWindow.to) }}</span>
                            </div>
                            <div class="flex flex-wrap gap-x-3 gap-y-1 justify-center mt-1.5 text-[11px] text-gray-500">
                              <div v-for="item in TIMELINE_LEGEND" :key="item.label" class="flex items-center gap-1">
                                <span class="inline-block w-2 h-2 rounded-full" :style="{background: item.color}"></span>{{ item.label }}
                              </div>
                              <div class="flex items-center gap-1">
                                <span class="inline-block w-2 h-2 rounded-full border-2 border-red-500"></span>не засчитано (шум)
                              </div>
                            </div>
                          </template>
                        </td>
                      </tr>
                    </template>
                    <tr v-if="!shiftDetail.extensions.length">
                      <td colspan="10" class="text-center text-gray-400 py-3">Нет данных по операторам</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Доска одной очереди Asterisk (Техподдержка / Абонотдел) — общий компонент,
// параметризованный queueKey (см. config/pbx_queues.php на бэкенде). Раньше
// это был единственный top-level "Очередь АТС"-раздел Calls/Index.vue;
// 2026-09-28 вынесен в переиспользуемый компонент под вторую очередь
// (Абонотдел), плюс убрана полоска статуса SIP-провайдера — она переехала
// в общий TrunkStatusWidget.vue на вкладке "Журнал звонков", т.к. это
// сущность, общая для обеих очередей, а не своя у каждой.
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import Chart from 'chart.js/auto'

const props = defineProps({
  queueKey:        { type: String, required: true },
  showSessionIcon: { type: Boolean, default: true },
  blockedLabels:   { type: Object, default: () => ({}) },
})

const subTab = ref('queue')

function lanUserUrl(uid) {
  return uid ? `https://lan.sputnik-tele.com/#users/${uid}` : null
}
const BLOCK_DOT = { 0: 'bg-green-400', 1: 'bg-red-400', 2: 'bg-amber-400', 3: 'bg-purple-400', 4: 'bg-red-400', 5: 'bg-orange-400', 10: 'bg-gray-400' }
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
const BLOCK_LEGEND_SHORT = [
  { label: 'Нет данных',       title: 'Нет данных',                        dot: 'bg-white border border-gray-300' },
  { label: 'Активна',          title: 'Активна',                           dot: 'bg-green-400' },
  { label: 'Баланс',           title: 'Блок.: отрицательный баланс',       dot: 'bg-red-400' },
  { label: 'Абонентом',        title: 'Блок. абонентом',                   dot: 'bg-amber-400' },
  { label: 'Администратором',  title: 'Блок. администратором',             dot: 'bg-purple-400' },
  { label: 'Лимит трафика',    title: 'Блок.: превышен лимит трафика',     dot: 'bg-orange-400' },
  { label: 'Отключена',        title: 'Отключена',                         dot: 'bg-gray-400' },
]
function dndDuration(since) {
  const secs = Math.max(0, Math.floor((Date.now() - new Date(since).getTime()) / 1000))
  const h = Math.floor(secs / 3600)
  const m = Math.floor((secs % 3600) / 60)
  return h > 0 ? `${h}ч ${m}м` : `${m}м`
}
function dndSourceLabel(m) {
  const byCall = !!(m.dnd_missed_since && m.status !== 'in_call')
  return byCall ? 'presence+call' : 'presence'
}
function dndBadgeTitle(m) {
  const parts = ['presence: с ' + formatDate(m.dnd_since)]
  if (m.dnd_missed_since && m.status !== 'in_call') {
    parts.push('подтверждено недозвоном: начало ' + formatDate(m.dnd_missed_since) + ', обновлено ' + formatDate(m.dnd_missed_at))
  }
  return parts.join(' | ')
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

const cmdSending  = ref(null)
const qLatest     = ref(null)
const qHistory    = ref([])
const qDetail     = ref({ members: [], callers: [], phones: [] })
const qHours      = ref(3)
const qLoading    = ref(false)
const qMissedCalls = ref([])

function hourAlignedTicks(scale) {
  const { min, max } = scale
  if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min) return
  const start = new Date(min)
  start.setMinutes(0, 0, 0)
  if (start.getTime() < min) start.setHours(start.getHours() + 1)
  const ticks = []
  const MAX_TICKS = 500
  for (let t = start.getTime(); t <= max && ticks.length < MAX_TICKS; t += 3600000) ticks.push({ value: t })
  if (ticks.length) scale.ticks = ticks
}

const queueCanvas    = ref(null)
const timelineCanvas = ref(null)
let qChart = null
let qTimelineChart = null
let qRefreshTimer = null
let shiftRefreshTimer = null
const qTimeline = ref({ extensions: [], segments: {} })
const qWindow   = ref({ from: null, to: null })
const TIMELINE_COLORS = { offline: '#9ca3af', idle: '#22c55e', in_call: '#3b82f6', dnd: '#a855f7' }
const TIMELINE_LABELS = { offline: 'Офлайн', idle: 'На линии', in_call: 'В разговоре', dnd: 'DND' }
const TIMELINE_LEGEND = Object.keys(TIMELINE_COLORS).map(status => ({ label: TIMELINE_LABELS[status], color: TIMELINE_COLORS[status] }))
const TIMELINE_Y_AXIS_WIDTH = 60

async function loadQueue() {
  qLoading.value = true
  try {
    const res  = await fetch(route('pbx.queue-history') + '?queue=' + props.queueKey + '&hours=' + qHours.value)
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
    await axios.post(route('pbx.trigger-cmd'), { cmd, queue: props.queueKey })
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

// ── Отчёты по сменам ────────────────────────────────────────────────
const fmtD = d => {
  const pad = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`
}
const shiftFilter = ref({ date_from: fmtD(new Date(Date.now() - 6 * 86400000)), date_to: fmtD(new Date()) })
const shiftReportsGrouped = ref({})
const shiftLoading = ref(false)
const shiftDates = computed(() => Object.keys(shiftReportsGrouped.value).sort().reverse())
const expandedShiftId = ref(null)
const shiftDetail = ref(null)
const shiftDetailLoading = ref(false)
const showShiftSettings = ref(false)
const shiftDefs = ref([])
const newShiftDef = ref({ name: '', start_time: '', end_time: '' })
const currentShift = ref(null)
let shiftsLoadedOnce = false

async function loadShiftReports() {
  shiftLoading.value = true
  try {
    const res  = await axios.get(route('pbx.shift-reports.index'), { params: { ...shiftFilter.value, queue: props.queueKey } })
    shiftReportsGrouped.value = res.data.grouped ?? {}
  } catch (e) {}
  shiftLoading.value = false
}
async function loadCurrentShift() {
  try {
    const res = await axios.get(route('pbx.shift-reports.current'), { params: { queue: props.queueKey } })
    currentShift.value = res.data
  } catch (e) {}
}

const auditKey = ref(null)
const auditSegments = ref([])
const auditWindow = ref({ from: null, to: null })
const auditLoading = ref(false)

async function toggleAudit(key, params) {
  if (auditKey.value === key) { auditKey.value = null; return }
  auditKey.value = key
  auditSegments.value = []
  auditLoading.value = true
  try {
    const res = await axios.get(route('pbx.shift-reports.audit'), { params })
    auditSegments.value = res.data.segments ?? []
    auditWindow.value = res.data.window ?? { from: null, to: null }
  } catch (e) {}
  auditLoading.value = false
}
function formatAuditTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}
function segmentStyle(s) {
  const from = new Date(auditWindow.value.from).getTime()
  const to   = new Date(auditWindow.value.to).getTime()
  const total = to - from
  if (!(total > 0)) return { display: 'none' }
  const left  = (new Date(s.start).getTime() - from) / total * 100
  const width = (new Date(s.end).getTime() - new Date(s.start).getTime()) / total * 100
  return {
    left: Math.max(0, Math.min(100, left)) + '%',
    width: Math.max(0.2, Math.min(100, width)) + '%',
    background: TIMELINE_COLORS[s.status] ?? '#d1d5db',
  }
}
function segmentTitle(s) {
  const from = new Date(s.start).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  const to   = new Date(s.end).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
  const base = `${TIMELINE_LABELS[s.status] ?? s.status}: ${from} – ${to} (${s.seconds}с)`
  return s.counted ? base : base + ' — не засчитано (шум)'
}
async function toggleShiftReport(r) {
  if (expandedShiftId.value === r.id) {
    expandedShiftId.value = null
    shiftDetail.value = null
    return
  }
  expandedShiftId.value = r.id
  shiftDetail.value = null
  shiftDetailLoading.value = true
  try {
    const res = await axios.get(route('pbx.shift-reports.show', r.id))
    shiftDetail.value = res.data
  } catch (e) {}
  shiftDetailLoading.value = false
}
function formatShiftDate(date) {
  return new Date(date).toLocaleDateString('ru-RU', { day: '2-digit', month: 'long', year: 'numeric', weekday: 'long' })
}
function formatDur(secs) {
  if (secs === null || secs === undefined) return '—'
  secs = Math.round(secs)
  if (secs < 60) return secs + 'с'
  const h = Math.floor(secs / 3600)
  const m = Math.floor((secs % 3600) / 60)
  return h > 0 ? `${h}ч ${m}м` : `${m}м`
}

// Список смен ("1 смена 08:00-14:30" и т.п.) — общий для всех очередей,
// queue-параметр тут не нужен.
async function loadShiftDefs() {
  try {
    const res = await axios.get(route('pbx.shift-definitions.index'))
    shiftDefs.value = res.data.filter(d => d.is_active)
  } catch (e) {}
}
function toggleShiftSettings() {
  showShiftSettings.value = !showShiftSettings.value
  if (showShiftSettings.value && !shiftDefs.value.length) loadShiftDefs()
}
async function saveShiftDef(d) {
  try {
    await axios.put(route('pbx.shift-definitions.update', d.id), {
      name: d.name, start_time: d.start_time, end_time: d.end_time, sort_order: d.sort_order,
    })
  } catch (e) {
    alert('Не удалось сохранить смену' + (e.response?.status === 403 ? ' -- нет прав' : ''))
  }
}
async function deleteShiftDef(d) {
  if (!confirm(`Удалить смену «${d.name}»?`)) return
  try {
    await axios.delete(route('pbx.shift-definitions.destroy', d.id))
    shiftDefs.value = shiftDefs.value.filter(x => x.id !== d.id)
  } catch (e) {
    alert('Не удалось удалить смену' + (e.response?.status === 403 ? ' -- нет прав' : ''))
  }
}
async function addShiftDef() {
  if (!newShiftDef.value.name || !newShiftDef.value.start_time || !newShiftDef.value.end_time) return
  try {
    const res = await axios.post(route('pbx.shift-definitions.store'), newShiftDef.value)
    shiftDefs.value.push(res.data)
    newShiftDef.value = { name: '', start_time: '', end_time: '' }
  } catch (e) {
    alert('Не удалось добавить смену' + (e.response?.status === 403 ? ' -- нет прав' : ''))
  }
}

watch(subTab, val => {
  if (val === 'shifts' && !shiftsLoadedOnce) {
    shiftsLoadedOnce = true
    loadShiftReports()
    loadCurrentShift()
  }
  if (val === 'queue') loadQueue()
})
watch(qHistory, async () => { await nextTick(); renderChart() }, { deep: true })
watch(qTimeline, async () => { await nextTick(); renderTimeline() }, { deep: true })

onMounted(() => {
  loadQueue()
  qRefreshTimer = setInterval(() => {
    if (subTab.value === 'queue') loadQueue()
  }, 15000)
  shiftRefreshTimer = setInterval(() => {
    if (subTab.value === 'shifts') loadCurrentShift()
  }, 20000)
})
onUnmounted(() => {
  clearInterval(qRefreshTimer)
  clearInterval(shiftRefreshTimer)
  if (qChart) qChart.destroy()
  if (qTimelineChart) qTimelineChart.destroy()
})
</script>
