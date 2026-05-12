<template>
  <Head title="Адреса" />
  <AppLayout title="Адреса абонентов">
    <template #actions>
      <button @click="showImportModal = true" class="btn-outline text-sm">⬆ Импорт</button>
      <button @click="openAddModal"           class="btn-primary text-sm">+ Добавить</button>
    </template>

    <!-- ── Поиск по адресу ── -->
    <div class="relative mb-4 max-w-lg">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
           fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
      <input v-model="globalSearch"
             @input="onGlobalSearch"
             placeholder="Быстрый поиск: улица, дом, абонент, телефон, договор..."
             class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm
                    focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
      <!-- Результаты поиска -->
      <div v-if="searchResults.length"
           class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-gray-200
                  rounded-xl shadow-xl max-h-80 overflow-y-auto">
        <a v-for="r in searchResults" :key="r.id"
           :href="route('tickets.index', { address_id: r.id })"
           class="flex items-center justify-between px-4 py-2.5 hover:bg-blue-50
                  border-b border-gray-100 last:border-0">
          <div>
            <p class="text-sm font-medium text-gray-800">{{ r.label }}</p>
            <p class="text-xs text-gray-400">
              {{ [r.subscriber_name, r.phone].filter(Boolean).join(' · ') }}
            </p>
          </div>
          <div class="flex items-center gap-2 shrink-0 ml-3">
            <span v-if="r.tickets_count"
                  class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">
              {{ r.tickets_count }} заявок
            </span>
            <span class="text-xs text-gray-400">→</span>
          </div>
        </a>
        <div v-if="searchLoading" class="px-4 py-3 text-sm text-gray-400 text-center">Поиск...</div>
      </div>
      <div v-if="globalSearch.length >= 2 && !searchResults.length && !searchLoading"
           class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-gray-200
                  rounded-xl shadow-xl px-4 py-3 text-sm text-gray-400 text-center">
        Ничего не найдено
      </div>
    </div>

    <!-- ── Хлебные крошки (навигация) ── -->
    <nav class="flex items-center gap-1 text-sm mb-5 flex-wrap">
      <button @click="resetTo(0)"
              :class="['font-medium transition-colors',
                       level === 0 ? 'text-blue-600' : 'text-gray-500 hover:text-blue-600']">
        🏙 Все города
      </button>
      <template v-if="selected.city">
        <span class="text-gray-300">›</span>
        <button @click="resetTo(1)"
                :class="['font-medium transition-colors',
                         level === 1 ? 'text-blue-600' : 'text-gray-500 hover:text-blue-600']">
          {{ selected.city }}
        </button>
      </template>
      <template v-if="selected.street">
        <span class="text-gray-300">›</span>
        <button @click="resetTo(2)"
                :class="['font-medium transition-colors',
                         level === 2 ? 'text-blue-600' : 'text-gray-500 hover:text-blue-600']">
          {{ selected.street }}
        </button>
      </template>
      <template v-if="selected.building">
        <span class="text-gray-300">›</span>
        <span class="font-medium text-blue-600">д. {{ selected.building }}</span>
      </template>
    </nav>

    <!-- ── Уровень 0: Города ── -->
    <div v-if="level === 0">
      <p class="text-xs text-gray-400 mb-3">Выберите город</p>
      <div class="flex flex-wrap gap-2">
        <button v-for="city in items" :key="city.name"
                @click="selectCity(city.name)"
                class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200
                       rounded-xl hover:border-blue-400 hover:bg-blue-50 transition-colors text-sm font-medium">
          🏙 {{ city.name }}
          <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">{{ city.count }}</span>
        </button>
      </div>
      <p v-if="!items.length" class="text-gray-400 text-sm py-8 text-center">Адреса не добавлены</p>
    </div>

    <!-- ── Уровень 1: Улицы ── -->
    <div v-if="level === 1">
      <p class="text-xs text-gray-400 mb-3">{{ selected.city }} — выберите улицу/квартал</p>
      <!-- Поиск по улице -->
      <div class="relative mb-4 max-w-sm">
        <input v-model="streetSearch" placeholder="Поиск улицы..."
               class="w-full pl-8 pr-3 py-2 border border-gray-200 rounded-xl text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
        <button v-for="st in filteredStreets" :key="st.name"
                @click="selectStreet(st.name)"
                class="flex items-center justify-between px-3 py-2.5 bg-white border border-gray-200
                       rounded-xl hover:border-blue-400 hover:bg-blue-50 transition-colors text-sm text-left">
          <span class="font-medium truncate">{{ st.name }}</span>
          <span class="text-xs text-gray-400 ml-2 shrink-0">{{ st.count }}</span>
        </button>
      </div>
      <p v-if="!filteredStreets.length" class="text-gray-400 text-sm py-6 text-center">Улицы не найдены</p>
    </div>

    <!-- ── Уровень 2: Дома ── -->
    <div v-if="level === 2">
      <p class="text-xs text-gray-400 mb-3">{{ selected.city }}, {{ selected.street }} — выберите дом</p>
      <div class="flex flex-wrap gap-2">
        <button v-for="b in items" :key="b.building"
                @click="selectBuilding(b)"
                :class="['flex flex-col items-center px-4 py-3 border rounded-xl transition-colors text-sm',
                         b.has_apartments
                           ? 'bg-white border-gray-200 hover:border-blue-400 hover:bg-blue-50'
                           : 'bg-white border-gray-200 hover:border-green-400 hover:bg-green-50']">
          <span class="text-xl mb-1">{{ b.has_apartments ? '🏢' : '🏠' }}</span>
          <span class="font-semibold">{{ b.building }}</span>
          <span class="text-xs text-gray-400 mt-0.5">
            {{ b.has_apartments ? b.count + ' кв.' : 'ЧС' }}
          </span>
        </button>
      </div>
      <p v-if="!items.length" class="text-gray-400 text-sm py-6 text-center">Дома не найдены</p>
    </div>

    <!-- ── Уровень 3а: Квартиры (МКД) ── -->
    <div v-if="level === 3 && props.isMkd">
      <p class="text-xs text-gray-400 mb-3">
        {{ selected.city }}, {{ selected.street }} д.{{ selected.building }} — квартиры
      </p>
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50/50 text-xs text-gray-500 font-medium">
              <th class="text-left px-4 py-2.5">Кв.</th>
              <th class="text-left px-4 py-2.5">Абонент</th>
              <th class="text-left px-4 py-2.5 hidden sm:table-cell">Телефон</th>
              <th class="text-left px-4 py-2.5 hidden md:table-cell">Договор</th>
              <th class="text-left px-4 py-2.5">Заявки</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!pagedItems.length">
              <td colspan="6" class="text-center py-8 text-gray-400">Квартиры не найдены</td>
            </tr>
            <tr v-for="a in pagedItems" :key="a.id" class="hover:bg-gray-50">
              <td class="px-4 py-2.5 font-semibold text-gray-700">{{ a.apartment }}</td>
              <td class="px-4 py-2.5 text-gray-600">{{ a.subscriber_name ?? '—' }}</td>
              <td class="px-4 py-2.5 text-gray-500 hidden sm:table-cell">{{ a.phone ?? '—' }}</td>
              <td class="px-4 py-2.5 text-gray-500 font-mono text-xs hidden md:table-cell">{{ a.contract_no ?? '—' }}</td>
              <td class="px-4 py-2.5">
                <a :href="route('tickets.index', { address_id: a.id })"
                   class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium">
                  Заявки
                  <span v-if="a.tickets_count"
                        class="bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">
                    {{ a.tickets_count }}
                  </span>
                  <span v-else class="text-gray-400">—</span>
                </a>
              </td>
              <td class="px-4 py-2.5 text-right">
                <button @click="editAddress(a)" class="p-1 text-gray-400 hover:text-blue-600 rounded">✏️</button>
                <button @click="deleteAddr(a)"  class="p-1 text-gray-400 hover:text-red-500 rounded">🗑</button>
              </td>
            </tr>
          </tbody>
        </table>
        <!-- Пагинация -->
        <Pagination :total="items.length" :per-page="perPage" v-model="page" />
      </div>
    </div>

    <!-- ── Уровень 3б: Частный сектор — сразу заявки ── -->
    <div v-if="level === 3 && props.isMkd === false">
      <div class="flex items-center justify-between mb-3">
        <p class="text-xs text-gray-400">
          Частный дом · {{ selected.city }}, {{ selected.street }} д.{{ selected.building }}
        </p>
        <a :href="route('tickets.index', { city: selected.city, street: selected.street, building: selected.building })"
           class="text-sm text-blue-600 hover:text-blue-800 font-medium">
          Все заявки по адресу →
        </a>
      </div>
      <!-- Карточка адреса -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-4">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="font-semibold text-gray-800">
              🏠 {{ selected.city }}, {{ selected.street }}, д.{{ selected.building }}
            </h3>
            <p class="text-sm text-gray-500 mt-1">
              {{ buildingInfo?.subscriber_name ?? 'Абонент не указан' }}
              {{ buildingInfo?.phone ? '· ' + buildingInfo.phone : '' }}
              {{ buildingInfo?.contract_no ? '· Договор: ' + buildingInfo.contract_no : '' }}
            </p>
          </div>
          <div class="flex gap-1">
            <button @click="editAddress(buildingInfo)" class="btn-outline text-xs">✏️ Изменить</button>
          </div>
        </div>
      </div>
      <!-- Быстрое создание заявки -->
      <a :href="route('tickets.create', { address_id: buildingInfo?.id })"
         class="inline-flex items-center gap-2 btn-primary text-sm mb-4">
        + Новая заявка по этому адресу
      </a>
    </div>

    <!-- ── Пагинация для квартир ── -->

    <!-- ── Модалки ── -->

    <!-- Добавление адреса -->
    <Modal v-if="showAddModal" :title="editingAddr ? 'Редактировать адрес' : 'Новый адрес'" @close="closeAddModal">
      <form @submit.prevent="submitAddress" class="space-y-4">

        <!-- Режим (только при создании) -->
        <div v-if="!editingAddr" class="flex gap-2">
          <button v-for="m in modes" :key="m.key" type="button"
                  @click="addrMode = m.key"
                  :class="['px-3 py-1.5 rounded-xl text-sm border transition-colors',
                           addrMode === m.key
                             ? 'bg-blue-600 text-white border-blue-600'
                             : 'border-gray-200 text-gray-600 hover:bg-gray-50']">
            {{ m.label }}
          </button>
        </div>

        <!-- Город + Территория -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="field-label">Город <span class="text-red-500">*</span></label>
            <input v-model="addrForm.city" list="cl" required class="field-input" placeholder="Донецк" />
            <datalist id="cl">
              <option v-for="c in cityNames" :key="c" :value="c" />
            </datalist>
          </div>
          <div>
            <label class="field-label">Территория <span class="text-red-500">*</span></label>
            <div class="border border-gray-200 rounded-xl p-2 space-y-1 max-h-28 overflow-y-auto">
              <label v-for="t in territories" :key="t.id"
                     class="flex items-center gap-2 text-sm cursor-pointer p-1 hover:bg-gray-50 rounded">
                <input type="radio" :value="t.id" v-model="addrForm.territory_id" />
                {{ t.name }}
              </label>
            </div>
          </div>
        </div>

        <!-- Тип + Название улицы -->
        <div class="grid grid-cols-3 gap-2">
          <div>
            <label class="field-label">Тип</label>
            <select v-model="addrForm.street_type" class="field-input">
              <option>ул.</option><option>пр.</option><option>пер.</option>
              <option>кв-л</option><option>б-р</option><option>ш.</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="field-label">Улица <span class="text-red-500">*</span></label>
            <input v-model="addrForm.street_name" required list="sl" class="field-input" placeholder="Малиновского" />
            <datalist id="sl">
              <option v-for="s in streetNames" :key="s" :value="s" />
            </datalist>
          </div>
        </div>

        <!-- Одиночный адрес -->
        <template v-if="editingAddr || addrMode === 'single'">
          <div class="grid grid-cols-3 gap-2">
            <div><label class="field-label">Дом *</label>
              <input v-model="addrForm.building" required class="field-input" /></div>
            <div><label class="field-label">Кв./Офис</label>
              <input v-model="addrForm.apartment" class="field-input" /></div>
            <div><label class="field-label">Подъезд</label>
              <input v-model="addrForm.entrance" class="field-input" /></div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="field-label">Абонент</label>
              <input v-model="addrForm.subscriber_name" class="field-input" /></div>
            <div><label class="field-label">Телефон</label>
              <input v-model="addrForm.phone" class="field-input" /></div>
            <div><label class="field-label">№ договора</label>
              <input v-model="addrForm.contract_no" class="field-input" /></div>
          </div>
        </template>

        <!-- Частный сектор -->
        <template v-if="!editingAddr && addrMode === 'private'">
          <div class="bg-amber-50 rounded-xl p-3 text-xs text-amber-800">🏠 Частный сектор — только дома</div>
          <div class="grid grid-cols-3 gap-2">
            <div><label class="field-label">Дом с *</label>
              <input v-model.number="genFrom" type="number" min="1" required class="field-input" /></div>
            <div><label class="field-label">Дом по *</label>
              <input v-model.number="genTo" type="number" min="1" required class="field-input" /></div>
            <div><label class="field-label">Шаг</label>
              <select v-model.number="genStep" class="field-input">
                <option :value="1">Все</option><option :value="2">Чёт/Нечёт</option>
              </select>
            </div>
          </div>
          <p class="text-xs text-gray-400">≈{{ Math.ceil((genTo - genFrom + 1) / genStep) }} записей</p>
        </template>

        <!-- МКД -->
        <template v-if="!editingAddr && addrMode === 'mkd'">
          PLACEHOLDER
          <div class="grid grid-cols-2 gap-3">
            <div><label class="field-label">Дом с *</label>
              <input v-model.number="genFrom" type="number" min="1" required class="field-input" /></div>
            <div><label class="field-label">Дом по *</label>
              <input v-model.number="genTo" type="number" min="1" required class="field-input" /></div>
            <div><label class="field-label">Кв. с *</label>
              <input v-model.number="genAptFrom" type="number" min="1" required class="field-input" /></div>
            <div><label class="field-label">Кв. по *</label>
              <input v-model.number="genAptTo" type="number" min="1" required class="field-input" /></div>
          </div>
          <p class="text-xs text-gray-400">≈{{ (genTo-genFrom+1) * (genAptTo-genAptFrom+1) }} записей</p>
        </template>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="closeAddModal" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editingAddr ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Импорт -->
    <Modal v-if="showImportModal" title="Импорт адресов (CSV)" @close="showImportModal = false">
      <div class="space-y-4">
        <div class="bg-blue-50 rounded-xl p-3 text-xs text-blue-800 space-y-2">
          <p class="font-medium">Формат CSV (разделитель — запятая):</p>
          <pre class="bg-white rounded-lg p-2 text-xs overflow-x-auto select-all">city,street,building,apartment,subscriber_name,phone,contract_no,territory
Новый,Тестовая,111,1,,,,Нью-Васюки
Новый,Тестовая,111,2,,,,Нью-Васюки
Новый,Тестовая,111,3,,,,Нью-Васюки</pre>
          <p class="text-blue-600">💡 Поля subscriber_name, phone, contract_no можно оставить пустыми</p>
        </div>
        <input type="file" ref="importFile" accept=".csv" class="w-full text-sm" />
        <div v-if="importError" class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700">{{ importError }}</div>
        <div v-if="importResult" :class="['rounded-xl p-3 text-sm', importResult.errors?.length ? 'bg-amber-50' : 'bg-green-50']">
          ✅ Создано: {{ importResult.created }} | ⏭ Пропущено: {{ importResult.skipped }}
          <p v-for="e in importResult.errors" :key="e" class="text-red-600 text-xs mt-1">{{ e }}</p>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="showImportModal = false" class="btn-outline text-sm">Закрыть</button>
          <button @click="submitImport" :disabled="importLoading" class="btn-primary text-sm">
            {{ importLoading ? 'Загрузка...' : 'Загрузить' }}
          </button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Modal from '@/Components/UI/Modal.vue'

const props = defineProps({
  territories: { type: Array, default: () => [] },
  // Данные для каждого уровня передаются через props
  cityList:    { type: Array, default: () => [] },  // [{name, count}]
  streetList:  { type: Array, default: () => [] },  // [{name, count}]
  buildingList:{ type: Array, default: () => [] },  // [{building, count, has_apartments, id}]
  aptList:     { type: Array, default: () => [] },  // адреса (квартиры)
  isMkd:        { type: Boolean, default: null },     // null = не определено
  buildingInfo: { type: Object,  default: null },     // данные ЧС дома
  // Текущий выбор
  currentCity:     { type: String, default: '' },
  currentStreet:   { type: String, default: '' },
  currentBuilding: { type: String, default: '' },
})

// ── Навигация ──────────────────────────────────────────────────────
const selected = ref({
  city:     props.currentCity,
  street:   props.currentStreet,
  building: props.currentBuilding,
})

const level = computed(() => {
  if (selected.value.building) return 3
  if (selected.value.street)   return 2
  if (selected.value.city)     return 1
  return 0
})

const items = computed(() => {
  if (level.value === 0) return props.cityList
  if (level.value === 1) return props.streetList
  if (level.value === 2) return props.buildingList
  if (level.value === 3) return props.aptList
  return []
})

const selectedBuilding = computed(() =>
  props.buildingList.find(b => b.building === selected.value.building) ?? null
)

// Пагинация для квартир
const page    = ref(1)

// Глобальный поиск
const globalSearch  = ref('')
const searchResults = ref([])
const searchLoading = ref(false)
let searchTimer2    = null

function onGlobalSearch() {
  searchResults.value = []
  if (globalSearch.value.length < 2) return
  searchLoading.value = true
  clearTimeout(searchTimer2)
  searchTimer2 = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('addresses.search'), {
        params: { q: globalSearch.value }
      })
      searchResults.value = data
    } catch { searchResults.value = [] }
    finally { searchLoading.value = false }
  }, 300)
}
const perPage = 50
const pagedItems = computed(() => {
  const start = (page.value - 1) * perPage
  return items.value.slice(start, start + perPage)
})

// Поиск улиц
const streetSearch = ref('')
const filteredStreets = computed(() => {
  if (!streetSearch.value) return props.streetList
  const q = streetSearch.value.toLowerCase()
  return props.streetList.filter(s => s.name.toLowerCase().includes(q))
})

// Списки для datalist
const cityNames   = computed(() => props.cityList.map(c => c.name))
const streetNames = computed(() => props.streetList.map(s => s.name))

// ── Переходы ──────────────────────────────────────────────────────
function navigate(params = {}) {
  router.get(route('addresses.index'), params, { preserveState: false })
}

function selectCity(city) {
  selected.value = { city, street: '', building: '' }
  navigate({ city })
}

function selectStreet(street) {
  selected.value.street   = street
  selected.value.building = ''
  navigate({ city: selected.value.city, street })
}

function selectBuilding(b) {
  selected.value.building = b.building
  navigate({ city: selected.value.city, street: selected.value.street, building: b.building })
}

function resetTo(lvl) {
  if (lvl === 0) { selected.value = { city: '', street: '', building: '' }; navigate() }
  if (lvl === 1) { selected.value.street = ''; selected.value.building = ''; navigate({ city: selected.value.city }) }
  if (lvl === 2) { selected.value.building = ''; navigate({ city: selected.value.city, street: selected.value.street }) }
}

// ── Добавление/редактирование адреса ──────────────────────────────
const showAddModal = ref(false)
const editingAddr  = ref(null)
const addrMode     = ref('single')
const genFrom      = ref(1); const genTo   = ref(10)
const genStep      = ref(1); const genAptFrom = ref(1); const genAptTo = ref(50)

const modes = [
  { key: 'single',  label: '📍 Один' },
  { key: 'private', label: '🏠 ЧС' },
  { key: 'mkd',     label: '🏢 МКД' },
]

const addrForm = useForm({
  city: selected.value.city ?? '', territory_id: '',
  street_type: 'ул.', street_name: selected.value.street ?? '',
  building: selected.value.building ?? '', apartment: '', entrance: '',
  subscriber_name: '', phone: '', contract_no: '',
})

function openAddModal() {
  editingAddr.value = null
  addrForm.city         = selected.value.city ?? ''
  addrForm.street_name  = selected.value.street?.replace(/^[а-яё]+\.\s*/i, '') ?? ''
  addrForm.building     = ''
  showAddModal.value    = true
}

function editAddress(a) {
  if (!a) return
  editingAddr.value = a
  const parts = (a.street ?? '').split('. ')
  addrForm.city           = a.city ?? ''
  addrForm.territory_id   = a.territory_id ?? ''
  addrForm.street_type    = parts.length > 1 ? parts[0] + '.' : 'ул.'
  addrForm.street_name    = parts.length > 1 ? parts.slice(1).join('. ') : a.street
  addrForm.building       = a.building ?? ''
  addrForm.apartment      = a.apartment ?? ''
  addrForm.entrance       = a.entrance ?? ''
  addrForm.subscriber_name = a.subscriber_name ?? ''
  addrForm.phone          = a.phone ?? ''
  addrForm.contract_no    = a.contract_no ?? ''
  showAddModal.value      = true
}

function closeAddModal() { showAddModal.value = false; editingAddr.value = null }

function submitAddress() {
  const street = addrForm.street_type + ' ' + addrForm.street_name
  const base   = { ...addrForm.data(), street }

  if (addrMode.value === 'private') {
    base.building_from = genFrom.value; base.building_to = genTo.value; base.building_step = genStep.value
  } else if (addrMode.value === 'mkd') {
    base.building_from = genFrom.value; base.building_to = genTo.value
    base.apt_from = genAptFrom.value;   base.apt_to = genAptTo.value
  }

  if (editingAddr.value) {
    addrForm.transform(() => base).put(route('addresses.update', editingAddr.value.id), { onSuccess: () => { closeAddModal(); router.reload() } })
  } else {
    addrForm.transform(() => base).post(route('addresses.store'), { onSuccess: () => { closeAddModal(); router.reload() } })
  }
}

function deleteAddr(a) {
  if (confirm('Удалить адрес?')) router.delete(route('addresses.destroy', a.id), { onSuccess: () => router.reload() })
}

// ── Импорт ─────────────────────────────────────────────────────────
const showImportModal = ref(false)
const importFile      = ref(null)
const importLoading   = ref(false)
const importError     = ref(null)
const importResult    = ref(null)

async function submitImport() {
  if (!importFile.value?.files?.[0]) return
  importLoading.value = true; importError.value = null; importResult.value = null
  const data = new FormData()
  data.append('file', importFile.value.files[0])
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
    const res  = await axios.post(route('addresses.import'), data, {
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
      }
    })
    // Inertia возвращает props.flash или напрямую данные
    const result = res.data?.import_result
                ?? res.data?.props?.flash?.import_result
                ?? null
    if (result) {
      importResult.value = result
    } else {
      importError.value = 'Сервер не вернул результат. Проверьте логи.'
    }
    router.reload({ only: ['addresses', 'cities'] })
  } catch (e) {
    // Показываем детали ошибки
    const errData = e.response?.data
    if (errData?.errors) {
      // Ошибки валидации Laravel
      const msgs = Object.values(errData.errors).flat()
      importError.value = msgs.join('; ')
    } else if (errData?.message) {
      importError.value = errData.message
    } else if (e.response?.status === 422) {
      importError.value = 'Ошибка валидации файла'
    } else {
      importError.value = `Ошибка ${e.response?.status ?? ''}: ${e.message}`
    }
  } finally { importLoading.value = false }
}

// Пагинация — компонент
const Pagination = {
  props: { total: Number, perPage: Number, modelValue: Number },
  emits: ['update:modelValue'],
  computed: {
    pages() { return Math.ceil(this.total / this.perPage) }
  },
  template: `
    <div v-if="pages > 1" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
      <span>Стр. {{ modelValue }} из {{ pages }} ({{ total }} записей)</span>
      <div class="flex gap-1">
        <button @click="$emit('update:modelValue', Math.max(1, modelValue-1))"
                :disabled="modelValue===1" class="px-2.5 py-1 border rounded-lg disabled:opacity-30 hover:bg-gray-50">‹</button>
        <button @click="$emit('update:modelValue', Math.min(pages, modelValue+1))"
                :disabled="modelValue===pages" class="px-2.5 py-1 border rounded-lg disabled:opacity-30 hover:bg-gray-50">›</button>
      </div>
    </div>`
}
</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors disabled:opacity-40; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 bg-slate-50; }
</style>
