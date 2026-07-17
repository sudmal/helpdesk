<template>
  <Head>
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#2563eb" />
  </Head>
  <div class="flex h-screen bg-slate-100 overflow-hidden">

    <!-- Overlay для мобильного -->
    <div v-if="sidebarOpen"
         class="fixed inset-0 bg-black/50 z-30 md:hidden"
         @click="sidebarOpen = false" />

    <!-- Sidebar -->
    <div :class="['print:hidden fixed md:relative flex flex-col w-56 bg-[#141c2b] text-white shrink-0 h-full z-40 transition-transform duration-200',
                  sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0']">
      <Sidebar :user="$page.props.auth.user" />
    </div>

    <!-- Main -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

      <!-- Topbar: 3 колонки (слева/заголовок по центру/справа), чтобы заголовок
           оставался по центру независимо от того, сколько кнопок слева и справа -->
      <header class="print:hidden h-11 bg-white border-b border-slate-200 shadow-sm shrink-0 grid grid-cols-[1fr_auto_1fr] items-center px-3 gap-2">
        <div class="flex items-center gap-2 min-w-0">
          <!-- Бургер для мобильного -->
          <button @click="sidebarOpen = !sidebarOpen"
                  class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 transition-colors shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <slot name="before-title" />
        </div>

        <h1 class="text-sm font-semibold text-gray-800 truncate text-center">{{ title }}</h1>

        <div class="flex items-center gap-1.5 shrink-0 justify-self-end">
          <slot name="actions" />
          <PushNotifications />
        </div>
      </header>

      <!-- Flash -->
      <div v-if="flash.success || flash.error" class="px-3 pt-2 shrink-0">
        <div v-if="flash.success"
             class="flex items-center gap-2 bg-green-50 border border-green-200 text-green-800
                    rounded-lg px-3 py-1.5 text-sm">
          ✓ {{ flash.success }}
        </div>
        <div v-if="flash.error"
             class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-800
                    rounded-lg px-3 py-1.5 text-sm">
          ✕ {{ flash.error }}
        </div>
      </div>

      <!-- Content -->
      <main class="flex-1 overflow-y-auto px-3 md:px-4 py-3" style="scrollbar-gutter: stable">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Sidebar from './Sidebar.vue'
import PushNotifications from '@/Components/PushNotifications.vue'

defineProps({ title: { type: String, default: '' } })

const sidebarOpen = ref(false)
const page  = usePage()
const flash = computed(() => page.props.flash ?? {})
</script>
