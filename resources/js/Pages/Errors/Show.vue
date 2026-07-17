<template>
  <Head :title="content.title" />

  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900
              flex items-center justify-center px-4">

    <div class="w-full max-w-md text-center">

      <div class="inline-flex items-center justify-center w-16 h-16 mb-6 drop-shadow-xl">
        <img src="/logo.png" alt="Logo" class="w-full h-full" />
      </div>

      <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-8 shadow-2xl">
        <div class="text-5xl mb-4">{{ content.icon }}</div>
        <div class="text-xs font-mono text-blue-300/60 mb-2 tracking-widest">ОШИБКА {{ status }}</div>
        <h1 class="text-xl font-semibold text-white mb-3">{{ content.title }}</h1>
        <p class="text-sm text-blue-200/80 leading-relaxed mb-7">{{ content.message }}</p>

        <div class="flex flex-col gap-2.5">
          <button v-if="status === 419" @click="reload"
                  class="w-full bg-blue-500 hover:bg-blue-400 text-white font-semibold py-3
                         rounded-xl transition-all duration-200 shadow-lg shadow-blue-500/30
                         hover:shadow-blue-400/40 text-sm">
            Обновить страницу
          </button>
          <a v-else href="/"
             class="w-full bg-blue-500 hover:bg-blue-400 text-white font-semibold py-3
                    rounded-xl transition-all duration-200 shadow-lg shadow-blue-500/30
                    hover:shadow-blue-400/40 text-sm inline-block">
            На главную
          </a>
          <button @click="goBack"
                  class="w-full bg-white/10 hover:bg-white/15 border border-white/20 text-blue-100
                         font-medium py-3 rounded-xl transition-colors text-sm">
            Назад
          </button>
        </div>
      </div>

      <p class="text-xs text-blue-300/40 mt-6">
        Если ошибка повторяется — сообщите администратору.
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  status: { type: Number, required: true },
})

const texts = {
  403: {
    icon: '🔒',
    title: 'Нет доступа',
    message: 'У вашей учётной записи нет прав для этого действия. Если считаете, что это ошибка — обратитесь к администратору, он сможет выдать нужное право в настройках.',
  },
  404: {
    icon: '🔍',
    title: 'Страница не найдена',
    message: 'Такой страницы не существует или она была удалена. Проверьте ссылку либо вернитесь на главную.',
  },
  419: {
    icon: '⏳',
    title: 'Сессия истекла',
    message: 'Страница была открыта слишком долго, и сессия устарела. Обновите страницу и попробуйте ещё раз — введённые данные, скорее всего, придётся ввести заново.',
  },
  429: {
    icon: '🚦',
    title: 'Слишком много запросов',
    message: 'Превышен лимит запросов за короткое время. Подождите немного и попробуйте снова.',
  },
  500: {
    icon: '⚠️',
    title: 'Ошибка сервера',
    message: 'Что-то пошло не так на нашей стороне. Мы уже знаем о таких ошибках — попробуйте повторить действие через минуту.',
  },
  503: {
    icon: '🛠️',
    title: 'Технические работы',
    message: 'Портал временно недоступен — идут технические работы. Попробуйте зайти чуть позже.',
  },
}

const content = computed(() => texts[props.status] ?? {
  icon: '⚠️',
  title: 'Что-то пошло не так',
  message: 'Произошла непредвиденная ошибка. Попробуйте повторить действие или вернуться на главную.',
})

function reload() { window.location.reload() }
function goBack() { window.history.length > 1 ? window.history.back() : (window.location.href = '/') }
</script>
