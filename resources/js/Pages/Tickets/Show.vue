<template>
  <Head :title="`Заявка ${ticket.number}`" />
  <AppLayout :title="ticket.number">
    <template #actions>
      <!-- Действия по статусу -->
      <button v-if="canEdit && !ticket.status.is_final"
              @click="$inertia.get(route('tickets.edit', ticket.id))"
              class="btn-outline text-sm">Редактировать</button>

      <button v-if="canClose && ticket.status.slug === 'new'"
              @click="confirmAction('start')"
              class="btn-primary text-sm bg-amber-500 hover:bg-amber-600">▶ В работу</button>

      <button v-if="canClose && ticket.status.slug === 'in_progress'"
              @click="confirmAction('pause')"
              class="btn-outline text-sm">⏸ Приостановить</button>

      <button v-if="canClose && !ticket.status.is_final"
              @click="showCloseModal = true"
              class="btn-primary text-sm bg-green-600 hover:bg-green-700">✓ Закрыть</button>

      <button v-if="canEdit && ticket.status.is_final"
              @click="confirmAction('reopen')"
              class="btn-outline text-sm">↩ Переоткрыть</button>
    </template>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

      <!-- Левая колонка: основная информация -->
      <div class="xl:col-span-2 space-y-5">

        <!-- Карточка заявки -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
          <div class="flex items-start justify-between mb-5">
            <div>
              <span class="text-xs text-gray-400 font-mono">{{ ticket.number }}</span>
              <h2 class="text-xl font-semibold mt-0.5">{{ ticket.address?.full_address ?? 'Адрес не указан' }}</h2>
            </div>
            <div class="flex gap-2">
              <Badge :color="ticket.type.color" :label="ticket.type.name" />
              <Badge :color="ticket.status.color" :label="ticket.status.name" />
              <Badge :color="priorityColor" :label="priorityLabel" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4 text-sm mb-5">
            <InfoRow label="Телефон"    :value="ticket.phone ?? ticket.address?.phone ?? '—'" />
            <InfoRow label="Договор"    :value="ticket.contract_no ?? ticket.address?.contract_no ?? '—'" />
            <InfoRow label="Бригада"    :value="ticket.brigade?.name ?? 'Не назначена'" />
            <InfoRow label="Монтажник"  :value="ticket.assignee?.name ?? '—'" />
            <InfoRow label="Создал"     :value="ticket.creator?.name" />
            <InfoRow label="Создана"    :value="formatDate(ticket.created_at)" />
            <InfoRow label="Выезд"      :value="ticket.scheduled_at ? formatDate(ticket.scheduled_at) : '—'" />
            <InfoRow label="Закрыта"    :value="ticket.closed_at ? formatDate(ticket.closed_at) : '—'" />
          </div>

          <div class="border-t border-gray-100 pt-4">
            <p class="text-xs text-gray-400 mb-1">Описание</p>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ ticket.description }}</p>
          </div>

          <div v-if="ticket.close_notes" class="mt-4 bg-green-50 rounded-xl p-4">
            <p class="text-xs text-green-600 mb-1 font-medium">Итог при закрытии</p>
            <p class="text-sm text-green-800">{{ ticket.close_notes }}</p>
          </div>
        </div>

        <!-- Назначение бригады (Оператор/Бригадир/Админ) -->
        <div v-if="canAssign" class="bg-white rounded-2xl border border-gray-200 p-5">
          <h3 class="font-medium text-sm mb-3 text-gray-700">Назначить бригаду</h3>
          <form @submit.prevent="submitAssign" class="flex gap-3 flex-wrap">
            <select v-model="assignForm.brigade_id"
                    class="flex-1 min-w-[180px] border border-gray-200 rounded-xl px-3 py-2 text-sm">
              <option value="">— Выберите бригаду —</option>
              <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
            <select v-model="assignForm.user_id"
                    class="flex-1 min-w-[180px] border border-gray-200 rounded-xl px-3 py-2 text-sm">
              <option value="">— Выберите монтажника —</option>
              <option v-for="m in selectedBrigadeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <button class="btn-primary text-sm">Назначить</button>
          </form>
        </div>

        <!-- Вложения к заявке -->
        <div v-if="ticket.attachments?.length" class="bg-white rounded-2xl border border-gray-200 p-5">
          <h3 class="font-medium text-sm mb-3 text-gray-700">Вложения</h3>
          <AttachmentList :attachments="ticket.attachments" />
        </div>

        <!-- Комментарии -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
          <h3 class="font-medium text-sm mb-4 text-gray-700">
            Комментарии ({{ ticket.comments?.length ?? 0 }})
          </h3>

          <div class="space-y-4 mb-6">
            <div v-for="comment in ticket.comments" :key="comment.id"
                 :class="['rounded-xl p-4', comment.is_internal ? 'bg-amber-50 border border-amber-100' : 'bg-gray-50']">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold">
                  {{ comment.author?.name?.[0] }}
                </div>
                <span class="text-sm font-medium">{{ comment.author?.name }}</span>
                <span class="text-xs text-gray-400 ml-auto">{{ formatDate(comment.created_at) }}</span>
                <span v-if="comment.is_internal" class="text-xs text-amber-600 font-medium">Внутренний</span>
              </div>
              <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ comment.body }}</p>
              <AttachmentList v-if="comment.attachments?.length" :attachments="comment.attachments" class="mt-3" />
            </div>

            <div v-if="!ticket.comments?.length" class="text-center text-sm text-gray-400 py-6">
              Комментариев пока нет
            </div>
          </div>

          <!-- Форма комментария -->
          <form v-if="canComment" @submit.prevent="submitComment" class="border-t border-gray-100 pt-4">
            <textarea v-model="commentForm.body" rows="3"
                      placeholder="Добавить комментарий..."
                      class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none
                             focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400"></textarea>
            <div class="flex items-center gap-3 mt-3">
              <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="commentForm.is_internal" class="rounded" />
                Внутренний
              </label>
              <AttachmentUpload v-model="commentFiles" class="flex-1" />
              <button :disabled="!commentForm.body.trim()" class="btn-primary text-sm">
                Отправить
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Правая колонка: история -->
      <div class="space-y-5">

        <!-- История по адресу -->
        <div v-if="addressHistory?.length" class="bg-white rounded-2xl border border-gray-200 p-5">
          <h3 class="font-medium text-sm mb-3 text-gray-700">
            Предыдущие заявки по адресу
          </h3>
          <div class="space-y-2">
            <Link v-for="h in addressHistory" :key="h.id"
                  :href="route('tickets.show', h.id)"
                  class="flex items-center justify-between hover:bg-gray-50 rounded-lg p-2 transition-colors">
              <div>
                <p class="text-xs font-mono text-blue-600">{{ h.number }}</p>
                <p class="text-xs text-gray-500">{{ h.type?.name }}</p>
              </div>
              <Badge :color="h.status?.color" :label="h.status?.name" small />
            </Link>
          </div>
        </div>

        <!-- Лог изменений -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
          <h3 class="font-medium text-sm mb-3 text-gray-700">История изменений</h3>
          <div class="space-y-3">
            <div v-for="h in ticket.history" :key="h.id" class="text-xs">
              <div class="flex items-center gap-1.5 text-gray-400">
                <span class="font-medium text-gray-600">{{ h.user?.name ?? 'Система' }}</span>
                <span>·</span>
                <span>{{ formatDate(h.created_at) }}</span>
              </div>
              <p class="text-gray-600 mt-0.5">
                {{ actionLabel(h) }}
              </p>
            </div>
            <p v-if="!ticket.history?.length" class="text-xs text-gray-400">Нет записей</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Модалка закрытия -->
    <Modal v-if="showCloseModal" title="Закрыть заявку" @close="showCloseModal = false">
      <form @submit.prevent="submitClose">
        <p class="text-sm text-gray-600 mb-3">Укажите итог работы (необязательно):</p>
        <textarea v-model="closeForm.comment" rows="4"
                  class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none mb-3"></textarea>
        <AttachmentUpload v-model="closeFiles" label="Прикрепить фото/документы" />
        <div class="flex justify-end gap-3 mt-4">
          <button type="button" @click="showCloseModal = false" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm bg-green-600 hover:bg-green-700">Закрыть заявку</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'
import Modal from '@/Components/UI/Modal.vue'
import AttachmentList from '@/Components/Tickets/AttachmentList.vue'
import AttachmentUpload from '@/Components/Tickets/AttachmentUpload.vue'
import dayjs from 'dayjs'

const props = defineProps({
  ticket: Object, addressHistory: Array, statuses: Array, brigades: Array,
  canEdit: Boolean, canAssign: Boolean, canClose: Boolean, canComment: Boolean,
})

const showCloseModal = ref(false)
const commentFiles   = ref([])
const closeFiles     = ref([])

const commentForm = useForm({ body: '', is_internal: false })
const closeForm   = useForm({ comment: '' })
const assignForm  = useForm({ brigade_id: props.ticket.brigade_id ?? '', user_id: props.ticket.assigned_to ?? '' })

const selectedBrigadeMembers = computed(() =>
  props.brigades.find(b => b.id == assignForm.brigade_id)?.members ?? []
)

const priorityMap = { low: ['#94a3b8','Низкий'], normal: ['#3b82f6','Обычный'], high: ['#f59e0b','Высокий'], urgent: ['#ef4444','Срочный'] }
const priorityColor = computed(() => priorityMap[props.ticket.priority]?.[0] ?? '#94a3b8')
const priorityLabel = computed(() => priorityMap[props.ticket.priority]?.[1] ?? props.ticket.priority)

function formatDate(d) { return d ? dayjs(d).format('DD.MM.YY HH:mm') : '—' }

function actionLabel(h) {
  if (h.action === 'created') return 'Заявка создана'
  if (h.action === 'status_changed') return `Статус: ${h.old_value} → ${h.new_value}`
  if (h.action === 'assigned') return `Бригада: ${h.old_value ?? '—'} → ${h.new_value ?? '—'}`
  if (h.action === 'comment_added') return 'Добавлен комментарий'
  return h.action
}

function confirmAction(action) {
  router.post(route(`tickets.${action}`, props.ticket.id))
}

function submitAssign() {
  assignForm.post(route('tickets.assign', props.ticket.id))
}

function submitComment() {
  const data = new FormData()
  data.append('body', commentForm.body)
  data.append('is_internal', commentForm.is_internal ? '1' : '0')
  commentFiles.value.forEach(f => data.append('attachments[]', f))
  router.post(route('tickets.comment', props.ticket.id), data, {
    onSuccess: () => { commentForm.reset(); commentFiles.value = [] }
  })
}

function submitClose() {
  const data = new FormData()
  data.append('comment', closeForm.comment)
  closeFiles.value.forEach(f => data.append('attachments[]', f))
  router.post(route('tickets.close', props.ticket.id), data, {
    onSuccess: () => { showCloseModal.value = false; closeFiles.value = [] }
  })
}
</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
</style>

<!-- Вспомогательный компонент строки -->
<script>
const InfoRow = {
  props: { label: String, value: String },
  template: `<div><p class="text-xs text-gray-400 mb-0.5">{{label}}</p><p class="text-sm font-medium text-gray-700">{{value ?? '—'}}</p></div>`
}
</script>
