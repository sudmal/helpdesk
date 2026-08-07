<template>
  <Head title="Бригады" />
  <AppLayout title="Бригады" help-tab="admin" help-section="admin-brigades">
    <template #actions>
      <button @click="showModal = true" class="btn-primary text-sm">+ Добавить</button>
    </template>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 bg-gray-50 text-xs text-gray-500 font-medium">
            <th class="text-left px-4 py-2">Название</th>
            <th class="text-left px-4 py-2">Территории</th>
            <th class="text-left px-4 py-2">Бригадир</th>
            <th class="text-center px-3 py-2 w-24">Участников</th>
            <th class="px-3 py-2 w-48"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="!brigades.length">
            <td colspan="5" class="text-center py-8 text-gray-400 text-xs">Бригады не найдены</td>
          </tr>
          <tr v-for="b in brigades" :key="b.id" class="hover:bg-gray-50 transition-colors" :class="!b.is_active ? 'opacity-60' : ''">
            <td class="px-4 py-2 font-medium text-gray-800">
              {{ b.name }}
              <span v-if="!b.is_active" class="ml-2 inline-block px-1.5 py-0.5 rounded text-[11px] font-normal bg-gray-100 text-gray-500 align-middle">Неактивна</span>
            </td>
            <td class="px-4 py-2 text-xs text-gray-400">{{ b.territories?.map(t => t.name).join(', ') || '—' }}</td>
            <td class="px-4 py-2 text-gray-700">{{ b.foreman?.name ?? '—' }}</td>
            <td class="px-3 py-2 text-center text-gray-600">{{ b.members_count ?? 0 }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <a v-if="b.is_active" :href="route('brigades.schedule.show', b.id)"
                 class="text-xs text-blue-600 hover:text-blue-800 font-medium mr-3 transition-colors">
                Расписание
              </a>
              <button @click="edit(b)" class="text-xs text-blue-600 hover:text-blue-800 mr-3 transition-colors">Изменить</button>
              <button @click="toggleActive(b)" class="text-xs text-gray-400 hover:text-amber-600 mr-3 transition-colors">
                {{ b.is_active ? 'Деактивировать' : 'Активировать' }}
              </button>
              <button @click="del(b)" class="text-xs text-gray-400 hover:text-red-500 transition-colors">Удалить</button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <Modal v-if="showModal" size="xl" :title="editing ? 'Редактировать бригаду' : 'Новая бригада'" @close="close">
      <form @submit.prevent="submit" class="space-y-4">

        <!-- Название — на всю ширину -->
        <div>
          <label class="field-label">Название *</label>
          <input v-model="form.name" required class="field-input" />
        </div>

        <!-- Двухколоночный блок -->
        <div class="grid grid-cols-[1fr_210px] gap-5">

          <!-- Левая колонка: участники + бригадир -->
          <div class="space-y-3">
            <div>
              <label class="field-label flex items-center gap-1">
                Участники
                <Tip>Выберите состав бригады. Бригадир назначается из состава. Сотрудники уже в другой бригаде — недоступны.</Tip>
              </label>
              <div class="space-y-1 overflow-y-auto border border-gray-200 rounded-xl p-2 min-h-32 max-h-52">
                <label v-for="u in technicians" :key="u.id"
                       class="flex items-center gap-2 text-sm p-1 rounded"
                       :class="isInOtherBrigade(u) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50'">
                  <input type="checkbox" :value="u.id" v-model="form.member_ids"
                         :disabled="isInOtherBrigade(u)" class="rounded disabled:cursor-not-allowed" />
                  <span class="flex-1">{{ u.name }}</span>
                  <span v-if="isInOtherBrigade(u)" class="text-xs text-gray-400 italic">{{ u.in_brigade_name }}</span>
                </label>
              </div>
            </div>

            <div>
              <label class="field-label flex items-center gap-1">
                Бригадир
                <Tip>Выбирается только из состава бригады и только среди пользователей с ролью «Бригадир». Сначала добавьте участников выше.</Tip>
              </label>
              <select v-model="form.foreman_id" class="field-input" :disabled="form.member_ids.length === 0">
                <option value="">{{ form.member_ids.length ? '— Выбрать —' : '— Сначала добавьте участников —' }}</option>
                <option v-for="u in foremanCandidates" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
              <p v-if="form.member_ids.length > 0 && !foremanCandidates.length"
                 class="mt-1 text-xs text-amber-600">⚠ Среди участников нет пользователя с ролью «Бригадир»</p>
              <p v-if="editing?.foreman_id && !form.foreman_id && form.member_ids.length > 0 && editing?.is_active"
                 class="mt-1 text-xs text-amber-600">⚠ Нельзя убрать бригадира без назначения нового</p>
            </div>
          </div>

          <!-- Правая колонка: территории -->
          <div class="flex flex-col">
            <label class="field-label flex items-center gap-1">
              Территории
              <Tip>Участки обслуживания бригады. Влияют на фильтрацию уведомлений и заявок.</Tip>
            </label>
            <div class="flex-1 overflow-y-auto border border-gray-200 rounded-xl p-2 min-h-48 space-y-1">
              <label v-for="t in territories" :key="t.id"
                     class="flex items-center gap-2 text-sm cursor-pointer p-1 hover:bg-gray-50 rounded">
                <input type="checkbox" :value="t.id" v-model="form.territory_ids" class="rounded" />
                {{ t.name }}
              </label>
            </div>
          </div>
        </div>

        <div v-if="form.errors && Object.keys(form.errors).length"
             class="bg-red-50 border border-red-200 rounded-xl px-3 py-2 text-sm text-red-700">
          <p v-for="(err, field) in form.errors" :key="field">{{ err }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
          <button type="button" @click="close" class="btn-outline text-sm">Отмена</button>
          <button class="btn-primary text-sm">{{ editing ? 'Сохранить' : 'Создать' }}</button>
        </div>
      </form>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Modal from '@/Components/UI/Modal.vue'
import Tip from '@/Components/UI/Tip.vue'

const props = defineProps({ brigades: Array, territories: Array, technicians: Array })
const showModal = ref(false)
const editing   = ref(null)
const form = useForm({ name: '', foreman_id: '', territory_ids: [], member_ids: [] })

const editingId = computed(() => editing.value?.id ?? null)

function isInOtherBrigade(u) {
  return u.in_brigade_id && u.in_brigade_id !== editingId.value
}

const foremanCandidates = computed(() =>
  props.technicians.filter(u => form.member_ids.includes(u.id) && (u.role === 'foreman' || u.role === 'admin'))
)

watch(() => [...form.member_ids], (ids) => {
  if (form.foreman_id && !ids.includes(Number(form.foreman_id)) && !ids.includes(form.foreman_id)) {
    form.foreman_id = ''
  }
})

function edit(b) {
  editing.value = b
  form.name = b.name
  form.foreman_id = b.foreman_id ?? ''
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
function toggleActive(b) {
  const msg = b.is_active
    ? `Деактивировать бригаду «${b.name}»? Она пропадёт из списков назначения новых заявок, но старые заявки сохранят её в истории. Состав можно будет опустошить.`
    : `Активировать бригаду «${b.name}»?`
  if (confirm(msg)) router.patch(route('brigades.toggle-active', b.id))
}
</script>
