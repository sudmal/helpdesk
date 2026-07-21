<template>
  <div class="h-screen flex flex-col" style="background:#121212">
    <!-- Шапка -->
    <div class="shrink-0 px-3 py-3 flex items-center gap-2" style="background:#1D4ED8">
      <button @click="$router.push({ name: 'dashboard' })" class="text-white w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1 min-w-0">
        <div class="text-white text-lg font-bold leading-tight">Подключения</div>
        <div class="text-white/55 text-[10px] leading-tight">{{ lastSyncLabel }}</div>
      </div>
      <select v-model="territoryFilter" @change="load"
              class="bg-[#1E1E1E] text-white text-sm rounded-lg px-2 py-1.5 max-w-[40%] border-none">
        <option value="">Все территории</option>
        <option v-for="t in territories" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
    </div>

    <!-- Вкладки -->
    <div class="shrink-0 flex" style="background:#1D4ED8">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
              class="flex-1 py-2.5 text-sm font-medium border-b-2 transition-colors"
              :class="activeTab === tab.key ? 'text-white border-white' : 'text-[#AACCFF] border-transparent'">
        {{ tab.label }}
        <span v-if="tab.count" class="ml-1">({{ tab.count }})</span>
      </button>
    </div>

    <!-- Список -->
    <div class="flex-1 min-h-0">
      <PullToRefresh @refresh="load">
        <div class="p-2">
          <div v-if="loading && !hasLoadedOnce" class="flex justify-center py-10">
            <svg class="w-6 h-6 text-[#3B82F6] animate-spin" viewBox="0 0 24 24" fill="none">
              <path d="M21 12a9 9 0 11-2.64-6.36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
            </svg>
          </div>

          <div v-else-if="currentList.length === 0" class="flex flex-col items-center justify-center py-16 text-[#888888]">
            <svg class="w-14 h-14 mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="text-sm">Нет заявок</span>
          </div>

          <ConnectionCard v-for="r in currentList" :key="r.id" :request="r"
                           @open="$router.push({ name: 'connection-detail', params: { id: r.id } })"
                           @open-act="$router.push({ name: 'act-detail', params: { id: $event } })" />

          <button v-if="canLoadMore" @click="loadMore" :disabled="loadingMore"
                  class="w-full h-10 rounded-lg text-[#9E9E9E] text-sm border border-white/10 mt-1 disabled:opacity-50">
            {{ loadingMore ? '...' : 'Показать ещё' }}
          </button>
        </div>
      </PullToRefresh>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../api'
import PullToRefresh from '../components/PullToRefresh.vue'
import ConnectionCard from '../components/ConnectionCard.vue'

const items = ref([])
const territories = ref([])
const territoryFilter = ref('')
const loading = ref(false)
const loadingMore = ref(false)
const hasLoadedOnce = ref(false)
const lastSyncLabel = ref('Ещё не синхронизировано')
const activeTab = ref('pending')
const page = ref(1)
const lastPage = ref(1)

const tabs = computed(() => [
  { key: 'pending', label: 'Ожидает', count: items.value.filter((r) => r.status === 'pending').length },
  { key: 'scheduled', label: 'Назначено', count: items.value.filter((r) => r.status === 'scheduled').length },
  { key: 'rejected', label: 'Отклонено', count: items.value.filter((r) => r.status === 'rejected').length },
  { key: 'closed', label: 'Выполнено', count: items.value.filter((r) => r.status === 'closed').length },
])

const currentList = computed(() => items.value.filter((r) => r.status === activeTab.value))
const canLoadMore = computed(() => page.value < lastPage.value)

async function load() {
  loading.value = true
  page.value = 1
  try {
    const { data } = await api.get('/connection-requests', {
      params: { territory_id: territoryFilter.value || undefined, page: 1 },
    })
    items.value = data.data
    territories.value = data.territories || []
    lastPage.value = data.last_page
    lastSyncLabel.value = 'Обновлено в ' + new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  } catch {
    lastSyncLabel.value = 'Нет соединения, показаны кешированные данные'
  } finally {
    loading.value = false
    hasLoadedOnce.value = true
  }
}

async function loadMore() {
  if (!canLoadMore.value) return
  loadingMore.value = true
  try {
    const { data } = await api.get('/connection-requests', {
      params: { territory_id: territoryFilter.value || undefined, page: page.value + 1 },
    })
    items.value = [...items.value, ...data.data]
    page.value = data.current_page
    lastPage.value = data.last_page
  } finally {
    loadingMore.value = false
  }
}

onMounted(load)
</script>
