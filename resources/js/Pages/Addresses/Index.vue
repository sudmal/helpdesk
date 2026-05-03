<template>
  <Head title="Адреса" />
  <AppLayout title="Адреса абонентов">
    <template #actions>
      <button @click="showImportModal = true" class="btn-outline text-sm">⬆ Импорт</button>
      <button @click="showModal = true"       class="btn-primary text-sm">+ Добавить</button>
    </template>

    <!-- Поиск -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex gap-3 flex-wrap">
      <div class="relative flex-1 min-w-[220px]">
        <Icon name="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
        <input v-model="form.search" @input="debouncedSearch"
               placeholder="Адрес, телефон, абонент, договор..."
               class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
      </div>
      <select v-model="form.territory_id" @change="applyFilters"
              class="border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white">
        <option value="">Все территории</option>
        <option v-for="t in territories" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
    </div>

    <!-- Таблица -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="px-6 py-3 border-b border-gray-100 text-sm text-gray-500">Всего: {{ addresses.total }}</div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/50">
              <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Адрес</th>
              <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Абонент</th>
              <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Телефон</th>
              <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Договор</th>
              <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Территория</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="addresses.data.length === 0"><td colspan="6" class="text-center py-12 text-gray-400">Адреса не найдены</td></tr>
            <tr v-for="a in addresses.data" :key="a.id" class="hover:bg-gray-50">
              <td class="px-5 py-3 font-medium">{{ a.full_address ?? [a.street, a.building, a.apartment].filter(Boolean).join(', ') }}</td>
              <td class="px-5 py-3 text-gray-600">{{ a.subscriber_name ?? '—' }}</td>
              <td class="px-5 py-3 text-gray-600">{{ a.phone ?? '—' }}</td>
              <td class="px-5 py-3 text-gray-600 font-mono text-xs">{{ a.contract_no ?? '—' }}</td>
              <td class="px-5 py-3"><span class="text-xs bg-gray-100 px-2 py-0.5 rounded-full">{{ a.territory?.name ?? '—' }}</span></td>
              <td class="px-5 py-3 flex gap-1 justify-end">
                <button @click="edit(a)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50">✏️</button>
                <button @click="del(a)" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50">🗑</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Пагинация -->
      <div class="px-6 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
        <span>Страница {{ addresses.current_page }} из {{ addresses.last_page }}</span>
        <div class="flex gap-1">
          <Link v-if="addresses.prev_page_url" :href="addresses.prev_page_url" class="px-3 py-1 border rounded-lg hover:bg-gray-50">‹</Link>
          <Link v-if="addresses.next_page_url" :href="addresses.next_page_url" class="px-3 py-1 border rounded-lg hover:bg-gray-50">›</Link>
        </div>
      </div>
    </div>

    <!-- Модалка добавления -->
    <Modal v-if="showModal" :title="editing ? 'Редактировать адрес' : 'Новый адрес'" @close="closeModal">
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div><label class="field-label">Улица *</label><input v-model="addrForm.street" required class="field-input" /></div>
          <div><label class="field-label">Дом *</label><input v-model="addrForm.building" required class="field-input" /></div>
          <div><label class="field-label">Квартира</label><input v-model="addrForm.apartment" class="field-input" /></div>
          <div><label class="field-label">Город</label><input v-model="addrForm.city" class="field-input" /></div>
          <div class="col-span-2">
            <label class="field-label">Территория</label>
            <select v-model="addrForm.territory_id" class="field-input">
              <option value="">— Выбрать —</option>
              <option v-for="t in territories" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div><label class="field-label">Абонент</label><input v-model="addrForm.subscriber_name" class="field-input" /></div>
          <div><label class="field-label">Телефон</label><input v-model="addrForm.phone" class="field-input" /></div>
          <div><label class="field-label">№ договора</label><input v-model="addrForm.contract_no" class="field-input" /></div>
        </div>
        <!-- Генерация квартир -->
        <div v-if="!editing" class="border-t border-gray-100 pt-3">
          <p class="text-xs text-gray-500 mb-2">Автогенерация квартир (необязательно):</p>
          <div class="flex gap-3">
            <div class="flex-1"><label class="field-label">Кв. с</label><input v-model.number="addrForm.apt_from" type="number" min="1" class="field-input" /></div>
            <div class="flex-1"><label class="field-label">Кв. по</label><input v-model.number="addrForm.apt_to" type="number" min="1" class="field-input" /></div>
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" @click="closeModal" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editing ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Модалка импорта -->
    <Modal v-if="showImportModal" title="Импорт адресов (XLS/CSV)" @close="showImportModal = false">
      <form @submit.prevent="submitImport" class="space-y-4">
        <p class="text-sm text-gray-600">Файл должен содержать колонки: street, building, apartment, city, subscriber_name, phone, contract_no</p>
        <input type="file" ref="importFile" accept=".xlsx,.xls,.csv" class="text-sm" required />
        <div v-if="importResult" :class="['rounded-xl p-3 text-sm', importResult.errors?.length ? 'bg-amber-50' : 'bg-green-50']">
          <p>✅ Создано: {{ importResult.created }} | ⏭ Пропущено: {{ importResult.skipped }}</p>
          <p v-for="e in importResult.errors" :key="e" class="text-red-600 text-xs mt-1">{{ e }}</p>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" @click="showImportModal = false" class="btn-outline text-sm">Закрыть</button>
          <button class="btn-primary text-sm">Загрузить</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Modal from '@/Components/UI/Modal.vue'
import Icon from '@/Components/UI/Icon.vue'

const props = defineProps({ addresses: Object, filters: Object, territories: Array })

const showModal       = ref(false)
const showImportModal = ref(false)
const editing         = ref(null)
const importResult    = ref(null)
const importFile      = ref(null)

const form = useForm({ search: props.filters?.search ?? '', territory_id: props.filters?.territory_id ?? '' })
const addrForm = useForm({ street: '', building: '', apartment: '', city: '', territory_id: '',
                            subscriber_name: '', phone: '', contract_no: '', apt_from: null, apt_to: null })

let timer = null
function debouncedSearch() { clearTimeout(timer); timer = setTimeout(applyFilters, 400) }
function applyFilters() { router.get(route('addresses.index'), form.data(), { preserveState: true, replace: true }) }

function edit(a) { editing.value = a; Object.assign(addrForm, a); showModal.value = true }
function closeModal() { showModal.value = false; editing.value = null; addrForm.reset() }
function submit() {
  if (editing.value) addrForm.put(route('addresses.update', editing.value.id), { onSuccess: closeModal })
  else addrForm.post(route('addresses.store'), { onSuccess: closeModal })
}
function del(a) { if (confirm('Удалить адрес?')) router.delete(route('addresses.destroy', a.id)) }

function submitImport() {
  const data = new FormData()
  data.append('file', importFile.value.files[0])
  router.post(route('addresses.import'), data, {
    onSuccess: (page) => { importResult.value = page.props.flash?.import_result }
  })
}
</script>
<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30; }
</style>
