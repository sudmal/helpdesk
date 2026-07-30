<template>
  <div class="h-screen flex flex-col" style="background:#121212">
    <div class="shrink-0 px-3 py-3 flex items-center gap-2" style="background:#1D4ED8">
      <button @click="$router.push({ name: 'dashboard' })" class="text-white w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <div class="text-white text-lg font-bold leading-tight">Акты</div>
        <div class="text-white/55 text-[10px] leading-tight">{{ lastSyncLabel }}</div>
      </div>
    </div>

    <div class="flex-1 min-h-0">
      <PullToRefresh @refresh="load">
        <div class="p-2">
          <div v-if="loading && !hasLoadedOnce" class="flex justify-center py-10">
            <svg class="w-6 h-6 text-[#3B82F6] animate-spin" viewBox="0 0 24 24" fill="none">
              <path d="M21 12a9 9 0 11-2.64-6.36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>

          <div v-else-if="acts.length === 0" class="flex flex-col items-center justify-center py-16 text-[#888888]">
            <svg class="w-14 h-14 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="text-sm">Нет актов, требующих внимания</span>
          </div>

          <ActCard v-for="a in acts" :key="a.id" :act="a"
                   @open="$router.push({ name: 'act-detail', params: { id: a.id } })" />
        </div>
      </PullToRefresh>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import PullToRefresh from '../components/PullToRefresh.vue'
import ActCard from '../components/ActCard.vue'

const acts = ref([])
const loading = ref(false)
const hasLoadedOnce = ref(false)
const lastSyncLabel = ref('Ещё не синхронизировано')

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/acts')
    acts.value = data.data
    lastSyncLabel.value = 'Обновлено в ' + new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  } catch {
    lastSyncLabel.value = 'Нет соединения, показаны кешированные данные'
  } finally {
    loading.value = false
    hasLoadedOnce.value = true
  }
}

onMounted(load)
</script>
