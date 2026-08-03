<template>
  <div v-if="pages > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
    <span>Стр. {{ modelValue }} из {{ pages }} ({{ total }} записей)</span>
    <div class="flex gap-1">
      <button @click="$emit('update:modelValue', Math.max(1, modelValue - 1))"
              :disabled="modelValue === 1" class="px-2.5 py-1 border rounded-lg disabled:opacity-30 hover:bg-gray-50">‹</button>
      <button @click="$emit('update:modelValue', Math.min(pages, modelValue + 1))"
              :disabled="modelValue === pages" class="px-2.5 py-1 border rounded-lg disabled:opacity-30 hover:bg-gray-50">›</button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  total: { type: Number, required: true },
  perPage: { type: Number, required: true },
  modelValue: { type: Number, required: true },
})
defineEmits(['update:modelValue'])

const pages = computed(() => Math.ceil(props.total / props.perPage))
</script>
