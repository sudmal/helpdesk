<template>
  <Head title="Расходные материалы" />
  <AppLayout title="Расходные материалы">
    <template #actions>
      <button v-if="canManage" @click="openCreate"
              class="inline-flex items-center gap-1 bg-blue-600 hover:bg-blue-700
                     text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
        + Добавить
      </button>
    </template>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50 text-xs text-gray-500 font-medium">
            <th class="text-center px-3 py-2 w-20">Код</th>
            <th class="text-left px-4 py-2">Наименование</th>
            <th class="text-center px-3 py-2 w-20">Ед. изм.</th>
            <th class="text-right px-4 py-2 w-28">Цена, ₽</th>
            <th class="text-center px-3 py-2 w-16">Акт.</th>
            <th class="px-3 py-2 w-28"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="!materials.length">
            <td colspan="6" class="text-center py-8 text-gray-400 text-xs">Справочник пуст</td>
          </tr>
          <tr v-for="m in materials" :key="m.id"
              :class="['transition-colors', m.is_active ? 'hover:bg-gray-50' : 'opacity-40 hover:bg-gray-50']">
            <td class="px-3 py-0.5 text-center font-mono text-xs text-gray-400">{{ m.code || '—' }}</td>
            <td class="px-4 py-0.5 text-gray-800 text-sm">{{ m.name }}</td>
            <td class="px-3 py-0.5 text-gray-500 text-xs text-center">{{ m.unit }}</td>
            <td class="px-4 py-0.5 text-right font-mono tabular-nums text-sm">{{ formatPrice(m.price) }}</td>
            <td class="px-3 py-0.5 text-center">
              <span :class="['inline-block w-2 h-2 rounded-full',
                             m.is_active ? 'bg-green-500' : 'bg-gray-300']" />
            </td>
            <td class="px-3 py-0.5 text-right whitespace-nowrap">
              <button v-if="canManage" @click="openEdit(m)"
                      class="text-xs text-blue-600 hover:text-blue-800 mr-3 transition-colors">
                Изменить
              </button>
              <button v-if="canManage" @click="deactivate(m)"
                      class="text-xs text-gray-300 hover:text-red-500 transition-colors">
                {{ m.is_active ? 'Откл' : 'Вкл' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Модалка -->
    <Modal v-if="showModal"
           :title="editing ? 'Редактировать материал' : 'Новый материал'"
           @close="showModal = false">
      <form @submit.prevent="submit" class="space-y-4">

        <!-- Код -->
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Код (артикул)</label>
          <input v-model="form.code"
                 placeholder="001"
                 maxlength="50"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono
                        focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400" />
        </div>

        <!-- Наименование -->
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Наименование *</label>
          <input v-model="form.name" required
                 placeholder="Витая пара AI-Cu 4×2 внутренний"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm
                        focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400" />
        </div>

        <!-- Единица + Цена -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Единица измерения *</label>
            <select v-model="form.unit"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white
                           focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
              <option value="шт">шт (штука)</option>
              <option value="м">м (метр)</option>
              <option value="кг">кг (килограмм)</option>
              <option value="л">л (литр)</option>
              <option value="компл">компл</option>
              <option value="вызов">вызов</option>
              <option value="услуга">услуга</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Цена, ₽ *</label>
            <div class="relative">
              <input v-model.number="form.price" type="number" step="0.01" min="0" required
                     placeholder="0.00"
                     class="w-full border border-gray-200 rounded-xl pl-3 pr-8 py-2 text-sm
                            focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400" />
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 text-sm select-none">₽</span>
            </div>
          </div>
        </div>

        <!-- Активен -->
        <label class="flex items-center gap-2.5 cursor-pointer select-none">
          <input type="checkbox" v-model="form.is_active"
                 class="w-4 h-4 rounded text-blue-600 cursor-pointer" />
          <span class="text-sm text-gray-600">Активен <span class="text-gray-400">(отображается при выборе)</span></span>
        </label>

        <!-- Кнопки -->
        <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
          <button type="button" @click="showModal = false"
                  class="px-4 py-2 text-sm border border-gray-200 rounded-xl
                         hover:bg-gray-50 text-gray-600 transition-colors">
            Отмена
          </button>
          <button type="submit"
                  class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700
                         text-white rounded-xl font-medium transition-colors">
            Сохранить
          </button>
        </div>

      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Modal from '@/Components/UI/Modal.vue'

const props = defineProps({ materials: Array, canManage: Boolean })

const showModal = ref(false)
const editing   = ref(null)
const form      = ref({ code: '', name: '', unit: 'шт', price: 0, is_active: true })

function openCreate() {
  editing.value = null
  form.value = { code: '', name: '', unit: 'шт', price: 0, is_active: true }
  showModal.value = true
}

function openEdit(m) {
  editing.value = m
  form.value = { code: m.code || '', name: m.name, unit: m.unit, price: m.price, is_active: m.is_active }
  showModal.value = true
}

function submit() {
  if (editing.value) {
    router.put(route('materials.update', editing.value.id), form.value, {
      onSuccess: () => { showModal.value = false }
    })
  } else {
    router.post(route('materials.store'), form.value, {
      onSuccess: () => { showModal.value = false }
    })
  }
}

function deactivate(m) {
  router.put(route('materials.update', m.id), {
    ...m, is_active: !m.is_active
  })
}

function formatPrice(p) {
  return Number(p).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>