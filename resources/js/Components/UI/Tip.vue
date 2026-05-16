<template>
  <span ref="wrap" class="tip-wrap" @mouseenter="show = true" @mouseleave="show = false">
    <span class="tip-icon">?</span>
    <Teleport to="body">
      <div v-if="show" class="tip-popup" :style="pos">
        <slot />
      </div>
    </Teleport>
  </span>
</template>

<script setup>
import { ref, computed } from 'vue'
const wrap = ref(null)
const show = ref(false)
const pos  = computed(() => {
  if (!wrap.value) return {}
  const r = wrap.value.getBoundingClientRect()
  return { top: (r.bottom + 6) + 'px', left: r.left + 'px' }
})
</script>

<style>
.tip-popup {
  position: fixed; z-index: 9999; width: 220px;
  background: #1f2937; color: #f9fafb; font-size: 11px;
  line-height: 1.55; border-radius: 8px; padding: 8px 10px;
  box-shadow: 0 4px 14px rgba(0,0,0,.22); pointer-events: none;
}
</style>
<style scoped>
.tip-wrap { display: inline-flex; vertical-align: middle; cursor: help; }
.tip-icon {
  width: 15px; height: 15px; border-radius: 50%;
  background: #3b82f6; color: #fff; font-size: 10px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; user-select: none;
}
</style>