<template>
  <div class="min-h-screen flex flex-col" style="background:#121212">
    <!-- Шапка -->
    <div class="shrink-0 px-3 py-3 flex items-center gap-2" style="background:#1D4ED8">
      <button @click="$router.back()" class="text-white w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <span class="text-white font-bold text-base truncate">{{ request?.name || '...' }}</span>
    </div>

    <div v-if="loading" class="flex justify-center py-10">
      <svg class="w-6 h-6 text-[#3B82F6] animate-spin" viewBox="0 0 24 24" fill="none">
        <path d="M21 12a9 9 0 11-2.64-6.36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>

    <div v-else-if="request" class="flex-1 overflow-y-auto p-3 space-y-3">
      <!-- Статус -->
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-white text-xs px-2 py-1 rounded" :style="{ background: statusColor }">{{ statusLabel }}</span>
        <span v-if="request.service_type?.name" class="text-white text-xs px-2 py-1 rounded"
              :style="{ background: request.service_type?.color || '#6B7280' }">
          {{ request.service_type.name }}
        </span>
        <button v-if="request.needs_callback" @click="markCalled" :disabled="markingCalled"
                class="text-black text-xs px-2 py-1 rounded font-medium disabled:opacity-50" style="background:#FBBF24">
          {{ markingCalled ? '...' : '📞 Прозвонил' }}
        </button>
      </div>

      <!-- Контакты / адрес -->
      <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-2">
        <button @click="copy(request.address_string)" class="flex items-center justify-between w-full text-left">
          <span class="text-[#E0E0E0] text-sm">{{ request.address_string || 'Адрес не указан' }}</span>
          <span class="text-[#9E9E9E] text-xs shrink-0 ml-2">{{ copiedField === 'address' ? 'скопировано' : 'копир.' }}</span>
        </button>
        <div v-if="request.phone" class="flex items-center justify-between">
          <a :href="'tel:' + request.phone" class="text-[#3B82F6] text-sm">{{ request.phone }}</a>
          <div class="flex gap-3 shrink-0 ml-2">
            <button @click="copy(request.phone, 'phone')" class="text-[#9E9E9E] text-xs">
              {{ copiedField === 'phone' ? 'скопировано' : 'копир.' }}
            </button>
            <a :href="'tel:' + request.phone" class="text-[#4ADE80]">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
              </svg>
            </a>
          </div>
        </div>
        <div v-if="request.scheduled_at" class="text-[#9E9E9E] text-xs">Назначено на: {{ formatDateTime(request.scheduled_at) }}</div>
        <div v-if="request.territory?.name" class="text-[#9E9E9E] text-xs">Территория: {{ request.territory.name }}</div>
        <div v-if="request.creator" class="text-[#9E9E9E] text-xs">Создал: {{ request.creator }}</div>
      </div>

      <!-- Описание / заметки -->
      <div v-if="request.description" class="bg-[#1E1E1E] rounded-lg p-3">
        <div class="text-[#9E9E9E] text-xs mb-1">Описание</div>
        <div class="text-[#E0E0E0] text-sm whitespace-pre-wrap">{{ request.description }}</div>
      </div>
      <div v-if="request.notes" class="bg-[#1E1E1E] rounded-lg p-3">
        <div class="text-[#9E9E9E] text-xs mb-1">Заметки</div>
        <div class="text-[#E0E0E0] text-sm whitespace-pre-wrap">{{ request.notes }}</div>
      </div>

      <!-- Акт -->
      <button v-if="request.act" @click="$router.push({ name: 'act-detail', params: { id: request.act.id } })"
              class="w-full bg-[#1E1E1E] rounded-lg p-3 flex items-center justify-between text-left">
        <div>
          <div class="text-[#E0E0E0] text-sm">Акт {{ request.act.number }}</div>
          <div class="text-[#9E9E9E] text-xs">{{ actStatusLabel(request.act.status) }}</div>
        </div>
        <span v-if="request.act.materials_changed_at" class="text-black text-[10px] px-2 py-1 rounded" style="background:#FBBF24">
          есть правки акта
        </span>
      </button>

      <!-- Действия -->
      <div v-if="!isFinal" class="space-y-2">
        <div class="flex gap-2">
          <button @click="openScheduleModal" class="flex-1 h-11 rounded-lg text-white text-sm font-medium" style="background:#3B82F6">
            {{ request.status === 'scheduled' ? 'Изменить дату' : 'Назначить дату' }}
          </button>
          <button @click="rejectModal = true" class="flex-1 h-11 rounded-lg text-white text-sm font-medium" style="background:#DC2626">
            Отклонить
          </button>
        </div>
        <button @click="openCloseModal" class="w-full h-11 rounded-lg text-white text-sm font-medium" style="background:#10B981">
          Выполнено
        </button>
      </div>
    </div>

    <!-- Модалка назначения даты -->
    <div v-if="scheduleModal" class="fixed inset-0 bg-black/60 flex items-end z-50" @click.self="scheduleModal = false">
      <div class="bg-[#1E1E1E] w-full rounded-t-2xl p-4 space-y-3">
        <div class="text-white font-medium">Назначить дату визита</div>
        <input v-model="scheduleAt" type="datetime-local"
               class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10" />
        <textarea v-model="scheduleNotes" placeholder="Заметка (необязательно)" rows="2"
                  class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10"></textarea>
        <div class="flex gap-2">
          <button @click="scheduleModal = false" class="flex-1 h-11 rounded-lg text-white text-sm" style="background:#374151">Отмена</button>
          <button @click="submitSchedule" :disabled="!scheduleAt || scheduling"
                  class="flex-1 h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#3B82F6">
            {{ scheduling ? '...' : 'Назначить' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Модалка отклонения -->
    <div v-if="rejectModal" class="fixed inset-0 bg-black/60 flex items-end z-50" @click.self="rejectModal = false">
      <div class="bg-[#1E1E1E] w-full rounded-t-2xl p-4 space-y-3">
        <div class="text-white font-medium">Отклонить заявку</div>
        <textarea v-model="rejectNotes" placeholder="Причина отклонения..." rows="3"
                  class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10"></textarea>
        <div class="flex gap-2">
          <button @click="rejectModal = false" class="flex-1 h-11 rounded-lg text-white text-sm" style="background:#374151">Отмена</button>
          <button @click="submitReject" :disabled="rejecting"
                  class="flex-1 h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#DC2626">
            {{ rejecting ? '...' : 'Отклонить' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Модалка закрытия -->
    <div v-if="closeModal" class="fixed inset-0 bg-black/60 flex items-end z-50" @click.self="closeModal = false">
      <div class="bg-[#1E1E1E] w-full rounded-t-2xl p-4 space-y-3 max-h-[85vh] overflow-y-auto">
        <div class="text-white font-medium">Завершить подключение</div>
        <textarea v-model="closeNotes" placeholder="Что было сделано..." rows="3"
                  class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10"></textarea>

        <!-- Участок отсутствует -- нужен до материалов -->
        <div v-if="!request.service_type" class="space-y-1.5 bg-[#2A2A2A] rounded-lg p-2.5">
          <div class="text-[#FBBF24] text-xs">Не указан участок — нужен перед добавлением материалов</div>
          <div class="flex gap-2">
            <select v-model="serviceTypeToSave"
                    class="flex-1 min-w-0 bg-[#1E1E1E] text-white text-sm rounded-lg px-2 py-2 border border-white/10">
              <option value="">— Участок —</option>
              <option v-for="st in serviceTypes" :key="st.id" :value="st.id">{{ st.name }}</option>
            </select>
            <button @click="saveServiceType" :disabled="!serviceTypeToSave || savingServiceType"
                    class="px-3 rounded-lg text-white text-sm shrink-0 disabled:opacity-50" style="background:#3B82F6">
              {{ savingServiceType ? '...' : 'Сохранить' }}
            </button>
          </div>
        </div>

        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="useMaterials" :disabled="!request.service_type" class="w-4 h-4" />
          <span class="text-[#E0E0E0] text-sm" :class="{ 'opacity-50': !request.service_type }">📦 Использовались расходные материалы</span>
        </label>

        <div v-if="useMaterials" class="space-y-2">
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

          <div v-if="materialsTotal > 0">
            <select v-model="promotionId"
                    class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10">
              <option :value="null">— без акции, по стоимости материалов —</option>
              <option v-for="p in promotions" :key="p.id" :value="p.id">{{ p.name }} — {{ p.price }}₽</option>
            </select>
            <p v-if="selectedPromotion" class="text-[#9E9E9E] text-xs mt-1">
              Абонент платит {{ selectedPromotion.price }}₽ вместо {{ materialsTotal.toFixed(2) }}₽ по факту материалов.
            </p>
          </div>
        </div>

        <div class="flex gap-2">
          <button @click="closeModal = false" class="flex-1 h-11 rounded-lg text-white text-sm" style="background:#374151">Отмена</button>
          <button @click="submitClose" :disabled="closing"
                  class="flex-1 h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#10B981">
            {{ closing ? '...' : 'Завершить' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'

const route = useRoute()

const request = ref(null)
const loading = ref(true)
const copiedField = ref('')
const markingCalled = ref(false)

const scheduleModal = ref(false)
const scheduleAt = ref('')
const scheduleNotes = ref('')
const scheduling = ref(false)

const rejectModal = ref(false)
const rejectNotes = ref('')
const rejecting = ref(false)

const closeModal = ref(false)
const closeNotes = ref('')
const closing = ref(false)
const useMaterials = ref(false)
const materialItems = ref([{ material_id: '', quantity: 1 }])
const materialsCatalog = ref([])
const loadingMaterials = ref(false)
const promotions = ref([])
const promotionId = ref(null)
const serviceTypes = ref([])
const serviceTypeToSave = ref('')
const savingServiceType = ref(false)

const statusLabels = { pending: 'Ожидает', scheduled: 'Назначено', rejected: 'Отклонено', closed: 'Выполнено' }
const statusColors = { pending: '#CA8A04', scheduled: '#2563EB', rejected: '#DC2626', closed: '#16A34A' }
const actStatusLabels = { pending_foreman: 'Ждёт бригадира', approved: 'Утверждён', processing: 'В обработке', pending_subscriber_dept: 'Ждёт Абонотдел', completed: 'Завершён' }

const statusLabel = computed(() => statusLabels[request.value?.status] || request.value?.status)
const statusColor = computed(() => statusColors[request.value?.status] || '#6B7280')
const isFinal = computed(() => ['rejected', 'closed'].includes(request.value?.status))

const materialsTotal = computed(() => {
  return materialItems.value.reduce((sum, item) => {
    const mat = materialsCatalog.value.find((m) => m.id == item.material_id)
    if (!mat || !item.quantity) return sum
    return sum + mat.price * item.quantity
  }, 0)
})

const selectedPromotion = computed(() => promotions.value.find((p) => p.id === promotionId.value) || null)

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
    // буфер обмена недоступен -- тихо игнорируем
  }
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/connection-requests/${route.params.id}`)
    request.value = data
  } finally {
    loading.value = false
  }
}

async function markCalled() {
  markingCalled.value = true
  try {
    await api.post(`/connection-requests/${route.params.id}/mark-called`)
    request.value.needs_callback = false
  } finally {
    markingCalled.value = false
  }
}

function openScheduleModal() {
  scheduleAt.value = request.value.scheduled_at ? request.value.scheduled_at.slice(0, 16) : ''
  scheduleNotes.value = request.value.notes || ''
  scheduleModal.value = true
}

async function submitSchedule() {
  if (!scheduleAt.value) return
  scheduling.value = true
  try {
    const { data } = await api.put(`/connection-requests/${route.params.id}`, {
      status: 'scheduled',
      scheduled_at: scheduleAt.value.replace('T', ' ') + ':00',
      notes: scheduleNotes.value || undefined,
    })
    request.value = data
    scheduleModal.value = false
  } finally {
    scheduling.value = false
  }
}

async function submitReject() {
  rejecting.value = true
  try {
    const { data } = await api.put(`/connection-requests/${route.params.id}`, {
      status: 'rejected',
      notes: rejectNotes.value || undefined,
    })
    request.value = data
    rejectModal.value = false
    rejectNotes.value = ''
  } finally {
    rejecting.value = false
  }
}

function addMaterialRow() {
  materialItems.value.push({ material_id: '', quantity: 1 })
}

function removeMaterialRow(idx) {
  materialItems.value.splice(idx, 1)
  if (!materialItems.value.length) addMaterialRow()
}

async function saveServiceType() {
  if (!serviceTypeToSave.value) return
  savingServiceType.value = true
  try {
    const { data } = await api.put(`/connection-requests/${route.params.id}`, { service_type_id: serviceTypeToSave.value })
    request.value = data
  } finally {
    savingServiceType.value = false
  }
}

async function openCloseModal() {
  closeModal.value = true
  closeNotes.value = request.value.notes || ''
  if (!materialsCatalog.value.length && !loadingMaterials.value) {
    loadingMaterials.value = true
    try {
      const [materialsRes, promotionsRes, serviceTypesRes] = await Promise.all([
        api.get('/materials'),
        api.get('/promotions'),
        api.get('/service_types'),
      ])
      materialsCatalog.value = materialsRes.data
      promotions.value = promotionsRes.data
      serviceTypes.value = serviceTypesRes.data
    } finally {
      loadingMaterials.value = false
    }
  }
}

async function submitClose() {
  closing.value = true
  try {
    const payload = { notes: closeNotes.value }
    if (useMaterials.value) {
      const validItems = materialItems.value.filter((i) => i.material_id && i.quantity > 0)
      if (validItems.length) {
        payload.materials = validItems
        payload.promotion_id = promotionId.value
      }
    }
    const { data } = await api.post(`/connection-requests/${route.params.id}/close`, payload)
    request.value = data
    closeModal.value = false
  } finally {
    closing.value = false
  }
}

onMounted(load)
</script>
