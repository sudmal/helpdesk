<template>
  <Head :title="`Акт ${act.number}`" />
  <AppLayout :title="`Акт ${act.number}`">

    <!-- Все действия с актом — в самой верхней панели, рядом с заголовком и колокольчиком -->
    <template #actions>
      <div class="flex items-center gap-2 flex-wrap justify-end">
        <button @click="goBack" class="btn-act-outline">← К списку актов</button>

        <a v-if="act.status !== 'pending_foreman'" :href="route('acts.print', act.id)" target="_blank"
           class="btn-act-outline">Печать акта</a>

        <button v-if="can.acknowledge" @click="acknowledge" class="btn-act-primary">Принято</button>

        <template v-if="can.foremanReview && !editMode">
          <button @click="approve" :disabled="!canApprove" :title="approveDisabledReason"
                  class="btn-act-primary disabled:opacity-40 disabled:cursor-not-allowed">{{ approveLabel }}</button>
        </template>
        <button v-if="can.editMaterials && !editMode" @click="editMode = true" class="btn-act-outline">Редактировать состав</button>
        <button v-if="can.editMaterials && editMode" @click="finishEditing" class="btn-act-primary">Сохранить</button>

        <button v-if="can.processPeo" @click="post('acts.process-peo')" class="btn-act-primary">Отметить проведённым (ПЭО)</button>
        <button v-if="can.processLogistics" @click="post('acts.process-logistics')" class="btn-act-primary">Отметить проведённым (Логистика)</button>
        <button v-if="can.complete" @click="post('acts.complete')" class="btn-act-primary">Завершить акт</button>
      </div>
    </template>

    <div class="max-w-3xl mx-auto space-y-4">

      <!-- Шапка: номер + бейджи типа/статуса -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="text-lg font-semibold text-gray-800 font-mono">{{ act.number }}</h2>
            <button v-if="act.ticket" class="text-sm text-blue-600 hover:underline mt-0.5"
                    @click="router.get(route('tickets.show', act.ticket.id))">
              Заявка #{{ act.ticket.number }}
            </button>
            <button v-else-if="act.connection_request" class="text-sm text-blue-600 hover:underline mt-0.5"
                    @click="router.get(route('connection-requests.index') + '?open=' + act.connection_request.id)">
              Заявка на подключение — {{ act.connection_request.name }}
            </button>
          </div>
          <div class="flex items-center gap-2 flex-wrap justify-end">
            <span class="px-2 py-1 rounded-lg bg-indigo-100 text-indigo-700 text-xs font-medium">{{ typeLabel(act.type) }}</span>
            <span :class="statusClass(act.status)" class="px-2 py-1 rounded-lg text-xs font-medium">{{ statusLabels[act.status] || act.status }}</span>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4 pt-4 border-t border-gray-100">
          <div>
            <p class="text-xs text-gray-400 mb-0.5">Дата создания</p>
            <p class="text-sm font-medium text-gray-700">{{ fmtDateTime(act.created_at) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 mb-0.5">Территория / адрес</p>
            <p class="text-sm font-medium text-gray-700">
              <template v-if="act.ticket">
                <span v-if="act.ticket.address?.territory">{{ act.ticket.address.territory.name }} — </span>{{ act.ticket.address?.full_address || '—' }}
              </template>
              <template v-else-if="act.connection_request">
                <span v-if="act.connection_request.territory">{{ act.connection_request.territory.name }} — </span>{{ act.connection_request.address_string || '—' }}
              </template>
            </p>
          </div>
          <div>
            <p class="text-xs text-gray-400 mb-0.5">Монтажник (автор)</p>
            <p class="text-sm font-medium text-gray-700">{{ act.creator?.name || '—' }}</p>
          </div>
        </div>
      </div>

      <div v-if="act.materials_changed_at" class="bg-red-50 border border-red-200 rounded-2xl px-4 py-3 text-sm text-red-700 flex items-center justify-between gap-3">
        <span>⚠ Бригадир изменил состав акта — изменения отмечены красным ниже. Перепишите бумажный акт по факту изменений и переподпишите его у абонента, затем подтвердите.</span>
        <button v-if="can.acknowledge" @click="acknowledge" class="btn-act-primary shrink-0">Принято</button>
      </div>

      <!-- Прогресс по этапам -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="font-medium text-sm text-gray-700 mb-3">Прогресс согласования</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="rounded-xl border border-gray-100 p-3">
            <p class="text-xs text-gray-400 mb-1">Бригадир</p>
            <p class="text-sm font-medium" :class="act.foreman_reviewed_at ? 'text-green-600' : 'text-gray-400'">
              {{ act.foreman_reviewed_at ? 'Утверждён' : 'Ожидает' }}
            </p>
            <p v-if="act.foreman_reviewer" class="text-[11px] text-gray-400 mt-0.5">{{ act.foreman_reviewer.name }}</p>
          </div>
          <div class="rounded-xl border border-gray-100 p-3" :class="act.type !== 'regular' ? 'opacity-40' : ''">
            <p class="text-xs text-gray-400 mb-1">ПЭО</p>
            <p class="text-sm font-medium" :class="act.peo_processed_at ? 'text-green-600' : 'text-gray-400'">
              {{ act.type !== 'regular' ? 'Не требуется' : (act.peo_processed_at ? 'Проведён' : 'Ожидает') }}
            </p>
            <p v-if="act.peo_processor" class="text-[11px] text-gray-400 mt-0.5">{{ act.peo_processor.name }}</p>
          </div>
          <div class="rounded-xl border border-gray-100 p-3">
            <p class="text-xs text-gray-400 mb-1">Логистика</p>
            <p class="text-sm font-medium" :class="act.logistics_processed_at ? 'text-green-600' : 'text-gray-400'">
              {{ act.logistics_processed_at ? 'Проведён' : 'Ожидает' }}
            </p>
            <p v-if="act.logistics_processor" class="text-[11px] text-gray-400 mt-0.5">{{ act.logistics_processor.name }}</p>
          </div>
          <div class="rounded-xl border border-gray-100 p-3">
            <p class="text-xs text-gray-400 mb-1">Абонотдел</p>
            <p class="text-sm font-medium" :class="act.subscriber_dept_completed_at ? 'text-green-600' : 'text-gray-400'">
              {{ act.subscriber_dept_completed_at ? 'Завершён' : 'Ожидает' }}
            </p>
            <p v-if="act.subscriber_dept_completer" class="text-[11px] text-gray-400 mt-0.5">{{ act.subscriber_dept_completer.name }}</p>
          </div>
        </div>
      </div>

      <!-- Материалы -->
      <div v-if="act.materials?.length || removedPending.length || can.editMaterials"
           class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
          <h3 class="font-medium text-sm text-gray-700">📦 Расходные материалы</h3>
          <span class="text-sm font-semibold text-blue-600">Итого: {{ totalMaterials }} ₽</span>
        </div>
        <table class="w-full text-xs">
          <thead class="bg-gray-50 text-[11px] text-gray-400 uppercase tracking-wide">
            <tr>
              <th v-if="can.foremanReview && act.materials?.length" class="px-2 py-2 text-center">
                <input type="checkbox" :checked="allChecked" @change="toggleAll" />
              </th>
              <th class="px-3 py-2 text-left">Код</th>
              <th class="px-4 py-2 text-left">Материал</th>
              <th class="px-3 py-2 text-right">Кол-во</th>
              <th class="px-3 py-2 text-right">Цена</th>
              <th class="px-3 py-2 text-right">Сумма</th>
              <th v-if="can.editMaterials && editMode" class="px-3 py-2 text-right">Действия</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="m in act.materials" :key="m.id" :class="isMaterialHighlighted(m.id) ? 'bg-red-50' : ''">
              <td v-if="can.foremanReview && act.materials?.length" class="px-2 py-2 text-center">
                <input type="checkbox" v-model="checkedMaterials[m.id]" />
              </td>
              <td class="px-3 py-2 text-center text-gray-400 font-mono text-xs">{{ m.material_code || '—' }}</td>
              <td class="px-4 py-2 text-gray-800">
                {{ m.material_name }}
                <span v-if="isMaterialHighlighted(m.id)" class="text-red-500 text-[10px] ml-1">изменено бригадиром</span>
              </td>
              <td class="px-3 py-2 text-right">
                <template v-if="can.editMaterials && editMode && editingId === m.id">
                  <input v-model.number="editQty" type="number" step="0.001" min="0.001"
                         class="w-20 border border-gray-200 rounded px-1 py-0.5 text-right" />
                </template>
                <template v-else>{{ m.quantity }} {{ m.material_unit }}</template>
              </td>
              <td class="px-3 py-2 text-right">{{ m.price_at_time }} ₽</td>
              <td class="px-3 py-2 text-right font-medium">{{ (m.price_at_time * m.quantity).toFixed(2) }} ₽</td>
              <td v-if="can.editMaterials && editMode" class="px-3 py-2 text-right whitespace-nowrap">
                <template v-if="editingId === m.id">
                  <button @click="saveEdit(m)" class="text-green-600 hover:text-green-800 mr-2">Сохранить</button>
                  <button @click="editingId = null" class="text-gray-400 hover:text-gray-600">Отмена</button>
                </template>
                <template v-else>
                  <button @click="startEdit(m)" class="text-blue-600 hover:text-blue-800 mr-2">Изменить</button>
                  <button @click="removeMaterialRow(m)" class="text-red-500 hover:text-red-700">Удалить</button>
                </template>
              </td>
            </tr>
            <tr v-for="h in removedPending" :key="'removed-' + h.id" class="bg-red-50 text-red-500">
              <td v-if="can.foremanReview && act.materials?.length"></td>
              <td class="px-3 py-2 text-center text-xs">—</td>
              <td class="px-4 py-2 line-through" colspan="3">Удалено бригадиром: {{ h.old_value }}</td>
              <td class="px-3 py-2 text-right text-xs">—</td>
              <td v-if="can.editMaterials && editMode"></td>
            </tr>
          </tbody>
        </table>

        <!-- Добавить материал (только бригадир, в режиме редактирования) -->
        <div v-if="can.editMaterials && editMode" class="flex flex-wrap gap-2 items-center px-4 py-3 border-t border-gray-100 bg-gray-50">
          <select v-model="newMaterialId" class="field-input text-sm flex-1 min-w-40">
            <option value="">— выбрать материал —</option>
            <option v-for="mc in materialsCatalog" :key="mc.id" :value="mc.id">
              {{ mc.code ? '[' + mc.code + '] ' : '' }}{{ mc.name }} — {{ mc.price }} ₽/{{ mc.unit }}
            </option>
          </select>
          <input v-model.number="newMaterialQty" type="number" step="0.001" min="0.001"
                 placeholder="Кол-во" class="field-input text-sm w-24" />
          <button @click="addMaterialRow" :disabled="!newMaterialId || !newMaterialQty"
                  class="btn-act-primary disabled:opacity-40">+ Добавить</button>
        </div>
      </div>

      <!-- История -->
      <div v-if="act.history?.length" class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="font-medium text-sm text-gray-700 mb-3">История</h3>
        <div class="space-y-2.5">
          <div v-for="h in act.history" :key="h.id" class="text-xs"
               :class="isUnackedEntry(h) ? 'bg-red-50 rounded-lg px-2 py-1.5 -mx-2' : ''">
            <div class="flex flex-wrap items-baseline gap-x-2">
              <span class="text-gray-400 whitespace-nowrap font-mono">{{ fmtDateTime(h.created_at) }}</span>
              <span :class="isUnackedEntry(h) ? 'text-red-700 font-semibold' : 'text-gray-800 font-medium'">{{ h.user?.name || 'Система' }}</span>
              <span :class="isUnackedEntry(h) ? 'text-red-600' : 'text-gray-500'">— {{ actionLabel(h.action) }}</span>
            </div>
            <p v-if="h.new_value || (h.action === 'material_removed' && h.old_value)"
               class="mt-0.5 pl-1" :class="isUnackedEntry(h) ? 'text-red-500' : 'text-gray-400'">
              ↳ {{ h.action === 'material_removed' ? h.old_value : h.new_value }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  act: Object,
  can: Object,
  materialsCatalog: { type: Array, default: () => [] },
})

const statusLabels = {
  pending_foreman:          'Ждёт бригадира',
  approved:                 'Утверждён',
  processing:               'В обработке',
  pending_subscriber_dept:  'Ждёт Абонотдел',
  completed:                'Завершён',
}

const totalMaterials = computed(() =>
  (props.act.materials ?? []).reduce((s, m) => s + m.price_at_time * m.quantity, 0).toFixed(2)
)

function typeLabel(type) {
  return type === 'repair' ? 'Ремонт/Восстановление' : type === 'regular' ? 'Обычный' : '—'
}

function statusClass(status) {
  return {
    pending_foreman:         'bg-amber-100 text-amber-700',
    approved:                'bg-indigo-100 text-indigo-700',
    processing:              'bg-indigo-100 text-indigo-700',
    pending_subscriber_dept: 'bg-indigo-100 text-indigo-700',
    completed:                'bg-green-100 text-green-700',
  }[status] || 'bg-gray-100 text-gray-600'
}

function actionLabel(action) {
  return {
    created:              'Акт создан',
    approved:             'Утверждён бригадиром',
    peo_processed:        'Проведён ПЭО',
    logistics_processed:  'Проведён Логистикой',
    completed:            'Завершён Абонотделом',
    material_added:       'Бригадир добавил материал',
    material_changed:     'Бригадир изменил количество',
    material_removed:     'Бригадир удалил материал',
    acknowledged:         'Изменения подтверждены монтажником',
  }[action] || action
}

function fmtDateTime(d) {
  return d ? new Date(d).toLocaleString('ru-RU') : '—'
}

function post(routeName) {
  router.post(route(routeName, props.act.id), {}, { preserveScroll: true })
}

// Возврат к списку актов — именно к той вкладке/фильтру/странице, откуда
// сюда пришли (Acts/Index.vue передаёт свой текущий адрес через ?from=),
// а не всегда на дефолтную /acts. Если открыли карточку напрямую по ссылке
// (from нет) — обычный переход на список.
function goBack() {
  const from = new URLSearchParams(window.location.search).get('from')
  if (from) {
    router.get(from)
  } else {
    router.get(route('acts.index'))
  }
}

function approve() {
  post('acts.approve')
}

function acknowledge() {
  post('acts.acknowledge')
}

// ── Режим редактирования состава (только бригадир, пока pending_foreman) ──
const editMode = ref(false)

function finishEditing() {
  editingId.value = null
  editMode.value = false
}

// ── Чеклист подтверждения позиций бригадиром (гейт для "Утвердить") ──
const checkedMaterials = reactive({})
watch(() => props.act.materials, (mats) => {
  Object.keys(checkedMaterials).forEach(k => delete checkedMaterials[k])
  ;(mats ?? []).forEach(m => { checkedMaterials[m.id] = false })
}, { immediate: true })

const allChecked = computed(() => {
  const mats = props.act.materials ?? []
  return mats.length === 0 || mats.every(m => checkedMaterials[m.id])
})

// Блокируем "Утвердить", пока не отмечены все позиции ИЛИ пока монтажник не
// подтвердил последние правки бригадира (materials_changed_at всё ещё висит).
const canApprove = computed(() => allChecked.value && !props.act.materials_changed_at)

const approveDisabledReason = computed(() => {
  if (props.act.materials_changed_at) return 'Дождитесь, пока монтажник подтвердит изменения состава'
  if (!allChecked.value) return 'Подтвердите все позиции материалов ниже'
  return ''
})

// После цикла "правка → Ознакомлен" кнопка меняет подпись, чтобы отличать
// первичное утверждение от возврата в активное состояние после правок.
const approveLabel = computed(() =>
  (props.act.history ?? []).some(h => h.action === 'acknowledged')
    ? 'Утвердить в активное состояние'
    : 'Утвердить'
)

function toggleAll(e) {
  const val = e.target.checked
  ;(props.act.materials ?? []).forEach(m => { checkedMaterials[m.id] = val })
}

// ── Подсветка правок бригадира, ждущих "Принято" от монтажника ──
const unacknowledgedHistory = computed(() =>
  (props.act.history ?? []).filter(h =>
    !h.acknowledged_at && ['material_added', 'material_changed', 'material_removed'].includes(h.action)
  )
)

const removedPending = computed(() =>
  unacknowledgedHistory.value.filter(h => h.action === 'material_removed')
)

function isMaterialHighlighted(materialId) {
  return unacknowledgedHistory.value.some(h => h.related_material_id === materialId)
}

function isUnackedEntry(h) {
  return !h.acknowledged_at && ['material_added', 'material_changed', 'material_removed'].includes(h.action)
}

// ── Правки состава внутри режима редактирования ──
const editingId = ref(null)
const editQty = ref(0)

function startEdit(m) {
  editingId.value = m.id
  editQty.value = m.quantity
}

function saveEdit(m) {
  router.put(route('acts.materials.update', [props.act.id, m.id]), { quantity: editQty.value }, {
    preserveScroll: true,
    onSuccess: () => { editingId.value = null },
  })
}

function removeMaterialRow(m) {
  if (!confirm(`Удалить материал "${m.material_name}" из акта?`)) return
  router.delete(route('acts.materials.destroy', [props.act.id, m.id]), { preserveScroll: true })
}

const newMaterialId = ref('')
const newMaterialQty = ref(1)

function addMaterialRow() {
  if (!newMaterialId.value || !newMaterialQty.value) return
  router.post(route('acts.materials.store', props.act.id), {
    material_id: newMaterialId.value,
    quantity: newMaterialQty.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { newMaterialId.value = ''; newMaterialQty.value = 1 },
  })
}
</script>
