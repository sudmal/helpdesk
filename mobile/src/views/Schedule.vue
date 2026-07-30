<template>
  <div class="h-screen flex flex-col" style="background:#121212">
    <div class="shrink-0 px-3 py-3 flex items-center gap-2" style="background:#1D4ED8">
      <button @click="$router.push({ name: 'dashboard' })" class="text-white w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <div class="text-white text-lg font-bold leading-tight">Расписание выходов</div>
        <div class="text-white/55 text-[10px] leading-tight">{{ brigadeName }}</div>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto p-3 space-y-4">
      <div v-if="loading" class="flex justify-center py-10">
        <svg class="w-6 h-6 text-[#3B82F6] animate-spin" viewBox="0 0 24 24" fill="none">
          <path d="M21 12a9 9 0 11-2.64-6.36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </div>

      <div v-else-if="error" class="flex flex-col items-center justify-center py-16 text-[#888888]">
        <span class="text-sm">{{ error }}</span>
      </div>

      <div v-for="m in months" :key="m.month">
        <div class="text-white font-bold text-sm mb-2">{{ monthTitle(m.month) }}</div>
        <div class="overflow-x-auto rounded-lg border border-white/10">
          <table class="border-collapse" style="min-width: max-content">
            <thead>
              <tr>
                <th class="sched-name-col bg-[#1A1A1A] px-2 py-1 sticky left-0"></th>
                <th v-for="d in m.days" :key="d.date"
                    class="text-center w-7 min-w-[28px] px-0.5 py-1"
                    :class="d.date === today ? 'bg-[#FACC15]' : 'bg-[#1A1A1A]'">
                  <div class="text-[11px] font-bold" :class="d.date === today ? 'text-[#1E293B]' : 'text-[#E0E0E0]'">{{ d.day }}</div>
                  <div class="text-[9px]" :class="d.date === today ? 'text-[#1E293B]' : dowColor(d)">{{ d.dow }}</div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="mem in m.members" :key="mem.id">
                <td class="sched-name-col px-2 py-1.5 text-[11px] whitespace-nowrap sticky left-0"
                    :class="mem.is_me ? 'bg-[#1F2937] text-white font-bold' : 'bg-[#1A1A1A] text-[#D1D5DB]'">
                  {{ mem.name }}
                </td>
                <td v-for="d in m.days" :key="d.date"
                    class="w-7 h-7 text-center border-t border-white/5"
                    :class="mem.is_me ? 'border-l border-r border-[#1F2937]' : ''"
                    :style="{ background: cellColor(d, mem.schedule) }"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const months = ref([])
const brigadeName = ref('')
const loading = ref(true)
const error = ref('')
const today = new Date().toISOString().slice(0, 10)

function dowColor(d) {
  if (d.isHoliday) return 'text-[#7C3AED]'
  if (d.isWeekend) return 'text-[#F97316]'
  return 'text-[#9E9E9E]'
}

function cellColor(d, schedule) {
  if (d.isHoliday) return '#7C3AED'
  return (schedule[d.date] ?? 'work') === 'off' ? '#374151' : '#10B981'
}

function monthTitle(m) {
  const names = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь']
  const [y, mo] = m.split('-')
  return `${names[parseInt(mo) - 1]} ${y}`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/schedule')
    brigadeName.value = data.brigade.name
    months.value = data.months
  } catch (e) {
    error.value = e.response?.status === 404 ? 'Вы не состоите ни в одной бригаде' : 'Нет соединения с сервером'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.sched-name-col { min-width: 100px; z-index: 10; }
</style>
