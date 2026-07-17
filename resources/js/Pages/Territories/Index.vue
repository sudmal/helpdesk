<template>
  <Head title="Территории" />
  <AppLayout title="Территории">
    <template #actions>
      <button @click="showModal = true" class="btn-primary text-sm">+ Добавить</button>
    </template>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50 text-xs text-gray-500 font-medium">
            <th class="text-left px-4 py-2">Название</th>
            <th class="text-left px-4 py-2">Описание</th>
            <th class="text-left px-4 py-2">Бригады</th>
            <th class="px-3 py-2 w-20"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="!territories.length">
            <td colspan="4" class="text-center py-8 text-gray-400 text-xs">Территории не добавлены</td>
          </tr>
          <tr v-for="t in territories" :key="t.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-2 font-medium text-gray-800">{{ t.name }}</td>
            <td class="px-4 py-2 text-gray-500 text-xs">{{ t.description || '—' }}</td>
            <td class="px-4 py-2 text-gray-500 text-xs">
              {{ t.brigades?.length ? t.brigades.map(b => b.name).join(', ') : '—' }}
            </td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <button @click="edit(t)" class="text-xs text-blue-600 hover:text-blue-800 mr-3 transition-colors">Изменить</button>
              <button @click="del(t)" class="text-xs text-gray-400 hover:text-red-500 transition-colors">Удалить</button>
            </td>
          </tr>
        </tbody>
      </table>
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