<template>
  <Head :title="`Редактирование ${ticket.number}`" />
  <AppLayout :title="`Редактирование заявки ${ticket.number}`">
    <div class="max-w-3xl">
      <form @submit.prevent="submit" class="space-y-5">

        <!-- Адрес -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
          <h3 class="font-medium text-sm text-gray-700">Адрес абонента</h3>

          <div class="relative">
            <Icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input v-model="addressQuery" @input="searchAddresses"
                   placeholder="Поиск адреса..."
                   class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm
                          focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400" />
            <div v-if="suggestions.length"
                 class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-gray-200
                        rounded-xl shadow-lg max-h-52 overflow-y-auto">
              <button v-for="a in suggestions" :key="a.id" type="button"
                      @click="selectAddress(a)"
                      class="w-full text-left px-4 py-2.5 hover:bg-blue-50 text-sm border-b border-gray-100 last:border-0">
                <p class="font-medium">{{ a.label }}</p>
                <p class="text-xs text-gray-400">{{ a.subscriber_name }} · {{ a.phone }}</p>
              </button>
            </div>
          </div>

          <div v-if="currentAddress" class="bg-blue-50 rounded-xl p-3 text-sm text-blue-800">
            📍 {{ currentAddress }}
          </div>
        </div>

        <!-- Детали -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
          <h3 class="font-medium text-sm text-gray-700">Детали заявки</h3>

          <div class="grid grid-cols-2 gap-4">
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
              <label class="field-label">Монтажник</label>
              <select v-model="form.assigned_to" class="field-input">
                <option value="">— Не назначен —</option>
                <option v-for="m in selectedBrigadeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="field-label">Описание *</label>
            <textarea v-model="form.description" rows="5" required
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
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Icon from '@/Components/UI/Icon.vue'
import TimePicker from '@/Components/UI/TimePicker.vue'
import dayjs from 'dayjs'

const props = defineProps({
  ticket: Object, types: Array, statuses: Array, brigades: Array,
  settings: { type: Object, default: () => ({ work_hours_start: '09:00', work_hours_end: '17:00', schedule_step_minutes: 30 }) },
})

const addressQuery   = ref(props.ticket.address?.full_address ?? '')
const currentAddress = ref(props.ticket.address?.full_address ?? '')
const suggestions    = ref([])

const form = useForm({
  address_id:   props.ticket.address_id ?? '',
  type_id:      props.ticket.type_id,
  status_id:    props.ticket.status_id,
  brigade_id:   props.ticket.brigade_id ?? '',
  assigned_to:  props.ticket.assigned_to ?? '',
  description:  props.ticket.description,
  phone:        props.ticket.phone ?? '',
  contract_no:  props.ticket.contract_no ?? '',
  priority:     props.ticket.priority,
  scheduled_at: props.ticket.scheduled_at
    ? dayjs(props.ticket.scheduled_at).format('YYYY-MM-DDTHH:mm') : '',
})


let timer = null
function searchAddresses() {
  clearTimeout(timer)
  if (addressQuery.value.length < 2) { suggestions.value = []; return }
  timer = setTimeout(async () => {
    const { data } = await axios.get(route('addresses.search'), { params: { q: addressQuery.value } })
    suggestions.value = data
  }, 300)
}

function selectAddress(a) {
  form.address_id  = a.id
  currentAddress.value = a.label
  addressQuery.value   = a.label
  suggestions.value    = []
}

function submit() {
  form.put(route('tickets.update', props.ticket.id))
}

// Inline helper component
const FieldError = {
  props: { error: String },
  template: `<p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>`
}
</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors disabled:opacity-40; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 bg-slate-50; }
</style>
