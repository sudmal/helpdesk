<template>
  <div class="h-screen flex flex-col" style="background:#121212">
    <!-- Шапка -->
    <div class="shrink-0 px-4 pt-3 pb-2.5 flex items-center gap-2" style="background:#1D4ED8">
      <div class="flex-1 min-w-0">
        <div class="text-white text-lg font-bold leading-tight">HelpDesk</div>
        <div class="text-white/80 text-xs leading-tight">{{ todayLabel }}</div>
        <div class="text-white/55 text-[10px] leading-tight">{{ lastSyncLabel }}</div>
      </div>

      <select v-model="serviceTypeFilter"
              class="bg-[#1E1E1E] text-white text-sm rounded-lg px-2 py-1.5 max-w-[40%] border-none">
        <option value="">Все участки</option>
        <option v-for="st in serviceTypes" :key="st" :value="st">{{ st }}</option>
      </select>

      <button @click="menuOpen = !menuOpen" class="text-white shrink-0 w-8 h-8 flex items-center justify-center">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 6a2 2 0 100-4 2 2 0 000 4zM10 12a2 2 0 100-4 2 2 0 000 4zM10 18a2 2 0 100-4 2 2 0 000 4z"/>
        </svg>
      </button>
    </div>

    <!-- Выпадающее меню -->
    <div v-if="menuOpen" class="shrink-0 bg-[#1E1E1E] border-b border-white/10">
      <button @click="sortOrder = sortOrder === 'time' ? 'address' : 'time'; menuOpen = false"
              class="w-full text-left px-4 py-3 text-white text-sm active:bg-white/5">
        Сортировка: {{ sortOrder === 'time' ? 'по времени' : 'по адресу' }}
      </button>
      <button @click="doLogout" class="w-full text-left px-4 py-3 text-[#F87171] text-sm active:bg-white/5">
        Выйти
      </button>
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

          <TicketCard v-for="t in currentList" :key="t.id" :ticket="t" :group="activeTab === 'overdue' ? 'overdue' : activeTab"
                      :is-new="newIds.has(t.id)"
                      @open="$router.push({ name: 'ticket-detail', params: { id: t.id } })" />
        </div>
      </PullToRefresh>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '../api'
import { auth } from '../store/auth'
import { useRouter } from 'vue-router'
import PullToRefresh from '../components/PullToRefresh.vue'
import TicketCard from '../components/TicketCard.vue'

const router = useRouter()

const raw = ref({ overdue: [], today: [], new_today: [], tomorrow: [] })
const loading = ref(false)
const hasLoadedOnce = ref(false)
const lastSyncLabel = ref('Ещё не синхронизировано')
const activeTab = ref('overdue')
const menuOpen = ref(false)
const serviceTypeFilter = ref('')
const sortOrder = ref('time') // time | address

const todayLabel = new Date().toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', weekday: 'short' })

const newIds = computed(() => new Set(raw.value.new_today.map((t) => t.id)))

const todayMerged = computed(() => {
  const map = new Map()
  ;[...raw.value.today, ...raw.value.new_today].forEach((t) => map.set(t.id, t))
  return [...map.values()]
})

function applyFilterSort(list) {
  let out = list
  if (serviceTypeFilter.value) {
    out = out.filter((t) => t.service_type?.name === serviceTypeFilter.value)
  }
  out = [...out]
  if (sortOrder.value === 'address') {
    out.sort((a, b) => (a.address?.full || '').localeCompare(b.address?.full || ''))
  } else {
    out.sort((a, b) => new Date(a.scheduled_at || 0) - new Date(b.scheduled_at || 0))
  }
  return out
}

const tabs = computed(() => [
  { key: 'overdue', label: 'Просрочено', count: raw.value.overdue.length },
  { key: 'today', label: 'Сегодня', count: todayMerged.value.length },
  { key: 'tomorrow', label: 'Завтра', count: raw.value.tomorrow.length },
])

const currentList = computed(() => {
  const list = activeTab.value === 'today' ? todayMerged.value : raw.value[activeTab.value]
  return applyFilterSort(list)
})

const serviceTypes = computed(() => {
  const all = [...raw.value.overdue, ...raw.value.today, ...raw.value.new_today, ...raw.value.tomorrow]
  return [...new Set(all.map((t) => t.service_type?.name).filter(Boolean))].sort()
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/tickets')
    raw.value = data
    lastSyncLabel.value = 'Обновлено в ' + new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  } catch {
    lastSyncLabel.value = 'Нет соединения, показаны кешированные данные'
  } finally {
    loading.value = false
    hasLoadedOnce.value = true
  }
}

function doLogout() {
  menuOpen.value = false
  auth.logout()
  router.replace({ name: 'login' })
}

let autoRefreshTimer = null
onMounted(() => {
  load()
  // Автообновление раз в 15 минут -- как в Android-приложении
  autoRefreshTimer = setInterval(load, 15 * 60 * 1000)
})
onUnmounted(() => clearInterval(autoRefreshTimer))
</script>
