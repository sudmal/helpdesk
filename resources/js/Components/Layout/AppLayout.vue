<template>
  <div class="flex h-screen bg-gray-50 overflow-hidden">

    <!-- Overlay для мобильного -->
    <div v-if="sidebarOpen"
         class="fixed inset-0 bg-black/50 z-30 md:hidden"
         @click="sidebarOpen = false" />

    <!-- Sidebar -->
    <div :class="['fixed md:relative flex flex-col w-64 bg-[#141c2b] text-white shrink-0 h-full z-40 transition-transform duration-200',
                  sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0']">
      <Sidebar :user="$page.props.auth.user" />
    </div>

    <!-- Main -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

      <!-- Topbar -->
      <header class="h-14 bg-white border-b border-gray-200 flex items-center px-4 gap-3 shrink-0">
        <!-- Бургер для мобильного -->
        <button @click="sidebarOpen = !sidebarOpen"
                class="md:hidden p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <h1 class="text-base font-semibold text-gray-800 truncate">{{ title }}</h1>

        <div class="ml-auto flex items-center gap-2 shrink-0">
          <slot name="actions" />
        </div>
      </header>

      <!-- Flash -->
      <div v-if="flash.success || flash.error" class="px-4 pt-3 shrink-0">
        <div v-if="flash.success"
             class="flex items-center gap-2 bg-green-50 border border-green-200 text-green-800
                    rounded-xl px-4 py-2.5 text-sm">
          ✓ {{ flash.success }}
        </div>
        <div v-if="flash.error"
             class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-800
                    rounded-xl px-4 py-2.5 text-sm">
          ✕ {{ flash.error }}
        </div>
      </div>

      <!-- Content -->
      <main class="flex-1 overflow-y-auto px-4 md:px-6 py-4 md:py-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Sidebar from './Sidebar.vue'

defineProps({ title: { type: String, default: '' } })

const sidebarOpen = ref(false)
const page  = usePage()
const flash = computed(() => page.props.flash ?? {})
</script>
