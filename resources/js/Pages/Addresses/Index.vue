<template>
  <Head title="Адреса" />
  <AppLayout title="Адреса абонентов" help-tab="admin" help-section="addresses">
    <template #actions>
      <button @click="showImportModal = true" class="btn-outline text-sm">⬆ Импорт</button>
      <button @click="openAddModal"           class="btn-primary text-sm">+ Добавить</button>
    </template>

    <!-- ── Поиск по адресу ── -->
    <div class="flex items-center gap-2 mb-4">
      <div class="relative flex-1 max-w-lg">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
             fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input v-model="globalSearch"
               @input="onGlobalSearch"
               placeholder="Быстрый поиск: улица, дом, абонент, телефон, договор..."
               class="w-full pl-9 pr-9 py-2.5 border border-gray-200 rounded-xl text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500/30" />
        <svg v-if="searchLoading"
             class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-blue-500 animate-spin"
             fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
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
        </div>
        <div v-if="globalSearch.length >= 2 && !searchResults.length && !searchLoading"
             class="absolute z-50 top-full left-0 right-0 mt-1 bg-white border border-gray-200
                    rounded-xl shadow-xl px-4 py-3 text-sm text-gray-400 text-center">
          Ничего не найдено
        </div>
      </div>
      <button @click="openAddrModal" type="button"
              class="shrink-0 btn-outline text-sm whitespace-nowrap">
        📍 Адрес
      </button>
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
        <div v-for="city in items" :key="city.name"
             class="flex items-center gap-1 px-2.5 py-1.5 bg-white border border-gray-200
                    rounded-xl hover:border-blue-400 hover:bg-blue-50 transition-colors text-sm font-medium">
          <button @click="selectCity(city.name)" class="flex items-center gap-2 px-1.5 py-1">
            🏙 {{ city.name }}
            <span class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">{{ city.count }}</span>
          </button>
          <button @click.stop="openRename('city', city.name)" title="Переименовать город"
                  class="shrink-0 text-gray-300 hover:text-blue-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
          <button v-if="canDeleteAddresses" @click.stop="deleteCity(city.name, city.count)" title="Удалить город"
                  class="shrink-0 text-gray-300 hover:text-red-500 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h18"/>
              <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6"/><path d="M14 11v6"/>
            </svg>
          </button>
        </div>
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
      <div v-for="group in groupedStreets" :key="group.letter" class="mb-4">
        <div class="text-sm font-bold text-gray-400 px-1 mb-1.5">{{ group.letter }}</div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
          <div v-for="st in group.streets" :key="st.name"
               class="flex items-center px-3 py-2.5 bg-white border border-gray-200
                      rounded-xl hover:border-blue-400 hover:bg-blue-50 transition-colors text-sm">
            <button @click="selectStreet(st.name)"
                    class="flex-1 min-w-0 flex items-center justify-between text-left">
              <span class="font-medium truncate">{{ st.name }}</span>
              <span class="text-xs text-gray-400 ml-2 shrink-0">{{ st.count }}</span>
            </button>
            <button @click.stop="openRename('street', st.name, { city: selected.city })" title="Переименовать улицу"
                    class="ml-1.5 shrink-0 text-gray-300 hover:text-blue-600 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
            </button>
            <button v-if="canDeleteAddresses" @click.stop="deleteStreet(st.name, st.count)" title="Удалить улицу"
                    class="ml-1 shrink-0 text-gray-300 hover:text-red-500 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h18"/>
              <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6"/><path d="M14 11v6"/>
            </svg>
            </button>
          </div>
        </div>
      </div>
      <p v-if="!filteredStreets.length" class="text-gray-400 text-sm py-6 text-center">Улицы не найдены</p>
    </div>

    <!-- ── Уровень 2: Дома ── -->
    <div v-if="level === 2">
      <!-- Обычный режим: подсказка + кнопка -->
      <div v-if="!editTypeMode" class="flex items-center gap-3 mb-4">
        <p class="text-xs text-gray-400">{{ selected.city }}, {{ selected.street }} — выберите дом</p>
        <button @click="toggleEditTypeMode" title="Изменить тип"
                class="flex items-center gap-1 px-3 py-2 rounded-xl bg-violet-100 hover:bg-violet-200
                       border border-violet-200 text-violet-700 shadow-sm transition-colors shrink-0">
          <span class="text-base leading-none">🏢</span>
          <span class="text-sm leading-none opacity-70 px-0.5">⇄</span>
          <span class="text-base leading-none">🏠</span>
        </button>
      </div>
      <!-- Режим выбора: инструкция-баннер + действия в одной строке -->
      <div v-else class="flex items-center gap-3 mb-4 px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl">
        <span class="text-sm text-blue-800 flex-1">
          <span v-if="!selectedBuildings.length">Отметьте дома, затем выберите тип</span>
          <span v-else>Выбрано домов: <strong>{{ selectedBuildings.length }}</strong></span>
        </span>
        <button @click="setBuildingType('private')" :disabled="typeChanging || !selectedBuildings.length"
                class="text-sm px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg
                       disabled:opacity-30 transition-colors font-medium whitespace-nowrap">
          → ЧС
        </button>
        <button @click="setBuildingType('mkd')" :disabled="typeChanging || !selectedBuildings.length"
                class="text-sm px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg
                       disabled:opacity-30 transition-colors font-medium whitespace-nowrap">
          → МКД
        </button>
        <button @click="toggleEditTypeMode"
                class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 bg-white text-gray-600
                       hover:bg-gray-50 transition-colors whitespace-nowrap">
          Отмена
        </button>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-for="b in items" :key="b.building"
                @click="editTypeMode ? toggleBuildingSelect(b) : selectBuilding(b)"
                :class="['flex flex-col items-center px-4 py-3 border rounded-xl transition-colors text-sm relative',
                         editTypeMode && isBuildingSelected(b)
                           ? 'bg-blue-50 border-blue-500 ring-2 ring-blue-300'
                           : b.has_apartments
                             ? 'bg-white border-gray-200 hover:border-blue-400 hover:bg-blue-50'
                             : 'bg-white border-gray-200 hover:border-green-400 hover:bg-green-50']">
          <span v-if="editTypeMode"
                class="absolute top-1 right-1 w-4 h-4 rounded border flex items-center justify-center text-xs leading-none"
                :class="isBuildingSelected(b) ? 'bg-blue-600 border-blue-600 text-white' : 'border-gray-300 bg-white'">
            <span v-if="isBuildingSelected(b)">✓</span>
          </span>
          <span v-if="!editTypeMode"
                role="button"
                title="Переименовать дом"
                @click.stop="openRename('building', b.building, { city: selected.city, street: selected.street })"
                class="absolute top-1 right-1 w-4 h-4 flex items-center justify-center text-gray-300 hover:text-blue-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </span>
          <span v-if="!editTypeMode && canDeleteAddresses"
                role="button"
                title="Удалить дом"
                @click.stop="deleteBuilding(b)"
                class="absolute top-1 right-6 w-4 h-4 flex items-center justify-center text-gray-300 hover:text-red-500 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6h18"/>
              <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6"/><path d="M14 11v6"/>
            </svg>
          </span>
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
      <div class="flex items-center justify-between mb-3">
        <p class="text-xs text-gray-400">
          {{ selected.city }}, {{ selected.street }} д.{{ selected.building }} — квартиры
        </p>
        <div class="flex gap-2 shrink-0">
          <button @click="openTerritoryChangeModal" class="btn-outline text-xs">
            🏷 Территория
          </button>
          <button @click="openAddApartmentModal" class="btn-outline text-xs">
            + Добавить квартиру
          </button>
        </div>
      </div>
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
                <a :href="ticketsLink(a)"
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
                <button v-if="canDeleteAddresses" @click="deleteAddr(a)"  class="p-1 text-gray-400 hover:text-red-500 rounded">🗑</button>
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
            <button @click="openTerritoryChangeModal" class="btn-outline text-xs">🏷 Территория</button>
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
      <form @submit.prevent="submitAddress()" class="space-y-4">

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
            <label class="field-label">Город <span v-if="!cityLocked" class="text-red-500">*</span></label>
            <p v-if="cityLocked" class="field-input text-gray-600 truncate">{{ addrForm.city }}</p>
            <div v-else class="grid grid-cols-3 gap-1.5">
              <select v-model="addrForm.city_prefix" class="field-input px-1.5">
                <option v-for="p in CITY_PREFIXES" :key="p.value" :value="p.value">{{ p.label }}</option>
              </select>
              <input v-model="addrForm.city_name" list="cl" required class="field-input col-span-2" placeholder="Донецк" />
              <datalist id="cl">
                <option v-for="c in cityNames" :key="c" :value="c" />
              </datalist>
            </div>
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

        <!-- Улица -->
        <div>
          <label class="field-label">Улица <span v-if="!streetLocked" class="text-red-500">*</span></label>
          <p v-if="streetLocked" class="field-input text-gray-600 truncate">{{ addrForm.street }}</p>
          <div v-else class="grid grid-cols-3 gap-2">
            <select v-model="addrForm.street_prefix" class="field-input">
              <option v-for="p in STREET_PREFIXES_SELECT" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
            <div class="col-span-2">
              <input v-model="addrForm.street_name" required list="sl" class="field-input" placeholder="Малиновского" />
              <datalist id="sl">
                <option v-for="s in streetNames" :key="s" :value="s" />
              </datalist>
            </div>
          </div>
        </div>

        <!-- Одиночный адрес -->
        <template v-if="editingAddr || addrMode === 'single'">
          <div>
            <label class="field-label">Дом <span v-if="!buildingLocked" class="text-red-500">*</span></label>
            <p v-if="buildingLocked" class="field-input text-gray-600 truncate">{{ addrForm.building }}</p>
            <div v-else class="grid grid-cols-3 gap-2">
              <select v-model="addrForm.building_prefix" class="field-input">
                <option v-for="p in BUILDING_PREFIXES" :key="p.value" :value="p.value">{{ p.label }}</option>
              </select>
              <input v-model="addrForm.building_name" required class="field-input col-span-2" />
            </div>
          </div>
          <div class="grid grid-cols-3 gap-2">
            <div><label class="field-label">Тип кв./помещ.</label>
              <select v-model="addrForm.apartment_prefix" class="field-input">
                <option v-for="p in APARTMENT_PREFIXES" :key="p.value" :value="p.value">{{ p.label }}</option>
              </select></div>
            <div><label class="field-label">Кв./Офис</label>
              <input v-model="addrForm.apartment_name" class="field-input" /></div>
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

        <div v-if="addrForm.errors && Object.keys(addrForm.errors).length"
             class="bg-red-50 border border-red-200 rounded-xl px-3 py-2 text-sm text-red-700">
          <p v-for="(err, field) in addrForm.errors" :key="field">{{ err }}</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="closeAddModal" class="btn-outline text-sm">Отмена</button>
          <button v-if="addrForm.errors && addrForm.errors.duplicate" type="button"
                  @click="submitAddress(true)" class="btn-outline text-sm text-amber-700 border-amber-300">
            Всё равно создать
          </button>
          <button class="btn-primary text-sm">{{ editingAddr ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Смена территории дома целиком -->
    <Modal v-if="showTerritoryChangeModal" title="Территория дома" @close="showTerritoryChangeModal = false">
      <form @submit.prevent="submitTerritoryChange" class="space-y-4">
        <p class="text-sm text-gray-500">
          {{ selected.city }}, {{ selected.street }}, д.{{ selected.building }}
          — территория изменится сразу для всех {{ items.length }} записей этого дома (все квартиры переедут вместе с домом).
        </p>
        <div class="border border-gray-200 rounded-xl p-2 space-y-1 max-h-52 overflow-y-auto">
          <label v-for="t in territories" :key="t.id"
                 class="flex items-center gap-2 text-sm cursor-pointer p-1 hover:bg-gray-50 rounded">
            <input type="radio" :value="t.id" v-model="territoryChangeForm.territory_id" />
            {{ t.name }}
          </label>
        </div>
        <div v-if="territoryChangeForm.errors.territory_id"
             class="bg-red-50 border border-red-200 rounded-xl px-3 py-2 text-sm text-red-700">
          {{ territoryChangeForm.errors.territory_id }}
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="showTerritoryChangeModal = false" class="btn-outline text-sm">Отмена</button>
          <button :disabled="territoryChangeForm.processing" class="btn-primary text-sm disabled:opacity-50">
            {{ territoryChangeForm.processing ? 'Сохраняю…' : 'Сохранить' }}
          </button>
        </div>
      </form>
    </Modal>

    <!-- Переименование города/улицы/дома -->
    <Modal v-if="showRenameModal" :title="`Переименовать ${RENAME_LABELS[renameLevel]}`" @close="closeRenameModal">
      <div class="space-y-4">
        <p v-if="renameParent.city || renameParent.street" class="text-xs text-gray-400">
          <span v-if="renameParent.city">{{ renameParent.city }}</span>
          <span v-if="renameParent.street"> › {{ renameParent.street }}</span>
        </p>
        <div v-if="!renameConfirm">
          <label class="field-label">Название</label>
          <div class="grid grid-cols-3 gap-2">
            <select v-model="renamePrefix" class="field-input">
              <option v-for="p in PREFIX_LISTS[renameLevel]" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
            <input v-model="renameNameField" type="text" class="field-input col-span-2"
                   @keyup.enter="submitRename" />
          </div>
          <p class="text-xs text-gray-400 mt-1">
            Было: «{{ renameOld }}». Если новое название совпадёт с уже существующим —
            адреса объединятся в него.
          </p>
        </div>
        <div v-else class="bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5 text-sm text-amber-800">
          «{{ composeValue(renamePrefix, renameNameField) }}» уже существует
          ({{ renameConfirm.target_count }} адресов). Все {{ renameConfirm.source_count }}
          адресов с «{{ renameOld }}» будут перенесены туда, дубликаты объединятся.
          Продолжить?
        </div>
        <div v-if="renameError" class="bg-red-50 border border-red-200 rounded-xl px-3 py-2 text-sm text-red-700">
          {{ renameError }}
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="closeRenameModal" class="btn-outline text-sm">Отмена</button>
          <button @click="submitRename" :disabled="renameLoading"
                  :class="['text-sm disabled:opacity-50', renameConfirm ? 'btn-primary bg-amber-600 hover:bg-amber-700 border-amber-600' : 'btn-primary']">
            {{ renameLoading ? 'Сохраняю…' : (renameConfirm ? 'Объединить' : 'Сохранить') }}
          </button>
        </div>
      </div>
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
          ✅ Создано: {{ importResult.created }} | ⏭ Пропущено: {{ importResult.skipped }}<template v-if="importResult.corrected"> | 🛠 Исправлено городов: {{ importResult.corrected }}</template>
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
          <select v-model="addrSel.building" class="field-input">
            <option value="">— Выбрать дом —</option>
            <option v-for="b in addrBuildings" :key="b" :value="b">{{ b }}</option>
          </select>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="showAddrModal = false" class="btn-outline text-sm">Отмена</button>
          <button type="button" @click="applyAddrModal"
                  :disabled="!addrSel.city || !addrSel.street || !addrSel.building"
                  class="btn-primary text-sm">
            Перейти →
          </button>
        </div>
      </div>
    </Modal>  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, useForm, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Modal from '@/Components/UI/Modal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { PREFIX_LISTS, CITY_PREFIXES, STREET_PREFIXES_SELECT, BUILDING_PREFIXES, APARTMENT_PREFIXES, splitPrefix, composeValue } from '@/utils/addressAffix.js'

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

// Удаление объектов адресов -- необратимая операция, доступна только
// пользователю id=1 (см. AddressController::ensureCanDeleteHierarchy)
const canDeleteAddresses = computed(() => usePage().props.auth?.user?.id === 1)

// ── Навигация ──────────────────────────────────────────────────────
function ticketsLink(a) {
  if (a.apartment) {
    return route('tickets.index', {
      city: selected.value.city,
      street: selected.value.street,
      building: selected.value.building,
      apartment: a.apartment,
    })
  }
  return route('tickets.index', { address_id: a.id })
}

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

// Буквенные группы улиц (как в адресном справочнике) — буква вычисляется
// от названия БЕЗ префикса (ул./пер./пр. и т.п.), той же логикой, что и
// backend Address::normalizeStreet() (которым уже отсортирован streetList) —
// иначе "Ленина" и "ул. Ленина" попали бы в разные группы Л/У.
const STREET_PREFIXES = [
  'ул\\.?,?', 'улица',
  'пр-т', 'пр\\.?', 'проспект',
  'пер\\.?', 'переулок',
  'бул\\.?', 'блв\\.?', 'б-р', 'бульвар',
  'мкрн\\.?', 'микрорайон',
  'кв-л', 'квартал',
  'пос\\.?', 'поселок',
  'с\\.?', 'село',
]
const STREET_PREFIX_RE = new RegExp('^(' + STREET_PREFIXES.join('|') + ')\\s+', 'iu')

function streetGroupLetter(name) {
  let s = (name ?? '').toLowerCase().trim().replace(/\s+/g, ' ').replace(/ё/g, 'е')
  s = s.replace(STREET_PREFIX_RE, '').trim()
  return s.charAt(0).toUpperCase() || '#'
}

const groupedStreets = computed(() => {
  const groups = []
  let currentLetter = null
  for (const st of filteredStreets.value) {
    const letter = streetGroupLetter(st.name)
    if (letter !== currentLetter) {
      currentLetter = letter
      groups.push({ letter, streets: [] })
    }
    groups[groups.length - 1].streets.push(st)
  }
  return groups
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

// ── Переключение ЧС/МКД ────────────────────────────────────────────
const editTypeMode      = ref(false)
const selectedBuildings = ref([])
const typeChanging      = ref(false)

function toggleEditTypeMode() {
  editTypeMode.value = !editTypeMode.value
  if (!editTypeMode.value) selectedBuildings.value = []
}

function isBuildingSelected(b) {
  return selectedBuildings.value.some(s => s.building === b.building)
}

function toggleBuildingSelect(b) {
  const idx = selectedBuildings.value.findIndex(s => s.building === b.building)
  if (idx === -1) selectedBuildings.value.push(b)
  else selectedBuildings.value.splice(idx, 1)
}

async function setBuildingType(type) {
  if (!selectedBuildings.value.length) return
  typeChanging.value = true
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
    await axios.post(route('addresses.bulk-set-type'), {
      ids: selectedBuildings.value.map(b => b.id),
      type,
    }, { headers: { 'X-CSRF-TOKEN': csrf } })
    selectedBuildings.value = []
    editTypeMode.value = false
    router.reload()
  } catch (e) {
    alert('Ошибка: ' + (e.response?.data?.message ?? e.message))
  } finally {
    typeChanging.value = false
  }
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
  { key: 'private', label: '🏠 ЧС - Множеств.' },
  { key: 'mkd',     label: '🏢 МКД - Множеств.' },
]

// cityLocked/streetLocked/buildingLocked — когда true, соответствующий уровень
// показывается read-only (сырое значение как есть, без разбора на префикс+
// название). Это и есть исправление бага "ул. ул. Садовая": родитель, внутри
// которого создаётся/редактируется дочерняя сущность, никогда не проходит
// через попытку раскроить уже готовую строку обратно на части.
const cityLocked     = ref(false)
const streetLocked   = ref(false)
const buildingLocked = ref(false)

const addrForm = useForm({
  city: '', street: '', building: '', territory_id: '',
  city_prefix: '', city_name: '',
  street_prefix: '', street_name: '',
  building_prefix: '', building_name: '',
  apartment_prefix: '', apartment_name: '',
  entrance: '', subscriber_name: '', phone: '', contract_no: '',
  confirm_duplicate: false,
})

function openAddModal() {
  editingAddr.value = null
  cityLocked.value     = level.value >= 1
  streetLocked.value   = level.value >= 2
  buildingLocked.value = false

  addrForm.city = selected.value.city ?? ''
  addrForm.city_prefix = ''; addrForm.city_name = ''
  addrForm.street = selected.value.street ?? ''
  addrForm.street_prefix = ''; addrForm.street_name = ''
  addrForm.building = ''
  addrForm.building_prefix = ''; addrForm.building_name = ''
  addrForm.apartment_prefix = ''; addrForm.apartment_name = ''
  addrForm.territory_id = ''
  addrForm.entrance = ''; addrForm.subscriber_name = ''; addrForm.phone = ''; addrForm.contract_no = ''
  showAddModal.value = true
}

// Единичная/именная квартира прямо из списка квартир конкретного дома МКД —
// в отличие от общей кнопки "+ Добавить" (открывается пустой, режим "МКД" даёт
// только массовую генерацию диапазона apt_from/apt_to, оба числовые), тут дом
// и улица уже известны из контекста драм-дауна, и режим сразу "Один" — можно
// сразу вписать любой текст в номер квартиры (например "маг. Ромашка"). Город/
// улица/дом всегда read-only здесь — берутся из контекста как есть.
function openAddApartmentModal() {
  editingAddr.value = null
  addrMode.value     = 'single'
  cityLocked.value = true; streetLocked.value = true; buildingLocked.value = true

  addrForm.city     = selected.value.city ?? ''
  addrForm.street   = selected.value.street ?? ''
  addrForm.building = selected.value.building ?? ''
  addrForm.apartment_prefix = ''; addrForm.apartment_name = ''
  addrForm.entrance = ''
  addrForm.subscriber_name = ''
  addrForm.phone       = ''
  addrForm.contract_no = ''
  // Территория обязательна — подхватываем у любой уже существующей квартиры
  // этого же дома, если она есть; иначе оператор выбирает вручную в форме.
  addrForm.territory_id = items.value[0]?.territory_id ?? ''
  showAddModal.value = true
}

// Город/улица/дом при редактировании всегда read-only (это не операция
// переименования всей группы, а правка одной конкретной строки) — только
// apartment разбирается best-effort на префикс+название для предзаполнения.
function editAddress(a) {
  if (!a) return
  editingAddr.value = a
  cityLocked.value = true; streetLocked.value = true; buildingLocked.value = true

  addrForm.city         = a.city ?? ''
  addrForm.street       = a.street ?? ''
  addrForm.building     = a.building ?? ''
  addrForm.territory_id = a.territory_id ?? ''
  const { type, name } = splitPrefix(a.apartment, APARTMENT_PREFIXES)
  addrForm.apartment_prefix = type
  addrForm.apartment_name   = name
  addrForm.entrance         = a.entrance ?? ''
  addrForm.subscriber_name  = a.subscriber_name ?? ''
  addrForm.phone            = a.phone ?? ''
  addrForm.contract_no      = a.contract_no ?? ''
  showAddModal.value        = true
}

function closeAddModal() {
  showAddModal.value = false
  editingAddr.value  = null
  cityLocked.value = false; streetLocked.value = false; buildingLocked.value = false
}

function submitAddress(confirmDuplicate = false) {
  if (!addrForm.territory_id) {
    addrForm.errors.territory_id = 'Выберите территорию'
    return
  }
  const city      = cityLocked.value     ? addrForm.city     : composeValue(addrForm.city_prefix, addrForm.city_name)
  const street    = streetLocked.value   ? addrForm.street   : composeValue(addrForm.street_prefix, addrForm.street_name)
  const building  = buildingLocked.value ? addrForm.building : composeValue(addrForm.building_prefix, addrForm.building_name)
  const apartment = composeValue(addrForm.apartment_prefix, addrForm.apartment_name)

  const base = { ...addrForm.data(), city, street, building, apartment, confirm_duplicate: confirmDuplicate }

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

function hierarchyDeleteCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content
}

function deleteCity(name, count) {
  if (!confirm(`Удалить город «${name}» и все адреса внутри (${count})? Это необратимо.`)) return
  axios.delete(route('addresses.destroy-city'), {
    data: { city: name },
    headers: { 'X-CSRF-TOKEN': hierarchyDeleteCsrf() },
  }).then(() => router.reload()).catch(e => alert(e.response?.data?.message ?? e.message))
}

function deleteStreet(name, count) {
  if (!confirm(`Удалить улицу «${name}» и все адреса на ней (${count})? Это необратимо.`)) return
  axios.delete(route('addresses.destroy-street'), {
    data: { city: selected.value.city, street: name },
    headers: { 'X-CSRF-TOKEN': hierarchyDeleteCsrf() },
  }).then(() => router.reload()).catch(e => alert(e.response?.data?.message ?? e.message))
}

function deleteBuilding(b) {
  if (!confirm(`Удалить дом «${b.building}» и все адреса в нём (${b.count})? Это необратимо.`)) return
  axios.delete(route('addresses.destroy-building'), {
    data: { city: selected.value.city, street: selected.value.street, building: b.building },
    headers: { 'X-CSRF-TOKEN': hierarchyDeleteCsrf() },
  }).then(() => router.reload()).catch(e => alert(e.response?.data?.message ?? e.message))
}

// ── Смена территории дома целиком (квартиры переезжают вместе с домом) ──
const showTerritoryChangeModal = ref(false)
const territoryChangeForm = useForm({ territory_id: '' })

function openTerritoryChangeModal() {
  territoryChangeForm.clearErrors()
  territoryChangeForm.territory_id = items.value[0]?.territory_id ?? props.buildingInfo?.territory_id ?? ''
  showTerritoryChangeModal.value = true
}

function submitTerritoryChange() {
  territoryChangeForm
    .transform(data => ({
      ...data,
      city: selected.value.city,
      street: selected.value.street,
      building: selected.value.building,
    }))
    .post(route('addresses.set-territory'), {
      onSuccess: () => { showTerritoryChangeModal.value = false; router.reload() },
    })
}

// ── Переименование/слияние города/улицы/дома ────────────────────────
const RENAME_LABELS = { city: 'город', street: 'улицу', building: 'дом' }
const RENAME_ROUTES = { city: 'addresses.rename-city', street: 'addresses.rename-street', building: 'addresses.rename-building' }

const showRenameModal = ref(false)
const renameLevel     = ref('street') // 'city' | 'street' | 'building'
const renameParent    = reactive({ city: '', street: '' })
const renameOld       = ref('')
const renamePrefix    = ref('')
const renameNameField = ref('')
const renameConfirm   = ref(null)
const renameError     = ref('')
const renameLoading   = ref(false)

function openRename(levelKey, oldValue, parent = {}) {
  renameLevel.value = levelKey
  renameOld.value = oldValue
  renameParent.city = parent.city ?? ''
  renameParent.street = parent.street ?? ''
  const { type, name } = splitPrefix(oldValue, PREFIX_LISTS[levelKey])
  renamePrefix.value = type
  renameNameField.value = name
  renameConfirm.value = null
  renameError.value = ''
  showRenameModal.value = true
}

function closeRenameModal() {
  showRenameModal.value = false
  renameConfirm.value = null
  renameError.value = ''
}

async function submitRename() {
  const newValue = composeValue(renamePrefix.value, renameNameField.value)
  if (!newValue) { renameError.value = 'Название не может быть пустым'; return }

  renameLoading.value = true
  renameError.value = ''
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
    const payload = { confirm_merge: !!renameConfirm.value }
    if (renameLevel.value === 'city') {
      payload.city = renameOld.value
      payload.new_city = newValue
    } else if (renameLevel.value === 'street') {
      payload.city = renameParent.city
      payload.street = renameOld.value
      payload.new_street = newValue
    } else {
      payload.city = renameParent.city
      payload.street = renameParent.street
      payload.building = renameOld.value
      payload.new_building = newValue
    }
    const { data } = await axios.patch(route(RENAME_ROUTES[renameLevel.value]), payload, {
      headers: { 'X-CSRF-TOKEN': csrf },
    })

    if (data.needs_confirm) {
      renameConfirm.value = data
      return
    }

    showRenameModal.value = false
    if (data.merged) {
      alert(`Готово. Перенесено адресов: ${data.moved}, объединено дублей: ${data.merged}.`)
    }
    router.reload()
  } catch (e) {
    renameError.value = e.response?.data?.message ?? e.message
  } finally {
    renameLoading.value = false
  }
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

// ── Модал выбора адреса ─────────────────────────────────────────────
const showAddrModal = ref(false)
const addrCities    = ref([])
const addrStreets   = ref([])
const addrBuildings = ref([])
const addrSel       = reactive({ city: '', street: '', building: '' })

async function openAddrModal() {
  Object.assign(addrSel, { city: '', street: '', building: '' })
  addrStreets.value = []; addrBuildings.value = []
  showAddrModal.value = true
  try {
    addrCities.value = (await axios.get(route('addresses.hierarchy'))).data
  } catch { addrCities.value = [] }
}

async function onAddrCity() {
  addrSel.street = ''; addrSel.building = ''
  addrStreets.value = []; addrBuildings.value = []
  if (!addrSel.city) return
  try {
    addrStreets.value = (await axios.get(route('addresses.hierarchy'), { params: { city: addrSel.city } })).data
  } catch { addrStreets.value = [] }
}

async function onAddrStreet() {
  addrSel.building = ''
  addrBuildings.value = []
  if (!addrSel.street) return
  try {
    addrBuildings.value = (await axios.get(route('addresses.hierarchy'), { params: { city: addrSel.city, street: addrSel.street } })).data
  } catch { addrBuildings.value = [] }
}

function applyAddrModal() {
  if (!addrSel.building) return
  showAddrModal.value = false
  navigate({ city: addrSel.city, street: addrSel.street, building: addrSel.building })
}</script>
