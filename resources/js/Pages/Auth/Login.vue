<template>
  <Head title="Вход" />

  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900
              flex items-center justify-center px-4">

    <!-- Карточка -->
    <div class="w-full max-w-md">

      <!-- Логотип -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl
                    bg-blue-500 text-white text-2xl font-bold mb-4 shadow-xl shadow-blue-500/30">
          HD
        </div>
        <h1 class="text-2xl font-bold text-white">HelpDesk</h1>
        <p class="text-blue-300 text-sm mt-1">Система управления заявками</p>
      </div>

      <!-- Форма -->
      <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-8 shadow-2xl">
        <form @submit.prevent="submit" class="space-y-5">

          <!-- Ошибка -->
          <div v-if="form.errors.email"
               class="bg-red-500/20 border border-red-500/30 rounded-xl px-4 py-3
                      text-red-200 text-sm flex items-center gap-2">
            <span>⚠</span>
            {{ form.errors.email }}
          </div>

          <div>
            <label class="block text-sm text-blue-200 mb-1.5">Email</label>
            <input v-model="form.email"
                   type="email"
                   required
                   autofocus
                   autocomplete="username"
                   placeholder="admin@helpdesk.local"
                   class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3
                          text-white placeholder-white/30 text-sm
                          focus:outline-none focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400/50
                          transition-colors" />
          </div>

          <div>
            <label class="block text-sm text-blue-200 mb-1.5">Пароль</label>
            <div class="relative">
              <input v-model="form.password"
                     :type="showPassword ? 'text' : 'password'"
                     required
                     autocomplete="current-password"
                     placeholder="••••••••"
                     class="w-full bg-white/10 border border-white/20 rounded-xl px-4 py-3
                            text-white placeholder-white/30 text-sm pr-12
                            focus:outline-none focus:ring-2 focus:ring-blue-400/50 focus:border-blue-400/50
                            transition-colors" />
              <button type="button"
                      @click="showPassword = !showPassword"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-white/40
                             hover:text-white/80 transition-colors text-lg leading-none">
                {{ showPassword ? '🙈' : '👁' }}
              </button>
            </div>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-blue-200 cursor-pointer">
              <input v-model="form.remember"
                     type="checkbox"
                     class="rounded border-white/20 bg-white/10 text-blue-500" />
              Запомнить меня
            </label>
          </div>

          <button
            :disabled="form.processing"
            class="w-full bg-blue-500 hover:bg-blue-400 disabled:opacity-50
                   text-white font-semibold py-3 rounded-xl transition-all duration-200
                   shadow-lg shadow-blue-500/30 hover:shadow-blue-400/40
                   disabled:cursor-not-allowed text-sm">
            {{ form.processing ? 'Вход...' : 'Войти в систему' }}
          </button>
        </form>
      </div>

      <p class="text-center text-blue-400/60 text-xs mt-6">
        HelpDesk v1.0 · Управление заявками техподдержки
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

const showPassword = ref(false)
const form = useForm({
  email:    '',
  password: '',
  remember: false,
})

function submit() {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>
