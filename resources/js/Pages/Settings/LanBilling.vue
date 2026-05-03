<template>
  <Head title="Настройки LANBilling" />
  <AppLayout title="Интеграция с LANBilling">
    <div class="max-w-xl space-y-5">

      <!-- Форма -->
      <form @submit.prevent="submit" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
        <div class="flex items-center gap-3 mb-2">
          <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg">🔗</div>
          <div>
            <h2 class="font-semibold text-gray-800">LANBilling API</h2>
            <p class="text-xs text-gray-400">Поиск абонентов по телефону и номеру договора</p>
          </div>
        </div>

        <div>
          <label class="field-label">URL API *</label>
          <input v-model="form.url" type="url" required
                 placeholder="http://billing.example.com/api"
                 class="field-input" />
          <p class="text-xs text-gray-400 mt-1">JSON-RPC эндпоинт LANBilling</p>
          <FieldError :error="form.errors.url" />
        </div>

        <div>
          <label class="field-label">Логин *</label>
          <input v-model="form.login" required
                 placeholder="api_user"
                 class="field-input" />
          <FieldError :error="form.errors.login" />
        </div>

        <div>
          <label class="field-label">Пароль</label>
          <input v-model="form.password" type="password"
                 placeholder="Оставьте пустым, чтобы не менять"
                 class="field-input" />
          <FieldError :error="form.errors.password" />
        </div>

        <div class="pt-2 flex items-center gap-3">
          <button :disabled="form.processing" class="btn-primary text-sm">
            {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
          </button>
          <button type="button" @click="testConnection" :disabled="testing"
                  class="btn-outline text-sm">
            {{ testing ? 'Проверка...' : '🔌 Проверить подключение' }}
          </button>
        </div>
      </form>

      <!-- Результат проверки -->
      <div v-if="testResult !== null"
           :class="['rounded-2xl border p-4 text-sm',
                    testResult.ok
                      ? 'bg-green-50 border-green-200 text-green-800'
                      : 'bg-red-50 border-red-200 text-red-800']">
        <p class="font-medium mb-1">{{ testResult.ok ? '✅ Подключение успешно' : '❌ Ошибка подключения' }}</p>
        <p class="text-xs opacity-80">{{ testResult.message }}</p>
      </div>

      <!-- Инструкция -->
      <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-800">
        <h3 class="font-semibold mb-2">📖 Как настроить</h3>
        <ol class="list-decimal list-inside space-y-1 text-xs">
          <li>Убедитесь, что в LANBilling включён JSON-RPC API</li>
          <li>Создайте отдельного пользователя API с правами на чтение абонентов</li>
          <li>Укажите полный URL эндпоинта (обычно <code class="bg-amber-100 px-1 rounded">/api/</code>)</li>
          <li>После сохранения нажмите «Проверить подключение»</li>
          <li>При поиске в заявке используйте телефон (+79...) или номер договора</li>
        </ol>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({ config: Object })

const form = useForm({
  url:      props.config?.url ?? '',
  login:    props.config?.login ?? '',
  password: '',
})

const testing    = ref(false)
const testResult = ref(null)

function submit() {
  form.put(route('settings.lanbilling.update'))
}

async function testConnection() {
  testing.value    = true
  testResult.value = null
  try {
    const { data } = await axios.get(route('lanbilling.lookup'), {
      params: { phone: '70000000000' }
    })
    testResult.value = { ok: true, message: 'API отвечает корректно' }
  } catch (e) {
    const status = e.response?.status
    if (status === 404) {
      // 404 = абонент не найден, но API работает
      testResult.value = { ok: true, message: 'API отвечает (абонент не найден — это нормально для теста)' }
    } else {
      testResult.value = { ok: false, message: e.response?.data?.message ?? e.message }
    }
  } finally {
    testing.value = false
  }
}

const FieldError = {
  props: { error: String },
  template: `<p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>`
}
</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors disabled:opacity-40; }
.btn-outline  { @apply border border-gray-200 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-xl font-medium transition-colors disabled:opacity-40; }
.field-label  { @apply block text-xs text-gray-500 mb-1; }
.field-input  { @apply w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/30 bg-white; }
</style>
