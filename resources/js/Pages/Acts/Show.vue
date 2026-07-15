<template>
  <Head :title="`Акт ${act.number}`" />
  <AppLayout :title="`Акт ${act.number}`">

    <div class="max-w-3xl mx-auto space-y-4">

      <!-- Шапка -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="text-lg font-semibold text-gray-800 font-mono">{{ act.number }}</h2>
            <button class="text-sm text-blue-600 hover:underline mt-0.5"
                    @click="router.get(route('tickets.show', act.ticket.id))">
              Заявка #{{ act.ticket.number }}
            </button>
          </div>
          <div class="flex gap-2">
            <span class="px-2 py-1 rounded-lg bg-indigo-100 text-indigo-700 text-xs font-medium">{{ typeLabel(act.type) }}</span>
            <span :class="statusClass(act.status)" class="px-2 py-1 rounded-lg text-xs font-medium">{{ statusLabels[act.status] || act.status }}</span>
          </div>
        </div>
      </div>

      <!-- Прогресс по этапам -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="font-medium text-sm text-gray-700 mb-3">Прогресс согласования</h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="rounded-xl border border-gray-100 p-3">
            <p class="text-xs text-gray-400 mb-1">Бригадир</p>
            <p class="text-sm font-medium" :class="act.foreman_reviewed_at ? 'text-green-600' : 'text-gray-400'">
              {{ act.foreman_reviewed_at ? (act.status === 'returned' ? 'Возвращён' : 'Утверждён') : 'Ожидает' }}
            </p>
            <p v-if="act.foreman_reviewer" class="text-[11px] text-gray-400 mt-0.5">{{ act.foreman_reviewer.name }}</p>
          </div>
          <div class="rounded-xl border border-gray-100 p-3" :class="act.type !== 'regular' ? 'opacity-40' : ''">
            <p class="text-xs text-gray-400 mb-1">ПЭО</p>
            <p class="text-sm font-medium" :class="act.peo_processed_at ? 'text-green-600' : 'text-gray-400'">
              {{ act.type !== 'regular' ? 'Не требуется' : (act.peo_processed_at ? 'Проведён' : 'Ожидает') }}
            </p>
            <p v-if="act.peo_processor" class="text-[11px] text-gray-400 mt-0.5">{{ act.peo_processor.name }}</p>
          </div>
          <div class="rounded-xl border border-gray-100 p-3">
            <p class="text-xs text-gray-400 mb-1">Логистика</p>
            <p class="text-sm font-medium" :class="act.logistics_processed_at ? 'text-green-600' : 'text-gray-400'">
              {{ act.logistics_processed_at ? 'Проведён' : 'Ожидает' }}
            </p>
            <p v-if="act.logistics_processor" class="text-[11px] text-gray-400 mt-0.5">{{ act.logistics_processor.name }}</p>
          </div>
          <div class="rounded-xl border border-gray-100 p-3">
            <p class="text-xs text-gray-400 mb-1">Абонотдел</p>
            <p class="text-sm font-medium" :class="act.subscriber_dept_completed_at ? 'text-green-600' : 'text-gray-400'">
              {{ act.subscriber_dept_completed_at ? 'Завершён' : 'Ожидает' }}
            </p>
            <p v-if="act.subscriber_dept_completer" class="text-[11px] text-gray-400 mt-0.5">{{ act.subscriber_dept_completer.name }}</p>
          </div>
        </div>
        <div v-if="act.foreman_return_comment" class="mt-3 bg-red-50 rounded-lg px-3 py-2 text-xs text-red-700">
          Комментарий бригадира: {{ act.foreman_return_comment }}
        </div>
      </div>

      <!-- Действия -->
      <div v-if="hasAnyAction" class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="font-medium text-sm text-gray-700 mb-3">Действия</h3>
        <div class="flex flex-wrap gap-2">
          <template v-if="can.foremanReview">
            <button @click="approve" class="btn-primary text-sm">Утвердить</button>
            <button @click="showReturnModal = true" class="btn-outline text-sm">Вернуть на доработку</button>
          </template>
          <button v-if="can.processPeo" @click="post('acts.process-peo')" class="btn-primary text-sm">Отметить проведённым (ПЭО)</button>
          <button v-if="can.processLogistics" @click="post('acts.process-logistics')" class="btn-primary text-sm">Отметить проведённым (Логистика)</button>
          <button v-if="can.complete" @click="post('acts.complete')" class="btn-primary text-sm">Завершить акт</button>
        </div>
      </div>

      <!-- Материалы -->
      <div v-if="act.materials?.length" class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
          <h3 class="font-medium text-sm text-gray-700">📦 Расходные материалы</h3>
        </div>
        <table class="w-full text-xs">
          <thead class="bg-gray-50 text-[11px] text-gray-400 uppercase tracking-wide">
            <tr>
              <th class="px-3 py-2 text-left">Код</th>
              <th class="px-4 py-2 text-left">Материал</th>
              <th class="px-3 py-2 text-right">Кол-во</th>
              <th class="px-3 py-2 text-right">Цена</th>
              <th class="px-3 py-2 text-right">Сумма</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="m in act.materials" :key="m.id">
              <td class="px-3 py-2 text-center text-gray-400 font-mono text-xs">{{ m.material_code || '—' }}</td>
              <td class="px-4 py-2 text-gray-800">{{ m.material_name }}</td>
              <td class="px-3 py-2 text-right">{{ m.quantity }} {{ m.material_unit }}</td>
              <td class="px-3 py-2 text-right">{{ m.price_at_time }} ₽</td>
              <td class="px-3 py-2 text-right font-medium">{{ (m.price_at_time * m.quantity).toFixed(2) }} ₽</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- История -->
      <div v-if="act.history?.length" class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="font-medium text-sm text-gray-700 mb-3">История</h3>
        <div class="space-y-2">
          <div v-for="h in act.history" :key="h.id" class="flex items-start gap-2 text-xs">
            <span class="text-gray-400 whitespace-nowrap">{{ fmtDateTime(h.created_at) }}</span>
            <span class="text-gray-700">{{ actionLabel(h.action) }}</span>
            <span v-if="h.user" class="text-gray-400">— {{ h.user.name }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Модалка возврата -->
    <div v-if="showReturnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-semibold mb-4">Вернуть акт на доработку</h3>
        <label class="block text-xs text-gray-500 mb-1">Комментарий <span class="text-red-400">*</span></label>
        <textarea v-model="returnComment" rows="3" class="field-input w-full"
                  placeholder="Что нужно исправить"></textarea>
        <div class="flex justify-end gap-2 mt-4">
          <button @click="showReturnModal = false" class="btn-outline text-sm">Отмена</button>
          <button @click="submitReturn" :disabled="!returnComment.trim()"
                  class="btn-primary text-sm disabled:opacity-40">Вернуть</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  act: Object,
  can: Object,
})

const statusLabels = {
  pending_foreman:          'Ждёт бригадира',
  returned:                 'Возвращён',
  approved:                 'Утверждён',
  processing:               'В обработке',
  pending_subscriber_dept:  'Ждёт Абонотдел',
  completed:                'Завершён',
}

const hasAnyAction = computed(() =>
  props.can.foremanReview || props.can.processPeo || props.can.processLogistics || props.can.complete
)

function typeLabel(type) {
  return type === 'repair' ? 'Ремонт/Восстановление' : type === 'regular' ? 'Обычный' : '—'
}

function statusClass(status) {
  return {
    pending_foreman:         'bg-amber-100 text-amber-700',
    returned:                'bg-red-100 text-red-700',
    approved:                'bg-indigo-100 text-indigo-700',
    processing:              'bg-indigo-100 text-indigo-700',
    pending_subscriber_dept: 'bg-indigo-100 text-indigo-700',
    completed:                'bg-green-100 text-green-700',
  }[status] || 'bg-gray-100 text-gray-600'
}

function actionLabel(action) {
  return {
    created:              'Акт создан',
    approved:             'Утверждён бригадиром',
    returned:             'Возвращён на доработку',
    peo_processed:        'Проведён ПЭО',
    logistics_processed:  'Проведён Логистикой',
    completed:            'Завершён Абонотделом',
  }[action] || action
}

function fmtDateTime(d) {
  return d ? new Date(d).toLocaleString('ru-RU') : '—'
}

function post(routeName) {
  router.post(route(routeName, props.act.id), {}, { preserveScroll: true })
}

function approve() {
  post('acts.approve')
}

const showReturnModal = ref(false)
const returnComment = ref('')

function submitReturn() {
  router.post(route('acts.return', props.act.id), { comment: returnComment.value }, {
    preserveScroll: true,
    onSuccess: () => { showReturnModal.value = false; returnComment.value = '' },
  })
}
</script>
