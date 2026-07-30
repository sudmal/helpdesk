<template>
  <aside class="flex flex-col w-56 bg-[#141c2b] text-white shrink-0 h-full text-[13px]">
    <div data-tour="tour-brand" class="flex items-center gap-2 px-4 py-2 border-b border-white/10 shrink-0">
      <img src="/logo.png" alt="Logo" class="w-6 h-6"/>
      <span class="font-semibold text-sm tracking-tight">HelpDesk</span>
    </div>
    <nav class="flex-1 px-2.5 py-2 space-y-px overflow-y-auto min-h-0">
      <a v-if="can('tickets.create')" :href="route('tickets.create')" data-tour="tour-new-ticket"
          class="flex items-center gap-2 px-2.5 py-1.5 mb-1 rounded-md
                 bg-green-600 hover:bg-green-700 text-white font-medium text-[13px]
                 transition-colors shadow-sm">
        <span class="text-sm leading-none">+</span>
        <span>Новая заявка</span>
      </a>
      <NavItem :href="route('dashboard')"           icon="grid"     label="Дашборд" data-tour="tour-nav-dashboard" />
      <NavItem :href="route('tickets.index')"       icon="ticket"   label="Заявки" data-tour="tour-nav-tickets" />
      <NavItem :href="route('connection-requests.index')" icon="wifi" label="Подключения" data-tour="tour-nav-connections">
        <span v-if="connectionAlerts.pending > 0 || connectionAlerts.needs_callback > 0 || connectionAlerts.needs_completion > 0"
              class="ml-auto flex items-center gap-1">
          <!-- Пульсирующий ! — есть необработанные (ждут монтажника) или назначенные (ждут выезда) -->
          <span v-if="connectionAlerts.pending > 0 || connectionAlerts.needs_completion > 0"
                class="animate-pulse flex items-center justify-center w-4 h-4 rounded-full bg-red-500 text-white text-[9px] font-bold leading-none"
                :title="connectionAlerts.pending > 0 ? 'Есть ожидающие заявки' : 'Есть назначенные подключения, ждут выезда'">!</span>
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
      <NavItem :href="route('calendar.index')"      icon="calendar" label="Календарь" data-tour="tour-nav-calendar" />
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
      <NavItem :href="route('help')" icon="help-circle" label="Справка" data-tour="tour-nav-help" />
    </nav>
    <div class="px-3 py-1.5 border-t border-white/10 shrink-0">
      <div class="flex items-center justify-between gap-2">
        <button @click="openProfile" title="Мои данные" class="min-w-0 text-left hover:bg-white/5 rounded-md -mx-1 px-1 py-0.5 transition-colors">
          <div class="text-xs font-medium truncate">{{ user.name }}</div>
          <div class="text-[11px] text-white/40 truncate">{{ user.email }}</div>
        </button>
        <button @click="logout" title="Выход"
                class="shrink-0 p-1 rounded-md text-white/50 hover:text-white hover:bg-white/10 transition-colors cursor-pointer">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
          </svg>
        </button>
      </div>
    </div>
    <div v-if="apk" class="px-3 py-1.5 border-t border-white/10 shrink-0 flex items-center justify-between gap-2">
      <a :href="apkStableUrl" target="_blank"
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

    <!-- Модалка: Мои данные -->
    <teleport to="body">
      <div v-if="profileOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="profileOpen = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-4 text-slate-700">
          <h3 class="text-sm font-semibold mb-3">Мои данные</h3>
          <div class="space-y-2">
            <div>
              <label class="block text-xs text-gray-500 mb-1">ФИО <span class="text-red-400">*</span></label>
              <input v-model="profileForm.name"
                     class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Телефон</label>
              <input v-model="profileForm.phone"
                     class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Email</label>
              <input v-model="profileForm.email" type="email"
                     class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">
                Telegram Chat ID
                <a href="https://t.me/userinfobot" target="_blank" class="text-blue-500 hover:underline">(узнать у @userinfobot)</a>
              </label>
              <input v-model="profileForm.telegram_chat_id"
                     class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">
                MAX ID
                <a href="https://max.ru/id380124799522_1_bot" target="_blank" class="text-blue-500 hover:underline">(узнать у бота-определителя)</a>
              </label>
              <input v-model="profileForm.max_chat_id"
                     class="w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
            </div>
            <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer select-none pt-1">
              <input type="checkbox" v-model="profileForm.notify_on_days_off" class="rounded border-gray-300" />
              Получать уведомления в выходные дни
            </label>
          </div>
          <div v-if="profileErrors" class="mt-2 text-xs text-red-600">{{ profileErrors }}</div>
          <div class="mt-4 flex justify-end gap-2">
            <button @click="profileOpen = false" class="px-3.5 py-1.5 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">Отмена</button>
            <button @click="submitProfile" :disabled="profileSubmitting"
                    class="px-3.5 py-1.5 rounded-lg text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition-colors disabled:opacity-50">
              {{ profileSubmitting ? 'Сохраняем...' : 'Сохранить' }}
            </button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- QR-модалка -->
    <teleport to="body">
      <div v-if="qrOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="qrOpen = false">
        <div class="bg-white rounded-2xl shadow-2xl p-6 flex flex-col items-center gap-4 min-w-[220px]">
          <div class="text-sm font-semibold text-slate-700">Скачать SP-Helpdesk {{ apk?.version_name }}</div>
          <canvas ref="qrCanvas" class="rounded-lg"></canvas>
          <a :href="apkStableUrl" target="_blank" class="text-xs text-blue-600 hover:underline">Прямая ссылка</a>
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

// Стабильная ссылка на APK -- не меняется при выходе новой версии, в отличие
// от apk.apk_url (там конкретное versioned-имя файла из version.json). Указывает
// на симлинк public/apk/latest.apk -- при выкладке новой версии его нужно
// перенаправить на новый файл: `ln -sf SP-Helpdesk-X.Y.Z.apk latest.apk`
// в public/apk/ (та же папка, где лежит version.json).
const apkStableUrl = computed(() => window.location.origin + '/apk/latest.apk')

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

const profileOpen       = ref(false)
const profileSubmitting = ref(false)
const profileErrors     = ref('')
const profileForm = ref({
  name: '', phone: '', email: '', telegram_chat_id: '', max_chat_id: '', notify_on_days_off: false,
})

function openProfile() {
  profileForm.value = {
    name:               props.user?.name ?? '',
    phone:              props.user?.phone ?? '',
    email:              props.user?.email ?? '',
    telegram_chat_id:   props.user?.telegram_chat_id ?? '',
    max_chat_id:        props.user?.max_chat_id ?? '',
    notify_on_days_off: !!props.user?.notify_on_days_off,
  }
  profileErrors.value = ''
  profileOpen.value = true
}

function submitProfile() {
  if (!profileForm.value.name) {
    profileErrors.value = 'Укажите ФИО'
    return
  }
  profileErrors.value = ''
  profileSubmitting.value = true
  router.put(route('profile.update'), profileForm.value, {
    preserveScroll: true,
    onSuccess: () => { profileOpen.value = false },
    onError:   (errors) => { profileErrors.value = Object.values(errors)[0] ?? 'Ошибка сохранения' },
    onFinish:  () => { profileSubmitting.value = false },
  })
}

const qrOpen = ref(false)
const qrCanvas = ref(null)

async function openQr() {
  qrOpen.value = true
  await nextTick()
  if (qrCanvas.value && apk.value?.apk_url) {
    QRCode.toCanvas(qrCanvas.value, apkStableUrl.value, { width: 200, margin: 2 })
  }
}
</script>
