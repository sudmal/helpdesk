<template>
  <Head title="Запросы услуг" />
  <AppLayout title="Запросы услуг">

    <!-- Фильтры -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-48">
        <label class="block text-xs text-gray-500 mb-1">Поиск</label>
        <input v-model="f.search" @keydown.enter="apply" class="field-input" placeholder="Имя, телефон, адрес, услуга..." />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Статус</label>
        <select v-model="f.status" class="field-input">
          <option value="">Все</option>
          <option value="pending">Ожидает</option>
          <option value="accepted">Принято</option>
          <option value="rejected">Отклонено</option>
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
        <div class="flex items-center gap-3">
          <span class="text-sm text-gray-500">Всего: {{ requests.total }}</span>
          <span v-if="totalPending > 0"
                class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-medium">
            {{ totalPending }} ожидают
          </span>
        </div>
        <button @click="openCreate"
                class="px-3 py-1.5 rounded-xl text-sm font-medium bg-green-600 hover:bg-green-700 text-white transition-colors">
          + Новый запрос
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-3 py-3 text-left w-8"></th>
              <th class="px-4 py-3 text-left">Дата</th>
              <th class="px-4 py-3 text-left">Имя</th>
              <th class="px-4 py-3 text-left">Телефон</th>
              <th class="px-4 py-3 text-left">Адрес</th>
              <th class="px-4 py-3 text-left">Услуга</th>
              <th class="px-4 py-3 text-left">Описание</th>
              <th class="px-4 py-3 text-left">Статус</th>
              <th class="px-4 py-3 text-left">Комментарий адм.</th>
              <th class="px-4 py-3 text-left">Обработал</th>
              <th class="px-4 py-3 text-left"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-xs">
            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-gray-50">
              <!-- Редактировать (только создатель/все операторы — просто просмотр) -->
              <td class="px-2 py-1.5 text-center">
                <button v-if="r.status === 'pending' && !canProcess"
                        @click="openEdit(r)" title="Редактировать"
                        class="text-gray-400 hover:text-blue-600 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
              </td>
              <td class="px-3 py-1.5 whitespace-nowrap text-gray-500">{{ fmtDate(r.created_at) }}</td>
              <td class="px-3 py-1.5 font-medium">{{ r.name }}</td>
              <td class="px-3 py-1.5 font-mono whitespace-nowrap">{{ r.phone }}</td>
              <td class="px-3 py-1.5 text-gray-700">{{ r.address_string }}</td>
              <td class="px-3 py-1.5">
                <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-xs font-medium">
                  {{ r.service_name }}
                </span>
              </td>
              <td class="px-3 py-1.5 text-gray-500 max-w-48 truncate" :title="r.description">{{ r.description || '—' }}</td>
              <td class="px-3 py-1.5">
                <span :class="statusClass(r.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ statusLabel(r.status) }}
                </span>
              </td>
              <td class="px-3 py-1.5 text-gray-600 max-w-48 truncate" :title="r.admin_comment">
                {{ r.admin_comment || '—' }}
              </td>
              <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap">
                {{ r.processor?.name || '—' }}
              </td>
              <td class="px-3 py-1.5 whitespace-nowrap">
                <div v-if="canProcess && r.status === 'pending'" class="flex gap-1">
                  <button @click="openAccept(r)"
                          class="px-2 py-0.5 rounded bg-green-100 text-green-700 hover:bg-green-200 text-xs font-medium">
                    Принять
                  </button>
                  <button @click="openReject(r)"
                          class="px-2 py-0.5 rounded bg-red-100 text-red-700 hover:bg-red-200 text-xs font-medium">
                    Отклонить
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!requests.data.length">
              <td colspan="11" class="px-4 py-8 text-center text-gray-400">Нет записей</td>
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

    <!-- Модал: Создать запрос -->
    <div v-if="modals.create" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-4">Новый запрос на услугу</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Имя абонента <span class="text-red-400">*</span></label>
            <input v-model="createForm.name" class="field-input w-full" placeholder="Иванов Иван Иванович" />
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
            <label class="block text-xs text-gray-500 mb-1">Запрашиваемая услуга <span class="text-red-400">*</span></label>
            <select v-model="createForm.service_name" class="field-input w-full">
              <option value="">— выберите услугу —</option>
              <option v-for="s in servicesList" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Описание / пожелания</label>
            <textarea v-model="createForm.description" class="field-input w-full" rows="3"
                      placeholder="Дополнительная информация..."></textarea>
          </div>
        </div>
        <div v-if="createErrors" class="mt-3 text-xs text-red-600">{{ createErrors }}</div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.create = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitCreate" :disabled="submitting" class="btn-primary text-sm">Создать</button>
        </div>
      </div>
    </div>

    <!-- Модал: Редактировать (оператор, пока pending) -->
    <div v-if="modals.edit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-4">Редактировать запрос</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Имя абонента <span class="text-red-400">*</span></label>
            <input v-model="editForm.name" class="field-input w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Телефон <span class="text-red-400">*</span></label>
            <input v-model="editForm.phone" class="field-input w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Адрес <span class="text-red-400">*</span></label>
            <input v-model="editForm.address_string" class="field-input w-full" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Услуга <span class="text-red-400">*</span></label>
            <select v-model="editForm.service_name" class="field-input w-full">
              <option value="">— выберите услугу —</option>
              <option v-for="s in servicesList" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Описание</label>
            <textarea v-model="editForm.description" class="field-input w-full" rows="3"></textarea>
          </div>
        </div>
        <div v-if="editErrors" class="mt-3 text-xs text-red-600">{{ editErrors }}</div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.edit = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitEdit" :disabled="submitting" class="btn-primary text-sm">Сохранить</button>
        </div>
      </div>
    </div>

    <!-- Модал: Принять (администратор) -->
    <div v-if="modals.accept" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-1">Принять запрос</h3>
        <p class="text-xs text-gray-500 mb-4">
          {{ activeRecord?.name }} — {{ activeRecord?.service_name }}
        </p>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Комментарий <span class="text-red-400">*</span></label>
          <textarea v-model="processForm.admin_comment" class="field-input w-full" rows="4"
                    placeholder="Услуга будет подключена в течение..."></textarea>
        </div>
        <div v-if="processErrors" class="mt-2 text-xs text-red-600">{{ processErrors }}</div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.accept = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitAccept" :disabled="submitting"
                  class="px-4 py-2 rounded-xl text-sm font-medium bg-green-600 hover:bg-green-700 text-white transition-colors">
            Принять
          </button>
        </div>
      </div>
    </div>

    <!-- Модал: Отклонить (администратор) -->
    <div v-if="modals.reject" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-1">Отклонить запрос</h3>
        <p class="text-xs text-gray-500 mb-4">
          {{ activeRecord?.name }} — {{ activeRecord?.service_name }}
        </p>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Причина отклонения <span class="text-red-400">*</span></label>
          <textarea v-model="processForm.admin_comment" class="field-input w-full" rows="4"
                    placeholder="Нет технической возможности..."></textarea>
        </div>
        <div v-if="processErrors" class="mt-2 text-xs text-red-600">{{ processErrors }}</div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.reject = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitReject" :disabled="submitting"
                  class="px-4 py-2 rounded-xl text-sm font-medium bg-red-600 hover:bg-red-700 text-white transition-colors">
            Отклонить
          </button>
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
const modals       = reactive({ create: false, edit: false, accept: false, reject: false })
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
  router.post(route('service-requests.store'), { ...editForm }, {
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
  return { pending: 'Ожидает', accepted: 'Принято', rejected: 'Отклонено' }[s] ?? s
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
</script>
