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
      <NavItem :href="route('connection-requests.index')" icon="wifi" label="Подключения">
        <span v-if="connectionAlerts.pending > 0 || connectionAlerts.needs_callback > 0"
              class="ml-auto flex items-center gap-1">
          <!-- Пульсирующий ! — есть необработанные -->
          <span v-if="connectionAlerts.pending > 0"
                class="animate-pulse flex items-center justify-center w-4 h-4 rounded-full bg-red-500 text-white text-[9px] font-bold leading-none"
                title="Есть ожидающие заявки">!</span>
          <!-- Прыгающий телефон — нужно прозвонить -->
          <span v-if="connectionAlerts.needs_callback > 0"
                class="animate-bounce"
                title="Требуется прозвон">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-amber-400" viewBox="0 0 24 24" fill="currentColor">
              <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
            </svg>
          </span>
        </span>
      </NavItem>
      <NavItem :href="route('calendar.index')"      icon="calendar" label="Календарь" />
      <NavItem v-if="isForeman && foremanBrigadeId"
               :href="route('brigades.show', foremanBrigadeId)"
               icon="users" label="Моя бригада" />
      <template v-if="canManageSettings">
        <NavItem :href="route('territories.index')" icon="map-pin"  label="Территории" />
        <NavItem :href="route('brigades.index')"    icon="users"    label="Бригады" />
      </template>
      <NavItem :href="route('addresses.index')"     icon="database" label="Адреса" />
      <NavItem v-if="can('materials.view')" :href="route('materials.index')"    icon="package"  label="Материалы" />
      <NavItem v-if="canManageSettings"
               :href="route('reports.index')"       icon="bar-chart-2" label="Отчёты" />
      <NavItem :href="route('calls.index')" icon="phone" label="Звонки" />
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
    <div v-if="apk" class="px-4 py-3 border-t border-white/10">
      <a :href="apk.apk_url" target="_blank"
         title="Приложение для выездных сотрудников (Android 11+)"
         class="flex items-center gap-2.5 text-sm text-green-400 hover:text-green-300 transition-colors font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
          <path d="M7.5 18.5h1V22c0 .55.45 1 1 1s1-.45 1-1v-3.5h2V22c0 .55.45 1 1 1s1-.45 1-1v-3.5h1c.55 0 1-.45 1-1V8h-10v9.5c0 .55.45 1 1 1zM4 8c-.55 0-1 .45-1 1v7c0 .55.45 1 1 1s1-.45 1-1V9c0-.55-.45-1-1-1zm16 0c-.55 0-1 .45-1 1v7c0 .55.45 1 1 1s1-.45 1-1V9c0-.55-.45-1-1-1zm-4.97-5l1.3-1.3a.496.496 0 0 0-.7-.7L14.15 2.48C13.46 2.17 12.75 2 12 2c-.75 0-1.46.17-2.15.48L8.37 1c-.19-.2-.51-.2-.7 0-.2.19-.2.51 0 .7L9 3C7.42 3.86 6.27 5.32 6.03 7H17.97c-.24-1.68-1.39-3.14-2.94-3.5zM10 5H9V4h1v1zm5 0h-1V4h1v1z"/>
        </svg>
        <span>SP-Helpdesk {{ apk.version_name }}</span>
      </a>
      <a :href="route('help') + '?tab=app'"
         class="flex items-center gap-1.5 mt-1.5 text-xs text-white/35 hover:text-white/70 transition-colors pl-0.5">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
        Инструкция
      </a>
    </div>
    <div class="px-4 py-2 border-t border-white/5">
      <div class="text-[10px] text-white/20 leading-tight">Sudmal @ Claude</div>
    </div>
  </aside>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import NavItem from './NavItem.vue'

const props = defineProps({ user: Object })

const apk = ref(null)

onMounted(async () => {
  try {
    const res = await fetch('/apk/version.json?_=' + Date.now())
    if (res.ok) apk.value = await res.json()
  } catch {}
})

const page = usePage()

const connectionAlerts = computed(() =>
  page.props.connectionAlerts ?? { pending: 0, needs_callback: 0 }
)

const canManageSettings = computed(() =>
  ['admin', 'head_support'].includes(props.user?.role?.slug)
)
const isForeman = computed(() => props.user?.role?.slug === 'foreman')
const foremanBrigadeId = computed(() => page.props.auth?.foreman_brigade_id)

function can(permission) {
  const perms = props.user?.role?.permissions ?? []
  return perms.includes('*') || perms.includes(permission)
}

function logout() {
  router.post(route('logout'))
}
</script>
