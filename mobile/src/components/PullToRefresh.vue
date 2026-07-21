<template>
  <div ref="containerRef" class="relative overflow-y-auto h-full" @scroll="onScroll"
       @touchstart="onTouchStart" @touchmove="onTouchMove" @touchend="onTouchEnd">
    <div class="flex items-center justify-center overflow-hidden transition-[height]"
         :style="{ height: pullHeight + 'px' }">
      <svg v-if="pullHeight > 0" class="w-5 h-5 text-[#3B82F6]" :class="{ 'animate-spin': refreshing }"
           viewBox="0 0 24 24" fill="none" :style="!refreshing ? { transform: `rotate(${pullProgress * 360}deg)` } : {}">
        <path d="M21 12a9 9 0 11-2.64-6.36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>
    <slot />
  </div>
</template>

<script setup>
import { ref } from 'vue'

const emit = defineEmits(['refresh'])
const containerRef = ref(null)
const pullHeight = ref(0)
const refreshing = ref(false)
const pullProgress = ref(0)

const THRESHOLD = 64
let startY = 0
let pulling = false
let atTop = true

function onScroll() {
  atTop = containerRef.value.scrollTop <= 0
}

function onTouchStart(e) {
  if (!atTop || refreshing.value) return
  startY = e.touches[0].clientY
  pulling = true
}

function onTouchMove(e) {
  if (!pulling) return
  const dy = e.touches[0].clientY - startY
  if (dy > 0 && atTop) {
    pullHeight.value = Math.min(dy * 0.5, 90)
    pullProgress.value = Math.min(pullHeight.value / THRESHOLD, 1)
  }
}

async function onTouchEnd() {
  if (!pulling) return
  pulling = false
  if (pullHeight.value >= THRESHOLD) {
    refreshing.value = true
    pullHeight.value = 48
    await Promise.resolve(emit('refresh'))
    refreshing.value = false
  }
  pullHeight.value = 0
  pullProgress.value = 0
}
</script>
