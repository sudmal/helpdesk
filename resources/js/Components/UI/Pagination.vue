<template>
  <div v-if="pages > 1"
       class="px-4 py-3 border-t border-gray-200 bg-gray-50 rounded-b-2xl flex items-center justify-between text-sm">
    <span class="font-medium text-gray-600">Стр. {{ modelValue }} из {{ pages }} <span class="text-gray-400 font-normal">({{ total }} записей)</span></span>
    <div class="flex gap-2">
      <button @click="$emit('update:modelValue', Math.max(1, modelValue - 1))"
              :disabled="modelValue === 1"
              class="px-3 py-1.5 border border-gray-300 rounded-lg font-medium text-gray-700 bg-white
                     disabled:opacity-30 disabled:cursor-not-allowed hover:bg-blue-50 hover:border-blue-400 hover:text-blue-700 transition-colors">
        ‹ Назад
      </button>
      <button @click="$emit('update:modelValue', Math.min(pages, modelValue + 1))"
              :disabled="modelValue === pages"
              class="px-3 py-1.5 border border-gray-300 rounded-lg font-medium text-gray-700 bg-white
                     disabled:opacity-30 disabled:cursor-not-allowed hover:bg-blue-50 hover:border-blue-400 hover:text-blue-700 transition-colors">
        Далее ›
      </button>
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
