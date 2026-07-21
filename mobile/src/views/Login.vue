<template>
  <div class="min-h-screen flex flex-col justify-center px-8 py-12" style="background:#121212">
    <h1 class="text-white text-3xl font-bold mb-1">HelpDesk</h1>
    <p class="text-[#888888] text-sm mb-8">Система заявок</p>

    <form @submit.prevent="submit" class="space-y-3">
      <div>
        <label class="block text-xs text-[#9E9E9E] mb-1">Логин</label>
        <input v-model="login_" type="text" autocomplete="username" required
               class="w-full bg-[#2A2A2A] text-white placeholder-[#757575] border border-[#888888]
                      rounded-lg px-3 py-3 text-base focus:outline-none focus:border-2 focus:border-[#3B82F6]" />
      </div>
      <div>
        <label class="block text-xs text-[#9E9E9E] mb-1">Пароль</label>
        <div class="relative">
          <input v-model="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required
                 class="w-full bg-[#2A2A2A] text-white placeholder-[#757575] border border-[#888888]
                        rounded-lg px-3 py-3 text-base focus:outline-none focus:border-2 focus:border-[#3B82F6]" />
          <button type="button" @click="showPassword = !showPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9E9E9E] text-sm">
            {{ showPassword ? 'скрыть' : 'показать' }}
          </button>
        </div>
      </div>

      <p v-if="error" class="text-[#CF6679] text-sm pt-1">{{ error }}</p>

      <button type="submit" :disabled="loading"
              class="w-full h-12 rounded-lg text-white font-medium mt-6 disabled:opacity-60"
              style="background:#1565C0">
        {{ loading ? '...' : 'Войти' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth } from '../store/auth'

const router = useRouter()
const login_ = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(login_.value, password.value)
    router.replace({ name: 'dashboard' })
  } catch (e) {
    error.value = e.response?.status === 401
      ? 'Неверный логин или пароль'
      : 'Не удалось подключиться к серверу'
  } finally {
    loading.value = false
  }
}
</script>
