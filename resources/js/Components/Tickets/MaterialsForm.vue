<template>
  <div class="mt-4 border-t border-gray-200 pt-4">
    <h4 class="text-sm font-semibold text-gray-700 mb-3">📦 Расходные материалы</h4>

    <!-- Строки материалов -->
    <div class="space-y-2 mb-3">
      <div v-for="(item, idx) in items" :key="idx"
           class="flex gap-2 items-center">
        <!-- Выбор материала -->
        <select v-model="item.material_id" @change="onMaterialChange(idx)"
                class="flex-1 border border-gray-200 rounded-lg px-2 py-1.5 text-sm
                       focus:outline-none focus:ring-2 focus:ring-blue-500/30">
          <option value="">— Материал —</option>
          <option v-for="m in materials" :key="m.id" :value="m.id">
            {{ m.code ? '[' + m.code + '] ' : '' }}{{ m.name }} — {{ m.price }} ₽/{{ m.unit }}
          </option>
        </select>
        <!-- Количество -->
        <input v-model.number="item.quantity" type="number" min="0"
               placeholder="Кол-во"
               class="w-24 border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center
                      focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        <!-- Цена -->
        <div class="w-24 text-sm text-gray-500 text-right tabular-nums">
          {{ itemTotal(item) }} ₽
        </div>
        <!-- Удалить -->
        <button type="button" @click="remove(idx)"
                class="text-gray-300 hover:text-red-500 transition-colors text-lg leading-none">✕</button>
      </div>
    </div>

    <!-- Итого + кнопка добавить -->
    <div class="flex items-center justify-between">
      <button type="button" @click="addRow"
              class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
        + Добавить строку
      </button>
      <div v-if="total > 0" class="text-sm font-semibold text-gray-700">
        Итого: <span class="text-blue-600">{{ total.toFixed(2) }} ₽</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  materials: { type: Array, default: () => [] },
  modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const items = ref(props.modelValue.length ? props.modelValue : [{ material_id: '', quantity: 1 }])

function addRow() {
  items.value.push({ material_id: '', quantity: 1 })
  emit('update:modelValue', items.value)
}

function remove(idx) {
  items.value.splice(idx, 1)
  if (!items.value.length) addRow()
  emit('update:modelValue', items.value)
}

function onMaterialChange(idx) {
  emit('update:modelValue', items.value)
}

function itemTotal(item) {
  if (!item.material_id || !item.quantity) return '0.00'
  const mat = props.materials.find(m => m.id == item.material_id)
  if (!mat) return '0.00'
  return (mat.price * item.quantity).toFixed(2)
}

const total = computed(() => {
  return items.value.reduce((sum, item) => {
    const mat = props.materials.find(m => m.id == item.material_id)
    if (!mat || !item.quantity) return sum
    return sum + mat.price * item.quantity
  }, 0)
})
</script>