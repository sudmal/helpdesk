<template>
  <Head title="Настройки" />
  <AppLayout title="Настройки">
    <!-- Табы -->
    <div class="flex gap-1 bg-gray-100 p-1 rounded-xl w-fit mb-6">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
              :class="['px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                       activeTab === tab.key ? 'bg-white shadow text-blue-600' : 'text-gray-600 hover:text-gray-800']">
        {{ tab.label }}
      </button>
    </div>

    <!-- Типы заявок -->
    <div v-if="activeTab === 'types'" class="bg-white rounded-2xl border border-gray-200">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold">Типы заявок</h2>
        <button @click="showTypeModal = true" class="btn-primary text-sm">+ Добавить</button>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-for="t in ticketTypes" :key="t.id"
             class="flex items-center px-6 py-3.5 gap-3 hover:bg-gray-50">
          <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: t.color }"></span>
          <span class="flex-1 text-sm font-medium">{{ t.name }}</span>
          <span :class="['text-xs px-2 py-0.5 rounded-full', t.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
            {{ t.is_active ? 'Активен' : 'Скрыт' }}
          </span>
          <button @click="editType(t)" class="text-gray-400 hover:text-blue-600 transition-colors text-sm">✏️</button>
          <button @click="deleteType(t)" class="text-gray-400 hover:text-red-500 transition-colors text-sm">🗑</button>
        </div>
      </div>
    </div>

    <!-- Статусы -->
    <div v-if="activeTab === 'statuses'" class="bg-white rounded-2xl border border-gray-200">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold">Статусы заявок</h2>
        <button @click="showStatusModal = true" class="btn-primary text-sm">+ Добавить</button>
      </div>
      <div class="divide-y divide-gray-100">
        <div v-for="s in ticketStatuses" :key="s.id"
             class="flex items-center px-6 py-3.5 gap-3 hover:bg-gray-50">
          <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: s.color }"></span>
          <span class="flex-1 text-sm font-medium">{{ s.name }}</span>
          <span class="text-xs text-gray-400 font-mono">{{ s.slug }}</span>
          <span v-if="s.is_final" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Финальный</span>
          <button @click="editStatus(s)" class="text-gray-400 hover:text-blue-600 text-sm">✏️</button>
          <button @click="deleteStatus(s)" class="text-gray-400 hover:text-red-500 text-sm">🗑</button>
        </div>
      </div>
    </div>

    <!-- Модалка типа -->
    <Modal v-if="showTypeModal" :title="editingType ? 'Редактировать тип' : 'Новый тип'" @close="closeTypeModal">
      <form @submit.prevent="submitType" class="space-y-4">
        <div>
          <label class="field-label">Название *</label>
          <input v-model="typeForm.name" required class="field-input" />
        </div>
        <div>
          <label class="field-label">Цвет</label>
          <input v-model="typeForm.color" type="color" class="h-10 w-20 rounded cursor-pointer border border-gray-200" />
        </div>
        <div class="flex items-center gap-2">
          <input v-model="typeForm.is_active" type="checkbox" id="ta" class="rounded" />
          <label for="ta" class="text-sm">Активен</label>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="closeTypeModal" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editingType ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Модалка статуса -->
    <Modal v-if="showStatusModal" :title="editingStatus ? 'Редактировать статус' : 'Новый статус'" @close="closeStatusModal">
      <form @submit.prevent="submitStatus" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="field-label">Название *</label>
            <input v-model="statusForm.name" required class="field-input" />
          </div>
          <div>
            <label class="field-label">Slug * (латиница, _)</label>
            <input v-model="statusForm.slug" required class="field-input" placeholder="in_progress" />
          </div>
        </div>
        <div>
          <label class="field-label">Цвет</label>
          <input v-model="statusForm.color" type="color" class="h-10 w-20 rounded cursor-pointer border border-gray-200" />
        </div>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="statusForm.is_final" type="checkbox" class="rounded" /> Финальный
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="statusForm.requires_comment" type="checkbox" class="rounded" /> Требует комментарий
          </label>
          <label class="flex items-center gap-2 text-sm cursor-pointer">
            <input v-model="statusForm.is_active" type="checkbox" class="rounded" /> Активен
          </label>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="closeStatusModal" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editingStatus ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Modal from '@/Components/UI/Modal.vue'

defineProps({ ticketTypes: Array, ticketStatuses: Array })

const activeTab = ref('types')
const tabs = [
  { key: 'types', label: 'Типы заявок' },
  { key: 'statuses', label: 'Статусы' },
]

// Типы
const showTypeModal = ref(false)
const editingType   = ref(null)
const typeForm = useForm({ name: '', color: '#3b82f6', is_active: true, sort_order: 0 })

function editType(t) { editingType.value = t; Object.assign(typeForm, t); showTypeModal.value = true }
function closeTypeModal() { showTypeModal.value = false; editingType.value = null; typeForm.reset() }
function submitType() {
  if (editingType.value) typeForm.put(route('settings.types.update', editingType.value.id), { onSuccess: closeTypeModal })
  else typeForm.post(route('settings.types.store'), { onSuccess: closeTypeModal })
}
function deleteType(t) {
  if (confirm(`Удалить тип «${t.name}»?`)) router.delete(route('settings.types.destroy', t.id))
}

// Статусы
const showStatusModal = ref(false)
const editingStatus   = ref(null)
const statusForm = useForm({ name: '', slug: '', color: '#3b82f6', is_final: false, requires_comment: false, is_active: true })

function editStatus(s) { editingStatus.value = s; Object.assign(statusForm, s); showStatusModal.value = true }
function closeStatusModal() { showStatusModal.value = false; editingStatus.value = null; statusForm.reset() }
function submitStatus() {
  if (editingStatus.value) statusForm.put(route('settings.statuses.update', editingStatus.value.id), { onSuccess: closeStatusModal })
  else statusForm.post(route('settings.statuses.store'), { onSuccess: closeStatusModal })
}
function deleteStatus(s) {
  if (confirm(`Удалить статус «${s.name}»?`)) router.delete(route('settings.statuses.destroy', s.id))
}
</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30; }
</style>
