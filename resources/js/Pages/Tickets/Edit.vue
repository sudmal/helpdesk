<template>
  <Head :title="`Редактирование ${ticket.number}`" />
  <AppLayout :title="`Редактирование заявки ${ticket.number}`">
    <div class="max-w-3xl">
      <form @submit.prevent="submit" class="space-y-3">

        <!-- Адрес -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-3">
          <h3 class="font-medium text-sm text-gray-700">Адрес абонента</h3>

          <div v-if="currentAddress" class="bg-blue-50 rounded-xl p-2.5 text-sm text-blue-800">
            📍 {{ currentAddress }}
          </div>

          <div class="grid grid-cols-2 gap-3">
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
            <div v-if="addrSel.building">
              <label class="field-label">Квартира</label>
              <select v-model="selectedApartmentId" @change="onAddrApartment" class="field-input" :disabled="!addrApartments.length">
                <option value="">{{ addrApartments.length ? '— Выбрать квартиру —' : 'нет квартир в справочнике для этого дома' }}</option>
                <option v-for="apt in addrApartments" :key="apt.id" :value="apt.id">
                  кв. {{ apt.apartment }}{{ apt.subscriber_name ? ' — ' + apt.subscriber_name : '' }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Детали -->
        <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-3">
          <h3 class="font-medium text-sm text-gray-700">Детали заявки</h3>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="field-label">Тип заявки *</label>
              <select v-model="form.type_id" required class="field-input">
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <FieldError :error="form.errors.type_id" />
            </div>

            <div>
              <label class="field-label">Статус *</label>
              <select v-model="form.status_id" required class="field-input">
                <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>

            <div>
              <label class="field-label">Приоритет *</label>
              <select v-model="form.priority" required class="field-input">
                <option value="low">Низкий</option>
                <option value="normal">Обычный</option>
                <option value="high">Высокий</option>
                <option value="urgent">Срочный</option>
              </select>
            </div>

            <div>
              <label class="field-label">Желаемое время выезда</label>
              <input v-model="form.scheduled_at" type="datetime-local" class="field-input" />
              <FieldError :error="form.errors.scheduled_at" />
            </div>

            <div>
              <label class="field-label">Телефон</label>
              <input v-model="form.phone" type="tel" class="field-input" />
            </div>

            <div>
              <label class="field-label">№ договора</label>
              <input v-model="form.contract_no" class="field-input" />
            </div>

            <div>
              <label class="field-label">Бригада</label>
              <select v-model="form.brigade_id" class="field-input">
                <option value="">— Не назначена —</option>
                <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </div>

            <div>
              <label class="field-label">Участок</label>
              <select v-model="form.service_type_id" class="field-input">
                <option value="">— Не указан —</option>
                <option v-for="st in serviceTypes" :key="st.id" :value="st.id">{{ st.name }}</option>
              </select>
            </div>

          </div>

          <div>
            <label class="field-label">Описание *</label>
            <textarea v-model="form.description" rows="4" required
                      class="field-input resize-none"></textarea>
            <FieldError :error="form.errors.description" />
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('tickets.show', ticket.id)" class="btn-outline text-sm">Отмена</Link>
          <button :disabled="form.processing" class="btn-primary text-sm">
            {{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import TimePicker from '@/Components/UI/TimePicker.vue'
import dayjs from 'dayjs'

const props = defineProps({
  ticket: Object, types: Array, statuses: Array, brigades: Array, serviceTypes: Array,
  settings: { type: Object, default: () => ({ work_hours_start: '09:00', work_hours_end: '17:00', schedule_step_minutes: 30 }) },
})

// Не берём address.full_address напрямую: Address может быть общим на несколько
// заявок в доме, и его apartment — устаревшее значение от первой заявки/импорта,
// не обязательно от этой. Собираем адрес без квартиры и добавляем apartment этой заявки.
function ticketAddressLabel() {
  const a = props.ticket.address
  if (!a) return ''
  const base = [a.city, a.street, a.building].filter(Boolean).join(', ')
  return props.ticket.apartment ? base + ', кв. ' + props.ticket.apartment : base
}

const currentAddress = ref(ticketAddressLabel())

const form = useForm({
  address_id:   props.ticket.address_id ?? '',
  apartment:    props.ticket.apartment ?? '',
  type_id:      props.ticket.type_id,
  status_id:    props.ticket.status_id,
  brigade_id:   props.ticket.brigade_id ?? '',
  assigned_to:  props.ticket.assigned_to ?? '',
  description:  props.ticket.description,
  phone:        props.ticket.phone ?? '',
  contract_no:  props.ticket.contract_no ?? '',
  priority:        props.ticket.priority,
  service_type_id: props.ticket.service_type_id ?? '',
  scheduled_at: props.ticket.scheduled_at
    ? dayjs(props.ticket.scheduled_at).format('YYYY-MM-DDTHH:mm') : '',
})

// ── Каскадный выбор адреса: Город → Улица → Дом → Квартира ──
// Варианты ограничены территориями назначенной бригады (brigade_id), если она есть.
const addrCities        = ref([])
const addrStreets       = ref([])
const addrBuildings     = ref([])
const addrApartments    = ref([]) // [{id, apartment, subscriber_name}] — строго из справочника
const selectedApartmentId = ref('')
const addrSel = reactive({ city: '', street: '', building: '' })

function hierarchyParams(extra) {
  return { ...extra, brigade_id: form.brigade_id || undefined }
}

async function fetchCities()                    { return (await axios.get(route('addresses.hierarchy'), { params: hierarchyParams({}) })).data }
async function fetchStreets(city)                { return (await axios.get(route('addresses.hierarchy'), { params: hierarchyParams({ city }) })).data }
async function fetchBuildings(city, street)      { return (await axios.get(route('addresses.hierarchy'), { params: hierarchyParams({ city, street }) })).data }
async function fetchApartments(city, street, building) {
  return (await axios.get(route('addresses.hierarchy'), { params: hierarchyParams({ city, street, building, with_id: 1 }) })).data
}

async function onAddrCity() {
  addrSel.street = ''; addrSel.building = ''
  addrBuildings.value = []; addrApartments.value = []; selectedApartmentId.value = ''
  addrStreets.value = addrSel.city ? await fetchStreets(addrSel.city) : []
}

async function onAddrStreet() {
  addrSel.building = ''
  addrApartments.value = []; selectedApartmentId.value = ''
  addrBuildings.value = addrSel.street ? await fetchBuildings(addrSel.city, addrSel.street) : []
}

async function onAddrBuilding() {
  selectedApartmentId.value = ''
  addrApartments.value = addrSel.building ? await fetchApartments(addrSel.city, addrSel.street, addrSel.building) : []
}

function onAddrApartment() {
  const picked = addrApartments.value.find(a => String(a.id) === String(selectedApartmentId.value))
  if (!picked) return
  // Старый адрес просто открепляется — address_id меняется на новый, сама
  // запись Address (общая на несколько заявок в доме) не трогается.
  form.address_id = picked.id
  form.apartment   = picked.apartment ?? ''
  currentAddress.value = [addrSel.city, addrSel.street, addrSel.building].filter(Boolean).join(', ')
    + (picked.apartment ? ', кв. ' + picked.apartment : '')
}

onMounted(async () => {
  addrCities.value = await fetchCities()

  const addr = props.ticket.address
  if (!addr?.city) return
  addrSel.city = addr.city
  addrStreets.value = await fetchStreets(addr.city)
  if (!addr.street) return
  addrSel.street = addr.street
  addrBuildings.value = await fetchBuildings(addr.city, addr.street)
  if (!addr.building) return
  addrSel.building = addr.building
  addrApartments.value = await fetchApartments(addr.city, addr.street, addr.building)
  const match = addrApartments.value.find(a => a.apartment === props.ticket.apartment)
  if (match) selectedApartmentId.value = match.id
})

function submit() {
  form.put(route('tickets.update', props.ticket.id))
}

// Inline helper component
const FieldError = {
  props: { error: String },
  template: `<p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>`
}
</script>
