<template>
  <Head title="Запросы услуг" />
  <AppLayout title="Запросы услуг">

    <!-- Фильтры -->
    <div class="bg-white rounded-xl border border-gray-200 p-3 mb-3 flex flex-wrap gap-2.5 items-end">
      <div class="flex-1 min-w-48">
        <label class="field-label">Поиск</label>
        <input v-model="f.search" @keydown.enter="apply" class="field-input" placeholder="Имя, телефон, адрес, услуга..." />
      </div>
      <div>
        <label class="field-label">Статус</label>
        <select v-model="f.status" class="field-input">
          <option value="">Все</option>
          <option value="pending">Ожидает</option>
          <option value="accepted">Выполнено</option>
          <option value="rejected">Отклонено</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button @click="apply" class="btn-primary text-sm">Найти</button>
        <button @click="reset" class="btn-outline text-sm">Сброс</button>
      </div>
    </div>

    <!-- Таблица -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-500">Всего: {{ requests.total }}</span>
          <span v-if="totalPending > 0"
                class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-medium">
            {{ totalPending }} ожидают
          </span>
        </div>
        <button @click="openCreate"
                class="px-2.5 py-1.5 rounded-lg text-sm font-medium bg-green-600 hover:bg-green-700 text-white transition-colors">
          + Новый запрос
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-50 text-[11px] text-gray-400 uppercase tracking-wide">
            <tr>
              <th class="px-3 py-2 text-left whitespace-nowrap">Дата</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Имя</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Телефон</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Адрес</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Услуга</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Описание</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Статус</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Комм. адм.</th>
              <th class="px-3 py-2 text-left whitespace-nowrap">Обработал</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 whitespace-nowrap text-gray-400">{{ fmtDate(r.created_at) }}</td>
              <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-800">{{ r.name }}</td>
              <td class="px-3 py-2 whitespace-nowrap font-mono text-gray-600">{{ r.phone }}</td>
              <td class="px-3 py-2 text-blue-600 hover:underline cursor-pointer max-w-[160px] truncate"
                  :title="r.address_string" @click="openDetail(r)">{{ r.address_string }}</td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span class="px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 font-medium">{{ r.service_name }}</span>
              </td>
              <td class="px-3 py-2 text-gray-500 max-w-[140px] truncate" :title="r.description">{{ r.description || '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span :class="statusClass(r.status)" class="px-1.5 py-0.5 rounded font-medium">{{ statusLabel(r.status) }}</span>
              </td>
              <td class="px-3 py-2 text-gray-600 max-w-[150px] truncate" :title="r.admin_comment">{{ r.admin_comment || '—' }}</td>
              <td class="px-3 py-2 text-gray-500 max-w-[130px] truncate" :title="r.processor?.name">{{ r.processor?.name || '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">
                <div class="flex gap-1 items-center">
                  <button v-if="r.status === 'pending'"
                          @click="openEdit(r)" title="Редактировать"
                          class="text-gray-300 hover:text-blue-500 transition-colors mr-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                  </button>
                  <template v-if="canProcess && r.status === 'pending'">
                    <button @click="openAccept(r)"
                            class="px-2 py-0.5 rounded bg-green-100 text-green-700 hover:bg-green-200 font-medium">
                      Выполнить
                    </button>
                    <button @click="openReject(r)"
                            class="px-2 py-0.5 rounded bg-red-100 text-red-700 hover:bg-red-200 font-medium">
                      Отклонить
                    </button>
                  </template>
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
           class="px-4 py-2.5 border-t border-gray-100 flex items-center gap-2">
        <button v-for="link in requests.links" :key="link.label"
                :disabled="!link.url || link.active"
                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                v-html="link.label"
                :class="['px-3 py-0.5 rounded-lg text-sm transition-colors',
                         link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600 disabled:opacity-40 disabled:cursor-default']" />
      </div>
    </div>

    <!-- Модал: Создать запрос -->
    <div v-if="modals.create" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-4">
        <h3 class="text-sm font-semibold mb-3">Новый запрос на услугу</h3>
        <div class="space-y-2">
          <div class="field-row">
            <label class="field-label">Имя <span class="text-red-400">*</span></label>
            <input v-model="createForm.name" class="field-input" placeholder="Иванов Иван Иванович" />
          </div>
          <div class="field-row">
            <label class="field-label">Телефон <span class="text-red-400">*</span></label>
            <input v-model="createForm.phone" class="field-input" placeholder="+7..." />
          </div>
          <div class="field-row">
            <label class="field-label">Адрес <span class="text-red-400">*</span></label>
            <input v-model="createForm.address_string" class="field-input" placeholder="ул. Ленина, 5, кв. 10" />
          </div>
          <div class="field-row">
            <label class="field-label">Услуга <span class="text-red-400">*</span></label>
            <select v-model="createForm.service_name" class="field-input">
              <option value="">— выберите услугу —</option>
              <option v-for="s in servicesList" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div>
            <label class="field-label">Описание / пожелания</label>
            <textarea v-model="createForm.description" class="field-input resize-none" rows="2"
                      placeholder="Дополнительная информация..."></textarea>
          </div>
        </div>
        <div v-if="createErrors" class="mt-2 text-xs text-red-600">{{ createErrors }}</div>
        <div class="mt-4 flex justify-end gap-2">
          <button @click="modals.create = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitCreate" :disabled="submitting" class="btn-primary text-sm">Создать</button>
        </div>
      </div>
    </div>

    <!-- Модал: Редактировать (оператор, пока pending) -->
    <div v-if="modals.edit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-4">
        <h3 class="text-sm font-semibold mb-3">Редактировать запрос</h3>
        <div class="space-y-2">
          <div class="field-row">
            <label class="field-label">Имя <span class="text-red-400">*</span></label>
            <input v-model="editForm.name" class="field-input" />
          </div>
          <div class="field-row">
            <label class="field-label">Телефон <span class="text-red-400">*</span></label>
            <input v-model="editForm.phone" class="field-input" />
          </div>
          <div class="field-row">
            <label class="field-label">Адрес <span class="text-red-400">*</span></label>
            <input v-model="editForm.address_string" class="field-input" />
          </div>
          <div class="field-row">
            <label class="field-label">Услуга <span class="text-red-400">*</span></label>
            <select v-model="editForm.service_name" class="field-input">
              <option value="">— выберите услугу —</option>
              <option v-for="s in servicesList" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div>
            <label class="field-label">Описание</label>
            <textarea v-model="editForm.description" class="field-input resize-none" rows="2"></textarea>
          </div>
        </div>
        <div v-if="editErrors" class="mt-2 text-xs text-red-600">{{ editErrors }}</div>
        <div class="mt-4 flex justify-end gap-2">
          <button @click="modals.edit = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitEdit" :disabled="submitting" class="btn-primary text-sm">Сохранить</button>
        </div>
      </div>
    </div>

    <!-- Модал: Выполнить (администратор) -->
    <div v-if="modals.accept" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-4">
        <h3 class="text-sm font-semibold mb-1">Выполнить запрос</h3>
        <p class="text-xs text-gray-500 mb-3">
          {{ activeRecord?.name }} — {{ activeRecord?.service_name }}
        </p>
        <div>
          <label class="field-label">Комментарий <span class="text-red-400">*</span></label>
          <textarea v-model="processForm.admin_comment" class="field-input resize-none" rows="3"
                    placeholder="Услуга будет подключена в течение..."></textarea>
        </div>
        <div v-if="processErrors" class="mt-2 text-xs text-red-600">{{ processErrors }}</div>
        <div class="mt-4 flex justify-end gap-2">
          <button @click="modals.accept = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitAccept" :disabled="submitting"
                  class="px-3.5 py-1.5 rounded-lg text-sm font-medium bg-green-600 hover:bg-green-700 text-white transition-colors">
            Выполнить
          </button>
        </div>
      </div>
    </div>

    <!-- Модал: Отклонить (администратор) -->
    <div v-if="modals.reject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-4">
        <h3 class="text-sm font-semibold mb-1">Отклонить запрос</h3>
        <p class="text-xs text-gray-500 mb-3">
          {{ activeRecord?.name }} — {{ activeRecord?.service_name }}
        </p>
        <div>
          <label class="field-label">Причина отклонения <span class="text-red-400">*</span></label>
          <textarea v-model="processForm.admin_comment" class="field-input resize-none" rows="3"
                    placeholder="Нет технической возможности..."></textarea>
        </div>
        <div v-if="processErrors" class="mt-2 text-xs text-red-600">{{ processErrors }}</div>
        <div class="mt-4 flex justify-end gap-2">
          <button @click="modals.reject = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitReject" :disabled="submitting"
                  class="px-3.5 py-1.5 rounded-lg text-sm font-medium bg-red-600 hover:bg-red-700 text-white transition-colors">
            Отклонить
          </button>
        </div>
      </div>
    </div>

    <!-- Модал: Полная информация -->
    <div v-if="modals.detail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="modals.detail = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-3">
            <h3 class="text-base font-semibold text-gray-800">Запрос услуги</h3>
            <span v-if="detailData" :class="statusClass(detailData.status)"
                  class="px-2 py-0.5 rounded text-xs font-medium">
              {{ statusLabel(detailData.status) }}
            </span>
          </div>
          <button @click="modals.detail = false" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div v-if="detailLoading" class="flex-1 flex items-center justify-center p-10 text-sm text-gray-400">
          Загрузка...
        </div>
        <div v-else-if="detailData" class="flex-1 overflow-y-auto p-4 space-y-3">
          <!-- Основные данные — лейбл в строку со значением -->
          <div class="grid grid-cols-2 gap-x-3 gap-y-1.5 text-sm">
            <p><span class="text-xs text-gray-400">Имя: </span><span class="font-medium">{{ detailData.name }}</span></p>
            <p><span class="text-xs text-gray-400">Телефон: </span><span class="font-mono">{{ detailData.phone }}</span></p>
            <p class="col-span-2"><span class="text-xs text-gray-400">Адрес: </span>{{ detailData.address_string }}</p>
            <p class="col-span-2">
              <span class="text-xs text-gray-400">Услуга: </span>
              <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 font-medium text-xs">
                {{ detailData.service_name }}
              </span>
            </p>
            <div v-if="detailData.description" class="col-span-2">
              <p class="text-xs text-gray-400 mb-0.5">Описание</p>
              <p class="text-gray-700 whitespace-pre-wrap">{{ detailData.description }}</p>
            </div>
            <div v-if="detailData.admin_comment" class="col-span-2">
              <p class="text-xs text-gray-400 mb-0.5">Комментарий администратора</p>
              <p class="text-gray-700 whitespace-pre-wrap">{{ detailData.admin_comment }}</p>
            </div>
          </div>

          <!-- История -->
          <div class="border-t border-gray-100 pt-3">
            <div class="text-xs font-medium text-gray-500 mb-2">История</div>
            <div v-if="!detailData.logs || !detailData.logs.length"
                 class="text-xs text-gray-400">История не записана (заявка создана до включения логирования)</div>
            <div v-else class="space-y-2">
              <div v-for="log in detailData.logs" :key="log.id"
                   class="flex gap-3 text-xs">
                <div class="flex flex-col items-center">
                  <div :class="logDotClass(log.action)" class="w-2 h-2 rounded-full mt-0.5 shrink-0"></div>
                  <div class="w-px flex-1 bg-gray-200 mt-1"></div>
                </div>
                <div class="pb-3 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-medium text-gray-800">{{ logActionLabel(log.action) }}</span>
                    <span class="text-gray-400">{{ fmtDateTime(log.created_at) }}</span>
                    <span v-if="log.user" class="text-gray-500">— {{ log.user.name }}</span>
                  </div>
                  <div v-if="log.notes" class="mt-0.5 text-gray-600 whitespace-pre-wrap">{{ log.notes }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="px-4 py-2.5 border-t border-gray-100 flex justify-end shrink-0">
          <button @click="modals.detail = false" class="btn-outline text-sm">Закрыть</button>
        </div>
      </div>
    </div>


  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  requests:     Object,
  filters:      Object,
  servicesList: { type: Array, default: () => [] },
  totalPending: { type: Number, default: 0 },
  canProcess:   { type: Boolean, default: false },
})

const f = ref({
  search: props.filters?.search ?? '',
  status: props.filters?.status ?? '',
})

function apply() {
  router.get(route('service-requests.index'), { ...f.value }, { preserveState: true })
}

function reset() {
  f.value = { search: '', status: '' }
  router.get(route('service-requests.index'), {}, { preserveState: true })
}

// Модалы
const modals       = reactive({ create: false, edit: false, accept: false, reject: false, detail: false })
const submitting   = ref(false)
const activeRecord = ref(null)

const createForm  = reactive({ name: '', phone: '', address_string: '', service_name: '', description: '' })
const createErrors = ref('')

const editForm    = reactive({ name: '', phone: '', address_string: '', service_name: '', description: '' })
const editErrors  = ref('')

const processForm   = reactive({ admin_comment: '' })
const processErrors = ref('')

function openCreate() {
  Object.assign(createForm, { name: '', phone: '', address_string: '', service_name: '', description: '' })
  createErrors.value = ''
  modals.create = true
}

function openEdit(r) {
  activeRecord.value = r
  Object.assign(editForm, {
    name:           r.name,
    phone:          r.phone,
    address_string: r.address_string,
    service_name:   r.service_name,
    description:    r.description ?? '',
  })
  editErrors.value = ''
  modals.edit = true
}

function openAccept(r) {
  activeRecord.value = r
  processForm.admin_comment = ''
  processErrors.value = ''
  modals.accept = true
}

function openReject(r) {
  activeRecord.value = r
  processForm.admin_comment = ''
  processErrors.value = ''
  modals.reject = true
}

function submitCreate() {
  if (!createForm.name || !createForm.phone || !createForm.address_string || !createForm.service_name) {
    createErrors.value = 'Заполните все обязательные поля'
    return
  }
  submitting.value = true
  router.post(route('service-requests.store'), { ...createForm }, {
    onSuccess: () => { modals.create = false },
    onFinish:  () => { submitting.value = false },
  })
}

function submitEdit() {
  if (!editForm.name || !editForm.phone || !editForm.address_string || !editForm.service_name) {
    editErrors.value = 'Заполните все обязательные поля'
    return
  }
  submitting.value = true
  router.put(route('service-requests.update', activeRecord.value.id), { ...editForm }, {
    onSuccess: () => { modals.edit = false },
    onFinish:  () => { submitting.value = false },
  })
}

function submitAccept() {
  if (!processForm.admin_comment.trim()) {
    processErrors.value = 'Введите комментарий'
    return
  }
  submitting.value = true
  router.post(route('service-requests.accept', activeRecord.value.id), { ...processForm }, {
    onSuccess: () => { modals.accept = false },
    onFinish:  () => { submitting.value = false },
  })
}

function submitReject() {
  if (!processForm.admin_comment.trim()) {
    processErrors.value = 'Введите причину отклонения'
    return
  }
  submitting.value = true
  router.post(route('service-requests.reject', activeRecord.value.id), { ...processForm }, {
    onSuccess: () => { modals.reject = false },
    onFinish:  () => { submitting.value = false },
  })
}

function statusLabel(s) {
  return { pending: 'Ожидает', accepted: 'Выполнено', rejected: 'Отклонено' }[s] ?? s
}
function statusClass(s) {
  return {
    pending:  'bg-yellow-100 text-yellow-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-700',
  }[s] ?? 'bg-gray-100 text-gray-600'
}
function fmtDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('ru-RU')
}
function fmtDateTime(val) {
  if (!val) return '—'
  const d = new Date(val)
  return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

const detailData    = ref(null)
const detailLoading = ref(false)

async function openDetail(r) {
  detailData.value    = null
  detailLoading.value = true
  modals.detail        = true
  try {
    const res = await fetch(route('service-requests.detail', r.id), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    detailData.value = await res.json()
  } catch (e) {
    console.error(e)
  } finally {
    detailLoading.value = false
  }
}

function logActionLabel(action) {
  return {
    created:  'Запрос создан',
    edited:   'Данные изменены',
    accepted: 'Выполнено',
    rejected: 'Отклонено',
  }[action] ?? action
}

function logDotClass(action) {
  return {
    created:  'bg-green-400',
    edited:   'bg-gray-300',
    accepted: 'bg-emerald-500',
    rejected: 'bg-red-400',
  }[action] ?? 'bg-gray-300'
}
</script>
