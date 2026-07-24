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
      </div>

      <!-- Возможность подключения -->
      <div v-if="request.feasibility === 'possible'" class="rounded-lg p-3" style="background:#0D2B1D">
        <div class="text-sm font-medium" style="color:#4ADE80">✓ Подключение возможно</div>
        <div v-if="request.feasibility_comment" class="text-[#9E9E9E] text-sm mt-1 whitespace-pre-wrap">{{ request.feasibility_comment }}</div>
      </div>
      <div v-else-if="request.feasibility === 'impossible'" class="rounded-lg p-3" style="background:#2D1414">
        <div class="text-sm font-medium" style="color:#EF4444">✕ Подключение невозможно</div>
        <div v-if="request.feasibility_comment" class="text-[#9E9E9E] text-sm mt-1 whitespace-pre-wrap">{{ request.feasibility_comment }}</div>
      </div>

      <!-- Контакты / адрес -->
      <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-2">
        <div class="text-[#E0E0E0] text-sm">Адрес: {{ request.address_string || '—' }}</div>
        <a v-if="request.phone" :href="'tel:' + request.phone" class="text-[#3B82F6] text-sm block">Тел: {{ request.phone }}</a>
        <div v-if="request.scheduled_at" class="text-[#9E9E9E] text-xs">Дата выезда: {{ formatDate(request.scheduled_at) }}</div>
        <div class="text-[#9E9E9E] text-xs">Территория: {{ request.territory?.name || '—' }}</div>
        <div class="text-[#9E9E9E] text-xs">Добавил: {{ request.creator || '—' }}</div>
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

      <!-- Закрытая заявка: акт / акция -->
      <template v-if="request.status === 'closed'">
        <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-2">
          <div class="text-[#E0E0E0] text-sm">Акт: {{ request.act_number || request.act?.number || 'б/а' }}</div>
          <div v-if="request.act?.promotion_name && request.act?.promotion_price != null"
               class="text-[#FBBF24] text-sm">
            🎁 Акция «{{ request.act.promotion_name }}» — к оплате {{ request.act.promotion_price.toFixed(2) }} руб.
          </div>
          <div v-if="request.materials && request.materials.length" class="space-y-1 pt-1 border-t border-white/10">
            <div v-for="m in request.materials" :key="m.id" class="text-[#D1D5DB] text-xs">
              {{ m.name }} — {{ m.quantity }} {{ m.unit }} × {{ m.price_at_time }} = {{ m.total }} руб.
            </div>
            <div class="text-[#E0E0E0] text-sm font-medium pt-1">Итого: {{ materialsTotalClosed.toFixed(2) }} руб.</div>
          </div>
        </div>
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
      </template>

      <!-- Действия монтажника: только ответ о возможности и завершение --
           назначение даты и отклонение теперь целиком на портале у оператора,
           см. память проекта (project-connection-feasibility). -->
      <div v-if="showFeasibilityAction || showCloseAction" class="space-y-2">
        <div v-if="showFeasibilityAction" class="flex gap-2">
          <button @click="openFeasibilityModal('possible')" class="flex-1 h-11 rounded-lg text-white text-sm font-medium" style="background:#16A34A">
            Возможно
          </button>
          <button @click="openFeasibilityModal('impossible')" class="flex-1 h-11 rounded-lg text-white text-sm font-medium" style="background:#DC2626">
            Невозможно
          </button>
        </div>
        <button v-if="showCloseAction" @click="openCloseModal" class="w-full h-11 rounded-lg text-white text-sm font-medium" style="background:#10B981">
          Завершить
        </button>
      </div>
    </div>

    <!-- Модалка ответа монтажника -->
    <div v-if="feasibilityModal" class="fixed inset-0 bg-black/60 flex items-end z-50" @click.self="feasibilityModal = false">
      <div class="bg-[#1E1E1E] w-full rounded-t-2xl p-4 space-y-3">
        <div class="text-white font-medium">
          {{ feasibilityAnswer === 'possible' ? 'Подключение возможно' : 'Подключение невозможно' }}
        </div>
        <textarea v-model="feasibilityComment" placeholder="Комментарий (необязательно)" rows="3"
                  class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10"></textarea>
        <div v-if="feasibilityError" class="text-[#EF4444] text-xs">{{ feasibilityError }}</div>
        <div class="flex gap-2">
          <button @click="feasibilityModal = false" class="flex-1 h-11 rounded-lg text-white text-sm" style="background:#374151">Отмена</button>
          <button @click="submitFeasibility" :disabled="submittingFeasibility"
                  class="flex-1 h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50"
                  :style="{ background: feasibilityAnswer === 'possible' ? '#16A34A' : '#DC2626' }">
            {{ submittingFeasibility ? 'Отправка...' : 'Отправить' }}
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

        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" v-model="useMaterials" class="w-4 h-4" />
          <span class="text-[#E0E0E0] text-sm">📦 Использовались расходные материалы</span>
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
              Материалы: {{ materialsTotal.toFixed(2) }}₽
            </div>
          </div>

          <div v-if="materialsTotal > 0">
            <select v-model="promotionId"
                    class="w-full bg-[#2A2A2A] text-white text-sm rounded-lg px-3 py-2 border border-white/10">
              <option :value="null">Без акции</option>
              <option v-for="p in promotions" :key="p.id" :value="p.id">{{ p.name }} — {{ p.price }}₽</option>
            </select>
            <p v-if="selectedPromotion" class="text-[#9E9E9E] text-xs mt-1">
              Абонент платит: {{ selectedPromotion.price.toFixed(2) }}₽
            </p>
          </div>
        </div>

        <div v-if="closeError" class="text-[#EF4444] text-xs">{{ closeError }}</div>

        <div class="flex gap-2">
          <button @click="closeModal = false" class="flex-1 h-11 rounded-lg text-white text-sm" style="background:#374151">Отмена</button>
          <button @click="submitClose" :disabled="closing"
                  class="flex-1 h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#10B981">
            {{ closing ? 'Отправка...' : 'Завершить' }}
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

const feasibilityModal = ref(false)
const feasibilityAnswer = ref('')
const feasibilityComment = ref('')
const feasibilityError = ref('')
const submittingFeasibility = ref(false)

const closeModal = ref(false)
const closeNotes = ref('')
const closeError = ref('')
const closing = ref(false)
const useMaterials = ref(false)
const materialItems = ref([{ material_id: '', quantity: 1 }])
const materialsCatalog = ref([])
const loadingMaterials = ref(false)
const promotions = ref([])
const promotionId = ref(null)

// Статусы/цвета -- строго по образцу Android (ConnectionAdapter.statusDisplay),
// см. память проекта, project-connection-feasibility.
const statusLabels = { pending: 'Ожидает', scheduled: 'Запланировано', rejected: 'Отклонено', closed: 'Выполнено' }
const statusColors = { pending: '#F59E0B', scheduled: '#3B82F6', rejected: '#EF4444', closed: '#10B981' }
const actStatusLabels = { pending_foreman: 'Ждёт бригадира', approved: 'Утверждён', processing: 'В обработке', pending_subscriber_dept: 'Ждёт Абонотдел', completed: 'Завершён' }

const statusLabel = computed(() => statusLabels[request.value?.status] || request.value?.status)
const statusColor = computed(() => statusColors[request.value?.status] || '#6B7280')

// Действия монтажника -- строго по образцу Android (applyActionPanel):
// только ответ о возможности (пока нет ответа) и завершение (пока назначено).
const showFeasibilityAction = computed(() => request.value?.status === 'pending' && !request.value?.feasibility)
const showCloseAction = computed(() => request.value?.status === 'scheduled')

const materialsTotal = computed(() => {
  return materialItems.value.reduce((sum, item) => {
    const mat = materialsCatalog.value.find((m) => m.id == item.material_id)
    if (!mat || !item.quantity) return sum
    return sum + mat.price * item.quantity
  }, 0)
})

const materialsTotalClosed = computed(() => {
  return (request.value?.materials || []).reduce((sum, m) => sum + Number(m.total || 0), 0)
})

const selectedPromotion = computed(() => promotions.value.find((p) => p.id === promotionId.value) || null)

function actStatusLabel(s) {
  return actStatusLabels[s] || s
}

function formatDate(s) {
  if (!s) return '—'
  const d = new Date(s)
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }) + ' ' +
         d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
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

function openFeasibilityModal(answer) {
  feasibilityAnswer.value = answer
  feasibilityComment.value = ''
  feasibilityError.value = ''
  feasibilityModal.value = true
}

async function submitFeasibility() {
  submittingFeasibility.value = true
  feasibilityError.value = ''
  try {
    const { data } = await api.post(`/connection-requests/${route.params.id}/feasibility`, {
      answer: feasibilityAnswer.value,
      comment: feasibilityComment.value || undefined,
    })
    request.value = data
    feasibilityModal.value = false
  } catch (e) {
    feasibilityError.value = e.response?.status === 403
      ? 'Ответить может только монтажник.'
      : 'Ошибка: ' + (e.response?.data?.message || 'нет соединения')
  } finally {
    submittingFeasibility.value = false
  }
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
  closeNotes.value = request.value.notes || ''
  closeError.value = ''
  if (!materialsCatalog.value.length && !loadingMaterials.value) {
    loadingMaterials.value = true
    try {
      const [materialsRes, promotionsRes] = await Promise.all([
        api.get('/materials'),
        api.get('/promotions').catch(() => ({ data: [] })),
      ])
      materialsCatalog.value = materialsRes.data
      promotions.value = promotionsRes.data
    } finally {
      loadingMaterials.value = false
    }
  }
}

async function submitClose() {
  closing.value = true
  closeError.value = ''
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
  } catch (e) {
    const fieldError = e.response?.data?.errors?.service_type_id?.[0]
    closeError.value = fieldError || (e.response?.status === 422
      ? 'Ошибка: ' + (e.response?.data?.message || 'проверьте поля')
      : 'Ошибка сервера')
  } finally {
    closing.value = false
  }
}

onMounted(load)
</script>
