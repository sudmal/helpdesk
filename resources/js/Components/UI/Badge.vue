<template>
  <span :class="['inline-flex items-center gap-1 font-medium rounded-full',
                  small ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs']"
        :style="{
          backgroundColor: color + '25',
          color: '#111827',
          border: `1px solid ${color}50`
        }">
    <span v-if="dot" class="w-1.5 h-1.5 rounded-full shrink-0"
          :style="{ backgroundColor: color }" />
    {{ label }}
    <slot />
  </span>
</template>

<script setup>
defineProps({
  color: { type: String, default: '#6366f1' },
  label: { type: String, default: '' },
  dot:   { type: Boolean, default: true },
  small: { type: Boolean, default: false },
})
</script>
