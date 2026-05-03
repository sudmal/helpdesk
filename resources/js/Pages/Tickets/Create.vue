<template>
  <Head title="Новая заявка" />
  <AppLayout title="Новая заявка">
    <div class="max-w-3xl space-y-5">

      <!-- Поиск адреса / LANBilling -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="font-medium text-sm text-gray-700 mb-3">Адрес абонента</h3>

        <!-- Поиск по базе адресов -->
        <div class="relative mb-3">
          <Icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input v-model="addressQuery" @input="searchAddresses"
                 placeholder="Улица, дом, квартира..."
                 class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm
                        focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400" />
          <!-- Выпадающий список -->
          <div v-if="addressSuggestions.length"
               class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-gray-200
                      rounded-xl shadow-lg max-h-60 overflow-y-auto">
            <button v-for="a in addressSuggestions" :key="a.id"
                    @click="selectAddress(a)" type="button"
                    class="w-full text-left px-4 py-2.5 hover:bg-blue-50 text-sm border-b border-gray-100 last:border-0">
              <p class="font-medium">{{ a.label }}</p>
              <p class="text-xs text-gray-400">{{ a.subscriber_name }} · {{ a.phone }}</p>
            </button>
          </div>
        </div>

        <!-- LANBilling поиск -->
        <div class="flex gap-2">
          <input v-model="billingQuery" placeholder="Телефон или № договора для поиска в LANBilling"
                 class="flex-1 border border-gray-200 rounded-xl px-3 py-2 text-sm
                        focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
          <button @click="lookupBilling" type="button" :disabled="billingLoading"
                  class="btn-outline text-sm whitespace-nowrap">
            {{ billingLoading ? 'Поиск...' : '🔍 Найти в биллинге' }}
          </button>
        </div>

        <!-- Выбранный адрес -->
        <div v-if="selectedAddress" class="mt-3 bg-blue-50 rounded-xl p-3 flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-blue-800">{{ selectedAddress.label }}</p>
            <p class="text-xs text-blue-600">{{ selectedAddress.subscriber_name }} · {{ selectedAddress.phone }}</p>
          </div>
          <button @click="clearAddress" class="text-blue-400 hover:text-blue-600">✕</button>
        </div>
      </div>

      <!-- История по адресу -->
      <div v-if="addressHistory?.length" class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
        <h3 class="font-medium text-sm text-amber-800 mb-3">
          📋 Предыдущие заявки по этому адресу ({{ addressHistory.length }})
        </h3>
        <div class="space-y-2">
          <Link v-for="h in addressHistory" :key="h.id" :href="route('tickets.show', h.id)"
                class="flex items-center justify-between bg-white rounded-lg p-2.5 hover:bg-amber-50 transition-colors">
            <div>
              <span class="text-xs font-mono text-blue-600">{{ h.number }}</span>
              <span class="text-xs text-gray-500 ml-2">{{ h.type?.name }}</span>
            </div>
            <Badge :color="h.status?.color" :label="h.status?.name" small />
          </Link>
        </div>
      </div>

      <!-- Форма заявки -->
      <form @submit.prevent="submitTicket" class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
        <h3 class="font-medium text-sm text-gray-700 mb-1">Детали заявки</h3>

        <div class="grid grid-cols-2 gap-4">
          <!-- Тип -->
          <div>
            <label class="field-label">Тип заявки *</label>
            <select v-model="form.type_id" required class="field-input">
              <option value="">— Выбрать —</option>
              <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <FieldError :error="form.errors.type_id" />
          </div>

          <!-- Приоритет -->
          <div>
            <label class="field-label">Приоритет *</label>
            <select v-model="form.priority" required class="field-input">
              <option value="low">Низкий</option>
              <option value="normal">Обычный</option>
              <option value="high">Высокий</option>
              <option value="urgent">Срочный</option>
            </select>
          </div>

          <!-- Телефон -->
          <div>
            <label class="field-label">Телефон</label>
            <input v-model="form.phone" type="tel" class="field-input" placeholder="+7..." />
          </div>

          <!-- Договор -->
          <div>
            <label class="field-label">№ договора</label>
            <input v-model="form.contract_no" class="field-input" placeholder="12345" />
          </div>

          <!-- Желаемая дата выезда -->
          <div class="col-span-2">
            <label class="field-label">Желаемое время выезда</label>
            <input v-model="form.scheduled_at" type="datetime-local" class="field-input" />
            <FieldError :error="form.errors.scheduled_at" />
          </div>

          <!-- Бригада -->
          <div>
            <label class="field-label">Бригада</label>
            <select v-model="form.brigade_id" class="field-input">
              <option value="">— Не назначена —</option>
              <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
        </div>

        <!-- Описание -->
        <div>
          <label class="field-label">Описание *</label>
          <textarea v-model="form.description" rows="4" required
                    placeholder="Опишите проблему или задачу..."
                    class="field-input resize-none"></textarea>
          <FieldError :error="form.errors.description" />
        </div>

        <!-- Вложения -->
        <div>
          <label class="field-label">Вложения</label>
          <AttachmentUpload v-model="attachmentFiles" />
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <Link :href="route('tickets.index')" class="btn-outline text-sm">Отмена</Link>
          <button :disabled="form.processing" class="btn-primary text-sm">
            {{ form.processing ? 'Создание...' : 'Создать заявку' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'
import Icon from '@/Components/UI/Icon.vue'
import AttachmentUpload from '@/Components/Tickets/AttachmentUpload.vue'

const props = defineProps({
  types: Array, statuses: Array, brigades: Array,
  address: Object, addressHistory: Array,
})

const addressQuery       = ref('')
const addressSuggestions = ref([])
const selectedAddress    = ref(props.address ? { id: props.address.id, label: props.address.full_address, ...props.address } : null)
const billingQuery       = ref('')
const billingLoading     = ref(false)
const attachmentFiles    = ref([])

const form = useForm({
  address_id: props.address?.id ?? '',
  type_id: '', priority: 'normal', phone: props.address?.phone ?? '',
  contract_no: props.address?.contract_no ?? '',
  brigade_id: '', description: '', scheduled_at: '',
})

let searchTimer = null
function searchAddresses() {
  clearTimeout(searchTimer)
  if (addressQuery.value.length < 2) { addressSuggestions.value = []; return }
  searchTimer = setTimeout(async () => {
    const { data } = await axios.get(route('addresses.search'), { params: { q: addressQuery.value } })
    addressSuggestions.value = data
  }, 300)
}

function selectAddress(a) {
  selectedAddress.value = a
  form.address_id  = a.id
  form.phone       = form.phone || a.phone || ''
  form.contract_no = form.contract_no || a.contract_no || ''
  addressQuery.value = a.label
  addressSuggestions.value = []
  // Загружаем историю
  router.get(route('tickets.create'), { address_id: a.id }, { preserveState: true, only: ['addressHistory'] })
}

function clearAddress() {
  selectedAddress.value = null
  form.address_id = ''
  addressQuery.value = ''
}

async function lookupBilling() {
  if (!billingQuery.value) return
  billingLoading.value = true
  try {
    const isPhone = /^\d/.test(billingQuery.value.replace(/\D/,''))
    const params  = isPhone ? { phone: billingQuery.value } : { contract: billingQuery.value }
    const { data } = await axios.get(route('lanbilling.lookup'), { params })
    form.phone       = data.phone || form.phone
    form.contract_no = data.contract_no || form.contract_no
    addressQuery.value = [data.street, data.building, data.apartment].filter(Boolean).join(', ')
  } catch {
    alert('Абонент не найден в LANBilling')
  } finally {
    billingLoading.value = false
  }
}

function submitTicket() {
  const data = new FormData()
  Object.entries(form.data()).forEach(([k, v]) => v !== '' && data.append(k, v))
  attachmentFiles.value.forEach(f => data.append('attachments[]', f))
  form.post(route('tickets.store'), { data })
}
</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors disabled:opacity-40; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-white; }
</style>
