<template>
  <div class="h-screen flex flex-col" style="background:#121212">
    <div class="shrink-0 px-3 py-3 flex items-center gap-2" style="background:#1D4ED8">
      <button @click="$router.back()" class="text-white w-8 h-8 flex items-center justify-center shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <span class="text-white font-bold text-base">Профиль</span>
    </div>

    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <svg class="w-6 h-6 text-[#3B82F6] animate-spin" viewBox="0 0 24 24" fill="none">
        <path d="M21 12a9 9 0 11-2.64-6.36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
      </svg>
    </div>

    <div v-else class="flex-1 overflow-y-auto p-3 space-y-3">
      <div>
        <label class="text-[#9E9E9E] text-xs">ФИО *</label>
        <input v-model="form.name"
               class="w-full mt-1 bg-[#1E1E1E] text-white text-sm rounded-lg px-3 py-2 border"
               :class="nameError ? 'border-[#EF4444]' : 'border-white/10'" />
        <div v-if="nameError" class="text-[#EF4444] text-xs mt-1">{{ nameError }}</div>
      </div>

      <div>
        <label class="text-[#9E9E9E] text-xs">Телефон</label>
        <input v-model="form.phone" type="tel"
               class="w-full mt-1 bg-[#1E1E1E] text-white text-sm rounded-lg px-3 py-2 border border-white/10" />
      </div>

      <div>
        <label class="text-[#9E9E9E] text-xs">Email</label>
        <input v-model="form.email" type="email"
               class="w-full mt-1 bg-[#1E1E1E] text-white text-sm rounded-lg px-3 py-2 border border-white/10" />
      </div>

      <div>
        <label class="text-[#9E9E9E] text-xs">Telegram Chat ID</label>
        <input v-model="form.telegram_chat_id"
               class="w-full mt-1 bg-[#1E1E1E] text-white text-sm rounded-lg px-3 py-2 border border-white/10" />
        <div class="text-[#777777] text-[11px] mt-1">Узнать Chat ID можно у @userinfobot в Telegram</div>
      </div>

      <div>
        <label class="text-[#9E9E9E] text-xs">MAX ID</label>
        <input v-model="form.max_chat_id"
               class="w-full mt-1 bg-[#1E1E1E] text-white text-sm rounded-lg px-3 py-2 border border-white/10" />
        <div class="text-[#777777] text-[11px] mt-1">Узнать ID можно у бота-определителя ID в MAX</div>
      </div>

      <label class="flex items-center gap-2 text-white text-sm pt-2 cursor-pointer select-none">
        <input type="checkbox" v-model="form.notify_on_days_off" class="w-4 h-4 rounded border-white/20" />
        Получать уведомления в выходные
      </label>

      <div v-if="errorMsg" class="text-[#EF4444] text-[13px]">{{ errorMsg }}</div>

      <button @click="save" :disabled="saving"
              class="w-full h-12 rounded-lg text-white text-sm font-medium disabled:opacity-50"
              style="background:#1D4ED8">
        {{ saving ? 'Сохраняем...' : 'Сохранить' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '../api'
import { auth } from '../store/auth'

const loading  = ref(true)
const saving   = ref(false)
const errorMsg = ref('')
const nameError = ref('')

const form = reactive({
  name: '', phone: '', email: '', telegram_chat_id: '', max_chat_id: '', notify_on_days_off: false,
})

onMounted(async () => {
  try {
    const { data } = await api.get('/profile')
    applyData(data)
  } catch {
    errorMsg.value = 'Ошибка загрузки профиля'
  } finally {
    loading.value = false
  }
})

function applyData(profile) {
  form.name               = profile.name ?? ''
  form.phone              = profile.phone ?? ''
  form.email              = profile.email ?? ''
  form.telegram_chat_id   = profile.telegram_chat_id ?? ''
  form.max_chat_id        = profile.max_chat_id ?? ''
  form.notify_on_days_off = !!profile.notify_on_days_off
}

async function save() {
  const name = form.name.trim()
  if (!name) {
    nameError.value = 'Обязательное поле'
    return
  }
  nameError.value = ''
  errorMsg.value  = ''
  saving.value = true
  try {
    const { data } = await api.put('/profile', {
      name,
      phone: form.phone.trim() || null,
      email: form.email.trim() || null,
      telegram_chat_id: form.telegram_chat_id.trim() || null,
      max_chat_id: form.max_chat_id.trim() || null,
      notify_on_days_off: form.notify_on_days_off,
    })
    applyData(data)
    if (auth.state.user) {
      auth.state.user.name = data.name
      localStorage.setItem('user', JSON.stringify(auth.state.user))
    }
  } catch (e) {
    if (e.response?.status === 422) {
      const errors = e.response.data?.errors
      errorMsg.value = errors ? Object.values(errors)[0]?.[0] : (e.response.data?.message || 'Проверьте заполненные поля')
    } else {
      errorMsg.value = 'Нет соединения'
    }
  } finally {
    saving.value = false
  }
}
</script>
