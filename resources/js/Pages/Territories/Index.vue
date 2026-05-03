<template>
  <Head title="Территории" />
  <AppLayout title="Территории">
    <template #actions>
      <button @click="showModal = true" class="btn-primary text-sm">+ Добавить</button>
    </template>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="t in territories" :key="t.id"
           class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-sm transition-shadow">
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-2">
            <span class="text-blue-500 text-lg">📍</span>
            <div>
              <h3 class="font-semibold text-gray-800">{{ t.name }}</h3>
              <p class="text-xs text-gray-400">{{ t.description }}</p>
            </div>
          </div>
          <div class="flex gap-1">
            <button @click="edit(t)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50">✏️</button>
            <button @click="del(t)" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50">🗑</button>
          </div>
        </div>
        <div class="text-sm text-gray-500">
          Бригад: {{ t.brigades?.length ?? 0 }}
          <span v-if="t.brigades?.length" class="text-gray-400 text-xs">
            ({{ t.brigades.map(b => b.name).join(', ') }})
          </span>
        </div>
      </div>
    </div>

    <Modal v-if="showModal" :title="editing ? 'Редактировать территорию' : 'Новая территория'" @close="close">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="field-label">Название *</label>
          <input v-model="form.name" required class="field-input" placeholder="Макеевка" />
        </div>
        <div>
          <label class="field-label">Описание</label>
          <textarea v-model="form.description" rows="2" class="field-input resize-none"></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" @click="close" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editing ? 'Сохранить' : 'Создать' }}</button>
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

defineProps({ territories: Array })
const showModal = ref(false)
const editing   = ref(null)
const form = useForm({ name: '', description: '' })

function edit(t) { editing.value = t; form.name = t.name; form.description = t.description ?? ''; showModal.value = true }
function close() { showModal.value = false; editing.value = null; form.reset() }
function submit() {
  if (editing.value) form.put(route('territories.update', editing.value.id), { onSuccess: close })
  else form.post(route('territories.store'), { onSuccess: close })
}
function del(t) { if (confirm(`Удалить «${t.name}»?`)) router.delete(route('territories.destroy', t.id)) }
</script>
<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30; }
</style>
