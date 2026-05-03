<template>
  <div class="flex h-screen bg-gray-50 overflow-hidden">

    <!-- Sidebar -->
    <aside
      :class="['flex flex-col w-64 bg-[#141c2b] text-white shrink-0 transition-transform duration-200',
               sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0']"
      style="position:relative; z-index:40"
    >
      <!-- Logo -->
      <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
        <div class="w-9 h-9 rounded-lg bg-blue-500 flex items-center justify-center font-bold text-sm">HD</div>
        <span class="font-semibold text-lg tracking-tight">HelpDesk</span>
      </div>

      <!-- Nav -->
      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <NavItem :href="route('dashboard')"       icon="grid"     label="Дашборд" />
        <NavItem :href="route('tickets.index')"   icon="ticket"   label="Заявки" />
        <NavItem :href="route('calendar.index')"  icon="calendar" label="Календарь" />
        <NavItem :href="route('territories.index')" icon="map-pin" label="Территории"
                 v-if="can('manage-settings')" />
        <NavItem :href="route('brigades.index')"  icon="users"    label="Бригады"
                 v-if="can('manage-settings')" />
        <NavItem :href="route('addresses.index')" icon="database" label="Адреса" />
        <NavItem :href="route('settings.index')"  icon="settings" label="Настройки"
                 v-if="can('manage-settings')" />
      </nav>

      <!-- User -->
      <div class="px-4 py-4 border-t border-white/10">
        <div class="text-sm font-medium">{{ $page.props.auth.user.name }}</div>
        <div class="text-xs text-white/50 truncate">{{ $page.props.auth.user.email }}</div>
        <Link :href="route('logout')" method="post" as="button"
              class="mt-3 flex items-center gap-2 text-xs text-white/60 hover:text-white transition-colors">
          <Icon name="log-out" class="w-4 h-4" />
          Выход
        </Link>
      </div>
    </aside>

    <!-- Overlay (mobile) -->
    <div v-if="sidebarOpen"
         class="fixed inset-0 bg-black/40 z-30 md:hidden"
         @click="sidebarOpen = false" />

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

      <!-- Topbar -->
      <header class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-4 shrink-0">
        <button class="md:hidden text-gray-500 hover:text-gray-700"
                @click="sidebarOpen = !sidebarOpen">
          <Icon name="menu" class="w-5 h-5" />
        </button>

        <h1 class="font-semibold text-gray-800 text-lg flex-1">{{ title }}</h1>

        <!-- Flash messages -->
        <Transition name="fade">
          <div v-if="flash.success"
               class="flex items-center gap-2 bg-green-50 text-green-700 border border-green-200
                      px-3 py-1.5 rounded-lg text-sm">
            <Icon name="check-circle" class="w-4 h-4" />
            {{ flash.success }}
          </div>
        </Transition>

        <slot name="actions" />
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import NavItem from '@/Components/Layout/NavItem.vue'
import Icon from '@/Components/UI/Icon.vue'

defineProps({ title: { type: String, default: '' } })

const page = usePage()
const sidebarOpen = ref(false)
const flash = computed(() => page.props.flash ?? {})

function can(permission) {
  const user = page.props.auth?.user
  if (!user) return false
  const perms = user.role?.permissions ?? []
  if (perms.includes('*')) return true
  if (permission === 'manage-settings') {
    return ['admin', 'head_support'].includes(user.role?.slug)
  }
  return perms.some(p => p === permission || (p.endsWith('.*') && permission.startsWith(p.slice(0, -2))))
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
