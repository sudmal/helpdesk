<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />
      <div :class="['relative bg-white rounded-2xl shadow-xl w-full max-h-[90vh] overflow-y-auto', sizeClass]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800">{{ title }}</h3>
          <button @click="$emit('close')"
                  class="text-gray-400 hover:text-gray-600 text-lg leading-none">✕</button>
        </div>
        <div class="px-6 py-4">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: String,
  size:  { type: String, default: 'md' },
})
defineEmits(['close'])

const sizeClass = computed(() => ({
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
}[props.size] ?? 'max-w-lg'))
</script>
