<template>
  <div class="min-h-screen flex flex-col" style="background:#121212">
    <div class="shrink-0 px-3 py-3 flex items-center gap-2" style="background:#1D4ED8">
      <button @click="$router.back()" class="text-white w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <span class="text-white font-bold text-base">Настройки</span>
    </div>

    <div class="flex-1 overflow-y-auto p-3 space-y-3">
      <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-2">
        <div class="text-[#9E9E9E] text-xs">Автообновление списка заявок</div>
        <select v-model.number="settings.syncIntervalMinutes"
                class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10">
          <option :value="15">Каждые 15 минут</option>
          <option :value="30">Каждые 30 минут</option>
          <option :value="60">Каждый час</option>
        </select>
      </div>

      <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-2">
        <div class="text-[#9E9E9E] text-xs">Сортировка заявок по умолчанию</div>
        <select v-model="settings.sortOrder"
                class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10">
          <option value="time">По времени</option>
          <option value="address">По адресу</option>
          <option value="service">По типу участка</option>
        </select>
      </div>

      <!-- Очередь исходящих комментариев -->
      <div v-if="commentQueue.state.items.length" class="bg-[#1E1E1E] rounded-lg p-3 space-y-2">
        <div class="text-[#FBBF24] text-xs">
          Не отправлено комментариев: {{ commentQueue.state.items.length }} (ждут сети)
        </div>
        <button @click="commentQueue.flush()" :disabled="commentQueue.state.flushing"
                class="w-full h-10 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#3B82F6">
          {{ commentQueue.state.flushing ? 'Отправка...' : 'Отправить сейчас' }}
        </button>
      </div>

      <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-2">
        <div class="text-[#9E9E9E] text-xs">Приложение зависает или показывает старую версию?</div>
        <button @click="resetAppCache" :disabled="resetting"
                class="w-full h-10 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#374151">
          {{ resetting ? '...' : 'Сбросить кэш приложения' }}
        </button>
      </div>

      <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-1">
        <div class="text-[#E0E0E0] text-sm">{{ auth.state.user?.name || '—' }}</div>
        <div class="text-[#9E9E9E] text-xs">Версия {{ appVersion }}</div>
      </div>

      <button @click="doLogout" class="w-full h-11 rounded-lg text-white text-sm font-medium" style="background:#DC2626">
        Выйти
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { settings } from '../store/settings'
import { commentQueue } from '../store/commentQueue'
import { auth } from '../store/auth'
import pkg from '../../package.json'

const router = useRouter()
const resetting = ref(false)
const appVersion = pkg.version

async function resetAppCache() {
  resetting.value = true
  try {
    if ('caches' in window) {
      const keys = await caches.keys()
      await Promise.all(keys.map((k) => caches.delete(k)))
    }
    if ('serviceWorker' in navigator) {
      const regs = await navigator.serviceWorker.getRegistrations()
      await Promise.all(regs.map((r) => r.unregister()))
    }
  } finally {
    location.reload()
  }
}

function doLogout() {
  auth.logout()
  router.replace({ name: 'login' })
}
</script>
