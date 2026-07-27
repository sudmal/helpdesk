<template>
  <Teleport to="body">
    <div v-if="state.active" class="tour-root" role="dialog" aria-modal="true">

      <!-- Четыре затемняющих панели вокруг подсвеченного элемента —
           одновременно и дим, и блокировка кликов по остальной странице.
           Область самого элемента ничем не перекрыта. -->
      <div class="tour-dim" :style="dimTop"></div>
      <div class="tour-dim" :style="dimBottom"></div>
      <div class="tour-dim" :style="dimLeft"></div>
      <div class="tour-dim" :style="dimRight"></div>

      <!-- Подсвечивающая рамка вокруг элемента -->
      <div v-if="rect" class="tour-ring" :style="ringStyle"></div>

      <!-- Карточка с описанием -->
      <div class="tour-card" :style="cardStyle" ref="cardEl">
        <div class="tour-progress">Шаг {{ state.index + 1 }} из {{ state.steps.length }}</div>
        <h4 class="tour-title">{{ currentStep?.title }}</h4>
        <p class="tour-text">{{ currentStep?.text }}</p>

        <div class="tour-footer">
          <label class="tour-check">
            <input type="checkbox" v-model="state.dontShowAgain" />
            Больше не показывать
          </label>
          <div class="tour-buttons">
            <button v-if="state.index > 0" type="button" class="tour-btn ghost" @click="tour.prev()">Назад</button>
            <button type="button" class="tour-btn ghost" @click="tour.skip()">Пропустить</button>
            <button type="button" class="tour-btn primary" @click="advance">
              {{ isLast ? 'Готово' : 'Далее' }}
            </button>
          </div>
        </div>
      </div>

    </div>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useTour } from '@/Composables/useTour'

const tour  = useTour()
const state = tour.state

const rect     = ref(null)
const cardEl   = ref(null)
const cardSize = ref({ width: 320, height: 160 })

const currentStep = computed(() => state.steps[state.index] ?? null)
const isLast       = computed(() => state.index === state.steps.length - 1)

const prefersReducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false

function advance() {
  if (isLast.value) tour.finish()
  else tour.next()
}

// Ищем целевой элемент; если он ещё не отрисован (например список только
// грузится) — пробуем несколько раз, иначе тихо пропускаем шаг, чтобы тур
// не завис на пустом месте.
let retryTimer = null
let retriesLeft = 0

function locate() {
  clearTimeout(retryTimer)
  const step = currentStep.value
  if (!step) { rect.value = null; return }

  const el = document.querySelector(step.selector)
  if (el) {
    if (typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ block: 'center', inline: 'nearest', behavior: prefersReducedMotion ? 'auto' : 'smooth' })
    }
    retriesLeft = 0
    // небольшая задержка, чтобы scrollIntoView успел прокрутить страницу
    // перед тем, как мы посчитаем координаты
    setTimeout(updateRect, prefersReducedMotion ? 0 : 260)
  } else if (retriesLeft > 0) {
    retriesLeft--
    retryTimer = setTimeout(locate, 200)
  } else {
    // элемента нет на странице (нет прав, пустой список и т.п.) — пропускаем шаг
    if (state.index < state.steps.length - 1) tour.next()
    else tour.finish()
  }
}

function updateRect() {
  const step = currentStep.value
  if (!step) return
  const el = document.querySelector(step.selector)
  if (!el) { rect.value = null; return }
  const r = el.getBoundingClientRect()
  rect.value = { top: r.top, left: r.left, width: r.width, height: r.height, bottom: r.bottom, right: r.right }
  nextTick(() => {
    if (cardEl.value) {
      cardSize.value = { width: cardEl.value.offsetWidth, height: cardEl.value.offsetHeight }
    }
  })
}

watch(() => [state.active, state.index], () => {
  if (state.active) {
    retriesLeft = 15
    locate()
  } else {
    rect.value = null
  }
}, { immediate: true })

function onViewportChange() {
  if (state.active) updateRect()
}

onMounted(() => {
  window.addEventListener('resize', onViewportChange, { passive: true })
  window.addEventListener('scroll', onViewportChange, { passive: true, capture: true })
  window.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
  window.removeEventListener('resize', onViewportChange)
  window.removeEventListener('scroll', onViewportChange, { capture: true })
  window.removeEventListener('keydown', onKeydown)
  clearTimeout(retryTimer)
})

function onKeydown(e) {
  if (!state.active) return
  if (e.key === 'Escape') tour.skip()
  else if (e.key === 'ArrowRight') advance()
  else if (e.key === 'ArrowLeft' && state.index > 0) tour.prev()
}

const PAD = 8

const dimTop = computed(() => {
  const r = rect.value
  const h = r ? Math.max(r.top - PAD, 0) : '100%'
  return { top: 0, left: 0, right: 0, height: typeof h === 'number' ? h + 'px' : h }
})
const dimBottom = computed(() => {
  const r = rect.value
  if (!r) return { top: '100%', left: 0, right: 0, bottom: 0 }
  return { top: (r.bottom + PAD) + 'px', left: 0, right: 0, bottom: 0 }
})
const dimLeft = computed(() => {
  const r = rect.value
  if (!r) return { top: 0, left: 0, width: 0, height: 0 }
  return { top: (r.top - PAD) + 'px', left: 0, width: Math.max(r.left - PAD, 0) + 'px', height: (r.height + PAD * 2) + 'px' }
})
const dimRight = computed(() => {
  const r = rect.value
  if (!r) return { top: 0, right: 0, width: 0, height: 0 }
  return { top: (r.top - PAD) + 'px', left: (r.right + PAD) + 'px', right: 0, height: (r.height + PAD * 2) + 'px' }
})

const ringStyle = computed(() => {
  const r = rect.value
  if (!r) return {}
  return {
    top:    (r.top - PAD) + 'px',
    left:   (r.left - PAD) + 'px',
    width:  (r.width  + PAD * 2) + 'px',
    height: (r.height + PAD * 2) + 'px',
  }
})

// Карточку ставим под элементом, если снизу хватает места, иначе — сверху;
// по горизонтали прижимаем к левому краю элемента, не давая уехать за экран.
const cardStyle = computed(() => {
  const r = rect.value
  const vw = window.innerWidth
  const vh = window.innerHeight
  const { width: cw, height: ch } = cardSize.value
  const margin = 14

  if (!r) {
    return { top: (vh / 2 - ch / 2) + 'px', left: (vw / 2 - cw / 2) + 'px' }
  }

  const spaceBelow = vh - r.bottom
  const placeBelow = spaceBelow >= ch + margin * 2 || spaceBelow >= r.top

  let top = placeBelow ? r.bottom + PAD + margin : r.top - PAD - margin - ch
  top = Math.min(Math.max(top, 10), vh - ch - 10)

  let left = r.left
  left = Math.min(Math.max(left, 10), vw - cw - 10)

  return { top: top + 'px', left: left + 'px' }
})
</script>

<style scoped>
.tour-root { position: fixed; inset: 0; z-index: 9999; }
.tour-dim {
  position: fixed;
  background: rgba(15, 20, 30, .62);
  pointer-events: auto;
  transition: none;
}
.tour-ring {
  position: fixed;
  border-radius: 10px;
  border: 2px solid #2563eb;
  box-shadow: 0 0 0 4px rgba(37, 99, 235, .22), 0 4px 18px rgba(0,0,0,.25);
  pointer-events: none;
  transition: top .25s ease, left .25s ease, width .25s ease, height .25s ease;
}
@media (prefers-reduced-motion: reduce) {
  .tour-ring { transition: none; }
}
.tour-card {
  position: fixed;
  width: min(320px, calc(100vw - 20px));
  background: #ffffff;
  border-radius: 14px;
  box-shadow: 0 20px 50px -12px rgba(15, 23, 42, .35), 0 0 0 1px rgba(15, 23, 42, .04);
  padding: 14px 16px 12px;
  pointer-events: auto;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}
.tour-progress {
  font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
  color: #2563eb; margin-bottom: 4px;
}
.tour-title { margin: 0 0 6px; font-size: 15px; font-weight: 700; color: #111827; line-height: 1.3; }
.tour-text  { margin: 0; font-size: 13px; color: #4b5563; line-height: 1.5; }
.tour-footer {
  margin-top: 12px; padding-top: 10px; border-top: 1px solid #f0f1f4;
  display: flex; flex-direction: column; gap: 8px;
}
.tour-check {
  display: flex; align-items: center; gap: 6px; font-size: 12px; color: #6b7280;
  cursor: pointer; user-select: none;
}
.tour-check input { width: 14px; height: 14px; accent-color: #2563eb; cursor: pointer; }
.tour-buttons { display: flex; justify-content: flex-end; gap: 8px; }
.tour-btn {
  font-size: 13px; font-weight: 600; padding: 6px 12px; border-radius: 8px; border: none;
  cursor: pointer; transition: background-color .15s, color .15s;
}
.tour-btn.ghost { background: transparent; color: #6b7280; }
.tour-btn.ghost:hover { background: #f3f4f6; color: #374151; }
.tour-btn.primary { background: #2563eb; color: #fff; }
.tour-btn.primary:hover { background: #1d4ed8; }
</style>
