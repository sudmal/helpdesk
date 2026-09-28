<template>
  <div class="bg-white rounded-xl border border-gray-200 px-4 py-2.5 flex items-center gap-3">
    <div class="flex items-center gap-1.5 shrink-0" :title="trunkTitle(latest)">
      <span :class="['w-2.5 h-2.5 rounded-full flex-shrink-0', trunkDotClass(trunkStatus, latest?.probe)]"></span>
      <span class="text-xs text-gray-500 font-medium whitespace-nowrap">PHOENIX SIP</span>
    </div>
    <div class="h-8 flex-1 min-w-0" style="max-width: 260px">
      <canvas ref="sparkCanvas" height="32"></canvas>
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
// (без Chart.js — не нужен для одной горизонтальной линии).
import { ref, onMounted, onUnmounted } from 'vue'

const latest  = ref(null)
const history = ref([])
let timer = null

const PROBE_HOSTS = [['gw', 'Шлюз оператора'], ['sip', 'SIP-сервер оператора'], ['phx', 'Коммутатор Феникс']]
const trunkStatus = () => latest.value?.trunk_status ?? null
function isTrunkDown(s) { return s === 'Unavail' || s === 'Unreachable' }
function probeHasLoss(p) {
  return !!p && PROBE_HOSTS.some(([k]) => (p[k]?.loss_last ?? 0) > 0)
}
function trunkDotClass(s, probe) {
  if (isTrunkDown(s))      return 'bg-red-400'
  if (probeHasLoss(probe)) return 'bg-yellow-400'
  if (s === 'Avail')       return 'bg-green-400'
  return 'bg-gray-300'
}
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

// Цвет одного замера — та же шкала потерь, что раньше была на большом
// графике: 0 -- зелёный, дальше жёлтый/оранжевый/красный, "не в норме" --
// серый ("нет регистрации").
function rowColor(row) {
  if (isTrunkDown(row.trunk_status)) return '#9ca3af'
  if (row.trunk_status !== 'Avail')  return '#d1d5db'
  const loss = row.trunk_loss_pct ?? 0
  if (loss <= 0)  return '#22c55e'
  if (loss <= 10) return '#eab308'
  if (loss <= 30) return '#f97316'
  return '#ef4444'
}

const sparkCanvas = ref(null)
function renderSpark() {
  const canvas = sparkCanvas.value
  if (!canvas || !history.value.length) return
  const ctx = canvas.getContext('2d')
  const w = canvas.clientWidth || 260
  const h = 32
  canvas.width = w
  canvas.height = h
  ctx.clearRect(0, 0, w, h)

  const rows = history.value
  const t0 = new Date(rows[0].recorded_at).getTime()
  const t1 = new Date(rows[rows.length - 1].recorded_at).getTime()
  const span = Math.max(1, t1 - t0)

  for (let i = 0; i < rows.length; i++) {
    const row = rows[i]
    const x1 = ((new Date(row.recorded_at).getTime() - t0) / span) * w
    const nextT = i + 1 < rows.length ? new Date(rows[i + 1].recorded_at).getTime() : t1
    const x2 = ((nextT - t0) / span) * w
    if (x2 <= x1) continue
    ctx.fillStyle = rowColor(row)
    ctx.fillRect(x1, 0, Math.max(1, x2 - x1), h)
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
})
onUnmounted(() => clearInterval(timer))
</script>
