<template>
  <Head title="Дашборд" />
  <AppLayout title="Дашборд">

    <!-- ── Тосты: новые заявки ── -->
    <Teleport to="body">
      <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
        <TransitionGroup name="toast">
          <div v-for="toast in toasts" :key="toast.id"
               class="pointer-events-auto bg-white border border-green-200 shadow-lg rounded-xl
                      px-4 py-3 flex items-start gap-3 w-72">
            <span class="text-green-500 text-base leading-none mt-0.5">🟢</span>
            <div class="flex-1 min-w-0 cursor-pointer" @click="router.visit(route('tickets.show', toast.ticketId))">
              <p class="text-xs font-semibold text-gray-700">Новая заявка #{{ toast.number }}</p>
              <p class="text-xs text-gray-400 truncate">{{ toast.address }}</p>
            </div>
            <button @click="removeToast(toast.id)"
                    class="text-gray-300 hover:text-gray-500 text-base leading-none shrink-0">✕</button>
          </div>
        </TransitionGroup>
      </div>
    </Teleport>

    <!-- ── Переключатель участков + дата ── -->
    <div class="bg-white rounded-2xl border border-gray-200 px-3 py-1.5 mb-1 flex items-center gap-2 flex-wrap">
      <span class="text-xs text-gray-400 font-medium">Участок:</span>
      <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
        <button v-for="st in serviceTypes" :key="st.id"
                @click="navigate({ service_type: st.id })"
                :class="['px-2.5 py-0.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1',
                         serviceType === st.id
                           ? 'bg-white shadow-sm text-gray-800'
                           : 'text-gray-500 hover:text-gray-700']">
          {{ serviceIcon(st.name) }} {{ st.name }}
          <span v-if="st.has_open" class="text-orange-500 font-bold text-sm leading-none">✱</span>
        </button>
        <button @click="navigate({ service_type: null })"
                :class="['px-2.5 py-0.5 rounded-lg text-xs font-medium transition-colors',
                         !serviceType
                           ? 'bg-white shadow-sm text-gray-800'
                           : 'text-gray-500 hover:text-gray-700']">
          Все
        </button>
      </div>
      <!-- Подключения ожидают -->
      <a v-if="pendingConnectionsCount > 0"
         :href="route('connection-requests.index', { status: 'pending' })"
         class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-orange-100 text-orange-700 text-xs font-medium hover:bg-orange-200 transition-colors whitespace-nowrap">
        🔌 {{ pendingConnectionsCount }} подключений
      </a>
      <!-- Запросы услуг ожидают -->
      <a v-if="pendingServiceRequestsCount > 0"
         :href="route('service-requests.index', { status: 'pending' })"
         class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-purple-100 text-purple-700 text-xs font-medium hover:bg-purple-200 transition-colors whitespace-nowrap">
        ⚙️ {{ pendingServiceRequestsCount }} запросов услуг
      </a>
      <!-- Только невыполненные -->
      <button @click="navigate({ only_open: !props.onlyOpen || undefined })"
              :class="['flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-xs font-medium transition-colors border',
                       props.onlyOpen
                         ? 'bg-blue-600 text-white border-blue-600'
                         : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300']">
        <span>{{ props.onlyOpen ? '●' : '○' }}</span> Только открытые
      </button>
      <!-- Дата справа -->
      <div class="flex items-center gap-1 ml-auto">
        <button @click="changeDate(-1)"
                class="px-1.5 py-1 rounded-lg hover:bg-gray-100 transition-colors text-gray-500">‹</button>
        <input type="date" :value="selectedDate" @change="changeDate(0, $event.target.value)"
               class="border border-gray-200 rounded-lg px-2 py-1 text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        <button @click="changeDate(1)"
                class="px-1.5 py-1 rounded-lg hover:bg-gray-100 transition-colors text-gray-500">›</button>
        <button @click="changeDate(0, today)"
                class="text-xs text-blue-600 hover:text-blue-800 font-medium px-1.5">Сег.</button>
      </div>
    </div>

    <!-- ── Основная таблица заявок ── -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-4">
      <!-- Вкладки территорий -->
      <div class="bg-gray-50 border-b border-gray-200 flex items-end px-2 pt-1 flex-wrap">
        <button v-for="t in territories" :key="t.id"
                @click="selectTerritory(t.id)"
                :class="['px-3 py-1 text-xs font-medium flex flex-col items-center gap-0.5 rounded-t-xl transition-colors relative min-w-[80px] border-r border-gray-200 last:border-r-0',
                         selectedTerritory === t.id
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          <span class="leading-tight whitespace-nowrap">{{ t.name }}</span>
          <div class="flex items-center gap-1 h-[18px]">
            <span v-if="t.overdue_count > 0"
                  class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-xs font-bold bg-orange-500 text-white leading-none">
              {{ t.overdue_count }}
            </span>
            <span v-if="t.open_count > 0"
                  class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-xs font-bold bg-red-500 text-white leading-none">
              {{ t.open_count }}
            </span>
            <span v-if="t.closed_count > 0"
                  class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-xs font-bold bg-green-500 text-white leading-none">
              {{ t.closed_count }}
            </span>
            <span v-if="!t.overdue_count && !t.open_count && !t.closed_count"
                  class="text-xs text-gray-300">—</span>
          </div>
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/50 text-gray-500 font-medium">
              <th class="w-5 px-1 py-2.5"></th>
              <th class="w-6 px-2 py-2.5"></th>
              <th class="px-3 py-2.5 text-left cursor-pointer hover:bg-gray-100 w-20"
                  @click="sortBy('scheduled_at')">
                Время {{ sortIcon('scheduled_at') }}
              </th>
              <th class="px-2 py-1.5 text-left cursor-pointer hover:bg-gray-100 w-20"
                  @click="sortBy('number')">
                № {{ sortIcon('number') }}
              </th>
              <th class="px-2 py-1.5 text-left">Адрес / Описание</th>
              <th class="px-2 py-1.5 text-left hidden md:table-cell">Тип</th>
              <th class="px-2 py-1.5 text-left hidden lg:table-cell">Телефон</th>
              <th class="px-2 py-1.5 text-left cursor-pointer hover:bg-gray-100"
                  @click="sortBy('status_id')">
                Статус {{ sortIcon('status_id') }}
              </th>
              <th class="px-2 py-1.5 text-left text-gray-500 w-14">Акт</th>
              <th class="px-2 py-1.5 text-left text-gray-500">Комментарий</th>
              <th class="px-2 py-1.5 w-16"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!todayTickets?.length">
              <td colspan="11" class="text-center py-10 text-gray-400">
                Заявок на {{ formatDateLabel(selectedDate) }} нет
              </td>
            </tr>
            <tr v-for="t in (todayTickets ?? [])" :key="t.id"
                :class="['cursor-pointer transition-all', t.status?.is_final ? 'opacity-60' : '']"
                :style="{ backgroundColor: (t.status?.color ?? '#6b7280') + '1a' }"
                @mouseenter="e => { e.currentTarget.style.filter='brightness(0.93)'; showTooltip(e, t) }"
                @mouseleave="e => { e.currentTarget.style.filter=''; tooltip.show = false }"
                @click="router.visit(route('tickets.show', t.id))">

              <!-- Полоска типа / галочка для закрытых -->
              <td class="pr-0 py-0 w-5 relative">
                <div v-if="!t.status?.is_final"
                     class="absolute inset-y-0 left-0 w-[3px] rounded-r"
                     :style="{ backgroundColor: t.type?.color ?? '#9ca3af' }"></div>
                <span v-if="t.status?.is_final" class="text-green-500 font-bold text-sm pl-2">✓</span>
              </td>
              <!-- Иконка участка -->
              <td class="pl-1.5 pr-1 py-0.5 text-center text-sm leading-none">
                {{ serviceIcon(t.service_type?.name) }}
              </td>
              <td class="px-2 py-0.5 font-medium tabular-nums text-gray-700 whitespace-nowrap text-xs">
                {{ formatTime(t.scheduled_at) }}
              </td>
              <td class="px-2 py-0.5">
                <span class="font-mono text-blue-600 font-medium text-xs">{{ t.number }}</span>
              </td>
              <td class="px-2 py-0.5 max-w-xs w-64">
                <p class="font-medium text-gray-800 truncate text-xs leading-tight">{{ fullAddress(t) }}</p>
                <p class="text-gray-600 text-xs leading-tight" :class="expandedDesc.has(t.id) ? 'whitespace-normal' : 'truncate'">
                  <span>{{ expandedDesc.has(t.id) ? t.description : t.description?.slice(0, 90) }}</span>
                  <button v-if="(t.description?.length ?? 0) > 90" @click.stop="toggleDesc(t.id)"
                          class="ml-0.5 text-blue-400 hover:text-blue-600 font-medium text-[10px] leading-none align-middle">
                    {{ expandedDesc.has(t.id) ? '[↑]' : '[…]' }}
                  </button>
                </p>
              </td>
              <td class="px-2 py-0.5 hidden md:table-cell">
                <Badge v-if="t.type" :color="t.type.color" :label="t.type.name" small />
              </td>
              <td class="px-2 py-0.5 hidden lg:table-cell text-gray-600 text-xs whitespace-nowrap">{{ t.phone ?? '—' }}</td>
              <td class="px-2 py-0.5">
                <Badge v-if="t.status" :color="t.status.color" :label="t.status.name" small />
              </td>
              <td class="px-2 py-0.5 w-14">
                <span v-if="t.status?.is_final"
                      class="text-xs font-medium text-green-700 bg-green-100 px-1.5 py-0.5 rounded whitespace-nowrap">
                  {{ t.act_number || 'б/а' }}
                </span>
              </td>
              <td class="px-2 pr-1 py-0.5 min-w-[120px] max-w-[200px]">
                <p v-if="t.status?.is_final && t.close_notes"
                   class="text-xs text-gray-400 truncate leading-tight" :title="t.close_notes">
                  {{ t.close_notes }}
                </p>
              </td>
              <!-- Кнопка закрыть -->
              <td class="px-2 py-0.5 text-right" @click.stop>
                <button v-if="!t.status?.is_final"
                        @click="openCloseModal(t)"
                        class="text-xs text-green-600 hover:text-green-800 border border-green-200
                               hover:border-green-400 rounded-lg px-2 py-0.5 transition-colors whitespace-nowrap">
                  Закрыть
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── ПРОСРОЧЕННЫЕ ── -->
    <div v-if="overdue?.length" ref="overdueSection"
         class="bg-red-50 border border-red-200 rounded-2xl overflow-hidden">
      <div class="px-4 py-3 border-b border-red-200 flex items-center justify-between flex-wrap gap-2">
        <h2 class="font-semibold text-red-700 text-sm flex items-center gap-2">
          <input type="checkbox" :checked="selectAllOverdue" @change="toggleSelectAllOverdue"
                 class="rounded border-red-300 cursor-pointer" />
          ⚠ Требуют внимания — просроченные
          <span class="bg-red-600 text-white text-xs px-2 py-0.5 rounded-full">{{ overdue?.length }}</span>
        </h2>
        <div class="flex items-center gap-2">
          <template v-if="selectedOverdue.size > 0">
            <span class="text-xs text-red-700 font-medium">Выбрано: {{ selectedOverdue.size }}</span>
            <button @click="bulkCloseOverdueModal = true"
                    class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded-lg font-medium transition-colors">
              ✓ Закрыть
            </button>
            <button @click="bulkRescheduleOverdueModal = true"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded-lg font-medium transition-colors">
              📅 Перенести
            </button>
            <button @click="selectedOverdue = new Set()" class="text-xs text-red-400 hover:text-red-700">✕</button>
          </template>
          <a :href="route('tickets.index', { overdue: 1, service_type: serviceType, territory: selectedTerritory })"
             class="text-xs text-red-600 hover:text-red-800 font-medium">Открыть список →</a>
        </div>
      </div>
      <div>
      <table class="w-full text-xs">
        <tbody class="divide-y divide-red-100">
          <tr v-for="t in (overdue ?? [])" :key="t.id"
              class="hover:bg-red-100/50 cursor-pointer transition-colors"
              @click="router.visit(route('tickets.show', t.id))">
            <td class="pl-2 pr-0 py-px text-center w-7" @click.stop>
              <input type="checkbox" :checked="selectedOverdue.has(t.id)"
                     @change="toggleOverdueSelect(t.id)"
                     class="rounded border-red-300 cursor-pointer" />
            </td>
            <td class="pl-1 pr-1 py-px text-center w-6">{{ serviceIcon(t.service_type?.name) }}</td>
            <td class="px-3 py-px w-20">
              <span class="font-mono text-red-700 font-medium">{{ t.number }}</span>
            </td>
            <td class="px-3 py-px">
              <p class="font-medium text-gray-800 truncate max-w-[180px]">{{ fullAddress(t) }}</p>
              <p class="text-gray-500 text-xs" :class="expandedDesc.has(t.id) ? 'whitespace-normal' : 'truncate max-w-[180px]'">
                <span>{{ expandedDesc.has(t.id) ? t.description : t.description?.slice(0, 90) }}</span>
                <button v-if="(t.description?.length ?? 0) > 90" @click.stop="toggleDesc(t.id)"
                        class="ml-0.5 text-blue-400 hover:text-blue-600 font-medium text-[10px] leading-none align-middle">
                  {{ expandedDesc.has(t.id) ? '[↑]' : '[…]' }}
                </button>
              </p>
            </td>
            <td class="px-3 py-px hidden sm:table-cell">
              <Badge v-if="t.type" :color="t.type.color" :label="t.type.name" small />
            </td>
            <td class="px-3 py-px">
              <Badge v-if="t.status" :color="t.status.color" :label="t.status.name" small />
            </td>
            <td class="px-3 py-px hidden md:table-cell text-gray-500">{{ t.phone ?? '—' }}</td>
            <td class="px-3 py-px text-red-600 font-medium whitespace-nowrap text-right pr-4">
              {{ formatDateTime(t.scheduled_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <!-- ── Модалка закрытия ── -->
    <Modal v-if="closingTicket" :title="`Закрыть заявку #${closingTicket.number}`" size="lg"
           @close="closingTicket = null">
      <form @submit.prevent="submitClose" class="space-y-3">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Номер акта</label>
          <input v-model="closeActNumber" type="text"
                 placeholder="Оставьте пустым для «б/а»"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm
                        focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
          <p class="text-xs text-gray-400 mt-1">Если не заполнено — будет «б/а» (без акта)</p>
        </div>
        <div v-if="$page.props.closeReasons?.length">
          <label class="block text-xs text-gray-500 mb-1">Причина <span class="text-gray-400">(шаблон)</span></label>
          <select @change="e => { closeComment = e.target.value; e.target.selectedIndex = 0 }"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30">
            <option value="">— выбрать из шаблонов —</option>
            <option v-for="r in $page.props.closeReasons" :key="r" :value="r">{{ r }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Комментарий</label>
          <textarea v-model="closeComment" rows="3"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm resize-none"></textarea>
        </div>
        <AttachmentUpload v-model="closeFiles" label="Прикрепить фото/документы" />
        <div class="border-t border-gray-100 pt-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="useMaterials" class="rounded w-4 h-4 text-blue-600" />
            <span class="text-sm text-gray-700">📦 Использовались расходные материалы</span>
          </label>
          <MaterialsForm v-if="useMaterials" :materials="materialsCatalog" v-model="materialItems" />
        </div>
        <div class="flex justify-end gap-2 pt-1">
          <button type="button" @click="closingTicket = null" class="btn-outline text-sm">Отмена</button>
          <button class="btn-sm bg-green-600 hover:bg-green-700 text-white">Закрыть заявку</button>
        </div>
      </form>
    </Modal>

    <!-- Модалка: массовое закрытие просроченных -->
    <Modal v-if="bulkCloseOverdueModal" title="Закрыть просроченные" @close="bulkCloseOverdueModal = false">
      <div class="w-80 space-y-3">
        <p class="text-sm text-gray-600">Выбрано заявок: <b>{{ selectedOverdue.size }}</b></p>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Номер акта <span class="text-gray-400">(если нет — будет «б/а»)</span></label>
          <input v-model="bulkOverdueCloseForm.act_number" type="text" placeholder="б/а"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Комментарий</label>
          <textarea v-model="bulkOverdueCloseForm.comment" rows="3" placeholder="Что было сделано..."
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30"></textarea>
        </div>
        <div class="flex gap-2 justify-end pt-1 border-t border-gray-100">
          <button @click="bulkCloseOverdueModal = false" class="btn-outline text-sm">Отмена</button>
          <button @click="doBulkCloseOverdue" :disabled="bulkOverdueLoading"
                  class="btn-primary text-sm disabled:opacity-50">
            {{ bulkOverdueLoading ? 'Закрываем...' : 'Закрыть ' + selectedOverdue.size + ' заявок' }}
          </button>
        </div>
      </div>
    </Modal>

    <!-- Модалка: массовый перенос просроченных -->
    <Modal v-if="bulkRescheduleOverdueModal" title="Перенести просроченные" @close="bulkRescheduleOverdueModal = false">
      <div class="w-80 space-y-3">
        <p class="text-sm text-gray-600">Выбрано заявок: <b>{{ selectedOverdue.size }}</b></p>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Новая дата и время <span class="text-red-400">*</span></label>
          <input v-model="bulkOverdueRescheduleForm.scheduled_at" type="datetime-local"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Комментарий</label>
          <textarea v-model="bulkOverdueRescheduleForm.comment" rows="3" placeholder="Причина переноса..."
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30"></textarea>
        </div>
        <div class="flex gap-2 justify-end pt-1 border-t border-gray-100">
          <button @click="bulkRescheduleOverdueModal = false" class="btn-outline text-sm">Отмена</button>
          <button @click="doBulkRescheduleOverdue" :disabled="!bulkOverdueRescheduleForm.scheduled_at || bulkOverdueLoading"
                  class="btn-primary text-sm disabled:opacity-50">
            {{ bulkOverdueLoading ? 'Переносим...' : 'Перенести ' + selectedOverdue.size + ' заявок' }}
          </button>
        </div>
      </div>
    </Modal>

  </AppLayout>

    <!-- Тултип при наведении на строку дашборда -->
    <Teleport to="body">
      <div v-if="tooltip.show && tooltip.ticket"
           :style="{ position: 'fixed', left: tooltip.x + 'px', top: tooltip.y + 'px', zIndex: 9999 }"
           class="bg-gray-900 text-white rounded-xl shadow-2xl p-3 w-72 pointer-events-none text-xs">
        <div class="flex items-center gap-2 mb-1.5">
          <span class="font-mono text-gray-400">{{ tooltip.ticket.number }}</span>
          <span v-if="tooltip.ticket.type"
                class="px-1.5 py-0.5 rounded text-[11px] font-medium"
                :style="{ backgroundColor: tooltip.ticket.type.color + '33', color: tooltip.ticket.type.color }">
            {{ tooltip.ticket.type.name }}
          </span>
        </div>
        <p class="font-semibold text-sm mb-1 leading-tight">{{ fullAddress(tooltip.ticket) }}</p>
        <p v-if="tooltip.ticket.description" class="text-gray-300 mb-1.5 leading-snug">
          {{ tooltip.ticket.description }}
        </p>
        <template v-if="tooltip.ticket.status?.is_final">
          <div class="border-t border-gray-700 pt-1.5 mt-1 flex flex-col gap-1">
            <div v-if="tooltip.ticket.act_number" class="flex gap-1.5">
              <span class="text-gray-500">Акт:</span>
              <span class="text-green-400 font-medium">{{ tooltip.ticket.act_number }}</span>
            </div>
            <div v-if="tooltip.ticket.close_notes" class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">Итог:</span>
              <span class="text-gray-300">{{ tooltip.ticket.close_notes }}</span>
            </div>
            <div v-if="tooltip.ticket.materials?.length" class="flex gap-1.5">
              <span class="text-gray-500 shrink-0">Матер.:</span>
              <span class="text-gray-300">{{ tooltip.ticket.materials.map(m => m.name + (m.qty > 1 ? ' ×' + m.qty : '')).join(', ') }}</span>
            </div>
          </div>
        </template>
        <div v-if="tooltip.ticket.phone" class="text-gray-400 mt-1 whitespace-nowrap">📞 {{ tooltip.ticket.phone }}</div>
      </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, reactive, onMounted, onUnmounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import 'dayjs/locale/ru'
dayjs.locale('ru')
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'
import Modal from '@/Components/UI/Modal.vue'
import AttachmentUpload from '@/Components/Tickets/AttachmentUpload.vue'
import MaterialsForm from '@/Components/Tickets/MaterialsForm.vue'
import axios from 'axios'

const props = defineProps({
  todayTickets:      { type: Array,  default: () => [] },
  overdue:           { type: Array,  default: () => [] },
  territories:       { type: Array,  default: () => [] },
  serviceTypes:      { type: Array,  default: () => [] },
  materialsCatalog:  { type: Array,  default: () => [] },
  selectedDate:      String,
  selectedTerritory: Number,
  serviceType:       Number,
  sort:              { type: String, default: 'scheduled_at' },
  sortDir:           { type: String, default: 'asc' },
  onlyOpen:          { type: Boolean, default: false },
  pendingConnectionsCount: { type: Number, default: 0 },
  pendingServiceRequestsCount: { type: Number, default: 0 },
})

// ── Тосты ──
const toasts = ref([])
function addToast(ticket) {
  const id = Date.now() + Math.random()
  toasts.value.push({ id, ticketId: ticket.id, number: ticket.number, address: ticket.address })
  setTimeout(() => removeToast(id), 7000)
}
function removeToast(id) {
  toasts.value = toasts.value.filter(t => t.id !== id)
}

// ── Поллинг новых заявок ──
const knownIds    = ref(new Set((props.todayTickets ?? []).map(t => t.id)))
const pollSince   = ref(Math.floor(Date.now() / 1000))
let   pollTimer   = null

async function checkNewTickets() {
  try {
    const params = new URLSearchParams({ since: pollSince.value })
    if (props.selectedTerritory) params.set('territory', props.selectedTerritory)
    if (props.serviceType)       params.set('service_type', props.serviceType)
    const resp = await fetch(`/dashboard/new-since?${params}`, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    if (!resp.ok) return
    const data = await resp.json()
    const fresh = data.filter(t => !knownIds.value.has(t.id))
    fresh.forEach(t => { knownIds.value.add(t.id); addToast(t) })
    if (fresh.length) pollSince.value = Math.floor(Date.now() / 1000)
  } catch { /* silent */ }
}

onMounted(()   => { pollTimer = setInterval(checkNewTickets, 30_000) })
onUnmounted(() => clearInterval(pollTimer))

// ── Модалка закрытия ──
const closingTicket  = ref(null)
const closeActNumber = ref('')
const closeComment   = ref('')
const closeFiles     = ref([])
const useMaterials   = ref(false)
const materialItems  = ref([{ material_id: '', quantity: 1 }])

function openCloseModal(t) {
  closingTicket.value  = t
  closeActNumber.value = ''
  closeComment.value   = ''
  closeFiles.value     = []
  useMaterials.value   = false
  materialItems.value  = [{ material_id: '', quantity: 1 }]
}

function submitClose() {
  const data = new FormData()
  data.append('comment',    closeComment.value)
  data.append('act_number', closeActNumber.value)
  closeFiles.value.forEach(f => data.append('attachments[]', f))
  if (useMaterials.value) {
    const valid = materialItems.value.filter(i => i.material_id && i.quantity > 0)
    if (valid.length) data.append('materials', JSON.stringify(valid))
  }
  router.post(route('tickets.close', closingTicket.value.id), data, {
    onSuccess: () => {
      closingTicket.value  = null
      closeFiles.value     = []
      closeComment.value   = ''
      closeActNumber.value = ''
      useMaterials.value   = false
      materialItems.value  = [{ material_id: '', quantity: 1 }]
    },
  })
}

// ── Навигация ──
const overdueSection = ref(null)
const today = dayjs().format('YYYY-MM-DD')

function navigate(extra = {}) {
  router.get(route('dashboard'), {
    date:         props.selectedDate,
    territory:    props.selectedTerritory,
    service_type: props.serviceType,
    sort:         props.sort,
    dir:          props.sortDir,
    only_open:    props.onlyOpen || undefined,
    ...extra,
  }, { preserveState: true, replace: true })
}

function selectTerritory(id) { navigate({ territory: id }) }

function changeDate(delta, value = null) {
  const d = value ?? dayjs(props.selectedDate).add(delta, 'day').format('YYYY-MM-DD')
  navigate({ date: d })
}

function sortBy(field) {
  const dir = props.sort === field && props.sortDir === 'asc' ? 'desc' : 'asc'
  navigate({ sort: field, dir })
}

function sortIcon(field) {
  if (props.sort !== field) return '↕'
  return props.sortDir === 'asc' ? '↑' : '↓'
}

// ── Expand/collapse description ──
const expandedDesc = ref(new Set())
function toggleDesc(id) {
  const s = new Set(expandedDesc.value)
  if (s.has(id)) s.delete(id); else s.add(id)
  expandedDesc.value = s
}

// ── Форматирование ──
function formatTime(d)      { return d ? dayjs(d).format('HH:mm') : '—' }
function formatDateTime(d)  { return d ? dayjs(d).format('DD MMM HH:mm') : '—' }
function formatDateLabel(d) {
  const dt = dayjs(d)
  if (dt.isSame(dayjs(), 'day'))              return 'сегодня'
  if (dt.isSame(dayjs().add(1, 'day'), 'day')) return 'завтра'
  return dt.format('DD.MM.YYYY')
}

const SERVICE_ICONS = { 'интернет': '🌐', 'inet': '🌐', 'ктв': '📺', 'ctv': '📺', 'волс': '🔆', 'подключ': '🟢' }
function serviceIcon(name) {
  if (!name) return '📋'
  const k = name.toLowerCase()
  for (const [key, icon] of Object.entries(SERVICE_ICONS)) {
    if (k.includes(key)) return icon
  }
  return '📋'
}

function fullAddress(t) {
  const a = t.address
  if (!a) return '—'
  const apt = t.apartment || a.apartment
  return [a.street, a.building ? 'д.' + a.building : null, apt ? 'кв.' + apt : null]
    .filter(Boolean).join(' ')
}
// ── Массовые операции по просроченным ──
const selectedOverdue          = ref(new Set())
const bulkCloseOverdueModal    = ref(false)
const bulkRescheduleOverdueModal = ref(false)
const bulkOverdueLoading       = ref(false)
const bulkOverdueCloseForm     = ref({ comment: '', act_number: '' })
const bulkOverdueRescheduleForm = ref({ scheduled_at: '', comment: '' })

const selectAllOverdue = computed(() => {
  const ids = props.overdue?.map(t => t.id) ?? []
  return ids.length > 0 && ids.every(id => selectedOverdue.value.has(id))
})

function toggleOverdueSelect(id) {
  const s = new Set(selectedOverdue.value)
  if (s.has(id)) s.delete(id); else s.add(id)
  selectedOverdue.value = s
}

function toggleSelectAllOverdue() {
  const ids = props.overdue?.map(t => t.id) ?? []
  selectedOverdue.value = selectAllOverdue.value ? new Set() : new Set(ids)
}

async function doBulkCloseOverdue() {
  bulkOverdueLoading.value = true
  try {
    await axios.post(route('tickets.bulk.close'), {
      ids: [...selectedOverdue.value],
      comment: bulkOverdueCloseForm.value.comment,
      act_number: bulkOverdueCloseForm.value.act_number,
    })
    bulkCloseOverdueModal.value = false
    selectedOverdue.value = new Set()
    bulkOverdueCloseForm.value = { comment: '', act_number: '' }
    router.reload({ only: ['overdue', 'todayTickets'], preserveState: true })
  } finally { bulkOverdueLoading.value = false }
}

async function doBulkRescheduleOverdue() {
  bulkOverdueLoading.value = true
  try {
    await axios.post(route('tickets.bulk.reschedule'), {
      ids: [...selectedOverdue.value],
      scheduled_at: bulkOverdueRescheduleForm.value.scheduled_at,
      comment: bulkOverdueRescheduleForm.value.comment,
    })
    bulkRescheduleOverdueModal.value = false
    selectedOverdue.value = new Set()
    bulkOverdueRescheduleForm.value = { scheduled_at: '', comment: '' }
    router.reload({ only: ['overdue', 'todayTickets'], preserveState: true })
  } finally { bulkOverdueLoading.value = false }
}

// ── Тултип ──
const tooltip = reactive({ show: false, x: 0, y: 0, ticket: null })
function showTooltip(e, t) {
  tooltip.x = Math.min(e.clientX + 16, window.innerWidth - 300)
  tooltip.y = Math.max(e.clientY - 10, 8)
  tooltip.ticket = t
  tooltip.show = true
}


</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all .3s ease; }
.toast-enter-from { opacity: 0; transform: translateX(1rem); }
.toast-leave-to   { opacity: 0; transform: translateX(1rem); }
</style>

