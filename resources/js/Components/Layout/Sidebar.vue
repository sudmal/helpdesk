<template>
  <aside class="flex flex-col w-64 bg-[#141c2b] text-white shrink-0">
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
      <div class="w-9 h-9 rounded-lg bg-blue-500 flex items-center justify-center font-bold text-sm">HD</div>
      <span class="font-semibold text-lg tracking-tight">HelpDesk</span>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
      <a :href="route('tickets.create')"
          class="flex items-center gap-2.5 px-3 py-2.5 mb-2 rounded-xl
                 bg-green-600 hover:bg-green-700 text-white font-medium text-sm
                 transition-colors shadow-sm">
        <span class="text-base">+</span>
        <span>Новая заявка</span>
      </a>
      <NavItem :href="route('dashboard')"           icon="grid"     label="Дашборд" />
      <NavItem :href="route('tickets.index')"       icon="ticket"   label="Заявки" />
      <NavItem :href="route('calendar.index')"      icon="calendar" label="Календарь" />
      <template v-if="canManageSettings">
        <NavItem :href="route('territories.index')" icon="map-pin"  label="Территории" />
        <NavItem :href="route('brigades.index')"    icon="users"    label="Бригады" />
      </template>
      <NavItem :href="route('addresses.index')"     icon="database" label="Адреса" />
      <NavItem v-if="can('materials.view')" :href="route('materials.index')"    icon="package"  label="Материалы" />
      <NavItem v-if="canManageSettings"
               :href="route('reports.index')"       icon="bar-chart-2" label="Отчёты" />
      <NavItem v-if="canManageSettings"
               :href="route('settings.index')"      icon="settings" label="Настройки" />
      <NavItem :href="route('help')" icon="help-circle" label="Справка" />
    </nav>
    <div class="px-4 py-4 border-t border-white/10">
      <div class="text-sm font-medium truncate">{{ user.name }}</div>
      <div class="text-xs text-white/50 truncate mb-3">{{ user.email }}</div>
      <button @click="logout"
              class="flex items-center gap-2 text-xs text-white/60 hover:text-white transition-colors w-full text-left cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
        </svg>
        Выход
      </button>
    </div>
    <div class="px-4 py-2 border-t border-white/5">
      <div class="text-[10px] text-white/20 leading-tight">Suntsov Dmitriy @ Claude</div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import NavItem from './NavItem.vue'

const props = defineProps({ user: Object })

const canManageSettings = computed(() =>
  ['admin', 'head_support'].includes(props.user?.role?.slug)
)

function can(permission) {
  const perms = props.user?.role?.permissions ?? []
  return perms.includes('*') || perms.includes(permission)
}

function logout() {
  router.post(route('logout'))
}
</script>
