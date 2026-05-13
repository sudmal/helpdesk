<template>
  <Head title="Новая заявка" />
  <AppLayout title="Новая заявка">

    <!-- Двухколоночный layout: форма слева, история справа -->
    <div class="flex gap-5 items-start">

      <!-- ── Левая колонка: форма ── -->
      <div class="flex-1 min-w-0 space-y-4">

        <!-- Адрес (обязателен) -->
        <div :class="['bg-white rounded-2xl border p-5',
                      showAddressError ? 'border-red-300 ring-1 ring-red-200' : 'border-gray-200']">
          <h3 class="font-medium text-sm text-gray-700 mb-3">
            Адрес абонента <span class="text-red-500">*</span>
          </h3>

          <div class="flex gap-2">
            <div class="relative flex-1">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                   fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
              <input v-model="addressQuery"
                     @input="searchAddresses"
                     @focus="showAddressError = false"
                     placeholder="Улица, дом, квартира, абонент, телефон..."
                     class="w-full pl-9 pr-9 py-2.5 border border-gray-200 rounded-xl text-sm
                            focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
              <svg v-if="addressLoading"
                   class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-blue-500 animate-spin"
                   fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <div v-if="suggestions.length"
                   class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-gray-200
                          rounded-xl shadow-xl max-h-64 overflow-y-auto">
                <button v-for="a in suggestions" :key="a.id"
                        @click="selectAddress(a)" type="button"
                        class="w-full text-left px-4 py-2.5 hover:bg-blue-50 text-sm
                               border-b border-gray-100 last:border-0">
                  <p class="font-medium text-gray-800">{{ a.label }}</p>
                  <p class="text-xs text-gray-400">
                    {{ [a.subscriber_name, a.phone, a.territory].filter(Boolean).join(' · ') }}
                  </p>
                </button>
              </div>
            </div>
            <button type="button" @click="openAddrModal"
                    class="shrink-0 btn-outline text-sm whitespace-nowrap self-start">
              📍 Адрес
            </button>
          </div>
          <!-- LANBilling -->
          <template v-if="lanbillingEnabled">
          <div class="flex gap-2 mb-3">
            <input v-model="billingQuery"
                   placeholder="Телефон или № договора → LANBilling"
                   class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm
                          focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
            <button @click="lookupBilling" type="button" :disabled="billingLoading"
                    class="btn-outline text-sm whitespace-nowrap">
              {{ billingLoading ? '...' : '🔍 LANBilling' }}
            </button>
          </div>
          </template>

          <!-- Выбранный адрес -->
          <div v-if="selectedAddress"
               class="bg-blue-50 border border-blue-200 rounded-xl p-3 flex items-start justify-between gap-2">
            <div>
              <p class="text-sm font-medium text-blue-800">📍 {{ selectedAddress.label }}</p>
              <div class="flex items-center gap-2 mt-1 flex-wrap">
                <span v-if="selectedAddress.territory"
                      class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                  📌 {{ selectedAddress.territory }}
                </span>
                <span v-if="selectedAddress.subscriber_name"
                      class="text-xs text-blue-500">
                  {{ selectedAddress.subscriber_name }}
                </span>
                <span v-if="selectedAddress.phone" class="text-xs text-blue-500">
                  {{ selectedAddress.phone }}
                </span>
              </div>
            </div>
            <button @click="clearAddress" type="button"
                    class="text-blue-300 hover:text-red-500 shrink-0 transition-colors">✕</button>
          </div>


          <p v-if="showAddressError"
             class="mt-2 text-xs text-red-600">⚠ Выберите адрес абонента из списка</p>
          <p v-else-if="fieldError.apartment"
             class="mt-2 text-xs text-red-600">⚠ Для МКД обязательно укажите квартиру</p>
          <p v-else-if="needsApartment"
             class="mt-2 text-xs text-amber-600">⚠ Это МКД — укажите номер квартиры</p>
          <p v-else-if="!selectedAddress"
             class="mt-2 text-xs text-gray-400">Начните вводить адрес для поиска</p>
        </div>

        <!-- Форма деталей -->
        <form @submit.prevent="submitTicket"
              class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
          <h3 class="font-medium text-sm text-gray-700">Детали заявки</h3>

          <div class="grid grid-cols-2 gap-4">

            <!-- Участок * -->
            <div>
              <label class="field-label">Участок <span class="text-red-500">*</span></label>
              <select v-model="form.service_type_id"
                      :class="['field-input', fieldError.service_type ? 'border-red-400 bg-red-50' : '']">
                <option value="" disabled>— Выбрать —</option>
                <option v-for="s in serviceTypes" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>

            <!-- Тип заявки * -->
            <div>
              <label class="field-label">Тип заявки <span class="text-red-500">*</span></label>
              <select v-model="form.type_id"
                      :class="['field-input', fieldError.type ? 'border-red-400 bg-red-50' : '']">
                <option value="" disabled>— Выбрать —</option>
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </div>

            <!-- Бригада * -->
            <div>
              <label class="field-label">Бригада <span class="text-red-500">*</span></label>
              <select v-model="form.brigade_id"
                      :class="['field-input', fieldError.brigade ? 'border-red-400 bg-red-50' : '']">
                <option value="" disabled>— Выбрать —</option>
                <option v-for="b in availableBrigades" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>

            <!-- Приоритет -->
            <div>
              <label class="field-label">Приоритет <span class="text-red-500">*</span></label>
              <select v-model="form.priority" class="field-input">
                <option value="low">Низкий</option>
                <option value="normal">Обычный</option>
                <option value="high">Высокий</option>
                <option value="urgent">Срочный</option>
              </select>
            </div>

            <!-- Телефон * -->
            <div>
              <label class="field-label">Телефон <span class="text-red-500">*</span></label>
              <input v-model="form.phone" type="tel"
                     :class="['field-input', fieldError.phone ? 'border-red-400 bg-red-50' : '']"
                     placeholder="+7..." />
            <p v-if="fieldError.phone" class="text-xs text-red-500 mt-1">⚠ Укажите телефон</p>
          </div>

            <!-- Договор -->
            <div>
              <label class="field-label">№ договора</label>
              <input v-model="form.contract_no" class="field-input" placeholder="12345" />
            </div>

            <!-- Время выезда * -->
            <div class="col-span-2" :class="fieldError.scheduled_at ? 'ring-1 ring-red-300 rounded-xl p-2 bg-red-50' : ''">
              <label class="field-label">
                Время выезда <span class="text-red-500">*</span>
              </label>
              <TimePicker v-model="form.scheduled_at"
                          :work-start="settings.work_hours_start"
                          :work-end="settings.work_hours_end"
                          :step-minutes="Number(settings.schedule_step_minutes)" />
              <div v-if="form.errors.scheduled_at"
                   class="mt-2 p-2.5 bg-red-50 border border-red-200 rounded-xl text-xs text-red-700">
                ⚠ {{ form.errors.scheduled_at }}
              </div>
            </div>
          </div>

          <!-- Описание * -->
          <div>
            <label class="field-label">Описание <span class="text-red-500">*</span></label>
            <textarea v-model="form.description" rows="4"
                      :class="['field-input resize-none', fieldError.description ? 'border-red-400 bg-red-50' : '']"
                      placeholder="Опишите проблему или задачу..."></textarea>
          <p v-if="fieldError.description" class="text-xs text-red-500 mt-1">⚠ Заполните описание</p>
          </div>

          <!-- Вложения -->
          <AttachmentUpload v-model="attachmentFiles" />

          <!-- Кнопки -->
          <div class="flex items-center justify-between pt-2 border-t border-gray-100">
            <!-- Индикаторы незаполненных полей -->
            <div v-if="submitted && !isFormComplete" class="text-xs text-red-500 flex items-center gap-1">
              ⚠ Заполните все обязательные поля
            </div>
            <div v-else class="text-xs text-gray-400">
              {{ isFormComplete ? '✓ Все поля заполнены' : 'Заполните обязательные поля (*)' }}
            </div>

            <div class="flex gap-3">
              <a :href="route('tickets.index')" class="btn-outline text-sm">Отмена</a>
              <button
                type="submit"
                :disabled="form.processing"
                :class="['btn-primary text-sm transition-all',
                         submitted && !isFormComplete ? 'opacity-60' : '']">
                {{ form.processing ? 'Создание...' : 'Создать заявку' }}
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- ── Правая колонка: история по адресу ── -->
      <div v-if="selectedAddress && historyTotal > 0"
           class="w-72 shrink-0 bg-white rounded-2xl border border-amber-200 overflow-hidden
                  sticky top-4 max-h-[calc(100vh-6rem)] flex flex-col">
        <div class="px-4 py-3 bg-amber-50 border-b border-amber-200 flex items-center justify-between">
          <h3 class="font-medium text-sm text-amber-800">
            📋 Заявки по адресу
          </h3>
          <span class="bg-amber-600 text-white text-xs px-2 py-0.5 rounded-full">{{ historyTotal }}</span>
        </div>

        <div class="overflow-y-auto flex-1 divide-y divide-gray-100">
          <a v-for="h in pagedHistory" :key="h.id"
             :href="route('tickets.show', h.id)"
             target="_blank"
             class="flex items-center justify-between px-4 py-2.5
                    hover:bg-amber-50 transition-colors">
            <div class="min-w-0 mr-2 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-mono text-blue-600 font-medium">{{ h.number }}</span>
                <span class="text-xs text-gray-400">{{ formatDate(h.created_at) }}</span>
              </div>
              <span class="text-xs text-gray-500">{{ h.type?.name }}</span>
              <p v-if="h.description" class="text-xs text-gray-600 mt-0.5" :title="h.description">
                {{ h.description.slice(0, 70) }}{{ h.description.length > 70 ? '…' : '' }}
              </p>
            </div>
            <Badge v-if="h.status" :color="h.status.color" :label="h.status.name" small />
          </a>
        </div>

        <!-- Пагинация истории -->
        <div v-if="historyPages > 1"
             class="px-4 py-2.5 border-t border-amber-100 flex items-center justify-between text-xs">
          <button @click="historyPage = Math.max(1, historyPage - 1)"
                  :disabled="historyPage === 1"
                  class="px-2 py-1 rounded border disabled:opacity-30 hover:bg-gray-50">‹</button>
          <span class="text-gray-400">{{ historyPage }} / {{ historyPages }}</span>
          <button @click="historyPage = Math.min(historyPages, historyPage + 1)"
                  :disabled="historyPage === historyPages"
                  class="px-2 py-1 rounded border disabled:opacity-30 hover:bg-gray-50">›</button>
        </div>
      </div>

    </div>

    <!-- Выбор адреса по иерархии -->
    <Modal v-if="showAddrModal" title="Выбор адреса" @close="showAddrModal = false">
      <div class="space-y-3">
        <div>
          <label class="field-label">Город</label>
          <select v-model="addrSel.city" @change="onAddrCity" class="field-input">
            <option value="">— Выбрать город —</option>
            <option v-for="c in addrCities" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>
        <div v-if="addrSel.city">
          <label class="field-label">Улица</label>
          <select v-model="addrSel.street" @change="onAddrStreet" class="field-input">
            <option value="">— Выбрать улицу —</option>
            <option v-for="s in addrStreets" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>
        <div v-if="addrSel.street">
          <label class="field-label">Дом</label>
          <select v-model="addrSel.building" @change="onAddrBuilding" class="field-input">
            <option value="">— Выбрать дом —</option>
            <option v-for="b in addrBuildings" :key="b" :value="b">{{ b }}</option>
          </select>
        </div>
        <div v-if="addrSel.building && addrApartments.length">
          <label class="field-label">Квартира <span class="text-red-500">*</span></label>
          <select v-model="addrSel.apartment" class="field-input">
            <option value="" disabled>— Выбрать квартиру —</option>
            <option v-for="apt in addrApartments" :key="apt" :value="apt">кв. {{ apt }}</option>
          </select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="showAddrModal = false" class="btn-outline text-sm">Отмена</button>
          <button type="button" @click="applyAddrModal"
                  :disabled="!addrSel.building || addrModalLoading || (addrApartments.length > 0 && !addrSel.apartment)"
                  class="btn-primary text-sm">
            {{ addrModalLoading ? 'Поиск...' : 'Выбрать →' }}
          </button>
        </div>
      </div>
    </Modal>  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch, reactive } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import dayjs from 'dayjs'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'
import TimePicker from '@/Components/UI/TimePicker.vue'
import AttachmentUpload from '@/Components/Tickets/AttachmentUpload.vue'
import Modal from '@/Components/UI/Modal.vue'

const props = defineProps({
  lanbillingEnabled: { type: Boolean, default: false },
  types:          Array,
  serviceTypes:   { type: Array, default: () => [] },
  statuses:       Array,
  brigades:       Array,
  address:        Object,
  addressHistory: { type: Array, default: () => [] },
  settings: {
    type: Object,
    default: () => ({ work_hours_start: '09:00', work_hours_end: '17:00', schedule_step_minutes: 30 })
  },
})

// Завтра + начало рабочего дня
function defaultScheduledAt() {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}T${props.settings?.work_hours_start ?? '09:00'}`
}

// Интернет по умолчанию
function defaultServiceType() {
  const inet = props.serviceTypes?.find(s =>
    s.name.toLowerCase().includes('интернет') || s.name.toLowerCase().includes('inet')
  )
  return inet?.id ?? props.serviceTypes?.[0]?.id ?? ''
}

// Ремонт по умолчанию
function defaultType() {
  const repair = props.types?.find(t => t.name.toLowerCase().includes('ремонт'))
  return repair?.id ?? props.types?.[0]?.id ?? ''
}

// Адрес
const addressQuery    = ref(props.address ? (props.address.full_address ?? props.address.street) : '')
const suggestions     = ref([])
const showAddressError = ref(false)
const selectedAddress = ref(
  props.address
    ? {
        id: props.address.id,
        label: props.address.full_address ?? props.address.street,
        ...props.address,
        territory: typeof props.address.territory === 'object'
          ? (props.address.territory?.name ?? '')
          : (props.address.territory ?? ''),
      }
    : null
)

// История
const historyPage = ref(1)
const historyPerPage = 50
const allHistory = ref(props.addressHistory ?? [])
const historyTotal = computed(() => allHistory.value.length)
const historyPages = computed(() => Math.max(1, Math.ceil(historyTotal.value / historyPerPage)))
const pagedHistory = computed(() => {
  const start = (historyPage.value - 1) * historyPerPage
  return allHistory.value.slice(start, start + historyPerPage)
})

// Форма
const submitted = ref(false)

const form = useForm({
  address_id:      props.address?.id ?? '',
  apartment:       props.address?.apartment ?? '',
  territory_id:    props.address?.territory_id ?? '',
  type_id:         defaultType(),
  service_type_id: defaultServiceType(),
  brigade_id:      props.brigades?.length === 1 ? props.brigades[0].id : '',
  priority:        'normal',
  phone:           props.address?.phone ?? '',
  contract_no:     props.address?.contract_no ?? '',
  description:     '',
  scheduled_at:    defaultScheduledAt(),
})

const attachmentFiles = ref([])
const billingQuery    = ref('')
const billingLoading  = ref(false)
const addressLoading  = ref(false)

async function fetchFreeSlot(brigadeId = null) {
  try {
    const params = {}
    if (brigadeId) params.brigade_id = brigadeId
    const { data } = await axios.get(route('tickets.free-slot'), { params })
    if (data.datetime) form.scheduled_at = data.datetime
  } catch { /* keep default */ }
}

onMounted(() => fetchFreeSlot(form.brigade_id || null))

watch(() => form.brigade_id, (id) => { if (id) fetchFreeSlot(id) })

// Бригады по территории адреса (пустой адрес = все бригады)
const availableBrigades = computed(() => {
  if (!form.territory_id) return props.brigades ?? []
  return (props.brigades ?? []).filter(b =>
    b.territories?.some(t => t.id == form.territory_id)
  )
})

// Проверка заполненности всех обязательных полей
const needsApartment = computed(() =>
  !!selectedAddress.value?.has_apartments && !form.apartment
)

const isFormComplete = computed(() =>
  !!selectedAddress.value &&
  !needsApartment.value &&
  !!form.service_type_id &&
  !!form.type_id &&
  !!form.phone.trim() &&
  !!form.scheduled_at &&
  !!form.description.trim()
)

// Подсветка незаполненных полей (только после попытки отправки)
const fieldError = computed(() => ({
  address:      submitted.value && !selectedAddress.value,
  apartment:    submitted.value && needsApartment.value,
  service_type: submitted.value && !form.service_type_id,
  type:         submitted.value && !form.type_id,
  phone:        submitted.value && !form.phone.trim(),
  scheduled_at: submitted.value && !form.scheduled_at,
  description:  submitted.value && !form.description.trim(),
}))

// Поиск адресов
let searchTimer = null
function searchAddresses() {
  clearTimeout(searchTimer)
  if (addressQuery.value.length < 2) { suggestions.value = []; addressLoading.value = false; return }
  addressLoading.value = true
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('addresses.search'), {
        params: { q: addressQuery.value }
      })
      suggestions.value = data
    } catch { suggestions.value = [] }
    finally { addressLoading.value = false }
  }, 300)
}

function selectAddress(a) {
  selectedAddress.value = a
  form.apartment = a.apartment ?? ''
  form.address_id   = a.id
  form.territory_id = a.territory_id ?? ''
  // Если выбранная бригада не обслуживает эту территорию — сбросить
  if (form.brigade_id && a.territory_id) {
    const stillOk = (props.brigades ?? []).some(b =>
      b.id == form.brigade_id && b.territories?.some(t => t.id == a.territory_id)
    )
    if (!stillOk) form.brigade_id = ''
  }
  form.phone        = form.phone || a.phone || ''
  form.contract_no  = form.contract_no || a.contract_no || ''
  addressQuery.value   = a.label
  suggestions.value    = []
  showAddressError.value = false
  historyPage.value = 1

  // Загружаем историю
  router.get(
    route('tickets.create'),
    { address_id: a.id },
    {
      preserveState: true,
      only: ['addressHistory'],
      onSuccess: (page) => {
        allHistory.value = page.props.addressHistory ?? []
      }
    }
  )
}

function clearAddress() {
  selectedAddress.value  = null
  form.address_id        = ''
  form.territory_id      = ''
  addressQuery.value     = ''
  suggestions.value      = []
  allHistory.value       = []
  submitted.value        = false   // сбрасываем состояние валидации
  showAddressError.value = false
}

// LANBilling
async function lookupBilling() {
  if (!billingQuery.value) return
  billingLoading.value = true
  try {
    const isPhone = /^\+?[\d\s\-()]{7,}$/.test(billingQuery.value)
    const params  = isPhone ? { phone: billingQuery.value } : { contract: billingQuery.value }
    const { data } = await axios.get(route('lanbilling.lookup'), { params })
    if (data.phone)       form.phone       = data.phone
    if (data.contract_no) form.contract_no = data.contract_no
    if (data.street) {
      addressQuery.value = [data.street, data.building, data.apartment].filter(Boolean).join(' ')
      await searchAddresses()
    }
  } catch { alert('Абонент не найден в LANBilling') }
  finally { billingLoading.value = false }
}

// Отправка
function submitTicket() {
  submitted.value = true

  if (!selectedAddress.value) {
    showAddressError.value = true
    return
  }

  if (!isFormComplete.value) {
    return
  }

  // Используем transform для добавления файлов
  form.transform((data) => {
    const fd = new FormData()
    Object.entries(data).forEach(([key, val]) => {
      if (val !== null && val !== undefined) fd.append(key, val)
    })
    attachmentFiles.value.forEach(file => fd.append('attachments[]', file))
    return fd
  }).post(route('tickets.store'), {
    forceFormData: true,
    onSuccess: () => {
      form.reset()
      attachmentFiles.value = []
      submitted.value = false
    },
    onError: (errors) => {
      console.log('Server errors:', errors)
    }
  })
}

function formatDate(d) {
  return d ? dayjs(d).format('DD.MM.YY') : ''
}

// ── Модал выбора адреса ─────────────────────────────────────────────
const showAddrModal    = ref(false)
const addrCities       = ref([])
const addrStreets      = ref([])
const addrBuildings    = ref([])
const addrApartments   = ref([])
const addrModalLoading = ref(false)
const addrSel          = reactive({ city: '', street: '', building: '', apartment: '' })

async function openAddrModal() {
  Object.assign(addrSel, { city: '', street: '', building: '', apartment: '' })
  addrStreets.value = []; addrBuildings.value = []; addrApartments.value = []
  showAddrModal.value = true
  try {
    addrCities.value = (await axios.get(route('addresses.hierarchy'))).data
  } catch { addrCities.value = [] }
}

async function onAddrCity() {
  addrSel.street = ''; addrSel.building = ''; addrSel.apartment = ''
  addrStreets.value = []; addrBuildings.value = []; addrApartments.value = []
  if (!addrSel.city) return
  try {
    addrStreets.value = (await axios.get(route('addresses.hierarchy'), { params: { city: addrSel.city } })).data
  } catch { addrStreets.value = [] }
}

async function onAddrStreet() {
  addrSel.building = ''; addrSel.apartment = ''
  addrBuildings.value = []; addrApartments.value = []
  if (!addrSel.street) return
  try {
    addrBuildings.value = (await axios.get(route('addresses.hierarchy'), { params: { city: addrSel.city, street: addrSel.street } })).data
  } catch { addrBuildings.value = [] }
}

async function onAddrBuilding() {
  addrSel.apartment = ''
  addrApartments.value = []
  if (!addrSel.building) return
  try {
    addrApartments.value = (await axios.get(route('addresses.hierarchy'), { params: { city: addrSel.city, street: addrSel.street, building: addrSel.building } })).data
  } catch { addrApartments.value = [] }
}

async function applyAddrModal() {
  if (!addrSel.building) return
  addrModalLoading.value = true
  const q = [addrSel.street, addrSel.building, addrSel.apartment].filter(Boolean).join(' ')
  addressQuery.value = q
  try {
    const { data } = await axios.get(route('addresses.search'), { params: { q } })
    suggestions.value = data
    const exact = data.find(a => a.building === addrSel.building && (!addrSel.apartment || a.apartment === addrSel.apartment))
    const pick  = exact ?? (data.length === 1 ? data[0] : null)
    if (pick) { selectAddress(pick); showAddrModal.value = false }
    else showAddrModal.value = false
  } catch {
    suggestions.value = []
    showAddrModal.value = false
  } finally { addrModalLoading.value = false }
}</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors disabled:cursor-not-allowed; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 bg-slate-50; }
</style>
