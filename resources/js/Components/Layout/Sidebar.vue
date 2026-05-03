<template>
  <aside class="flex flex-col w-64 bg-[#141c2b] text-white shrink-0">
    <div class="flex items-center gap-3 px-5 py-5 border-b border-white/10">
      <div class="w-9 h-9 rounded-lg bg-blue-500 flex items-center justify-center font-bold text-sm">HD</div>
      <span class="font-semibold text-lg tracking-tight">HelpDesk</span>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
      <NavItem :href="route('dashboard')"         icon="grid"     label="Дашборд" />
      <NavItem :href="route('tickets.index')"     icon="ticket"   label="Заявки" />
      <NavItem :href="route('calendar.index')"    icon="calendar" label="Календарь" />
      <template v-if="canManageSettings">
        <NavItem :href="route('territories.index')" icon="map-pin" label="Территории" />
        <NavItem :href="route('brigades.index')"  icon="users"    label="Бригады" />
      </template>
      <NavItem :href="route('addresses.index')"   icon="database" label="Адреса" />
      <NavItem v-if="canManageSettings" :href="route('settings.index')" icon="settings" label="Настройки" />
    </nav>
    <div class="px-4 py-4 border-t border-white/10">
      <div class="text-sm font-medium truncate">{{ user.name }}</div>
      <div class="text-xs text-white/50 truncate">{{ user.email }}</div>
      <Link :href="route('logout')" method="post" as="button"
            class="mt-3 flex items-center gap-2 text-xs text-white/60 hover:text-white transition-colors">
        <Icon name="log-out" class="w-4 h-4" /> Выход
      </Link>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import NavItem from './NavItem.vue'
import Icon from '@/Components/UI/Icon.vue'

const props = defineProps({ user: Object })
const canManageSettings = computed(() =>
  ['admin', 'head_support'].includes(props.user?.role?.slug)
)
</script>
