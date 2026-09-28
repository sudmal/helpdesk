<template>
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-2.5 flex items-center gap-3">
    <div class="flex items-center gap-1.5 shrink-0" :title="trunkTitle(latest)">
      <span :class="['w-2.5 h-2.5 rounded-full flex-shrink-0', trunkDotClass(latest)]"></span>
      <span class="text-xs text-gray-500 font-medium whitespace-nowrap">PHOENIX SIP</span>
    </div>
    <button @click="sendCmd('fix_dialing')" :disabled="cmdSending !== null"
            :class="['flex items-center justify-center w-6 h-6 rounded-full border-2 transition-colors flex-shrink-0 text-xs font-bold leading-none',
                     cmdSending === 'fix_dialing' ? 'bg-orange-500 border-orange-500 text-white' : 'bg-white border-orange-400 text-orange-600 hover:bg-orange-100']"
            title="Починить дозвон (Техподдержка + Абонотдел): pjsip reload + dialplan reload + queue reload + qualify all">
      {{ cmdSending === 'fix_dialing' ? '…' : '↻' }}
    </button>
    <div class="flex-1 min-w-0" style="max-width: 320px">
      <canvas ref="sparkCanvas" height="44"></canvas>
    </div>
    <span class="text-xs text-gray-400 whitespace-nowrap">
      {{ latest?.recorded_at ? 'Обновлено: ' + formatTime(latest.recorded_at) : 'Нет данных' }}
    </span>
  </div>
</template>

<script setup>
// Статус SIP-провайдера (транк PHOENIX) — сущность, общая для ОБЕИХ очередей
// (техподдержки и Абонотдела): это про физический канал до оператора связи,
// не про то, какая именно очередь его опросила. Поэтому живёт одним общим
// виджетом в "Журнале звонков" (2026-09-28), а не дублируется на вкладках
// каждой очереди, как было раньше. Раньше эта полоска рисовалась НАД
// Chart.js-таймлайном операторов конкретной очереди — здесь такого
// таймлайна нет, поэтому своя маленькая независимая canvas-полоска
// (без Chart.js — не нужен для одной горизонтальной линии + подписи часов).
//
// Кнопка "Почини дозвон" тоже сюда переехала (2026-09-28) — действия под
// ней (pjsip/dialplan/queue reload, qualify all) PBX-глобальные, не про
// одну конкретную очередь (см. PbxController::triggerCmd — без queue шлёт
// команду сразу пуллерам обеих очередей).
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const latest  = ref(null)
const history = ref([])
const cmdSending = ref(null)
let timer = null

function isTrunkDown(s) { return s === 'Unavail' || s === 'Unreachable' }
function trunkTitle(row) {
  const s = row?.trunk_status
  let head = 'PHOENIX SIP: нет данных'
  if (s === 'Avail')  head = `PHOENIX SIP: подключён, потери до SIP-сервера ${row.trunk_loss_pct ?? 0}%`
  if (isTrunkDown(s))  head = 'PHOENIX SIP: недоступен (нет ответа на проверку связи)'
  return head
}
function formatTime(val) {
  return new Date(val).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}
async function sendCmd(cmd) {
  cmdSending.value = cmd
  try {
    await axios.post(route('pbx.trigger-cmd'), { cmd })
  } catch (e) {}
  setTimeout(() => { cmdSending.value = null }, 800)
}

// Цвет по замеру — та же шкала, что раньше была на большом графике: 0 --
// зелёный, дальше жёлтый/оранжевый/красный по нарастанию потерь; "не в
// норме" (нет регистрации) -- серый; совсем нет данных -- светло-серый.
// endpoint pbx.trunk-history не отдаёт разбивку по шлюзу/SIP/Феникс (это
// упрощённый общий индикатор).
function rowColor(row) {
  if (!row.trunk_status) return '#e5e7eb'
  if (isTrunkDown(row.trunk_status)) return '#9ca3af'
  if (row.trunk_status !== 'Avail')  return '#d1d5db'
  const loss = row.trunk_loss_pct ?? 0
  if (loss <= 0)  return '#22c55e'
  if (loss <= 10) return '#eab308'
  if (loss <= 30) return '#f97316'
  return '#ef4444'
}
function dotClassFor(hex) {
  return {
    '#e5e7eb': 'bg-gray-200',
    '#9ca3af': 'bg-gray-400',
    '#d1d5db': 'bg-gray-300',
    '#22c55e': 'bg-green-400',
    '#eab308': 'bg-yellow-400',
    '#f97316': 'bg-orange-400',
    '#ef4444': 'bg-red-400',
  }[hex] ?? 'bg-gray-300'
}
function trunkDotClass(row) {
  if (!row) return 'bg-gray-200'
  return dotClassFor(rowColor(row))
}

const BAR_H = 22   // высота цветной полоски
const LABEL_H = 14 // высота подписей времени под ней

const sparkCanvas = ref(null)
function renderSpark() {
  const canvas = sparkCanvas.value
  if (!canvas || !history.value.length) return
  const ctx = canvas.getContext('2d')
  const w = canvas.clientWidth || 320
  const h = BAR_H + LABEL_H
  const dpr = window.devicePixelRatio || 1
  canvas.width = w * dpr
  canvas.height = h * dpr
  canvas.style.height = h + 'px'
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0)
  ctx.clearRect(0, 0, w, h)

  const rows = history.value
  const t0 = new Date(rows[0].recorded_at).getTime()
  const t1 = new Date(rows[rows.length - 1].recorded_at).getTime()
  const span = Math.max(1, t1 - t0)
  const xOf = t => ((t - t0) / span) * w

  // Цветная полоска
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i]
    const x1 = xOf(new Date(row.recorded_at).getTime())
    const nextT = i + 1 < rows.length ? new Date(rows[i + 1].recorded_at).getTime() : t1
    const x2 = xOf(nextT)
    if (x2 <= x1) continue
    ctx.fillStyle = rowColor(row)
    ctx.fillRect(x1, 0, Math.max(1, x2 - x1), BAR_H)
  }

  // Тайминг: подписи на ровных часовых границах (как на больших графиках) —
  // без этого не понять, что вообще за период показан.
  const start = new Date(t0)
  start.setMinutes(0, 0, 0)
  if (start.getTime() < t0) start.setHours(start.getHours() + 1)
  const ticks = []
  for (let t = start.getTime(); t <= t1; t += 3600000) ticks.push(t)
  const minGapPx = 40
  let lastX = -Infinity
  ctx.font = '10px system-ui, sans-serif'
  ctx.fillStyle = '#9ca3af'
  ctx.textBaseline = 'top'
  for (const t of ticks) {
    const x = xOf(t)
    if (x - lastX < minGapPx) continue
    lastX = x
    const label = new Date(t).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
    const tw = ctx.measureText(label).width
    const tx = Math.min(Math.max(0, x - tw / 2), w - tw)
    ctx.strokeStyle = '#e5e7eb'
    ctx.beginPath(); ctx.moveTo(x, BAR_H); ctx.lineTo(x, BAR_H + 3); ctx.stroke()
    ctx.fillText(label, tx, BAR_H + 3)
  }
}

async function load() {
  try {
    const res = await fetch(route('pbx.trunk-history') + '?hours=3')
    const data = await res.json()
    latest.value  = data.latest
    history.value = data.history ?? []
    requestAnimationFrame(renderSpark)
  } catch (e) {}
}

onMounted(() => {
  load()
  timer = setInterval(load, 20000)
  window.addEventListener('resize', renderSpark)
})
onUnmounted(() => {
  clearInterval(timer)
  window.removeEventListener('resize', renderSpark)
})
</script>
