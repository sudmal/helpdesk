<template>
  <div class="min-h-screen flex flex-col" style="background:#121212">
    <!-- Шапка -->
    <div class="shrink-0 px-3 py-3 flex items-center gap-2" style="background:#1D4ED8">
      <button @click="$router.back()" class="text-white w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <span class="text-white font-bold text-base truncate">Акт {{ act?.number || '...' }}</span>
    </div>

    <div v-if="loading" class="flex justify-center py-10">
      <svg class="w-6 h-6 text-[#3B82F6] animate-spin" viewBox="0 0 24 24" fill="none">
        <path d="M21 12a9 9 0 11-2.64-6.36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>

    <div v-else-if="act" class="flex-1 overflow-y-auto p-3 space-y-3">
      <!-- Статус/тип -->
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-white text-xs px-2 py-1 rounded" :style="{ background: statusColor }">{{ statusLabel }}</span>
        <span class="text-[#9E9E9E] text-xs">{{ typeLabel }}</span>
      </div>

      <div v-if="act.materials_changed_at" class="bg-[#3F2D0A] border border-[#FBBF24]/40 rounded-lg p-3 text-[#FBBF24] text-sm">
        Бригадир изменил состав материалов после создания акта
      </div>

      <!-- Инфо -->
      <div class="bg-[#1E1E1E] rounded-lg p-3 space-y-1">
        <div class="text-[#9E9E9E] text-xs">Создал: {{ act.creator || '—' }}, {{ formatDateTime(act.created_at) }}</div>
        <div v-if="act.foreman_reviewed_by" class="text-[#9E9E9E] text-xs">
          Утвердил: {{ act.foreman_reviewed_by }}, {{ formatDateTime(act.foreman_reviewed_at) }}
        </div>
        <div v-if="act.promotion_name" class="text-[#4ADE80] text-xs">
          Акция «{{ act.promotion_name }}»: абонент платит {{ act.promotion_price }}₽
        </div>
      </div>

      <!-- Материалы -->
      <div class="bg-[#1E1E1E] rounded-lg p-3">
        <div class="text-[#9E9E9E] text-xs mb-2">Материалы</div>

        <div v-for="m in act.materials" :key="m.id" class="flex items-center gap-2 py-1.5 border-b border-white/5 last:border-0">
          <div class="flex-1 min-w-0">
            <div class="text-[#E0E0E0] text-sm truncate">{{ m.code ? '[' + m.code + '] ' : '' }}{{ m.name }}</div>
            <div class="text-[#9E9E9E] text-xs">{{ m.price_at_time }}₽/{{ m.unit }}</div>
          </div>

          <template v-if="act.can.edit_materials">
            <input v-model.number="editQty[m.id]" type="number" min="0"
                   class="w-16 bg-[#2A2A2A] text-white text-sm rounded-lg px-2 py-1.5 border border-white/10 text-center" />
            <button v-if="editQty[m.id] != m.quantity" @click="saveQuantity(m)" :disabled="savingMaterial === m.id"
                    class="text-[#4ADE80] w-8 h-8 shrink-0 flex items-center justify-center">✓</button>
            <button @click="deleteMaterial(m)" :disabled="savingMaterial === m.id"
                    class="text-[#9E9E9E] w-8 h-8 shrink-0 text-lg leading-none">✕</button>
          </template>
          <div v-else class="text-[#E0E0E0] text-sm shrink-0 w-20 text-right">{{ m.quantity }} {{ m.unit }}</div>
        </div>

        <div v-if="!act.materials?.length" class="text-[#666] text-sm py-2">Материалов нет</div>

        <div v-if="act.can.edit_materials" class="flex gap-2 items-center mt-2 pt-2 border-t border-white/5">
          <select v-model="newMaterialId"
                  class="flex-1 min-w-0 bg-[#2A2A2A] text-white text-sm rounded-lg px-2 py-2 border border-white/10">
            <option value="">— Добавить материал —</option>
            <option v-for="m in materialsCatalog" :key="m.id" :value="m.id">
              {{ m.code ? '[' + m.code + '] ' : '' }}{{ m.name }} — {{ m.price }}₽/{{ m.unit }}
            </option>
          </select>
          <input v-model.number="newMaterialQty" type="number" min="0" placeholder="Кол-во"
                 class="w-16 bg-[#2A2A2A] text-white text-sm rounded-lg px-2 py-2 border border-white/10 text-center" />
          <button @click="addMaterial" :disabled="!newMaterialId || !newMaterialQty || addingMaterial"
                  class="px-3 rounded-lg text-white text-sm shrink-0 disabled:opacity-50" style="background:#3B82F6">+</button>
        </div>

        <div class="flex justify-end mt-2 pt-2 border-t border-white/5">
          <div class="text-[#E0E0E0] text-sm font-medium">Итого: {{ total.toFixed(2) }}₽</div>
        </div>
      </div>

      <!-- Действия -->
      <button v-if="act.can.foreman_review" @click="approve" :disabled="approving"
              class="w-full h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#10B981">
        {{ approving ? '...' : 'Утвердить акт' }}
      </button>
      <button v-if="act.can.acknowledge" @click="acknowledge" :disabled="acknowledging"
              class="w-full h-11 rounded-lg text-white text-sm font-medium disabled:opacity-50" style="background:#FBBF24; color:#1E1E1E">
        {{ acknowledging ? '...' : 'Подтвердить изменения' }}
      </button>

      <!-- История -->
      <div class="bg-[#1E1E1E] rounded-lg p-3">
        <div class="text-[#9E9E9E] text-xs mb-2">История</div>
        <div v-for="h in act.history" :key="h.id" class="mb-2.5 pb-2.5 border-b border-white/5 last:border-0 last:mb-0 last:pb-0">
          <div class="flex justify-between text-xs text-[#9E9E9E]">
            <span>{{ h.user || '—' }}</span>
            <span>{{ formatDateTime(h.created_at) }}</span>
          </div>
          <div class="text-[#E0E0E0] text-sm">{{ actionLabel(h.action) }}</div>
          <div v-if="h.new_value || (h.action === 'material_removed' && h.old_value)" class="text-[#9E9E9E] text-xs mt-0.5">
            ↳ {{ h.action === 'material_removed' ? h.old_value : h.new_value }}
          </div>
        </div>
        <div v-if="!act.history?.length" class="text-[#666] text-sm">Пусто</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../api'

const route = useRoute()

const act = ref(null)
const loading = ref(true)
const approving = ref(false)
const acknowledging = ref(false)
const savingMaterial = ref(null)
const addingMaterial = ref(false)
const editQty = ref({})
const materialsCatalog = ref([])
const newMaterialId = ref('')
const newMaterialQty = ref(1)

const statusLabels = { pending_foreman: 'Ждёт бригадира', approved: 'Утверждён', processing: 'В обработке', pending_subscriber_dept: 'Ждёт Абонотдел', completed: 'Завершён' }
const statusColors = { pending_foreman: '#CA8A04', approved: '#4F46E5', processing: '#4F46E5', pending_subscriber_dept: '#4F46E5', completed: '#16A34A' }
const typeLabels = { regular: 'Обычный', repair: 'Ремонт/Восстановление' }
const actionLabels = {
  created: 'Акт создан',
  approved: 'Утверждён бригадиром',
  peo_processed: 'Проведён ПЭО',
  logistics_processed: 'Проведён Логистикой',
  completed: 'Завершён Абонотделом',
  material_added: 'Бригадир добавил материал',
  material_changed: 'Бригадир изменил количество',
  material_removed: 'Бригадир удалил материал',
  acknowledged: 'Изменения подтверждены монтажником',
}

const statusLabel = computed(() => statusLabels[act.value?.status] || act.value?.status)
const statusColor = computed(() => statusColors[act.value?.status] || '#6B7280')
const typeLabel = computed(() => typeLabels[act.value?.type] || act.value?.type)

const total = computed(() => (act.value?.materials || []).reduce((s, m) => s + m.price_at_time * m.quantity, 0))

function actionLabel(a) {
  return actionLabels[a] || a
}

function formatDateTime(s) {
  if (!s) return '—'
  const d = new Date(s)
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: '2-digit' }) + ' ' +
         d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

function syncEditQty() {
  const map = {}
  ;(act.value?.materials || []).forEach((m) => (map[m.id] = m.quantity))
  editQty.value = map
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/acts/${route.params.id}`)
    act.value = data
    syncEditQty()
    if (data.can.edit_materials && !materialsCatalog.value.length) {
      const { data: mats } = await api.get('/materials')
      materialsCatalog.value = mats
    }
  } finally {
    loading.value = false
  }
}

async function approve() {
  approving.value = true
  try {
    const { data } = await api.post(`/acts/${route.params.id}/approve`)
    act.value = data
    syncEditQty()
  } finally {
    approving.value = false
  }
}

async function acknowledge() {
  acknowledging.value = true
  try {
    const { data } = await api.post(`/acts/${route.params.id}/acknowledge`)
    act.value = data
    syncEditQty()
  } finally {
    acknowledging.value = false
  }
}

async function saveQuantity(m) {
  const qty = editQty.value[m.id]
  if (!qty || qty <= 0) return
  savingMaterial.value = m.id
  try {
    const { data } = await api.put(`/acts/${route.params.id}/materials/${m.id}`, { quantity: qty })
    act.value = data
    syncEditQty()
  } finally {
    savingMaterial.value = null
  }
}

async function deleteMaterial(m) {
  savingMaterial.value = m.id
  try {
    const { data } = await api.delete(`/acts/${route.params.id}/materials/${m.id}`)
    act.value = data
    syncEditQty()
  } finally {
    savingMaterial.value = null
  }
}

async function addMaterial() {
  if (!newMaterialId.value || !newMaterialQty.value) return
  addingMaterial.value = true
  try {
    const { data } = await api.post(`/acts/${route.params.id}/materials`, {
      material_id: newMaterialId.value,
      quantity: newMaterialQty.value,
    })
    act.value = data
    syncEditQty()
    newMaterialId.value = ''
    newMaterialQty.value = 1
  } finally {
    addingMaterial.value = false
  }
}

onMounted(load)
</script>
