<template>
  <Head title="Пользователи" />
  <AppLayout title="Пользователи и роли">
    <template #actions>
      <button @click="showModal = true" class="btn-primary text-sm">+ Добавить пользователя</button>
    </template>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead><tr class="border-b border-gray-100 bg-gray-50/50">
          <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Пользователь</th>
          <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Роль</th>
          <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Телефон</th>
          <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Telegram</th>
          <th class="text-left px-5 py-3 text-xs text-gray-500 font-medium">Статус</th>
          <th class="px-5 py-3"></th>
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50">
            <td class="px-5 py-3">
              <p class="font-medium">{{ u.name }}</p>
              <p class="text-xs text-gray-400">{{ u.email }}</p>
            </td>
            <td class="px-5 py-3"><span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">{{ u.role?.name }}</span></td>
            <td class="px-5 py-3 text-gray-600">{{ u.phone ?? '—' }}</td>
            <td class="px-5 py-3 text-gray-600 text-xs font-mono">{{ u.telegram_chat_id ?? '—' }}</td>
            <td class="px-5 py-3">
              <span :class="['text-xs px-2 py-0.5 rounded-full', u.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600']">
                {{ u.is_active ? 'Активен' : 'Заблокирован' }}
              </span>
            </td>
            <td class="px-5 py-3 text-right">
              <button @click="edit(u)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg">✏️</button>
              <button @click="del(u)" class="p-1.5 text-gray-400 hover:text-red-500 rounded-lg">🗑</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Modal v-if="showModal" :title="editing ? 'Редактировать пользователя' : 'Новый пользователь'" @close="close">
      <form @submit.prevent="submit" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2"><label class="field-label">Имя *</label><input v-model="uForm.name" required class="field-input" /></div>
          <div><label class="field-label">Email *</label><input v-model="uForm.email" type="email" required class="field-input" /></div>
          <div><label class="field-label">Телефон</label><input v-model="uForm.phone" class="field-input" /></div>
          <div><label class="field-label">Роль *</label>
            <select v-model="uForm.role_id" required class="field-input">
              <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>
          <div><label class="field-label">Telegram Chat ID</label><input v-model="uForm.telegram_chat_id" class="field-input" placeholder="123456789" /></div>
          <div><label class="field-label">{{ editing ? 'Новый пароль' : 'Пароль *' }}</label><input v-model="uForm.password" type="password" :required="!editing" class="field-input" /></div>
          <div><label class="field-label">Повтор пароля</label><input v-model="uForm.password_confirmation" type="password" class="field-input" /></div>
        </div>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" v-model="uForm.notify_email" class="rounded" /> Email уведомления</label>
          <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" v-model="uForm.notify_telegram" class="rounded" /> Telegram уведомления</label>
          <label v-if="editing" class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" v-model="uForm.is_active" class="rounded" /> Активен</label>
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

defineProps({ users: Array, roles: Array })
const showModal = ref(false)
const editing   = ref(null)
const uForm = useForm({ name:'', email:'', phone:'', role_id:'', telegram_chat_id:'',
                        password:'', password_confirmation:'', notify_email:true, notify_telegram:false, is_active:true })

function edit(u) { editing.value = u; Object.assign(uForm, { ...u, password:'', password_confirmation:'' }); showModal.value = true }
function close() { showModal.value = false; editing.value = null; uForm.reset() }
function submit() {
  if (editing.value) uForm.put(route('settings.users.update', editing.value.id), { onSuccess: close })
  else uForm.post(route('settings.users.store'), { onSuccess: close })
}
function del(u) { if (confirm(`Деактивировать пользователя «${u.name}»?`)) router.delete(route('settings.users.destroy', u.id)) }
</script>
<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30; }
</style>
