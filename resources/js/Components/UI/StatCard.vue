<template>
  <div class="bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4">
    <div :class="['w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0', bgClass]">
      {{ icon }}
    </div>
    <div>
      <p class="text-2xl font-bold text-gray-800">{{ value }}</p>
      <p class="text-sm text-gray-500">{{ label }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  icon:    { type: String, required: true },
  value:   { type: [Number, String], required: true },
  label:   { type: String, required: true },
  bgClass: { type: String, default: 'bg-blue-100 text-blue-600' },
})
</script>
