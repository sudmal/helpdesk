<template>
  <Head title="Бригады" />
  <AppLayout title="Бригады">
    <template #actions>
      <button @click="showModal = true" class="btn-primary text-sm">+ Добавить</button>
    </template>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="b in brigades" :key="b.id"
           class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-sm transition-shadow">
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-100 text-green-700 flex items-center justify-center text-lg">👷</div>
            <div>
              <h3 class="font-semibold text-gray-800">{{ b.name }}</h3>
              <p class="text-xs text-gray-400">{{ b.territories?.map(t => t.name).join(', ') || 'Без территории' }}</p>
            </div>
          </div>
          <div class="flex gap-1">
            <button @click="edit(b)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50">✏️</button>
            <button @click="del(b)" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg hover:bg-red-50">🗑</button>
          </div>
        </div>
        <div class="text-sm text-gray-500 space-y-1">
          <p>Бригадир: <span class="font-medium text-gray-700">{{ b.foreman?.name ?? '—' }}</span></p>
          <p>Участников: <span class="font-medium">{{ b.members_count ?? 0 }}</span></p>
        </div>
        <div class="mt-3 pt-3 border-t border-gray-100">
          <a :href="route('brigades.schedule.show', b.id)"
             class="flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Расписание
          </a>
        </div>
      </div>
    </div>

    <Modal v-if="showModal" :title="editing ? 'Редактировать бригаду' : 'Новая бригада'" @close="close">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="field-label">Название *</label>
          <input v-model="form.name" required class="field-input" />
        </div>
        <div>
          <label class="field-label">Бригадир</label>
          <select v-model="form.foreman_id" class="field-input">
            <option value="">— Выбрать —</option>
            <option v-for="u in technicians" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
          <p v-if="editing?.foreman_id && !form.foreman_id"
             class="mt-1 text-xs text-amber-600">
            ⚠ Нельзя убрать бригадира без назначения нового
          </p>
        </div>
        <div>
          <label class="field-label">Территории</label>
          <div class="space-y-1 max-h-32 overflow-y-auto border border-gray-200 rounded-xl p-2">
            <label v-for="t in territories" :key="t.id" class="flex items-center gap-2 text-sm cursor-pointer p-1 hover:bg-gray-50 rounded">
              <input type="checkbox" :value="t.id" v-model="form.territory_ids" class="rounded" />
              {{ t.name }}
            </label>
          </div>
        </div>
        <div>
          <label class="field-label">Участники</label>
          <div class="space-y-1 max-h-32 overflow-y-auto border border-gray-200 rounded-xl p-2">
            <label v-for="u in technicians" :key="u.id" class="flex items-center gap-2 text-sm cursor-pointer p-1 hover:bg-gray-50 rounded">
              <input type="checkbox" :value="u.id" v-model="form.member_ids" class="rounded" />
              {{ u.name }}
            </label>
          </div>
        </div>
        <div v-if="form.errors && Object.keys(form.errors).length"
             class="bg-red-50 border border-red-200 rounded-xl px-3 py-2 text-sm text-red-700">
          <p v-for="(err, field) in form.errors" :key="field">{{ err }}</p>
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

const props = defineProps({ brigades: Array, territories: Array, technicians: Array })
const showModal = ref(false)
const editing   = ref(null)
const form = useForm({ name: '', foreman_id: '', territory_ids: [], member_ids: [] })

function edit(b) {
  editing.value = b
  form.name = b.name; form.foreman_id = b.foreman_id ?? ''
  form.territory_ids = b.territories?.map(t => t.id) ?? []
  form.member_ids    = b.members?.map(m => m.id) ?? []
  showModal.value = true
}
function close() { showModal.value = false; editing.value = null; form.reset() }
function submit() {
  if (editing.value) form.put(route('brigades.update', editing.value.id), { onSuccess: close })
  else form.post(route('brigades.store'), { onSuccess: close })
}
function del(b) { if (confirm(`Удалить бригаду «${b.name}»?`)) router.delete(route('brigades.destroy', b.id)) }
</script>
<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 bg-slate-50; }
</style>
