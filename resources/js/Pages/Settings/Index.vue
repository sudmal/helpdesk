<template>
  <Head title="Настройки" />
  <AppLayout title="Настройки">

    <!-- Табы -->
    <div class="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit mb-6 flex-wrap">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
              :class="['px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                       activeTab === tab.key
                         ? 'bg-white shadow text-blue-600'
                         : 'text-gray-600 hover:text-gray-800']">
        {{ tab.label }}
      </button>
    </div>

    <!-- ── Типы заявок ── -->
    <div v-if="activeTab === 'types'" class="bg-white rounded-2xl border border-gray-200">
      <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold">Типы заявок</h2>
        <button @click="openTypeModal()" class="btn-primary text-sm">+ Добавить</button>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-for="t in ticketTypes" :key="t.id"
             class="flex items-center px-4 py-1 gap-2 hover:bg-gray-50">
          <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: t.color }"></span>
          <span class="flex-1 text-sm font-medium">{{ t.name }}</span>
          <span :class="['text-xs px-2 py-0.5 rounded-full',
                         t.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
            {{ t.is_active ? 'Активен' : 'Скрыт' }}
          </span>
          <button @click="openTypeModal(t)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg">✏️</button>
          <button @click="deleteType(t)"    class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg">🗑</button>
        </div>
        <div v-if="!ticketTypes.length" class="px-6 py-8 text-center text-sm text-gray-400">Нет типов заявок</div>
      </div>
    </div>

    <!-- ── Участки (сервисы) ── -->
    <div v-if="activeTab === 'services'" class="bg-white rounded-2xl border border-gray-200">
      <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
        <div>
          <h2 class="font-semibold">Участки</h2>
          <p class="text-xs text-gray-400 mt-0.5">Интернет, КТВ и другие направления обслуживания</p>
        </div>
        <button @click="openServiceModal()" class="btn-primary text-sm">+ Добавить</button>
      </div>
      <div class="p-4 space-y-2">
        <div v-for="(s, idx) in sortableServiceTypes" :key="s.id"
             draggable="true"
             @dragstart="onDragStart('st', idx)"
             @dragover.prevent="onDragOver('st', idx)"
             @dragend="onDragEnd('st')"
             :class="['flex items-center gap-2 px-3 py-1 border rounded-xl transition-colors cursor-grab',
                      dragOver_st === idx ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:bg-gray-50']">
          <span class="text-gray-300 select-none text-lg">⠿</span>
          <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: s.color }"></span>
          <span class="flex-1 text-sm font-medium">{{ s.name }}</span>
          <span :class="['text-xs px-2 py-0.5 rounded-full',
                         s.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
            {{ s.is_active ? 'Активен' : 'Скрыт' }}
          </span>
          <button @click="openServiceModal(s)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg">✏️</button>
          <button @click="deleteService(s)"    class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg">🗑</button>
        </div>
        <div v-if="!sortableServiceTypes?.length" class="py-8 text-center text-sm text-gray-400">
          Нет участков — добавьте Интернет, КТВ и т.д.
        </div>
      </div>
    </div>

    <!-- ── Общие настройки ── -->
    <div v-if="activeTab === 'general'" class="max-w-xl">
      <form @submit.prevent="saveGeneral" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
        <h2 class="font-semibold mb-1">Рабочее время и расписание</h2>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="field-label">Начало рабочего дня</label>
            <input v-model="generalForm.work_hours_start" type="time" class="field-input" />
          </div>
          <div>
            <label class="field-label">Конец рабочего дня</label>
            <input v-model="generalForm.work_hours_end" type="time" class="field-input" />
          </div>
        </div>

        <div>
          <label class="field-label">Шаг времени при записи (минут)</label>
          <select v-model="generalForm.schedule_step_minutes" class="field-input">
            <option value="15">15 минут</option>
            <option value="30">30 минут</option>
            <option value="60">1 час</option>
          </select>
        </div>

        <div>
          <label class="field-label">Хранить вложения (дней)</label>
          <select v-model="generalForm.attachment_ttl_days" class="field-input">
            <option value="90">3 месяца</option>
            <option value="180">6 месяцев</option>
            <option value="365">1 год (по умолчанию)</option>
            <option value="730">2 года</option>
            <option value="0">Бессрочно</option>
          </select>
        </div>

        <div>
          <label class="field-label">Рабочие дни</label>
          <div class="flex gap-2 flex-wrap">
            <label v-for="day in weekDays" :key="day.value"
                   class="flex items-center gap-1.5 text-sm cursor-pointer border border-gray-200
                          rounded-lg px-3 py-1 hover:bg-gray-50 transition-colors"
                   :class="{ 'bg-blue-50 border-blue-300 text-blue-700': generalForm.work_days.includes(day.value) }">
              <input type="checkbox" :value="day.value" v-model="generalForm.work_days" class="hidden" />
              {{ day.label }}
            </label>
          </div>
        </div>

        <div class="border-t border-gray-100 pt-4">
          <h2 class="font-semibold mb-3">🔐 Защита от перебора</h2>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="field-label">Капча после N попыток</label>
              <input v-model.number="generalForm.login_captcha_attempts" type="number" min="1" max="10" class="field-input" />
            </div>
            <div>
              <label class="field-label">Блок IP после N попыток</label>
              <input v-model.number="generalForm.login_block_attempts" type="number" min="2" max="20" class="field-input" />
            </div>
            <div>
              <label class="field-label">Длительность блокировки</label>
              <select v-model.number="generalForm.login_block_minutes" class="field-input">
                <option :value="15">15 минут</option>
                <option :value="30">30 минут</option>
                <option :value="60">1 час</option>
                <option :value="120">2 часа</option>
                <option :value="1440">24 часа</option>
              </select>
            </div>
          </div>
        </div>

        <div class="pt-2">
          <button class="btn-primary text-sm">Сохранить настройки</button>
        </div>
        <p v-if="generalSaved" class="text-sm text-green-600">✓ Настройки сохранены</p>
      </form>
    </div>

    <!-- ── Территории ── -->
    <div v-if="activeTab === 'territories'" class="bg-white rounded-2xl border border-gray-200">
      <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
        <div>
          <h2 class="font-semibold">Территории</h2>
          <p class="text-xs text-gray-400 mt-0.5">Перетащите для изменения порядка вкладок</p>
        </div>
        <button @click="openTerritoryModal(null)"
                class="btn-primary text-sm">+ Добавить</button>
      </div>
      <div class="divide-y divide-gray-100 p-2 space-y-1">
        <div v-if="!sortableTerritories.length" class="text-center py-6 text-gray-400 text-sm">
          Территории не добавлены
        </div>
        <div v-for="(t, idx) in sortableTerritories" :key="t.id"
             draggable="true"
             @dragstart="onDragStart('ter', idx)"
             @dragover.prevent="onDragOver('ter', idx)"
             @dragend="onDragEnd('ter')"
             :class="['flex items-center gap-2 p-2 bg-white border rounded-xl transition-colors cursor-grab',
                      dragOver_ter === idx ? 'border-blue-400 bg-blue-50' : 'border-gray-200']">
          <span class="text-gray-300 select-none text-lg">⠿</span>
          <span class="flex-1 font-medium text-sm text-gray-800">{{ t.name }}</span>
          <button @click="openTerritoryModal(t)"
                  class="text-xs text-blue-600 hover:text-blue-800 mr-2">✏️</button>
          <button @click="deleteTerritory(t)"
                  class="text-xs text-gray-300 hover:text-red-500">✕</button>
        </div>
      </div>
    </div>

    <!-- ── Статусы ── -->
    <div v-if="activeTab === 'statuses'" class="bg-white rounded-2xl border border-gray-200">
      <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold">Статусы заявок</h2>
        <button @click="openStatusModal()" class="btn-primary text-sm">+ Добавить</button>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-for="s in ticketStatuses" :key="s.id"
             class="flex items-center px-4 py-1 gap-2 hover:bg-gray-50">
          <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: s.color }"></span>
          <span class="flex-1 text-sm font-medium">{{ s.name }}</span>
          <span class="text-xs text-gray-400 font-mono">{{ s.slug }}</span>
          <span v-if="s.is_final" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Финальный</span>
          <span v-if="s.requires_comment" class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Требует комментарий</span>
          <button @click="openStatusModal(s)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg">✏️</button>
          <button @click="deleteStatus(s)"    class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg">🗑</button>
        </div>
      </div>
    </div>

    <!-- ── Пользователи ── -->
    <div v-if="activeTab === 'users'" class="bg-white rounded-2xl border border-gray-200">
      <div class="px-4 py-2 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold">Пользователи ({{ users.length }})</h2>
        <button @click="openUserModal()" class="btn-primary text-sm">+ Добавить</button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/50">
              <th class="text-left px-3 py-0.5 text-xs text-gray-500 font-medium">Пользователь</th>
              <th class="text-left px-3 py-0.5 text-xs text-gray-500 font-medium">Роль</th>
              <th class="text-left px-3 py-0.5 text-xs text-gray-500 font-medium">Телефон</th>
              <th class="text-left px-3 py-0.5 text-xs text-gray-500 font-medium">Telegram</th>
              <th class="text-left px-3 py-0.5 text-xs text-gray-500 font-medium">Территории</th>
              <th class="text-left px-3 py-0.5 text-xs text-gray-500 font-medium">Статус</th>
              <th class="px-3 py-0.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!users.length">
              <td colspan="7" class="text-center py-10 text-gray-400">Нет пользователей</td>
            </tr>
            <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50">
              <td class="px-3 py-0.5">
                <p class="font-medium">{{ u.name }}</p>
                <p class="text-xs text-gray-400">{{ u.email }}</p>
              </td>
              <td class="px-3 py-0.5">
                <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">{{ u.role?.name }}</span>
              </td>
              <td class="px-3 py-0.5 text-gray-600">{{ u.phone ?? '—' }}</td>
              <td class="px-3 py-0.5 text-gray-500 text-xs font-mono">{{ u.telegram_chat_id ?? '—' }}</td>
              <td class="px-3 py-0.5 text-xs text-gray-500">
                {{ u.territories?.map(t => t.name).join(', ') || '—' }}
              </td>
              <td class="px-3 py-0.5">
                <span :class="['text-xs px-2 py-0.5 rounded-full',
                               u.is_active
                                 ? 'bg-green-100 text-green-700'
                                 : 'bg-red-100 text-red-600']">
                  {{ u.is_active ? 'Активен' : 'Заблокирован' }}
                </span>
              </td>
              <td class="px-3 py-0.5 text-right whitespace-nowrap">
                <button @click="openUserModal(u)"   class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg">✏️</button>
                <button @click="toggleBlock(u)"
                        class="p-1.5 rounded-lg"
                        :class="u.is_active ? 'text-gray-400 hover:text-amber-500' : 'text-gray-400 hover:text-green-500'">
                  {{ u.is_active ? '🔒' : '🔓' }}
                </button>
                <button @click="deleteUser(u)"      class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg">🗑</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Роли ── -->
    <div v-if="activeTab === 'roles'" class="space-y-3">
      <div v-for="role in roles" :key="role.id"
           class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

        <!-- Заголовок роли -->
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
          <div class="flex items-center gap-3">
            <div :class="['w-9 h-9 rounded-xl flex items-center justify-center text-lg',
                          roleColor(role.slug).bg]">
              {{ roleColor(role.slug).icon }}
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">{{ role.name }}</h3>
              <p class="text-xs text-gray-400 font-mono">{{ role.slug }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span v-if="role.permissions?.includes('*')"
                  class="text-xs bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full font-medium">
              ⚡ Полный доступ
            </span>
            <span v-else class="text-xs text-gray-400">
              {{ (role.permissions ?? []).length }} прав
            </span>
            <button @click="openRoleModal(role)"
                    class="flex items-center gap-1.5 text-sm border border-gray-200
                           hover:bg-gray-50 px-3 py-1 rounded-xl transition-colors text-gray-600">
              ✏️ Редактировать
            </button>
          </div>
        </div>

        <!-- Права сгруппированные -->
        <div v-if="!(role.permissions ?? []).includes('*')" class="px-5 py-4">
          <div v-if="!(role.permissions ?? []).length"
               class="text-sm text-gray-400 italic">Нет назначенных прав</div>
          <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
            <template v-for="group in permissionGroups" :key="group.key">
              <template v-for="perm in group.permissions" :key="perm.key">
                <div v-if="(role.permissions ?? []).includes(perm.key)"
                     class="flex items-center gap-2 px-3 py-2 rounded-xl border text-sm"
                     :class="group.badgeClass ?? 'bg-blue-50 border-blue-100 text-blue-700'">
                  <span>{{ group.icon }}</span>
                  <span class="font-medium truncate">{{ perm.label }}</span>
                </div>
              </template>
            </template>
          </div>
        </div>
        <div v-else class="px-5 py-3 text-sm text-amber-700 bg-amber-50">
          Роль имеет неограниченный доступ ко всем функциям системы
        </div>
      </div>
    </div>

    <!-- ── Уведомления ── -->
    <div v-if="activeTab === 'notifications'" class="space-y-5">
      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-4">Расписание уведомлений</h2>
        <div class="space-y-4 text-sm">
          <div class="flex items-center justify-between py-3 border-b border-gray-100">
            <div>
              <p class="font-medium">Утренняя сводка бригадирам</p>
              <p class="text-gray-400 text-xs">Список заявок на сегодня — отправляется каждый день в 08:00</p>
            </div>
            <span class="text-xs bg-green-100 text-green-700 px-3 py-0.5 rounded-full font-mono">08:00 ежедневно</span>
          </div>
          <div class="flex items-center justify-between py-3 border-b border-gray-100">
            <div>
              <p class="font-medium">Вечерний отчёт руководителям</p>
              <p class="text-gray-400 text-xs">Итоги дня для Администратора и Начальника ТП — в 20:00</p>
            </div>
            <span class="text-xs bg-blue-100 text-blue-700 px-3 py-0.5 rounded-full font-mono">20:00 ежедневно</span>
          </div>
        </div>

        <h3 class="font-semibold mt-6 mb-3">Отправить сейчас</h3>
        <div class="flex gap-3">
          <button @click="sendSummary"   :disabled="sending" class="btn-outline text-sm">
            {{ sending === 'summary' ? 'Отправка...' : '📋 Утренняя сводка' }}
          </button>
          <button @click="sendReport"    :disabled="sending" class="btn-outline text-sm">
            {{ sending === 'report' ? 'Отправка...' : '📊 Вечерний отчёт' }}
          </button>
        </div>
        <p v-if="sendResult" :class="['mt-3 text-sm', sendResult.ok ? 'text-green-600' : 'text-red-600']">
          {{ sendResult.message }}
        </p>
      </div>

      <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="font-semibold mb-1">Каналы уведомлений</h2>
        <p class="text-xs text-gray-400 mb-4">Настраиваются индивидуально для каждого пользователя во вкладке «Пользователи»</p>
        <div class="grid grid-cols-3 gap-4">
          <div class="border border-gray-200 rounded-xl p-4 text-center">
            <p class="text-2xl mb-1">✉️</p>
            <p class="text-sm font-medium">Email</p>
            <p class="text-xs text-gray-400">SMTP</p>
          </div>
          <div class="border border-gray-200 rounded-xl p-4 text-center">
            <p class="text-2xl mb-1">✈️</p>
            <p class="text-sm font-medium">Telegram</p>
            <p class="text-xs text-gray-400">Bot API</p>
          </div>
          <div class="border border-gray-200 rounded-xl p-4 text-center">
            <p class="text-2xl mb-1">💬</p>
            <p class="text-sm font-medium">Max</p>
            <p class="text-xs text-gray-400">Мессенджер</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── LANBilling ── -->
    <div v-if="activeTab === 'lanbilling'" class="max-w-xl">
      <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-gray-800">LANBilling интеграция</p>
          <p class="text-xs text-gray-400">Блок поиска абонента при создании заявки</p>
        </div>
        <div class="flex items-center gap-2 cursor-pointer select-none"
             @click="toggleLanbilling">
          <span class="text-sm" :class="settingsForm.lanbilling_enabled ? 'text-green-600' : 'text-gray-400'">
            {{ settingsForm.lanbilling_enabled ? 'Включена' : 'Выключена' }}
          </span>
          <div :class="['relative w-11 h-6 rounded-full transition-colors',
                        settingsForm.lanbilling_enabled ? 'bg-green-500' : 'bg-gray-300']">
            <div :class="['absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform',
                          settingsForm.lanbilling_enabled ? 'translate-x-5' : 'translate-x-0.5']"></div>
          </div>
        </div>
      </div>
      <form @submit.prevent="saveLanbilling" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg">🔗</div>
          <div>
            <h2 class="font-semibold">LANBilling API</h2>
            <p class="text-xs text-gray-400">Поиск абонентов по телефону и договору</p>
          </div>
        </div>
        <div>
          <label class="field-label">URL API *</label>
          <input v-model="lbForm.url" type="url" required class="field-input" placeholder="http://billing.example.com/api" />
        </div>
        <div>
          <label class="field-label">Логин *</label>
          <input v-model="lbForm.login" required class="field-input" />
        </div>
        <div>
          <label class="field-label">Пароль</label>
          <input v-model="lbForm.password" type="password" class="field-input" placeholder="Оставьте пустым чтобы не менять" />
        </div>
        <div class="flex gap-3 pt-2">
          <button :disabled="lbForm.processing" class="btn-primary text-sm">Сохранить</button>
          <button type="button" @click="testLanbilling" :disabled="lbTesting" class="btn-outline text-sm">
            {{ lbTesting ? 'Проверка...' : '🔌 Проверить' }}
          </button>
        </div>
        <div v-if="lbResult"
             :class="['rounded-xl p-3 text-sm', lbResult.ok ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800']">
          {{ lbResult.message }}
        </div>
      </form>
    </div>

    <!-- ══ МОДАЛКИ ══════════════════════════════════════════════════ -->

    <!-- Тип заявки -->
    <Modal v-if="showTypeModal" :title="editingType ? 'Редактировать тип' : 'Новый тип'" @close="closeTypeModal">
      <form @submit.prevent="submitType" class="space-y-4">
        <div>
          <label class="field-label">Название *</label>
          <input v-model="typeForm.name" required class="field-input" />
        </div>
        <div>
          <label class="field-label">Цвет</label>
          <input v-model="typeForm.color" type="color" class="h-10 w-20 rounded cursor-pointer border border-gray-200" />
        </div>
        <label class="flex items-center gap-2 text-sm cursor-pointer">
          <input type="checkbox" v-model="typeForm.is_active" class="rounded" /> Активен
        </label>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="closeTypeModal" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editingType ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Статус заявки -->
    <Modal v-if="showStatusModal" :title="editingStatus ? 'Редактировать статус' : 'Новый статус'" @close="closeStatusModal">
      <form @submit.prevent="submitStatus" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="field-label">Название *</label>
            <input v-model="statusForm.name" required class="field-input" />
          </div>
          <div>
            <label class="field-label">Slug * (латиница, _)</label>
            <input v-model="statusForm.slug" required class="field-input" placeholder="in_progress" />
          </div>
        </div>
        <div>
          <label class="field-label">Цвет</label>
          <input v-model="statusForm.color" type="color" class="h-10 w-20 rounded cursor-pointer border border-gray-200" />
        </div>
        <div class="flex gap-4 flex-wrap">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="statusForm.is_final" class="rounded" /> Финальный
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="statusForm.requires_comment" class="rounded" /> Требует комментарий
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="statusForm.is_active" class="rounded" /> Активен
          </label>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="closeStatusModal" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editingStatus ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Пользователь -->
    <Modal v-if="showUserModal" :title="editingUser ? 'Редактировать пользователя' : 'Новый пользователь'" @close="closeUserModal">
      <form @submit.prevent="submitUser" class="space-y-4">
        <div class="space-y-3">
          <!-- ФИО + Логин -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label">Фамилия Имя *</label>
              <input v-model="userForm.name" required class="field-input" placeholder="Иванов Иван" />
            </div>
            <div>
              <label class="field-label">Логин *</label>
              <input v-model="userForm.login" required class="field-input" placeholder="ivanov" autocomplete="off" />
            </div>
          </div>
          <!-- Email + Телефон -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label">Email</label>
              <input v-model="userForm.email" type="email" class="field-input" placeholder="email@example.com" autocomplete="off" />
            </div>
            <div>
              <label class="field-label">Телефон</label>
              <input v-model="userForm.phone" class="field-input" placeholder="+7..." />
            </div>
          </div>
          <!-- Роль -->
          <div>
            <label class="field-label">Роль *</label>
            <select v-model="userForm.role_id" required class="field-input">
              <option value="">— Выбрать роль —</option>
              <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>
          <!-- Бригада -->
          <div>
            <label class="field-label">Бригада</label>
            <select v-model="userForm.brigade_id" class="field-input">
              <option value="">— Не в бригаде —</option>
              <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <!-- TG ID + MAX ID в одной строке -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label">Telegram Chat ID</label>
              <input v-model="userForm.telegram_chat_id" class="field-input" placeholder="123456789" autocomplete="off" />
            </div>
            <div>
              <label class="field-label">MAX ID</label>
              <input v-model="userForm.max_chat_id" class="field-input" placeholder="ID в Max" autocomplete="off" />
            </div>
          </div>
          <!-- Пароль + Повтор в одной строке -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label">{{ editingUser ? 'Новый пароль (если нужно сменить)' : 'Пароль *' }}</label>
              <input v-model="userForm.password" type="password"
                     :required="!editingUser"
                     autocomplete="new-password"
                     class="field-input"
                     :placeholder="editingUser ? 'Оставьте пустым — не изменится' : 'Минимум 8 символов'" />
            </div>
            <div>
              <label class="field-label">Повтор пароля</label>
              <input v-model="userForm.password_confirmation" type="password"
                     autocomplete="new-password"
                     class="field-input" />
            </div>
          </div>
        </div>
        <!-- Территории -->
        <div>
          <label class="field-label">Доступные территории</label>
          <div class="border border-gray-200 rounded-xl p-3 max-h-32 overflow-y-auto space-y-1">
            <label v-for="t in territories" :key="t.id"
                   class="flex items-center gap-2 text-sm cursor-pointer p-1 hover:bg-gray-50 rounded">
              <input type="checkbox" :value="t.id" v-model="userForm.territory_ids" class="rounded" />
              {{ t.name }}
            </label>
            <p v-if="!territories.length" class="text-xs text-gray-400">Нет территорий</p>
          </div>
        </div>
        <div class="flex gap-4 flex-wrap">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="userForm.notify_email" class="rounded" /> Email
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="userForm.notify_telegram" class="rounded" /> Telegram
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="userForm.notify_max" class="rounded" /> Max
          </label>
          <label v-if="editingUser" class="flex items-center gap-2 text-sm cursor-pointer">
            <input type="checkbox" v-model="userForm.is_active" class="rounded" /> Активен
          </label>
        </div>
        <div v-if="userForm.errors && Object.keys(userForm.errors).length"
             class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700">
          <p v-for="(err, field) in userForm.errors" :key="field">{{ err }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="closeUserModal" class="btn-outline text-sm">Отмена</button>
          <button :disabled="userForm.processing" class="btn-primary text-sm">
            {{ editingUser ? 'Сохранить' : 'Создать' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- Роль -->
    <Modal v-if="showRoleModal" title="Редактировать роль" @close="showRoleModal = false">
      <form @submit.prevent="submitRole" class="space-y-4">
        <div>
          <label class="field-label">Название роли</label>
          <input v-model="roleForm.name" required class="field-input" />
        </div>

        <!-- Группы прав -->
        <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
          <div v-for="group in permissionGroups" :key="group.key"
               class="border border-gray-200 rounded-xl overflow-hidden">
            <!-- Заголовок группы -->
            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-b border-gray-200">
              <div class="flex items-center gap-2">
                <span>{{ group.icon }}</span>
                <span class="font-medium text-sm text-gray-700">{{ group.label }}</span>
              </div>
              <div class="flex gap-3">
                <button type="button" @click="selectGroup(group, true)"
                        class="text-xs text-blue-600 hover:text-blue-800">Все</button>
                <button type="button" @click="selectGroup(group, false)"
                        class="text-xs text-gray-400 hover:text-gray-600">Снять</button>
              </div>
            </div>
            <!-- Права группы -->
            <div class="grid grid-cols-2 gap-0 divide-y divide-gray-100">
              <label v-for="perm in group.permissions" :key="perm.key"
                     class="flex items-center gap-2.5 px-4 py-2 hover:bg-blue-50
                            cursor-pointer transition-colors text-sm"
                     :class="{ 'col-span-2': perm.wide }">
                <input type="checkbox"
                       :value="perm.key"
                       :checked="roleForm.permissions.includes(perm.key) || roleForm.permissions.includes('*')"
                       :disabled="roleForm.permissions.includes('*') && perm.key !== '*'"
                       @change="togglePerm(perm.key, $event.target.checked)"
                       class="rounded w-4 h-4 text-blue-600" />
                <div>
                  <p class="text-sm font-medium text-gray-700">{{ perm.label }}</p>
                  <p class="text-xs text-gray-400">{{ perm.desc }}</p>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Итог -->
        <div class="bg-gray-50 rounded-xl px-4 py-2.5 text-xs text-gray-500">
          Активных прав: <span class="font-medium text-blue-600">{{ roleForm.permissions.length }}</span>
          <span v-if="roleForm.permissions.includes('*')" class="ml-2 text-amber-600 font-medium">
            ⚠ Полный доступ (все права)
          </span>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="showRoleModal = false" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">Сохранить</button>
        </div>
      </form>
    </Modal>

    <!-- Модалка участка -->
    <Modal v-if="showServiceModal" :title="editingService ? 'Редактировать участок' : 'Новый участок'" @close="closeServiceModal">
      <form @submit.prevent="submitService" class="space-y-4">
        <div>
          <label class="field-label">Название *</label>
          <input v-model="serviceForm.name" required class="field-input" placeholder="Интернет, КТВ..." />
        </div>
        <div>
          <label class="field-label">Цвет</label>
          <input v-model="serviceForm.color" type="color" class="h-10 w-20 rounded cursor-pointer border border-gray-200" />
        </div>
        <label class="flex items-center gap-2 text-sm cursor-pointer">
          <input type="checkbox" v-model="serviceForm.is_active" class="rounded" /> Активен
        </label>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="closeServiceModal" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editingService ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>

  <!-- Модалка территории -->
  <Modal v-if="showTerritoryModal"
         :title="editingTerritory ? 'Редактировать территорию' : 'Новая территория'"
         @close="showTerritoryModal = false">
    <form @submit.prevent="submitTerritory" class="space-y-4">
      <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Название *</label>
        <input v-model="territoryForm.name" required
               class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
      </div>
      <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
        <button type="button" @click="showTerritoryModal = false"
                class="px-4 py-2 text-sm border border-gray-200 rounded-xl hover:bg-gray-50 text-gray-600">
          Отмена
        </button>
        <button type="submit"
                class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
          Сохранить
        </button>
      </div>
    </form>
  </Modal>

  </AppLayout>
</template>

<script setup>
import { ref, computed , watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Modal from '@/Components/UI/Modal.vue'

const props = defineProps({
  lanbillingEnabled: { type: Boolean, default: true },
  ticketTypes:    Array,
  ticketStatuses: Array,
  serviceTypes:   { type: Array, default: () => [] },
  users:          { type: Array, default: () => [] },
  roles:          { type: Array, default: () => [] },
  territories:    { type: Array, default: () => [] },
  brigades:       { type: Array, default: () => [] },
  lanbillingConfig:  { type: Object, default: () => ({}) },
  generalSettings:   { type: Object, default: () => ({}) },
})

const activeTab = ref('types')
const tabs = [
  { key: 'types',         label: 'Типы заявок' },
  { key: 'services',      label: 'Участки' },
  { key: 'territories',   label: 'Территории' },
  { key: 'statuses',      label: 'Статусы' },
  { key: 'users',         label: 'Пользователи' },
  { key: 'roles',         label: 'Роли' },
  { key: 'general',       label: 'Общие' },
  { key: 'notifications', label: 'Уведомления' },
  { key: 'lanbilling',    label: 'LANBilling' },
]

// ── Типы ────────────────────────────────────────────────────────────
const showTypeModal = ref(false)
const editingType   = ref(null)
const typeForm = useForm({ name: '', color: '#3b82f6', is_active: true, sort_order: 0 })

function openTypeModal(t = null) {
  editingType.value = t
  if (t) Object.assign(typeForm, { name: t.name, color: t.color, is_active: t.is_active })
  else typeForm.reset()
  showTypeModal.value = true
}
function closeTypeModal() { showTypeModal.value = false; editingType.value = null; typeForm.reset() }
function submitType() {
  const opts = { onSuccess: closeTypeModal }
  editingType.value
    ? typeForm.put(route('settings.ticket-types.update', editingType.value.id), opts)
    : typeForm.post(route('settings.ticket-types.store'), opts)
}
function deleteType(t) {
  if (confirm(`Удалить тип «${t.name}»?`)) router.delete(route('settings.ticket-types.destroy', t.id))
}

// ── Статусы ──────────────────────────────────────────────────────────
const showStatusModal = ref(false)
const editingStatus   = ref(null)
const statusForm = useForm({ name: '', slug: '', color: '#6366f1', is_final: false, requires_comment: false, is_active: true })

function openStatusModal(s = null) {
  editingStatus.value = s
  if (s) Object.assign(statusForm, { name: s.name, slug: s.slug, color: s.color, is_final: s.is_final, requires_comment: s.requires_comment, is_active: s.is_active })
  else statusForm.reset()
  showStatusModal.value = true
}
function closeStatusModal() { showStatusModal.value = false; editingStatus.value = null; statusForm.reset() }
function submitStatus() {
  const opts = { onSuccess: closeStatusModal }
  editingStatus.value
    ? statusForm.put(route('settings.ticket-statuses.update', editingStatus.value.id), opts)
    : statusForm.post(route('settings.ticket-statuses.store'), opts)
}
function deleteStatus(s) {
  if (confirm(`Удалить статус «${s.name}»?`)) router.delete(route('settings.ticket-statuses.destroy', s.id))
}

// ── Пользователи ─────────────────────────────────────────────────────
const showUserModal = ref(false)
const editingUser   = ref(null)
const userForm = useForm({
  name: '', login: '', email: '', phone: '', role_id: '',
  telegram_chat_id: '', max_chat_id: '',
  password: '', password_confirmation: '',
  notify_email: true, notify_telegram: false, notify_max: false,
  is_active: true, territory_ids: [], brigade_id: '',
})

function openUserModal(u = null) {
  editingUser.value = u
  if (u) {
    userForm.name              = u.name            ?? ''
    userForm.login             = u.login           ?? ''
    userForm.email             = u.email           ?? ''
    userForm.phone             = u.phone           ?? ''
    userForm.role_id           = u.role_id         ?? ''
    userForm.telegram_chat_id  = u.telegram_chat_id ?? ''
    userForm.max_chat_id       = u.max_chat_id     ?? ''
    userForm.password          = ''
    userForm.password_confirmation = ''
    userForm.notify_email      = u.notify_email    ?? true
    userForm.notify_telegram   = u.notify_telegram ?? false
    userForm.notify_max        = u.notify_max      ?? false
    userForm.is_active         = u.is_active       ?? true
    userForm.territory_ids     = u.territories?.map(t => t.id) ?? []
    userForm.brigade_id        = u.brigades?.[0]?.id ?? ''
  } else {
    userForm.name = ''; userForm.login = ''; userForm.email = ''
    userForm.phone = ''; userForm.role_id = ''
    userForm.telegram_chat_id = ''; userForm.max_chat_id = ''
    userForm.password = ''; userForm.password_confirmation = ''
    userForm.notify_email = true; userForm.notify_telegram = false
    userForm.notify_max = false; userForm.is_active = true
    userForm.territory_ids = []
    userForm.brigade_id = ''
  }
  showUserModal.value = true
}
function closeUserModal() { showUserModal.value = false; editingUser.value = null }
function submitUser() {
  const opts = { onSuccess: closeUserModal }
  editingUser.value
    ? userForm.put(route('settings.users.update', editingUser.value.id), opts)
    : userForm.post(route('settings.users.store'), opts)
}
function toggleBlock(u) {
  if (confirm(`${u.is_active ? 'Заблокировать' : 'Разблокировать'} пользователя «${u.name}»?`)) {
    router.put(route('settings.users.update', u.id), { ...u, is_active: !u.is_active, role_id: u.role_id })
  }
}
function deleteUser(u) {
  if (confirm(`Удалить пользователя «${u.name}»? Это действие необратимо.`)) {
    router.delete(route('settings.users.destroy', u.id))
  }
}

// ── Роли ─────────────────────────────────────────────────────────────
const showRoleModal = ref(false)
const editingRole   = ref(null)
const roleForm = useForm({ name: '', permissions: [] })

// Цвета для ролей
function roleColor(slug) {
  const map = {
    admin:        { bg: 'bg-red-100',    icon: '👑' },
    head_support: { bg: 'bg-purple-100', icon: '🎯' },
    operator:     { bg: 'bg-blue-100',   icon: '🖥️' },
    foreman:      { bg: 'bg-amber-100',  icon: '👷' },
    technician:   { bg: 'bg-green-100',  icon: '🔧' },
  }
  return map[slug] ?? { bg: 'bg-gray-100', icon: '👤' }
}

// Каталог всех прав системы
const permissionGroups = [
  {
    key: 'system', icon: '⚡', label: 'Полный доступ', badgeClass: 'bg-amber-50 border-amber-100 text-amber-700',
    permissions: [
      { key: '*', label: 'Суперадминистратор', desc: 'Все права без ограничений', wide: true },
    ]
  },
  {
    key: 'tickets', icon: '📋', label: 'Заявки', badgeClass: 'bg-blue-50 border-blue-100 text-blue-700',
    permissions: [
      { key: 'tickets.view',    label: 'Просмотр',    desc: 'Видеть список и карточки заявок' },
      { key: 'tickets.create',  label: 'Создание',    desc: 'Создавать новые заявки' },
      { key: 'tickets.update',  label: 'Редактирование', desc: 'Изменять данные заявки' },
      { key: 'tickets.delete',  label: 'Удаление',    desc: 'Удалять заявки' },
      { key: 'tickets.assign',  label: 'Назначение',  desc: 'Назначать бригаду на заявку' },
      { key: 'tickets.start',   label: 'В работу',    desc: 'Переводить заявку в статус "В работу"' },
      { key: 'tickets.close',   label: 'Закрытие',    desc: 'Закрывать и отменять заявки' },
      { key: 'tickets.comment', label: 'Комментарии', desc: 'Добавлять комментарии к заявкам' },
    ]
  },
  {
    key: 'addresses', icon: '📍', label: 'Адреса', badgeClass: 'bg-green-50 border-green-100 text-green-700',
    permissions: [
      { key: 'addresses.view',   label: 'Просмотр',  desc: 'Просматривать базу адресов' },
      { key: 'addresses.create', label: 'Создание',  desc: 'Добавлять новые адреса' },
      { key: 'addresses.update', label: 'Изменение', desc: 'Редактировать адреса' },
      { key: 'addresses.delete', label: 'Удаление',  desc: 'Удалять адреса' },
      { key: 'addresses.import', label: 'Импорт',    desc: 'Импортировать адреса из файла' },
    ]
  },
  {
    key: 'calendar', icon: '📅', label: 'Календарь', badgeClass: 'bg-purple-50 border-purple-100 text-purple-700',
    permissions: [
      { key: 'calendar.view', label: 'Просмотр', desc: 'Видеть календарь заявок' },
    ]
  },
  {
    key: 'settings', icon: '⚙️', label: 'Настройки', badgeClass: 'bg-gray-50 border-gray-200 text-gray-700',
    permissions: [
      { key: 'settings.view',          label: 'Просмотр',          desc: 'Видеть раздел настроек' },
      { key: 'settings.edit',          label: 'Редактирование',     desc: 'Изменять настройки системы' },
      { key: 'users.operators.create', label: 'Создание операторов', desc: 'Добавлять операторов и диспетчеров' },
      { key: 'users.operators.delete', label: 'Удаление операторов', desc: 'Удалять операторов и диспетчеров' },
    ]
  },
  {
    key: 'materials', icon: '📦', label: 'Расходные материалы', badgeClass: 'bg-teal-50 border-teal-100 text-teal-700',
    permissions: [
      { key: 'materials.view',   label: 'Просмотр',     desc: 'Видеть справочник материалов' },
      { key: 'materials.manage', label: 'Управление',   desc: 'Добавлять и редактировать материалы' },
    ]
  },
  {
    key: 'brigades', icon: '👷', label: 'Бригады и территории', badgeClass: 'bg-amber-50 border-amber-100 text-amber-700',
    permissions: [
      { key: 'brigades.view',      label: 'Просмотр бригад',    desc: 'Видеть список бригад' },
      { key: 'brigades.manage',    label: 'Управление бригадами', desc: 'Создавать и редактировать бригады' },
      { key: 'territories.view',   label: 'Просмотр участков',  desc: 'Видеть список участков' },
      { key: 'territories.manage', label: 'Управление участками', desc: 'Создавать и редактировать участки' },
    ]
  },
]

function openRoleModal(r) {
  editingRole.value = r
  roleForm.name        = r.name
  roleForm.permissions = Array.isArray(r.permissions) ? [...r.permissions] : []
  showRoleModal.value  = true
}

function togglePerm(key, checked) {
  if (checked) {
    if (!roleForm.permissions.includes(key)) {
      roleForm.permissions.push(key)
    }
  } else {
    roleForm.permissions = roleForm.permissions.filter(p => p !== key)
  }
}

function selectGroup(group, selectAll) {
  group.permissions.forEach(perm => {
    if (selectAll) {
      if (!roleForm.permissions.includes(perm.key)) {
        roleForm.permissions.push(perm.key)
      }
    } else {
      roleForm.permissions = roleForm.permissions.filter(p => p !== perm.key)
    }
  })
}

function submitRole() {
  router.put(route('settings.roles.update', editingRole.value.id), {
    name:        roleForm.name,
    permissions: roleForm.permissions,
  }, { onSuccess: () => { showRoleModal.value = false } })
}

// ── Участки ──────────────────────────────────────────────────────────
const showServiceModal = ref(false)
const editingService   = ref(null)
const serviceForm = useForm({ name: '', color: '#3b82f6', is_active: true, sort_order: 0 })

function openServiceModal(s = null) {
  editingService.value = s
  if (s) Object.assign(serviceForm, { name: s.name, color: s.color, is_active: s.is_active })
  else serviceForm.reset()
  showServiceModal.value = true
}
function closeServiceModal() { showServiceModal.value = false; editingService.value = null; serviceForm.reset() }
function submitService() {
  const opts = { onSuccess: closeServiceModal }
  editingService.value
    ? serviceForm.put(route('settings.services.update', editingService.value.id), opts)
    : serviceForm.post(route('settings.services.store'), opts)
}
function deleteService(s) {
  if (confirm(`Удалить участок «${s.name}»?`)) router.delete(route('settings.services.destroy', s.id))
}

// ── Общие настройки ───────────────────────────────────────────────────
const weekDays = [
  { value: '1', label: 'Пн' }, { value: '2', label: 'Вт' },
  { value: '3', label: 'Ср' }, { value: '4', label: 'Чт' },
  { value: '5', label: 'Пт' }, { value: '6', label: 'Сб' },
  { value: '7', label: 'Вс' },
]
const generalSaved = ref(false)
const generalForm = useForm({
  work_hours_start:      props.generalSettings?.work_hours_start ?? '09:00',
  work_hours_end:        props.generalSettings?.work_hours_end ?? '17:00',
  schedule_step_minutes: props.generalSettings?.schedule_step_minutes ?? '30',
  attachment_ttl_days:   props.generalSettings?.attachment_ttl_days ?? '365',
  work_days:             (props.generalSettings?.work_days ?? '1,2,3,4,5').split(','),
  login_captcha_attempts:  props.generalSettings?.login_captcha_attempts ?? 3,
  login_block_attempts:    props.generalSettings?.login_block_attempts ?? 6,
  login_block_minutes:     props.generalSettings?.login_block_minutes ?? 60,
})

function saveGeneral() {
  generalForm.put(route('settings.general.update'), {
    onSuccess: () => { generalSaved.value = true; setTimeout(() => generalSaved.value = false, 3000) }
  })
}

// ── Уведомления ──────────────────────────────────────────────────────
const sending    = ref(null)
const sendResult = ref(null)

async function sendSummary() {
  sending.value = 'summary'
  sendResult.value = null
  try {
    await axios.post(route('settings.notifications.send-summary'))
    sendResult.value = { ok: true, message: 'Утренняя сводка отправлена!' }
  } catch (e) {
    sendResult.value = { ok: false, message: e.response?.data?.message ?? 'Ошибка отправки' }
  } finally { sending.value = null }
}

async function sendReport() {
  sending.value = 'report'
  sendResult.value = null
  try {
    await axios.post(route('settings.notifications.send-report'))
    sendResult.value = { ok: true, message: 'Вечерний отчёт отправлен!' }
  } catch (e) {
    sendResult.value = { ok: false, message: e.response?.data?.message ?? 'Ошибка отправки' }
  } finally { sending.value = null }
}

// ── LANBilling ───────────────────────────────────────────────────────
const settingsForm = ref({ lanbilling_enabled: props.lanbillingEnabled })

async function toggleLanbilling() {
  settingsForm.value.lanbilling_enabled = !settingsForm.value.lanbilling_enabled
  await router.put(route('settings.general.update'), { lanbilling_enabled: settingsForm.value.lanbilling_enabled }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const lbForm    = useForm({ url: props.lanbillingConfig?.url ?? '', login: props.lanbillingConfig?.login ?? '', password: '' })
const lbTesting = ref(false)
const lbResult  = ref(null)

function saveLanbilling() {
  lbForm.put(route('settings.lanbilling.update'), { onSuccess: () => { lbResult.value = { ok: true, message: 'Настройки сохранены' } } })
}
// ── Сортировка вкладок ───────────────────────────────────────────
const sortableServiceTypes = ref([...(props.serviceTypes ?? [])])
const sortableTerritories  = ref([...(props.territories  ?? [])])
const dragOver_st  = ref(null)
const dragOver_ter = ref(null)
let dragIdx  = null
let dragType = null

watch(() => props.serviceTypes, v => { sortableServiceTypes.value = [...(v ?? [])] })
watch(() => props.territories,  v => { sortableTerritories.value  = [...(v ?? [])] })

// Территории
const showTerritoryModal = ref(false)
const editingTerritory   = ref(null)
const territoryForm      = ref({ name: '' })

function openTerritoryModal(t) {
  editingTerritory.value = t
  territoryForm.value = { name: t?.name ?? '' }
  showTerritoryModal.value = true
}

function submitTerritory() {
  if (editingTerritory.value) {
    router.put(route('territories.update', editingTerritory.value.id), territoryForm.value, {
      onSuccess: () => { showTerritoryModal.value = false }
    })
  } else {
    router.post(route('territories.store'), territoryForm.value, {
      onSuccess: () => { showTerritoryModal.value = false }
    })
  }
}

function deleteTerritory(t) {
  if (confirm(`Удалить территорию "${t.name}"?`)) {
    router.delete(route('territories.destroy', t.id))
  }
}

function onDragStart(type, idx) { dragType = type; dragIdx = idx }
function onDragOver(type, idx) {
  if (type === 'st')  dragOver_st.value  = idx
  if (type === 'ter') dragOver_ter.value = idx
  if (dragType !== type || dragIdx === idx) return
  const arr = type === 'st' ? sortableServiceTypes : sortableTerritories
  const items = [...arr.value]
  const [moved] = items.splice(dragIdx, 1)
  items.splice(idx, 0, moved)
  arr.value = items
  dragIdx = idx
}
async function onDragEnd(type) {
  dragOver_st.value  = null
  dragOver_ter.value = null
  const arr = type === 'st' ? sortableServiceTypes : sortableTerritories
  const name = type === 'st' ? 'settings.sort.service-types' : 'settings.sort.territories'
  try {
    await axios.post(route(name), { order: arr.value.map(i => i.id) })
  } catch(e) { console.error('Sort save failed', e) }
}

async function testLanbilling() {
  lbTesting.value = true; lbResult.value = null
  try {
    await axios.get(route('lanbilling.lookup'), { params: { phone: '70000000000' } })
    lbResult.value = { ok: true, message: 'API отвечает корректно' }
  } catch (e) {
    lbResult.value = e.response?.status === 404
      ? { ok: true,  message: 'API работает (абонент не найден — это нормально для теста)' }
      : { ok: false, message: e.response?.data?.message ?? 'Ошибка подключения' }
  } finally { lbTesting.value = false }
}
</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors disabled:opacity-40; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors disabled:opacity-40; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 bg-slate-50; }
</style>
