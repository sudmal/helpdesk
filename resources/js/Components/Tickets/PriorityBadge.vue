<template>
  <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', config.class]">
    {{ config.label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  priority: { type: String, default: 'normal' },
})

const map = {
  low:    { label: 'Низкий',  class: 'bg-gray-100 text-gray-600' },
  normal: { label: 'Обычный', class: 'bg-blue-100 text-blue-700' },
  high:   { label: 'Высокий', class: 'bg-amber-100 text-amber-700' },
  urgent: { label: 'Срочный', class: 'bg-red-100 text-red-700' },
}

const config = computed(() => map[props.priority] ?? map.normal)
</script>
