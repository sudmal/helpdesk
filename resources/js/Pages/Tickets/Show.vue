<template>
  <Head :title="`Заявка ${ticket.number}`" />
  <AppLayout :title="ticket.number">
    <template #actions>
      <div class="flex items-center gap-2 flex-wrap">
        <button v-if="canEdit && !ticket.status.is_final"
                @click="$inertia.get(route('tickets.edit', ticket.id))"
                class="btn-outline text-sm" title="Изменить">✏️</button>

        <button v-if="canClose && ticket.status.slug === 'new'"
                @click="doAction('start')"
                class="btn-sm bg-amber-500 hover:bg-amber-600 text-white" title="В работу">▶</button>

        <button v-if="canClose && ticket.status.slug === 'in_progress'"
                @click="doAction('pause')"
                class="btn-sm bg-gray-500 hover:bg-gray-600 text-white" title="Пауза">⏸</button>

        <button v-if="canClose && !ticket.status.is_final"
                @click="showPostponeModal = true"
                class="btn-outline text-sm" title="Перенести">📅</button>

        <button v-if="canClose && !ticket.status.is_final"
                @click="showCloseModal = true"
                class="btn-sm bg-green-600 hover:bg-green-700 text-white" title="Закрыть">✓</button>

        <button v-if="canEdit && ticket.status.is_final"
                @click="doAction('reopen')"
                class="btn-outline text-sm" title="Переоткрыть">↩</button>
      </div>
    </template>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

      <!-- ── Основная информация ── -->
      <div class="xl:col-span-2 space-y-4">

        <!-- Карточка -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 md:p-6">
          <!-- Шапка карточки: адрес слева, время справа, бейджи под адресом -->
          <div class="flex items-start justify-between gap-4 mb-4">
            <!-- Левая часть: номер + адрес + бейджи -->
            <div class="flex-1 min-w-0">
              <span class="text-xs text-gray-400 font-mono">{{ ticket.number }}</span>
              <h2 class="text-base md:text-lg font-semibold mt-0.5 leading-snug">
                {{ ticket.address
                ? [
                    ticket.address.city,
                    ticket.address.street,
                    ticket.address.building  ? 'д.'  + ticket.address.building  : null,
                    (ticket.apartment || ticket.address?.apartment) ? 'кв.' + (ticket.apartment || ticket.address?.apartment) : null,
                    ticket.address.entrance  ? 'под.' + ticket.address.entrance : null,
                  ].filter(Boolean).join(', ')
                : 'Адрес не указан' }}
              </h2>
              <div class="flex flex-wrap gap-1.5 mt-2">
              <Badge v-if="ticket.type"         :color="ticket.type.color"         :label="ticket.type.name" />
              <Badge v-if="ticket.service_type" :color="ticket.service_type.color" :label="ticket.service_type.name" />
              <Badge v-if="ticket.status"       :color="ticket.status.color"       :label="ticket.status.name" />
              <Badge :color="priorityColor" :label="priorityLabel" />
              </div>
            </div>
            <!-- Правая часть: время выезда -->
            <div v-if="ticket.scheduled_at" class="shrink-0 text-right">
              <p class="font-bold text-3xl leading-none tabular-nums"
                 :class="isOverdue ? 'text-red-600' : 'text-green-600'">
                {{ formatTime(ticket.scheduled_at) }}
              </p>
              <p class="text-sm mt-0.5 text-gray-400">
                {{ formatDay(ticket.scheduled_at) }}
              </p>
              <p v-if="isOverdue"
                 class="text-xs text-red-500 font-medium mt-0.5 animate-pulse">
                ⚠ Просрочена
              </p>
            </div>
          </div>

          <!-- Поля — сетка адаптивная -->
          <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm mb-4">
            <div><p class="text-xs text-gray-400 mb-0.5">Телефон</p><p class="text-sm font-medium text-gray-700 break-words">{{ ticket.phone ?? ticket.address?.phone ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400 mb-0.5">Договор</p><p class="text-sm font-medium text-gray-700 break-words">{{ ticket.contract_no ?? ticket.address?.contract_no ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400 mb-0.5">Территория</p><p class="text-sm font-medium text-gray-700 break-words">{{ ticket.address?.territory?.name ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400 mb-0.5">Бригада</p><p class="text-sm font-medium text-gray-700 break-words">{{ ticket.brigade?.name ?? 'Не назначена' ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400 mb-0.5">Создал</p><p class="text-sm font-medium text-gray-700 break-words">{{ ticket.creator?.name ?? '—' }}</p></div>
            <div><p class="text-xs text-gray-400 mb-0.5">Создана</p><p class="text-sm font-medium text-gray-700 break-words">{{ formatDate(ticket.created_at) ?? '—' }}</p></div>
<div><p class="text-xs text-gray-400 mb-0.5">Запланирован</p><p class="text-sm font-medium text-gray-700 break-words">{{ ticket.scheduled_at ? formatDateTime(ticket.scheduled_at) : '—' ?? '—' }}</p></div>
            <div v-if="ticket.closed_at"><p class="text-xs text-gray-400 mb-0.5">Закрыта</p><p class="text-sm font-medium text-gray-700 break-words">{{ formatDate(ticket.closed_at) ?? '—' }}</p></div>
            <div v-if="ticket.act_number"><p class="text-xs text-gray-400 mb-0.5">Акт</p><p class="text-sm font-medium text-gray-700 break-words">{{ ticket.act_number ?? '—' }}</p></div>
          </div>

          <div class="mt-3 bg-gray-50 rounded-xl px-4 py-3 border border-gray-100">
            <p class="text-xs text-gray-400 mb-1.5 font-medium uppercase tracking-wide">Описание</p>
            <p class="text-base text-gray-800 whitespace-pre-wrap leading-relaxed font-medium">
              {{ ticket.description || '—' }}
            </p>
          </div>

          <div v-if="ticket.close_notes" class="mt-3 bg-green-50 rounded-xl p-3">
            <p class="text-xs text-green-600 mb-1 font-medium">{{ ticket.status?.slug === 'postponed' ? 'Причина переноса' : 'Итог закрытия' }}</p>
            <p class="text-sm text-green-800">{{ ticket.close_notes }}</p>
          </div>
        </div>

        <!-- Назначение бригады -->
        <div v-if="canAssign && !ticket.status.is_final"
             class="bg-white rounded-2xl border border-gray-200 p-4">
          <h3 class="font-medium text-sm mb-3 text-gray-700">Назначить бригаду</h3>
          <form @submit.prevent="submitAssign" class="flex gap-2 flex-wrap">
            <select v-model="assignForm.brigade_id"
                    class="flex-1 min-w-[160px] border border-gray-200 rounded-xl px-3 py-2 text-sm">
              <option value="">— Бригада —</option>
              <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <button class="btn-sm bg-blue-600 hover:bg-blue-700 text-white px-4">Назначить</button>
          </form>
        </div>

        <!-- Вложения -->
        <div v-if="ticket.attachments?.length"
             class="bg-white rounded-2xl border border-gray-200 p-4">
          <h3 class="font-medium text-sm mb-3 text-gray-700">Вложения</h3>
          <AttachmentList :attachments="ticket.attachments" />
        </div>

        <!-- Расходные материалы (просмотр) -->
        <div v-if="ticket.materials?.length"
             class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-4">
          <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="font-medium text-sm text-gray-700 flex items-center gap-2">
              📦 Расходные материалы
            </h3>
            <span class="text-sm font-semibold text-blue-600">
              Итого: {{ totalMaterials }} ₽
            </span>
          </div>
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100 text-gray-500">
                <th class="text-center px-3 py-2 w-16">Код</th>
                <th class="text-left px-4 py-2">Наименование</th>
                <th class="text-center px-3 py-2 w-24">Кол-во</th>
                <th class="text-right px-3 py-2 w-24">Цена/ед</th>
                <th class="text-right px-4 py-2 w-24">Сумма</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="m in ticket.materials" :key="m.id"
                  class="hover:bg-gray-50">
                <td class="px-3 py-2 text-center text-gray-400 font-mono text-xs">{{ m.material_code || '—' }}</td>
                <td class="px-4 py-2 text-gray-800">{{ m.material_name }}</td>
                <td class="px-3 py-2 text-center text-gray-600">
                  {{ m.quantity }} {{ m.material_unit }}
                </td>
                <td class="px-3 py-2 text-right text-gray-500 tabular-nums">
                  {{ m.price_at_time }} ₽
                </td>
                <td class="px-4 py-2 text-right font-medium tabular-nums">
                  {{ (m.price_at_time * m.quantity).toFixed(2) }} ₽
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Комментарии -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
          <h3 class="font-medium text-sm mb-4 text-gray-700">
            Комментарии <span class="text-gray-400">({{ ticket.comments?.length ?? 0 }})</span>
          </h3>

          <div class="space-y-3 mb-4">
            <div v-for="c in ticket.comments" :key="c.id"
                 :class="['rounded-xl p-3', c.is_internal ? 'bg-amber-50 border border-amber-100' : 'bg-gray-50']">
              <div class="flex items-center gap-2 mb-2 flex-wrap">
                <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold shrink-0">
                  {{ c.author?.name?.[0] }}
                </div>
                <span class="text-sm font-medium">{{ c.author?.name }}</span>
                <span class="text-xs text-gray-400 ml-auto">{{ formatDate(c.created_at) }}</span>
                <span v-if="c.is_internal" class="text-xs text-amber-600 font-medium">Внутренний</span>
              </div>
              <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ c.body }}</p>
              <AttachmentList v-if="c.attachments?.length" :attachments="c.attachments" class="mt-2" />
            </div>
            <p v-if="!ticket.comments?.length" class="text-sm text-gray-400 text-center py-4">
              Комментариев пока нет
            </p>
          </div>

          <!-- Форма комментария -->
          <form v-if="canComment" @submit.prevent="submitComment"
                class="border-t border-gray-100 pt-3 space-y-2">
            <textarea v-model="commentBody" rows="3"
                      placeholder="Добавить комментарий..."
                      class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm resize-none
                             focus:outline-none focus:ring-2 focus:ring-blue-500/30"></textarea>
            <div class="flex items-center gap-2 flex-wrap">
              <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="commentInternal" class="rounded" />
                Внутренний
              </label>
              <AttachmentUpload v-model="commentFiles" class="flex-1 min-w-[140px]" />
              <button :disabled="!commentBody.trim()"
                      class="btn-sm bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-40">
                Отправить
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- ── Правая колонка ── -->
      <div class="space-y-4">

        <!-- История по адресу -->
        <div v-if="addressHistory?.length"
             class="bg-white rounded-2xl border border-gray-200 p-4">
          <h3 class="font-medium text-sm mb-3 text-gray-700">Предыдущие заявки</h3>
          <div class="space-y-1.5">
            <a v-for="h in addressHistory" :key="h.id"
               :href="route('tickets.show', h.id)"
               class="block px-3 py-2.5 hover:bg-amber-50 rounded-lg transition-colors">
              <div class="flex items-start justify-between gap-2 mb-0.5">
                <div>
                  <span class="text-xs font-mono text-blue-600 font-medium">{{ h.number }}</span>
                  <span class="text-xs text-gray-400 ml-1.5">{{ formatDate(h.created_at) }}</span>
                </div>
                <Badge v-if="h.status" :color="h.status.color" :label="h.status.name" small />
              </div>
              <p class="text-xs text-gray-500">{{ h.type?.name }}</p>
              <p v-if="h.description" class="text-xs text-gray-500 mt-0.5" :title="h.description">
                {{ h.description.slice(0, 70) }}{{ h.description.length > 70 ? '…' : '' }}
              </p>
              <div v-if="h.close_notes || h.act_number"
                   class="mt-1 bg-green-50 rounded px-2 py-1">
                <p v-if="h.act_number" class="text-xs text-green-600 font-medium">Акт: {{ h.act_number }}</p>
                <p v-if="h.close_notes" class="text-xs text-green-700">
                  {{ h.close_notes.slice(0, 60) }}{{ h.close_notes.length > 60 ? '…' : '' }}
                </p>
              </div>
            </a>
          </div>
        </div>

        <!-- История изменений -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
          <h3 class="font-medium text-sm mb-3 text-gray-700">История</h3>
          <div class="space-y-2.5 max-h-80 overflow-y-auto">
            <div v-for="h in ticket.history" :key="h.id" class="text-xs border-l-2 border-gray-100 pl-2">
              <div class="text-gray-400 mb-0.5">
                <span class="font-medium text-gray-600">{{ h.user?.name ?? 'Система' }}</span>
                · {{ formatDate(h.created_at) }}
              </div>
              <p class="text-gray-600">{{ actionLabel(h) }}</p>
            </div>
            <p v-if="!ticket.history?.length" class="text-xs text-gray-400">Нет записей</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Модалка закрытия -->
    <Modal v-if="showCloseModal" title="Закрыть заявку" size="lg" @close="showCloseModal = false">
      <form @submit.prevent="submitClose" class="space-y-3">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Номер акта</label>
          <input v-model="closeActNumber" type="text"
                 placeholder="Оставьте пустым для «б/а»"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
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

        <!-- Расходные материалы -->
        <div class="border-t border-gray-100 pt-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="useMaterials"
                   class="rounded w-4 h-4 text-blue-600" />
            <span class="text-sm text-gray-700">📦 Использовались расходные материалы</span>
          </label>
          <MaterialsForm v-if="useMaterials"
                         :materials="materialsCatalog"
                         v-model="materialItems" />
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <button type="button" @click="showCloseModal = false" class="btn-outline text-sm">Отмена</button>
          <button class="btn-sm bg-green-600 hover:bg-green-700 text-white">Закрыть заявку</button>
        </div>
      </form>
    </Modal>

    <!-- Модалка переноса -->
    <Modal v-if="showPostponeModal" title="Перенести заявку" @close="showPostponeModal = false">
      <form @submit.prevent="submitPostpone" class="space-y-3">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Новая дата и время выезда *</label>
          <TimePicker v-model="postponeDateTime"
                      :work-start="settings?.work_hours_start ?? '09:00'"
                      :work-end="settings?.work_hours_end ?? '17:00'"
                      :step-minutes="Number(settings?.schedule_step_minutes ?? 30)" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Причина переноса</label>
          <textarea v-model="postponeComment" rows="2"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm resize-none"></textarea>
        </div>
        <div class="flex justify-end gap-2 pt-1">
          <button type="button" @click="showPostponeModal = false" class="btn-outline text-sm">Отмена</button>
          <button :disabled="!postponeDateTime" class="btn-sm bg-amber-500 hover:bg-amber-600 text-white disabled:opacity-40">Перенести</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import 'dayjs/locale/ru'
dayjs.locale('ru')
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'
import Modal from '@/Components/UI/Modal.vue'
import TimePicker from '@/Components/UI/TimePicker.vue'
import AttachmentList from '@/Components/Tickets/AttachmentList.vue'
import MaterialsForm from '@/Components/Tickets/MaterialsForm.vue'
import AttachmentUpload from '@/Components/Tickets/AttachmentUpload.vue'

const createdBy = computed(() => {
  // Берём из истории — запись с action=created
  const hist = props.ticket.history ?? []
  const entry = hist.find(h => h.action === 'created')
  if (entry?.user?.name) return entry.user.name
  return props.ticket.creator?.name ?? '—'
})

const totalMaterials = computed(() =>
  (props.ticket.materials ?? []).reduce((s, m) => s + m.price_at_time * m.quantity, 0).toFixed(2)
)

const props = defineProps({
  ticket: Object, addressHistory: Array, statuses: Array, brigades: Array,
  materialsCatalog: { type: Array, default: () => [] },
  canEdit: Boolean, canAssign: Boolean, canClose: Boolean, canComment: Boolean,
  settings: { type: Object, default: () => ({ work_hours_start: '09:00', work_hours_end: '17:00', schedule_step_minutes: 30 }) },
})

// State
const showCloseModal    = ref(false)
const showPostponeModal = ref(false)
const closeActNumber    = ref('')
const closeComment      = ref('')
const closeFiles        = ref([])
const useMaterials      = ref(false)
const materialItems     = ref([{ material_id: '', quantity: 1 }])
const postponeDateTime  = ref('')
const postponeComment   = ref('')
const commentBody       = ref('')
const commentInternal   = ref(false)
const commentFiles      = ref([])

const assignForm = useForm({
  brigade_id: props.ticket.brigade_id ?? '',
})

// Computed
const priorityMap = {
  low:    ['#94a3b8', 'Низкий'],
  normal: ['#3b82f6', 'Обычный'],
  high:   ['#f59e0b', 'Высокий'],
  urgent: ['#ef4444', 'Срочный'],
}
const priorityColor = computed(() => priorityMap[props.ticket.priority]?.[0] ?? '#94a3b8')
const priorityLabel = computed(() => priorityMap[props.ticket.priority]?.[1] ?? props.ticket.priority)

function formatDate(d)     { return d ? dayjs(d).format('DD MMM, HH:mm') : '—' }
function formatDateTime(d) { return d ? dayjs(d).format('DD MMM YYYY HH:mm') : '—' }
function formatTime(d)     { return d ? dayjs(d).format('HH:mm') : '—' }
function formatDay(d)      { return d ? dayjs(d).format('DD MMM YYYY') : '—' }

function actionLabel(h) {
  if (h.action === 'created')        return 'Заявка создана'
  if (h.action === 'deleted')        return 'Заявка удалена'
  if (h.action === 'assigned')       return `Бригада: ${h.old_value ?? '—'} → ${h.new_value ?? '—'}`
  if (h.action === 'status_changed') return `Статус: ${h.old_value ?? '—'} → ${h.new_value ?? '—'}`
  if (h.action === 'field_changed')  return `${h.field ?? 'Поле'}: ${h.old_value ?? '—'} → ${h.new_value ?? '—'}`
  if (h.action === 'postponed')      return `Перенесена: ${h.new_value ?? ''}`
  if (h.action === 'closed')         return 'Заявка закрыта'
  if (h.action === 'cancelled')      return 'Заявка отменена'
  if (h.action === 'reopened')       return 'Заявка переоткрыта'
  // Статусы на русском из старой системы
  const statusMap = {
    'new': 'Новая', 'in_progress': 'В работе', 'paused': 'Пауза',
    'closed': 'Закрыта', 'cancelled': 'Отменена', 'postponed': 'Перенесена',
  }
  return statusMap[h.action] ?? h.new_value ?? h.action
}

// Actions
function doAction(action) {
  router.post(route(`tickets.${action}`, props.ticket.id))
}

function submitAssign() {
  assignForm.post(route('tickets.assign', props.ticket.id))
}

function submitComment() {
  const data = new FormData()
  data.append('body', commentBody.value)
  data.append('is_internal', commentInternal.value ? '1' : '0')
  commentFiles.value.forEach(f => data.append('attachments[]', f))
  router.post(route('tickets.comment', props.ticket.id), data, {
    onSuccess: () => { commentBody.value = ''; commentFiles.value = []; commentInternal.value = false }
  })
}

function submitClose() {
  const data = new FormData()
  data.append('comment', closeComment.value)
  data.append('act_number', closeActNumber.value)
  closeFiles.value.forEach(f => data.append('attachments[]', f))
  // Добавляем расходники
  if (useMaterials.value) {
    const validItems = materialItems.value.filter(i => i.material_id && i.quantity > 0)
    if (validItems.length) {
      data.append('materials', JSON.stringify(validItems))
    }
  }
  router.post(route('tickets.close', props.ticket.id), data, {
    onSuccess: () => {
      showCloseModal.value = false
      closeFiles.value = []
      closeComment.value = ''
      closeActNumber.value = ''
      useMaterials.value = false
      materialItems.value = [{ material_id: '', quantity: 1 }]
    }
  })
}

function submitPostpone() {
  router.post(route('tickets.postpone', props.ticket.id), {
    scheduled_at: postponeDateTime.value,
    comment: postponeComment.value,
  }, {
    onSuccess: () => {
      showPostponeModal.value = false
      postponeDateTime.value = ''
      postponeComment.value = ''
    }
  })
}

// Inline helper component

</script>

<style scoped>
.btn-sm     { @apply px-3 py-1.5 rounded-xl text-sm font-medium transition-colors; }
.btn-outline { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-xl text-sm font-medium transition-colors; }
</style>
