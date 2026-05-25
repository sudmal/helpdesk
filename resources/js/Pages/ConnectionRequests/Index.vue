<template>
  <Head title="Подключения" />
  <AppLayout title="Подключения">

    <!-- Вкладки территорий -->
    <div class="bg-white rounded-2xl border border-gray-200 px-4 py-2.5 mb-4 flex items-center gap-2 flex-wrap">
      <span class="text-xs text-gray-400 font-medium">Территория:</span>
      <button @click="selectTerritory(null)"
              :class="['px-3 py-1.5 rounded-xl text-sm font-medium transition-colors flex items-center gap-1',
                       selectedTerritory === null
                         ? 'bg-blue-600 text-white'
                         : 'text-gray-600 hover:bg-gray-100']">
        Все
        <span v-if="totalPending > 0"
              :class="selectedTerritory === null ? 'text-orange-300' : 'text-orange-500'"
              class="font-bold text-sm leading-none">✱</span>
      </button>
      <button v-for="t in territories" :key="t.id"
              @click="selectTerritory(t.id)"
              :class="['px-3 py-1.5 rounded-xl text-sm font-medium transition-colors flex items-center gap-1',
                       selectedTerritory === t.id
                         ? 'bg-blue-600 text-white'
                         : 'text-gray-600 hover:bg-gray-100']">
        {{ t.name }}
        <span v-if="pendingByTerritory[t.id]"
              :class="selectedTerritory === t.id ? 'text-orange-300' : 'text-orange-500'"
              class="font-bold text-sm leading-none">✱</span>
      </button>
    </div>

    <!-- Фильтры -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-48">
        <label class="block text-xs text-gray-500 mb-1">Поиск</label>
        <input v-model="f.search" @keydown.enter="apply" class="field-input" placeholder="Имя, телефон, адрес..." />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Статус</label>
        <select v-model="f.status" class="field-input">
          <option value="">Все</option>
          <option value="pending">Ожидает</option>
          <option value="scheduled">Назначено</option>
          <option value="rejected">Отклонено</option>
          <option value="closed">Выполнено</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button @click="apply" class="btn-primary text-sm">Найти</button>
        <button @click="reset" class="btn-outline text-sm">Сброс</button>
      </div>
    </div>

    <!-- Таблица -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <span class="text-sm text-gray-500">Всего: {{ requests.total }}</span>
        <button @click="openCreate"
                class="px-3 py-1.5 rounded-xl text-sm font-medium bg-green-600 hover:bg-green-700 text-white transition-colors">
          + Новая заявка
        </button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-4 py-3 text-left">Дата</th>
              <th class="px-4 py-3 text-left">Имя</th>
              <th class="px-4 py-3 text-left">Телефон</th>
              <th class="px-4 py-3 text-left">Адрес</th>
              <th class="px-4 py-3 text-left">Описание</th>
              <th class="px-4 py-3 text-left">Статус</th>
              <th class="px-4 py-3 text-left">Дата подкл.</th>
              <th class="px-4 py-3 text-left">Примечания / Акт</th>
              <th class="px-4 py-3 text-left"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-xs">
            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-gray-50">
              <td class="px-3 py-1.5 whitespace-nowrap text-gray-500">{{ fmtDate(r.created_at) }}</td>
              <td class="px-3 py-1.5 font-medium">{{ r.name }}</td>
              <td class="px-3 py-1.5 font-mono whitespace-nowrap">{{ r.phone }}</td>
              <td class="px-3 py-1.5 text-gray-700">{{ r.address_string }}</td>
              <td class="px-3 py-1.5 text-gray-500 max-w-48 truncate" :title="r.description">{{ r.description || '—' }}</td>
              <td class="px-3 py-1.5">
                <span :class="statusClass(r.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ statusLabel(r.status) }}
                </span>
              </td>
              <td class="px-3 py-1.5 whitespace-nowrap text-gray-600">{{ r.scheduled_at ? fmtDate(r.scheduled_at) : '—' }}</td>
              <td class="px-3 py-1.5 text-gray-600 max-w-48 truncate" :title="r.notes">
                <span v-if="r.act_number" class="mr-1 text-gray-400">[{{ r.act_number }}]</span>{{ r.notes || '—' }}
              </td>
              <td class="px-3 py-1.5 whitespace-nowrap">
                <div class="flex gap-1">
                  <button v-if="r.status === 'pending'"
                          @click="openSchedule(r)"
                          class="px-2 py-0.5 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 text-xs font-medium">
                    Назначить
                  </button>
                  <button v-if="r.status === 'pending'"
                          @click="openReject(r)"
                          class="px-2 py-0.5 rounded bg-red-100 text-red-700 hover:bg-red-200 text-xs font-medium">
                    Отклонить
                  </button>
                  <button v-if="r.status === 'scheduled'"
                          @click="openClose(r)"
                          class="px-2 py-0.5 rounded bg-green-100 text-green-700 hover:bg-green-200 text-xs font-medium">
                    Выполнено
                  </button>
                  <button v-if="r.status === 'scheduled' || r.status === 'rejected' || r.status === 'closed'"
                          @click="openSchedule(r)"
                          class="px-2 py-0.5 rounded bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs">
                    Изменить
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!requests.data.length">
              <td colspan="10" class="px-4 py-8 text-center text-gray-400">Нет записей</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Пагинация -->
      <div v-if="requests.last_page > 1"
           class="px-5 py-3 border-t border-gray-100 flex items-center gap-2">
        <button v-for="link in requests.links" :key="link.label"
                :disabled="!link.url || link.active"
                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                v-html="link.label"
                :class="['px-3 py-0.5 rounded-lg text-sm transition-colors',
                         link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600 disabled:opacity-40 disabled:cursor-default']" />
      </div>
    </div>

    <!-- Модал: Создать заявку -->
    <div v-if="modals.create" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-4">Новая заявка на подключение</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Имя клиента <span class="text-red-400">*</span></label>
            <input v-model="createForm.name" class="field-input w-full" placeholder="Иванов Иван" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Телефон <span class="text-red-400">*</span></label>
            <input v-model="createForm.phone" class="field-input w-full" placeholder="+7..." />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Адрес <span class="text-red-400">*</span></label>
            <input v-model="createForm.address_string" class="field-input w-full" placeholder="ул. Ленина, 5, кв. 10" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Территория <span class="text-red-400">*</span></label>
            <select v-model="createForm.territory_id" class="field-input w-full">
              <option :value="null">— выберите территорию —</option>
              <option v-for="t in territories" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Описание</label>
            <textarea v-model="createForm.description" class="field-input w-full" rows="3"
                      placeholder="Желаемый тариф, заметки..."></textarea>
          </div>
        </div>
        <div v-if="createErrors" class="mt-3 text-xs text-red-600">{{ createErrors }}</div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.create = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitCreate" :disabled="submitting" class="btn-primary text-sm">Создать</button>
        </div>
      </div>
    </div>

    <!-- Модал: Назначить/Изменить -->
    <div v-if="modals.schedule" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-4">Изменить заявку</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Статус</label>
            <select v-model="scheduleForm.status" class="field-input w-full">
              <option value="pending">Ожидает</option>
              <option value="scheduled">Назначено</option>
              <option value="rejected">Отклонено</option>
            </select>
          </div>
          <div v-if="scheduleForm.status === 'scheduled'">
            <label class="block text-xs text-gray-500 mb-1">Дата подключения</label>
            <input v-model="scheduleForm.scheduled_at" type="datetime-local" class="field-input w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Передать на территорию</label>
            <select v-model="scheduleForm.territory_id" class="field-input w-full">
              <option :value="null">— без изменений —</option>
              <option v-for="t in territories" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Примечания</label>
            <textarea v-model="scheduleForm.notes" class="field-input w-full" rows="3"></textarea>
          </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.schedule = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitSchedule" :disabled="submitting" class="btn-primary text-sm">Сохранить</button>
        </div>
      </div>
    </div>

    <!-- Модал: Отклонить -->
    <div v-if="modals.reject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-4">Отклонить заявку</h3>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Причина отклонения</label>
          <textarea v-model="rejectForm.notes" class="field-input w-full" rows="4"
                    placeholder="Нет технической возможности..."></textarea>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.reject = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitReject" :disabled="submitting"
                  class="px-4 py-2 rounded-xl text-sm font-medium bg-red-600 hover:bg-red-700 text-white transition-colors">
            Отклонить
          </button>
        </div>
      </div>
    </div>

    <!-- Модал: Выполнено -->
    <div v-if="modals.close" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 overflow-y-auto py-8">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6">
        <h3 class="text-base font-semibold mb-4">Закрыть — подключение выполнено</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Номер акта</label>
            <input v-model="closeForm.act_number" class="field-input w-full"
                   placeholder="А-123 (или оставьте пустым → б/а)" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Примечания</label>
            <textarea v-model="closeForm.notes" class="field-input w-full" rows="3"></textarea>
          </div>
          <div>
            <div class="flex items-center justify-between mb-2">
              <label class="text-xs text-gray-500 font-medium">Использованные материалы</label>
              <button @click="addMaterialRow" class="text-xs text-blue-600 hover:underline">+ добавить</button>
            </div>
            <div v-for="(row, idx) in closeForm.materials" :key="idx"
                 class="flex gap-2 items-center mb-1.5">
              <select v-model="row.material_id" class="field-input flex-1 text-xs">
                <option :value="null">— выберите материал —</option>
                <option v-for="m in materialsCatalog" :key="m.id" :value="m.id">
                  {{ m.name }} ({{ m.unit }})
                </option>
              </select>
              <input v-model="row.quantity" type="number" min="0.01" step="0.01"
                     class="field-input w-24 text-xs" placeholder="кол-во" />
              <button @click="removeMaterialRow(idx)" class="text-red-400 hover:text-red-600 px-1">✕</button>
            </div>
            <div v-if="!closeForm.materials.length" class="text-xs text-gray-400">Материалы не добавлены</div>
          </div>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.close = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitClose" :disabled="submitting"
                  class="px-4 py-2 rounded-xl text-sm font-medium bg-green-600 hover:bg-green-700 text-white transition-colors">
            Выполнено
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  requests:          Object,
  filters:           Object,
  territories:        Array,
  selectedTerritory:  { type: Number, default: null },
  pendingByTerritory: { type: Object, default: () => ({}) },
  totalPending:       { type: Number, default: 0 },
  materialsCatalog:  Array,
})

const f = ref({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
})

function selectTerritory(id) {
  router.get(route('connection-requests.index'), { ...f.value, territory: id }, { preserveState: true })
}

function apply() {
  router.get(route('connection-requests.index'), {
    ...f.value,
    territory: props.selectedTerritory,
  }, { preserveState: true })
}

function reset() {
  f.value = { search: '', status: '' }
  router.get(route('connection-requests.index'), { territory: props.selectedTerritory }, { preserveState: true })
}

// Модалы
const modals      = reactive({ create: false, schedule: false, reject: false, close: false })
const submitting  = ref(false)
const activeRecord = ref(null)

const createForm  = reactive({ name: '', phone: '', address_string: '', description: '', territory_id: null })
const createErrors = ref('')
const scheduleForm = reactive({ status: 'scheduled', scheduled_at: '', territory_id: null, notes: '' })
const rejectForm   = reactive({ notes: '' })
const closeForm    = reactive({ act_number: '', notes: '', materials: [] })

function openCreate() {
  Object.assign(createForm, {
    name: '', phone: '', address_string: '', description: '',
    territory_id: props.selectedTerritory ?? null,
  })
  createErrors.value = ''
  modals.create = true
}

function openSchedule(r) {
  activeRecord.value = r
  Object.assign(scheduleForm, {
    status:       r.status === 'pending' ? 'scheduled' : r.status,
    scheduled_at: r.scheduled_at ? r.scheduled_at.slice(0, 16) : '',
    territory_id: r.territory_id ?? null,
    notes:        r.notes ?? '',
  })
  modals.schedule = true
}

function openReject(r) {
  activeRecord.value = r
  rejectForm.notes = ''
  modals.reject = true
}

function openClose(r) {
  activeRecord.value = r
  Object.assign(closeForm, { act_number: '', notes: r.notes ?? '', materials: [] })
  modals.close = true
}

function addMaterialRow()       { closeForm.materials.push({ material_id: null, quantity: '' }) }
function removeMaterialRow(idx) { closeForm.materials.splice(idx, 1) }

function submitCreate() {
  if (!createForm.name || !createForm.phone || !createForm.address_string) {
    createErrors.value = 'Заполните обязательные поля'
    return
  }
  if (!createForm.territory_id) {
    createErrors.value = 'Выберите территорию'
    return
  }
  submitting.value = true
  router.post(route('connection-requests.store'), { ...createForm }, {
    onSuccess: () => { modals.create = false },
    onFinish:  () => { submitting.value = false },
  })
}

function submitSchedule() {
  submitting.value = true
  router.put(route('connection-requests.update', activeRecord.value.id), { ...scheduleForm }, {
    onSuccess: () => { modals.schedule = false },
    onFinish:  () => { submitting.value = false },
  })
}

function submitReject() {
  submitting.value = true
  router.put(route('connection-requests.update', activeRecord.value.id), {
    status: 'rejected',
    notes:  rejectForm.notes,
  }, {
    onSuccess: () => { modals.reject = false },
    onFinish:  () => { submitting.value = false },
  })
}

function submitClose() {
  const materials = closeForm.materials.filter(m => m.material_id && m.quantity)
  submitting.value = true
  router.post(route('connection-requests.close', activeRecord.value.id), {
    act_number: closeForm.act_number,
    notes:      closeForm.notes,
    materials,
  }, {
    onSuccess: () => { modals.close = false },
    onFinish:  () => { submitting.value = false },
  })
}

function statusLabel(s) {
  return { pending: 'Ожидает', scheduled: 'Назначено', rejected: 'Отклонено', closed: 'Выполнено' }[s] ?? s
}
function statusClass(s) {
  return {
    pending:   'bg-yellow-100 text-yellow-800',
    scheduled: 'bg-blue-100 text-blue-800',
    rejected:  'bg-red-100 text-red-700',
    closed:    'bg-green-100 text-green-800',
  }[s] ?? 'bg-gray-100 text-gray-600'
}
function fmtDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('ru-RU')
}
</script>
