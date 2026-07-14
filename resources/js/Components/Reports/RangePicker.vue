<template>
  <div class="flex flex-wrap items-center gap-3 mb-4">
    <div class="flex bg-gray-100 rounded-xl p-1 gap-0.5">
      <button v-for="m in periodModes" :key="m.key" @click="range.setMode(m.key)"
              :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                       range.state.periodMode === m.key
                         ? 'bg-white shadow text-gray-800'
                         : 'text-gray-500 hover:text-gray-700']">
        {{ m.label }}
      </button>
    </div>

    <template v-if="range.state.periodMode === 'day'">
      <input type="date" v-model="range.state.singleDay" @change="range.applyDay"
             class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
    </template>

    <template v-if="range.state.periodMode === 'period'">
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-500">С</label>
        <input type="date" v-model="range.state.localFrom"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <div class="flex items-center gap-2">
        <label class="text-sm text-gray-500">По</label>
        <input type="date" v-model="range.state.localTo"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>
      <button @click="range.applyPeriod"
              class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition-colors">
        Применить
      </button>
    </template>

    <span v-if="range.state.loading" class="text-xs text-gray-400">Загрузка…</span>
  </div>
</template>

<script setup>
defineProps({ range: { type: Object, required: true } })

const periodModes = [
  { key: 'day',     label: 'День' },
  { key: 'week',    label: 'Неделя' },
  { key: 'month',   label: 'Месяц' },
  { key: 'quarter', label: 'Квартал' },
  { key: 'period',  label: 'Период' },
]
</script>
