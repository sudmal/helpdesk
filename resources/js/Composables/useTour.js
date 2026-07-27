import { reactive } from 'vue'
import axios from 'axios'

// Единый движок для интерактивных обучалок (coach-mark туров).
// Состояние — модульный singleton, поэтому один <TourOverlay/>, смонтированный
// в AppLayout, обслуживает туры, запущенные с любой страницы.

const state = reactive({
  active: false,
  key: null,
  steps: [],
  index: 0,
  dontShowAgain: true,
})

// Последний набор шагов на каждый ключ — нужен для повторного запуска
// обучения вручную (кнопка "🎓" в шапке), даже если тур уже был пройден.
const registry = {}

function register(key, steps) {
  registry[key] = steps
}

function markSeen(key) {
  if (!key) return
  axios.post(`/onboarding/${key}/seen`).catch(() => { /* не критично, тур просто покажется снова */ })
}

function start(key, steps) {
  registry[key] = steps
  state.key = key
  state.steps = steps
  state.index = 0
  state.dontShowAgain = true
  state.active = true
}

function replay(key) {
  const steps = registry[key]
  if (steps?.length) start(key, steps)
}

function next() {
  if (state.index < state.steps.length - 1) {
    state.index++
  } else {
    finish()
  }
}

function prev() {
  if (state.index > 0) state.index--
}

function skip() {
  if (state.dontShowAgain) markSeen(state.key)
  state.active = false
}

function finish() {
  markSeen(state.key)
  state.active = false
}

export function useTour() {
  return { state, register, start, replay, next, prev, skip, finish }
}

export function hasSeenTour(user, key) {
  return !!(user?.onboarding_seen ?? []).includes(key)
}
