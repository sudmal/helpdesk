<template>
  <div class="grid grid-cols-2 gap-2">
    <div>
      <label class="block text-xs text-gray-500 mb-1">Дата *</label>
      <input v-model="localDate" type="date"
             :min="minDate"
             class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm
                    focus:outline-none focus:ring-2 focus:ring-blue-500/30"
             @change="emitValue" />
    </div>
    <div>
      <label class="block text-xs text-gray-500 mb-1">Время *</label>
      <select v-model="localTime"
              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm
                     focus:outline-none focus:ring-2 focus:ring-blue-500/30"
              @change="emitValue">
        <option value="">— Выбрать —</option>
        <option v-for="slot in timeSlots" :key="slot" :value="slot">{{ slot }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue:   { type: String, default: '' },
  workStart:    { type: String, default: '09:00' },
  workEnd:      { type: String, default: '17:00' },
  stepMinutes:  { type: Number, default: 30 },
})

const emit = defineEmits(['update:modelValue'])

// Парсим входное значение
const localDate = ref('')
const localTime = ref('')

watch(() => props.modelValue, (val) => {
  if (val) {
    const parts = val.split('T')
    if (parts.length === 2) {
      localDate.value = parts[0]
      localTime.value = parts[1].slice(0, 5)
    }
  }
}, { immediate: true })

// Генерируем слоты времени
const timeSlots = computed(() => {
  const slots = []
  const [startH, startM] = props.workStart.split(':').map(Number)
  const [endH, endM]     = props.workEnd.split(':').map(Number)
  const startMins = startH * 60 + startM
  const endMins   = endH * 60 + endM

  for (let m = startMins; m <= endMins; m += props.stepMinutes) {
    const h   = String(Math.floor(m / 60)).padStart(2, '0')
    const min = String(m % 60).padStart(2, '0')
    slots.push(`${h}:${min}`)
  }
  return slots
})

const minDate = computed(() => new Date().toISOString().split('T')[0])

function emitValue() {
  if (localDate.value && localTime.value) {
    emit('update:modelValue', `${localDate.value}T${localTime.value}`)
  }
}
</script>
