<template>
  <Head title="Подключения" />
  <AppLayout title="Подключения">

    <!-- Вкладки территорий + Фильтры -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-4">

      <!-- Вкладки -->
      <div class="bg-gray-50 border-b border-gray-200 flex items-end gap-0.5 px-3 pt-2 flex-wrap">
        <button @click="selectTerritory(null)"
                :class="['px-3 py-2 text-sm font-medium flex items-center gap-1.5 rounded-t-xl transition-colors relative',
                         selectedTerritory === null
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          Все
          <span v-if="totalPending > 0"
                class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-xs font-bold bg-orange-500 text-white leading-none">
            {{ totalPending }}
          </span>
          <span v-if="totalOverdue > 0"
                class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-xs font-bold bg-red-500 text-white leading-none">
            {{ totalOverdue }}
          </span>
        </button>
        <button v-for="t in territories" :key="t.id"
                @click="selectTerritory(t.id)"
                :class="['px-3 py-2 text-sm font-medium flex items-center gap-1.5 rounded-t-xl transition-colors relative',
                         selectedTerritory === t.id
                           ? 'bg-white border border-gray-200 border-b-white -mb-px z-10 text-gray-800'
                           : 'text-gray-500 hover:text-gray-700 hover:bg-white/60']">
          {{ t.name }}
          <span v-if="pendingByTerritory[t.id]"
                class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-xs font-bold bg-orange-500 text-white leading-none">
            {{ pendingByTerritory[t.id] }}
          </span>
          <span v-if="overdueByTerritory[t.id]"
                class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-xs font-bold bg-red-500 text-white leading-none">
            {{ overdueByTerritory[t.id] }}
          </span>
        </button>
      </div>

      <!-- Фильтры -->
      <div class="p-4 flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
          <label class="block text-xs text-gray-500 mb-1">Поиск</label>
          <input v-model="f.search" @keydown.enter="apply" class="field-input w-full" placeholder="Имя, телефон, адрес..." />
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
              <th class="px-3 py-3 text-left w-8"></th>
              <th class="px-4 py-3 text-left">Дата</th>
              <th class="px-4 py-3 text-left">Имя</th>
              <th class="px-4 py-3 text-left">Телефон</th>
              <th class="px-4 py-3 text-left">Адрес</th>
              <th class="px-4 py-3 text-left">Описание</th>
              <th class="px-4 py-3 text-left">Статус</th>
              <th class="px-4 py-3 text-left">Дата подкл.</th>
              <th class="px-4 py-3 text-left">Примечания / Акт</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-xs">
            <tr v-for="r in requests.data" :key="r.id" class="hover:bg-gray-50">
              <td class="px-2 py-1.5 text-center">
                <button @click="openEdit(r)" title="Редактировать"
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
              <td class="px-3 py-1.5 text-blue-600 hover:underline cursor-pointer"
                  @click="openDetail(r)">{{ r.address_string }}</td>
              <td class="px-3 py-1.5 text-gray-500 max-w-48 truncate" :title="r.description">{{ r.description || '—' }}</td>
              <td class="px-3 py-1.5">
                <div class="flex items-center gap-1.5">
                  <span :class="statusClass(r.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ statusLabel(r.status) }}
                  </span>
                  <span v-if="r.needs_callback"
                        class="animate-bounce text-amber-500"
                        title="Требуется прозвон">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                    </svg>
                  </span>
                </div>
              </td>
              <td class="px-3 py-1.5 whitespace-nowrap text-gray-600">{{ r.scheduled_at ? fmtDate(r.scheduled_at) : '—' }}</td>
              <td class="px-3 py-1.5 text-gray-600 max-w-48 truncate" :title="r.notes">
                <button v-if="r.act"
                        @click="router.get(route('acts.show', r.act.id))"
                        class="mr-1 text-blue-600 hover:underline font-medium text-xs">[{{ r.act.number }}]</button>
                {{ r.notes || '—' }}
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
                          class="px-2 py-0.5 rounded bg-orange-100 text-orange-700 hover:bg-orange-200 text-xs font-medium">
                    Завершить
                  </button>
                  <button v-if="r.status === 'scheduled'"
                          @click="openSchedule(r)"
                          title="Изменить назначенную дату подключения"
                          class="px-2 py-0.5 rounded bg-purple-100 text-purple-700 hover:bg-purple-200 text-xs font-medium">
                    Изменить дату
                  </button>
                  <button v-if="r.needs_callback"
                          @click="submitMarkCalled(r)"
                          class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 hover:bg-amber-200 text-xs font-medium"
                          title="Отметить: прозвонили клиенту">
                    Прозвонил
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
            <label class="block text-xs text-gray-500 mb-1">Бригада <span class="text-red-400">*</span></label>
            <select v-model="createForm.brigade_id" class="field-input w-full">
              <option :value="null">— выберите бригаду —</option>
              <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
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

    <!-- Модал: Редактировать -->
    <div v-if="modals.edit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-4">Редактировать заявку</h3>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Имя клиента <span class="text-red-400">*</span></label>
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
            <label class="block text-xs text-gray-500 mb-1">Территория</label>
            <select v-model="editForm.territory_id" class="field-input w-full">
              <option :value="null">— не указана —</option>
              <option v-for="t in territories" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Бригада</label>
            <select v-model="editForm.brigade_id" class="field-input w-full">
              <option :value="null">— не указана —</option>
              <option v-for="b in brigades" :key="b.id" :value="b.id">{{ b.name }}</option>
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
            <label class="block text-xs text-gray-500 mb-1">Примечания</label>
            <textarea v-model="closeForm.notes" class="field-input w-full" rows="3"></textarea>
          </div>
          <div>
            <div v-if="closeForm.materials.length" class="mb-3">
              <select v-model="closeForm.service" class="field-input w-full">
                <option value="">Тип услуги (для номера акта) *</option>
                <option value="internet">Интернет</option>
                <option value="ctv">КТВ</option>
              </select>
            </div>
            <label class="block text-xs text-gray-500 font-medium mb-2">Использованные материалы</label>
            <div v-for="(row, idx) in closeForm.materials" :key="idx"
                 class="flex gap-2 items-center mb-1.5">
              <select v-model="row.material_id" class="field-input flex-1 text-xs">
                <option :value="null">— выберите материал —</option>
                <option v-for="m in materialsCatalog" :key="m.id" :value="m.id">
                  {{ m.code ? '[' + m.code + '] ' : '' }}{{ m.name }} — {{ m.price }} ₽/{{ m.unit }}
                </option>
              </select>
              <input v-model="row.quantity" type="number" min="0.01" step="0.01"
                     class="field-input w-20 text-xs text-center" placeholder="кол-во" />
              <div class="w-24 text-xs text-gray-500 text-right tabular-nums shrink-0">
                {{ matRowTotal(row) }} ₽
              </div>
              <button @click="removeMaterialRow(idx)" class="text-red-400 hover:text-red-600 px-1 shrink-0">✕</button>
            </div>
            <div v-if="!closeForm.materials.length" class="text-xs text-gray-400 mb-2">Материалы не добавлены</div>
            <!-- Кнопка добавления сразу после списка — новая строка появляется прямо здесь, а не наверху -->
            <div class="flex items-center justify-between">
              <button @click="addMaterialRow" class="text-xs text-blue-600 hover:underline">+ добавить</button>
              <div v-if="closeMaterialsTotal > 0" class="text-sm font-semibold text-gray-700">
                Итого: <span class="text-blue-600">{{ closeMaterialsTotal.toFixed(2) }} ₽</span>
              </div>
            </div>
          </div>
        </div>
        <div v-if="closeErrors" class="text-xs text-red-600 mt-2">{{ closeErrors }}</div>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="modals.close = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitClose" :disabled="submitting"
                  class="px-4 py-2 rounded-xl text-sm font-medium bg-green-600 hover:bg-green-700 text-white transition-colors">
            Выполнено
          </button>
        </div>
      </div>
    </div>

    <!-- Модал: Полная информация -->
    <div v-if="modals.detail" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="modals.detail = false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-3">
            <h3 class="text-base font-semibold text-gray-800">Заявка на подключение</h3>
            <span v-if="detailData" :class="statusClass(detailData.status)"
                  class="px-2 py-0.5 rounded-full text-xs font-medium">
              {{ statusLabel(detailData.status) }}
            </span>
          </div>
          <button @click="modals.detail = false" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <div v-if="detailLoading" class="flex-1 flex items-center justify-center py-12">
          <div class="text-gray-400 text-sm">Загрузка...</div>
        </div>

        <div v-else-if="detailData" class="flex-1 overflow-y-auto p-6 space-y-5">
          <!-- Основные данные -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="text-xs text-gray-400 mb-0.5">Имя клиента</div>
              <div class="text-sm font-medium">{{ detailData.name }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-400 mb-0.5">Телефон</div>
              <div class="text-sm font-mono">{{ detailData.phone }}</div>
            </div>
            <div class="col-span-2">
              <div class="text-xs text-gray-400 mb-0.5">Адрес</div>
              <div class="text-sm">{{ detailData.address_string }}</div>
            </div>
            <div v-if="detailData.territory">
              <div class="text-xs text-gray-400 mb-0.5">Территория</div>
              <div class="text-sm">{{ detailData.territory.name }}</div>
            </div>
            <div v-if="detailData.brigade">
              <div class="text-xs text-gray-400 mb-0.5">Бригада</div>
              <div class="text-sm">{{ detailData.brigade.name }}</div>
            </div>
            <div v-if="detailData.scheduled_at">
              <div class="text-xs text-gray-400 mb-0.5">Дата подключения</div>
              <div class="text-sm">{{ fmtDateTime(detailData.scheduled_at) }}</div>
            </div>
            <div v-if="detailData.description" class="col-span-2">
              <div class="text-xs text-gray-400 mb-0.5">Описание</div>
              <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ detailData.description }}</div>
            </div>
            <div v-if="detailData.notes" class="col-span-2">
              <div class="text-xs text-gray-400 mb-0.5">Примечания</div>
              <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ detailData.notes }}</div>
            </div>
            <div v-if="detailData.act">
              <div class="text-xs text-gray-400 mb-0.5">Акт</div>
              <button @click="router.get(route('acts.show', detailData.act.id))"
                      class="text-sm font-medium text-blue-600 hover:underline">{{ detailData.act.number }} →</button>
            </div>
          </div>

          <!-- Материалы (теперь на акте, не на самой заявке) -->
          <div v-if="detailData.act?.materials && detailData.act.materials.length" class="border-t border-gray-100 pt-4">
            <div class="text-xs font-medium text-gray-500 mb-2">Использованные материалы</div>
            <table class="w-full text-xs">
              <thead>
                <tr class="text-gray-400">
                  <th class="text-left pb-1">Материал</th>
                  <th class="text-right pb-1">Кол-во</th>
                  <th class="text-right pb-1">Цена</th>
                  <th class="text-right pb-1">Сумма</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="m in detailData.act.materials" :key="m.id">
                  <td class="py-1">{{ m.material_name }} <span class="text-gray-400">{{ m.material_unit }}</span></td>
                  <td class="py-1 text-right">{{ m.quantity }}</td>
                  <td class="py-1 text-right text-gray-500">{{ m.price_at_time }}</td>
                  <td class="py-1 text-right font-medium">{{ (m.quantity * m.price_at_time).toFixed(2) }}</td>
                </tr>
              </tbody>
              <tfoot class="border-t border-gray-200">
                <tr>
                  <td colspan="3" class="pt-1.5 text-right text-gray-500">Итого:</td>
                  <td class="pt-1.5 text-right font-bold text-blue-600">
                    {{ detailData.act.materials.reduce((s,m) => s + m.quantity * m.price_at_time, 0).toFixed(2) }} ₽
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- История -->
          <div class="border-t border-gray-100 pt-4">
            <div class="text-xs font-medium text-gray-500 mb-3">История</div>
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
                  <div v-if="log.meta && log.meta.act_number" class="mt-0.5 text-gray-500">
                    Акт: {{ log.meta.act_number }}
                  </div>
                  <div v-if="log.meta && log.meta.scheduled_at" class="mt-0.5 text-gray-500">
                    Дата: {{ fmtDateTime(log.meta.scheduled_at) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="px-6 py-3 border-t border-gray-100 flex justify-end shrink-0">
          <button @click="modals.detail = false" class="btn-outline text-sm">Закрыть</button>
        </div>
      </div>
    </div>


  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  requests:          Object,
  filters:           Object,
  territories:        Array,
  brigades:           { type: Array, default: () => [] },
  selectedTerritory:  { type: Number, default: null },
  pendingByTerritory:  { type: Object, default: () => ({}) },
  totalPending:        { type: Number, default: 0 },
  overdueByTerritory:  { type: Object, default: () => ({}) },
  materialsCatalog:  Array,
})

const totalOverdue = computed(() =>
  Object.values(props.overdueByTerritory ?? {}).reduce((a, b) => a + b, 0)
)

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
const modals      = reactive({ create: false, edit: false, schedule: false, reject: false, close: false, detail: false })
const submitting  = ref(false)
const activeRecord = ref(null)
const closeErrors  = ref('')

const createForm  = reactive({ name: '', phone: '', address_string: '', description: '', territory_id: null, brigade_id: null })
const editForm    = reactive({ name: '', phone: '', address_string: '', description: '', territory_id: null, brigade_id: null })
const editErrors  = ref('')
const createErrors = ref('')
const scheduleForm = reactive({ status: 'scheduled', scheduled_at: '', territory_id: null, notes: '' })
const rejectForm   = reactive({ notes: '' })
const closeForm    = reactive({ service: '', notes: '', materials: [] })

function openCreate() {
  Object.assign(createForm, {
    name: '', phone: '', address_string: '', description: '',
    territory_id: props.selectedTerritory ?? null, brigade_id: null,
  })
  createErrors.value = ''
  modals.create = true
}

function openEdit(r) {
  activeRecord.value = r
  Object.assign(editForm, {
    name:           r.name,
    phone:          r.phone,
    address_string: r.address_string,
    description:    r.description ?? '',
    territory_id:   r.territory_id ?? null,
    brigade_id:     r.brigade_id ?? null,
  })
  editErrors.value = ''
  modals.edit = true
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
  Object.assign(closeForm, { service: '', notes: r.notes ?? '', materials: [] })
  closeErrors.value = ''
  modals.close = true
}

function matRowTotal(row) {
  if (!row.material_id || !row.quantity) return '0.00'
  const m = props.materialsCatalog?.find(m => m.id === row.material_id)
  return m ? (m.price * parseFloat(row.quantity || 0)).toFixed(2) : '0.00'
}

const closeMaterialsTotal = computed(() =>
  closeForm.materials.reduce((sum, row) => {
    if (!row.material_id || !row.quantity) return sum
    const m = props.materialsCatalog?.find(m => m.id === row.material_id)
    return sum + (m ? m.price * parseFloat(row.quantity || 0) : 0)
  }, 0)
)

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
  if (!createForm.brigade_id) {
    createErrors.value = 'Выберите бригаду'
    return
  }
  submitting.value = true
  router.post(route('connection-requests.store'), { ...createForm }, {
    onSuccess: () => { modals.create = false },
    onFinish:  () => { submitting.value = false },
  })
}

function submitEdit() {
  if (!editForm.name || !editForm.phone || !editForm.address_string) {
    editErrors.value = 'Заполните обязательные поля'
    return
  }
  submitting.value = true
  router.put(route('connection-requests.update', activeRecord.value.id), { ...editForm }, {
    onSuccess: () => { modals.edit = false },
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
  if (materials.length > 0 && !closeForm.service) {
    closeErrors.value = 'При использовании материалов обязателен тип услуги (Интернет/КТВ)'
    return
  }
  closeErrors.value = ''
  submitting.value = true
  router.post(route('connection-requests.close', activeRecord.value.id), {
    service: closeForm.service,
    notes:   closeForm.notes,
    materials,
  }, {
    onSuccess: () => { modals.close = false },
    onFinish:  () => { submitting.value = false },
  })
}

function submitMarkCalled(r) {
  router.post(route('connection-requests.mark-called', r.id))
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
function fmtDateTime(val) {
  if (!val) return '—'
  const d = new Date(val)
  return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

// Detail modal
const detailData    = ref(null)
const detailLoading = ref(false)

// Переход сюда со страницы акта (Acts/Show.vue -> "Заявка на подключение") —
// сразу открываем карточку конкретной заявки по ?open=<id>.
onMounted(() => {
  const openId = new URLSearchParams(window.location.search).get('open')
  if (openId) openDetail({ id: openId })
})

async function openDetail(r) {
  detailData.value    = null
  detailLoading.value = true
  modals.detail       = true
  try {
    const res = await fetch(route('connection-requests.detail', r.id), {
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
    created:    'Заявка создана',
    edited:     'Данные изменены',
    scheduled:  'Назначено',
    pending:    'Возвращено в ожидание',
    rejected:   'Отклонено',
    closed:     'Выполнено',
    called_back:'Прозвонили клиенту',
  }[action] ?? action
}

function logDotClass(action) {
  return {
    created:    'bg-green-400',
    edited:     'bg-gray-300',
    scheduled:  'bg-blue-400',
    pending:    'bg-yellow-400',
    rejected:   'bg-red-400',
    closed:     'bg-emerald-500',
    called_back:'bg-amber-400',
  }[action] ?? 'bg-gray-300'
}
</script>
