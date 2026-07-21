<template>
  <aside class="flex flex-col w-56 bg-[#141c2b] text-white shrink-0 h-full text-[13px]">
    <div class="flex items-center gap-2 px-4 py-2 border-b border-white/10 shrink-0">
      <img src="/logo.png" alt="Logo" class="w-6 h-6"/>
      <span class="font-semibold text-sm tracking-tight">HelpDesk</span>
    </div>
    <nav class="flex-1 px-2.5 py-2 space-y-px overflow-y-auto min-h-0">
      <a v-if="can('tickets.create')" :href="route('tickets.create')"
          class="flex items-center gap-2 px-2.5 py-1.5 mb-1 rounded-md
                 bg-green-600 hover:bg-green-700 text-white font-medium text-[13px]
                 transition-colors shadow-sm">
        <span class="text-sm leading-none">+</span>
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
      <NavItem :href="route('service-requests.index')" icon="tool" label="Запросы услуг">
        <span v-if="serviceRequestAlerts.pending > 0"
              class="ml-auto animate-pulse flex items-center justify-center w-4 h-4 rounded-full bg-purple-500 text-white text-[9px] font-bold leading-none"
              title="Есть необработанные запросы">!</span>
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
      <NavItem v-if="can('acts.view')" :href="route('acts.index')" icon="file-text" label="Акты">
        <span v-if="actsAlerts.pending > 0"
              class="ml-auto animate-pulse flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-white text-[9px] font-bold leading-none"
              :title="`Актов, требующих внимания: ${actsAlerts.pending}`">
          {{ actsAlerts.pending }}
        </span>
      </NavItem>
      <NavItem v-if="canManageSettings"
               :href="route('reports.index')"       icon="bar-chart-2" label="Отчёты" />
      <NavItem v-if="can('calls.view')" :href="route('calls.index')" icon="phone" label="Звонки" />
      <NavItem v-if="canManageSettings"
               :href="route('settings.index')"      icon="settings" label="Настройки" />
      <NavItem :href="route('help')" icon="help-circle" label="Справка" />
    </nav>
    <div class="px-3 py-1.5 border-t border-white/10 shrink-0">
      <div class="flex items-center justify-between gap-2">
        <div class="min-w-0">
          <div class="text-xs font-medium truncate">{{ user.name }}</div>
          <div class="text-[11px] text-white/40 truncate">{{ user.email }}</div>
        </div>
        <button @click="logout" title="Выход"
                class="shrink-0 p-1 rounded-md text-white/50 hover:text-white hover:bg-white/10 transition-colors cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
          </svg>
        </button>
      </div>
    </div>
    <div v-if="apk" class="px-3 py-1.5 border-t border-white/10 shrink-0 flex items-center justify-between gap-2">
      <a :href="apk.apk_url" target="_blank"
         title="Приложение для выездных сотрудников (Android 11+)"
         class="flex items-center gap-1.5 min-w-0 text-[12px] text-green-400 hover:text-green-300 transition-colors font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor">
          <path d="M7.5 18.5h1V22c0 .55.45 1 1 1s1-.45 1-1v-3.5h2V22c0 .55.45 1 1 1s1-.45 1-1v-3.5h1c.55 0 1-.45 1-1V8h-10v9.5c0 .55.45 1 1 1zM4 8c-.55 0-1 .45-1 1v7c0 .55.45 1 1 1s1-.45 1-1V9c0-.55-.45-1-1-1zm16 0c-.55 0-1 .45-1 1v7c0 .55.45 1 1 1s1-.45 1-1V9c0-.55-.45-1-1-1zm-4.97-5l1.3-1.3a.496.496 0 0 0-.7-.7L14.15 2.48C13.46 2.17 12.75 2 12 2c-.75 0-1.46.17-2.15.48L8.37 1c-.19-.2-.51-.2-.7 0-.2.19-.2.51 0 .7L9 3C7.42 3.86 6.27 5.32 6.03 7H17.97c-.24-1.68-1.39-3.14-2.94-3.5zM10 5H9V4h1v1zm5 0h-1V4h1v1z"/>
        </svg>
        <span class="truncate">SP-Helpdesk {{ apk.version_name }}</span>
      </a>
      <div class="flex items-center gap-1.5 shrink-0">
        <a :href="route('help') + '?tab=app'" title="Инструкция"
           class="p-1 rounded-md text-white/35 hover:text-white/70 hover:bg-white/10 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </a>
        <button @click="openQr" title="QR-код"
                class="p-1 rounded-md text-white/35 hover:text-white/70 hover:bg-white/10 transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
            <path d="M14 14h1v1h-1zm2 0h1v1h-1zm2 0h1v1h-1zm-4 2h1v1h-1zm2 0h1v1h-1zm2 0h1v1h-1zm-4 2h1v1h-1zm2 0h1v1h-1zm2 0h1v1h-1z"/>
          </svg>
        </button>
      </div>
    </div>
    <div class="px-3 py-1.5 border-t border-white/10 shrink-0">
      <a href="https://app.vega8.ru" target="_blank"
         title="Веб-версия приложения (PWA) — для iPhone или как альтернатива APK"
         class="flex items-center gap-1.5 min-w-0 text-[12px] text-blue-400 hover:text-blue-300 transition-colors font-medium">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zm0 0c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3 7.5 7.03 7.5 12s2.015 9 4.5 9zM3.6 9h16.8M3.6 15h16.8" />
        </svg>
        <span class="truncate">Веб-версия приложения (PWA)</span>
      </a>
    </div>

    <!-- QR-модалка -->
    <teleport to="body">
      <div v-if="qrOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="qrOpen = false">
        <div class="bg-white rounded-2xl shadow-2xl p-6 flex flex-col items-center gap-4 min-w-[220px]">
          <div class="text-sm font-semibold text-slate-700">Скачать SP-Helpdesk {{ apk?.version_name }}</div>
          <canvas ref="qrCanvas" class="rounded-lg"></canvas>
          <a :href="apk?.apk_url" target="_blank" class="text-xs text-blue-600 hover:underline">Прямая ссылка</a>
          <button @click="qrOpen = false" class="text-xs text-slate-400 hover:text-slate-600">Закрыть</button>
        </div>
      </div>
    </teleport>
  </aside>
</template>

<script setup>
import { computed, ref, onMounted, watch, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import NavItem from './NavItem.vue'
import QRCode from 'qrcode'

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

const serviceRequestAlerts = computed(() =>
  page.props.serviceRequestAlerts ?? { pending: 0 }
)

const actsAlerts = computed(() =>
  page.props.actsAlerts ?? { pending: 0 }
)

const canManageSettings = computed(() =>
  ['admin', 'head_support'].includes(props.user?.role?.slug)
)
const isForeman = computed(() => props.user?.role?.slug === 'foreman')
const foremanBrigadeId = computed(() => page.props.auth?.foreman_brigade_id)

// Повторяет User::hasPermission() на сервере — включая скоуп-wildcard'ы вида
// "tickets.*" (покрывает "tickets.view"/"tickets.create"/...). Без этого
// клиентская проверка расходилась с реальными правами для ролей, где такой
// wildcard есть (Начальник ТП, Оператор ТП) — риск и спрятать доступное
// действие, и показать недоступное, в зависимости от набора прав.
function can(permission) {
  const perms = props.user?.role?.permissions ?? []
  if (perms.includes('*')) return true
  return perms.some(p => p === permission || (p.endsWith('.*') && permission.startsWith(p.slice(0, -1))))
}

function logout() {
  router.post(route('logout'))
}

const qrOpen = ref(false)
const qrCanvas = ref(null)

async function openQr() {
  qrOpen.value = true
  await nextTick()
  if (qrCanvas.value && apk.value?.apk_url) {
    QRCode.toCanvas(qrCanvas.value, apk.value.apk_url, { width: 200, margin: 2 })
  }
}
</script>

