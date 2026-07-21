<template>
  <div class="min-h-screen flex flex-col" style="background:#121212">
    <!-- Шапка -->
    <div class="shrink-0 px-3 py-3 flex items-center gap-2" style="background:#1D4ED8">
      <button @click="$router.back()" class="text-white w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <span class="text-white font-bold text-base truncate">{{ ticket?.number || '...' }}</span>
    </div>

    <div v-if="loading" class="flex justify-center py-10">
      <svg class="w-6 h-6 text-[#3B82F6] animate-spin" viewBox="0 0 24 24" fill="none">
        <path d="M21 12a9 9 0 11-2.64-6.36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>

    <div v-else-if="ticket" class="flex-1 overflow-y-auto p-3 space-y-3">
      <!-- Статус/тип -->
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-white text-xs px-2 py-1 rounded" :style="{ background: ticket.status?.color || '#6B7280' }">
          {{ ticket.status?.name || '—' }}
        </span>
        <span v-if="ticket.service_type?.name" class="text-white text-xs px-2 py-1 rounded"
              :style="{ background: ticket.service_type?.color || '#6B7280' }">
          {{ ticket.service_type.name }}
        </span>
        <span class="text-[#9E9E9E] text-xs">{{ ticket.type }}</span>
      </div>

      <!-- Адрес / телефон -->
      <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-2">
        <button @click="copy(addressLine)" class="flex items-center justify-between w-full text-left">
          <span class="text-[#E0E0E0] text-sm">{{ addressLine }}</span>
          <span class="text-[#9E9E9E] text-xs shrink-0 ml-2">{{ copiedField === 'address' ? 'скопировано' : 'копир.' }}</span>
        </button>
        <div v-if="ticket.phone" class="flex items-center justify-between">
          <a :href="'tel:' + ticket.phone" class="text-[#3B82F6] text-sm">{{ ticket.phone }}</a>
          <div class="flex gap-3 shrink-0 ml-2">
            <button @click="copy(ticket.phone, 'phone')" class="text-[#9E9E9E] text-xs">
              {{ copiedField === 'phone' ? 'скопировано' : 'копир.' }}
            </button>
            <a :href="'tel:' + ticket.phone" class="text-[#4ADE80]">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
              </svg>
            </a>
          </div>
        </div>
        <div class="text-[#9E9E9E] text-xs">
          Время: {{ formatDateTime(ticket.scheduled_at) }}
        </div>
        <div v-if="ticket.brigade" class="text-[#9E9E9E] text-xs">Бригада: {{ ticket.brigade }}</div>
        <div v-if="ticket.assignee" class="text-[#9E9E9E] text-xs">Исполнитель: {{ ticket.assignee }}</div>
      </div>

      <!-- Описание -->
      <div v-if="ticket.description" class="bg-[#1E1E1E] rounded-lg p-3">
        <div class="text-[#9E9E9E] text-xs mb-1">Описание</div>
        <div class="text-[#E0E0E0] text-sm whitespace-pre-wrap">{{ ticket.description }}</div>
      </div>

      <!-- Акт -->
      <button v-if="ticket.act" @click="$router.push({ name: 'act-detail', params: { id: ticket.act.id } })"
              class="w-full bg-[#1E1E1E] rounded-lg p-3 flex items-center justify-between text-left">
        <div>
          <div class="text-[#E0E0E0] text-sm">Акт {{ ticket.act.number }}</div>
          <div class="text-[#9E9E9E] text-xs">{{ actStatusLabel(ticket.act.status) }}</div>
        </div>
        <span v-if="ticket.act.materials_changed_at" class="text-black text-[10px] px-2 py-1 rounded" style="background:#FBBF24">
          есть правки акта
        </span>
      </button>

      <!-- Вложения -->
      <div v-if="ticket.attachments?.length" class="bg-[#1E1E1E] rounded-lg p-3">
        <div class="text-[#9E9E9E] text-xs mb-2">Фото ({{ ticket.attachments.length }})</div>
        <div class="flex gap-2 flex-wrap">
          <a v-for="a in ticket.attachments" :key="a.id" :href="a.url" target="_blank">
            <img :src="a.url" class="w-16 h-16 rounded object-cover" />
          </a>
        </div>
      </div>

      <!-- Действия -->
      <div v-if="!ticket.status?.is_final" class="flex gap-2">
        <button @click="openCloseModal" class="flex-1 h-11 rounded-lg text-white text-sm font-medium" style="background:#10B981">
          Закрыть
        </button>
        <button @click="rescheduleModal = true" class="flex-1 h-11 rounded-lg text-white text-sm font-medium" style="background:#3B82F6">
          Перенести
        </button>
      </div>
      <label class="block">
        <input type="file" accept="image/*" capture="environment" class="hidden" @change="uploadPhoto" />
        <span class="block w-full h-11 rounded-lg text-white text-sm font-medium text-center leading-[44px]" style="background:#374151">
          📷 Добавить фото
        </span>
      </label>

      <!-- Комментарии -->
      <div class="bg-[#1E1E1E] rounded-lg p-3">
        <div class="text-[#9E9E9E] text-xs mb-2">Комментарии</div>
        <div v-for="c in displayComments" :key="c.id" class="mb-3 pb-3 border-b border-white/5 last:border-0 last:mb-0 last:pb-0">
          <div class="flex justify-between text-xs text-[#9E9E9E] mb-0.5">
            <span>{{ c.author || '—' }}</span>
            <span>{{ formatDateTime(c.created_at) }}</span>
          </div>
          <div class="text-[#E0E0E0] text-sm whitespace-pre-wrap">{{ c.body }}</div>
          <div v-if="c.pending" class="text-[#FBBF24] text-[10px] mt-0.5">⏳ ожидает отправки</div>
        </div>
        <div v-if="!displayComments.length" class="text-[#666] text-sm">Комментариев пока нет</div>

        <div class="flex gap-2 mt-3">
          <input v-model="commentText" placeholder="Написать комментарий..."
                 class="flex-1 bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10" />
          <button @click="sendComment" :disabled="!commentText.trim() || sendingComment"
                  class="px-4 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#1565C0">
            Отправить
          </button>
        </div>
      </div>
    </div>

    <!-- Модалка закрытия -->
    <div v-if="closeModal" class="fixed inset-0 bg-black/60 flex items-end z-50" @click.self="closeModal = false">
      <div class="bg-[#1E1E1E] w-full rounded-t-2xl p-4 space-y-3 max-h-[85vh] overflow-y-auto">
        <div class="text-white font-medium">Закрыть заявку</div>
        <textarea v-model="closeNotes" placeholder="Что было сделано..." rows="3"
                  class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10"></textarea>

        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="useMaterials" class="w-4 h-4" />
          <span class="text-[#E0E0E0] text-sm">📦 Использовались расходные материалы</span>
        </label>

        <div v-if="useMaterials" class="space-y-2">
          <select v-model="closeActType"
                  class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10">
            <option value="">— Тип акта —</option>
            <option value="regular">Обычный</option>
            <option value="repair">Ремонт/Восстановление</option>
          </select>

          <div v-if="loadingMaterials" class="text-[#9E9E9E] text-xs">Загрузка справочника...</div>

          <div v-for="(item, idx) in materialItems" :key="idx" class="flex gap-2 items-center">
            <select v-model="item.material_id"
                    class="flex-1 min-w-0 bg-[#2A2A2A] text-white text-sm rounded-lg px-2 py-2 border border-white/10">
              <option value="">— Материал —</option>
              <option v-for="m in materialsCatalog" :key="m.id" :value="m.id">
                {{ m.code ? '[' + m.code + '] ' : '' }}{{ m.name }} — {{ m.price }}₽/{{ m.unit }}
              </option>
            </select>
            <input v-model.number="item.quantity" type="number" min="0" placeholder="Кол-во"
                   class="w-16 bg-[#2A2A2A] text-white text-sm rounded-lg px-2 py-2 border border-white/10 text-center" />
            <button @click="removeMaterialRow(idx)" class="text-[#9E9E9E] w-8 h-8 shrink-0 text-lg leading-none">✕</button>
          </div>

          <div class="flex items-center justify-between">
            <button @click="addMaterialRow" class="text-[#3B82F6] text-sm">+ Добавить материал</button>
            <div v-if="materialsTotal > 0" class="text-[#E0E0E0] text-sm font-medium">
              Итого: {{ materialsTotal.toFixed(2) }}₽
            </div>
          </div>
        </div>

        <div class="flex gap-2">
          <button @click="closeModal = false" class="flex-1 h-11 rounded-lg text-white text-sm" style="background:#374151">Отмена</button>
          <button @click="closeTicket" :disabled="closing || (useMaterials && !closeActType)"
                  class="flex-1 h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#10B981">
            {{ closing ? '...' : 'Закрыть заявку' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Модалка переноса -->
    <div v-if="rescheduleModal" class="fixed inset-0 bg-black/60 flex items-end z-50" @click.self="rescheduleModal = false">
      <div class="bg-[#1E1E1E] w-full rounded-t-2xl p-4 space-y-3">
        <div class="text-white font-medium">Перенести заявку</div>
        <input v-model="rescheduleAt" type="datetime-local"
               class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10" />
        <textarea v-model="rescheduleComment" placeholder="Комментарий (необязательно)" rows="2"
                  class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10"></textarea>
        <div class="flex gap-2">
          <button @click="rescheduleModal = false" class="flex-1 h-11 rounded-lg text-white text-sm" style="background:#374151">Отмена</button>
          <button @click="rescheduleTicket" :disabled="!rescheduleAt || rescheduling"
                  class="flex-1 h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#3B82F6">
            {{ rescheduling ? '...' : 'Перенести' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../api'
import { commentQueue } from '../store/commentQueue'
import { auth } from '../store/auth'

const props = defineProps({ id: [String, Number] })
const route = useRoute()
const router = useRouter()

const ticket = ref(null)
const loading = ref(true)
const copiedField = ref('')

const closeModal = ref(false)
const closeNotes = ref('')
const closing = ref(false)
const useMaterials = ref(false)
const closeActType = ref('')
const materialItems = ref([{ material_id: '', quantity: 1 }])
const materialsCatalog = ref([])
const loadingMaterials = ref(false)

const rescheduleModal = ref(false)
const rescheduleAt = ref('')
const rescheduleComment = ref('')
const rescheduling = ref(false)

const commentText = ref('')
const sendingComment = ref(false)

const addressLine = computed(() => {
  if (!ticket.value) return ''
  const parts = []
  if (ticket.value.address?.full) parts.push(ticket.value.address.full)
  if (ticket.value.apartment) parts.push(`кв.${ticket.value.apartment}`)
  return parts.join(', ') || 'Адрес не указан'
})

const pendingComments = computed(() => commentQueue.pendingFor(route.params.id))
const displayComments = computed(() => [
  ...(ticket.value?.comments || []),
  ...pendingComments.value.map((p) => ({
    id: p.id,
    body: p.body,
    author: auth.state.user?.name,
    created_at: p.createdAt,
    pending: true,
  })),
])

const materialsTotal = computed(() => {
  return materialItems.value.reduce((sum, item) => {
    const mat = materialsCatalog.value.find(m => m.id == item.material_id)
    if (!mat || !item.quantity) return sum
    return sum + mat.price * item.quantity
  }, 0)
})

const actStatusLabels = { pending_foreman: 'Ждёт бригадира', approved: 'Утверждён', processing: 'В обработке', pending_subscriber_dept: 'Ждёт Абонотдел', completed: 'Завершён' }
function actStatusLabel(s) {
  return actStatusLabels[s] || s
}

function formatDateTime(s) {
  if (!s) return '—'
  const d = new Date(s)
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: '2-digit' }) + ' ' +
         d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

async function copy(text, field) {
  try {
    await navigator.clipboard.writeText(text)
    copiedField.value = field || 'address'
    setTimeout(() => (copiedField.value = ''), 1500)
  } catch {
    // буфер обмена недоступен (например, без HTTPS) -- тихо игнорируем
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/tickets/${route.params.id}`)
    ticket.value = data
  } finally {
    loading.value = false
  }
}

async function sendComment() {
  if (!commentText.value.trim()) return
  const body = commentText.value.trim()
  sendingComment.value = true
  try {
    const { data } = await api.post(`/tickets/${route.params.id}/comments`, { body })
    ticket.value.comments.push(data)
    commentText.value = ''
  } catch (e) {
    if (!e.response) {
      // нет сети -- откладываем в офлайн-очередь, отправится сама при восстановлении связи
      commentQueue.add(route.params.id, body)
      commentText.value = ''
    } else {
      throw e
    }
  } finally {
    sendingComment.value = false
  }
}

async function uploadPhoto(e) {
  const file = e.target.files[0]
  if (!file) return
  const form = new FormData()
  form.append('attachments[]', file)
  const { data } = await api.post(`/tickets/${route.params.id}/attachments`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  ticket.value.attachments = [...(ticket.value.attachments || []), ...data.attachments]
  e.target.value = ''
}

function addMaterialRow() {
  materialItems.value.push({ material_id: '', quantity: 1 })
}

function removeMaterialRow(idx) {
  materialItems.value.splice(idx, 1)
  if (!materialItems.value.length) addMaterialRow()
}

async function openCloseModal() {
  closeModal.value = true
  if (!materialsCatalog.value.length && !loadingMaterials.value) {
    loadingMaterials.value = true
    try {
      const { data } = await api.get('/materials')
      materialsCatalog.value = data
    } finally {
      loadingMaterials.value = false
    }
  }
}

async function closeTicket() {
  closing.value = true
  try {
    const payload = { close_notes: closeNotes.value }
    if (useMaterials.value) {
      payload.act_type = closeActType.value
      const validItems = materialItems.value.filter(i => i.material_id && i.quantity > 0)
      if (validItems.length) payload.materials = validItems
    }
    const { data } = await api.post(`/tickets/${route.params.id}/close`, payload)
    ticket.value = data
    closeModal.value = false
    closeNotes.value = ''
    useMaterials.value = false
    closeActType.value = ''
    materialItems.value = [{ material_id: '', quantity: 1 }]
  } finally {
    closing.value = false
  }
}

async function rescheduleTicket() {
  if (!rescheduleAt.value) return
  rescheduling.value = true
  try {
    const scheduledAt = rescheduleAt.value.replace('T', ' ') + ':00'
    const { data } = await api.post(`/tickets/${route.params.id}/reschedule`, {
      scheduled_at: scheduledAt,
      comment: rescheduleComment.value || undefined,
    })
    ticket.value = data
    rescheduleModal.value = false
  } finally {
    rescheduling.value = false
  }
}

watch(
  () => pendingComments.value.length,
  (newLen, oldLen) => {
    if (oldLen > 0 && newLen === 0) load()
  }
)

onMounted(async () => {
  await load()
  commentQueue.flush()
  if (route.query.action === 'close' && !ticket.value?.status?.is_final) openCloseModal()
  if (route.query.action === 'reschedule' && !ticket.value?.status?.is_final) rescheduleModal.value = true
  if (route.query.action) router.replace({ query: {} })
})
</script>
