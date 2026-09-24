<template>
  <div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
      <div class="flex flex-wrap gap-1 bg-gray-100 rounded-xl p-1">
        <button v-for="t in statusTabs" :key="t.id" @click="setStatus(t.id)"
                :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                         status === t.id ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700']">
          {{ t.label }}
          <span v-if="counts[t.id] !== undefined"
                :class="['ml-1 text-xs', t.id === 'due' && counts.due > 0 ? 'text-red-600 font-semibold' : 'text-gray-400']">{{ counts[t.id] }}</span>
        </button>
      </div>
      <span v-if="loading" class="text-xs text-gray-400">Загрузка…</span>
    </div>

    <p class="text-xs text-gray-400">
      <template v-if="status === 'due'">Абоненты, которым пора позвонить: с момента акта прошёл установленный срок, опрос ещё не проведён.</template>
      <template v-else-if="status === 'upcoming'">Опрос запланирован, но срок звонка ещё не наступил.</template>
      <template v-else-if="status === 'completed'">Проведённые опросы.</template>
      <template v-else>Абоненты, которые отказались от опроса.</template>
    </p>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-50 text-[11px] text-gray-400 uppercase tracking-wide">
            <tr>
              <th class="px-2 py-1 text-left whitespace-nowrap">Акт</th>
              <th class="px-2 py-1 text-left whitespace-nowrap">Абонент</th>
              <th class="px-2 py-1 text-left whitespace-nowrap">Адрес</th>
              <th class="px-2 py-1 text-left whitespace-nowrap">Бригада</th>
              <th class="px-2 py-1 text-left whitespace-nowrap">Акт от</th>
              <th class="px-2 py-1 text-left whitespace-nowrap">{{ status === 'completed' || status === 'declined' ? 'Опрос' : 'Позвонить с' }}</th>
              <th class="px-2 py-1 text-left whitespace-nowrap">Попытки</th>
              <th class="px-2 py-1"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in rows.data" :key="r.act_id" class="hover:bg-gray-50">
              <td class="px-2 py-1 whitespace-nowrap font-mono font-medium">
                <Link :href="route('acts.show', r.act_id)" class="text-blue-600 hover:underline">{{ r.act_number }}</Link>
              </td>
              <td class="px-2 py-1 whitespace-nowrap">
                <div class="text-gray-800">{{ r.subscriber_name || '—' }}</div>
                <a v-if="r.phone" :href="`tel:${r.phone}`" class="text-blue-600">{{ r.phone }}</a>
              </td>
              <td class="px-2 py-1 max-w-[260px] truncate" :title="fullAddress(r)">{{ fullAddress(r) }}</td>
              <td class="px-2 py-1 whitespace-nowrap">{{ r.brigade_name || '—' }}</td>
              <td class="px-2 py-1 whitespace-nowrap text-gray-500">{{ fmtDate(r.act_created_at) }}</td>
              <td class="px-2 py-1 whitespace-nowrap text-gray-500">
                <template v-if="status === 'completed'">{{ fmtDate(r.completed_at) }} · <b :class="scoreCls(r.avg_rating)">{{ r.avg_rating ? Number(r.avg_rating).toFixed(1) : '—' }}</b></template>
                <template v-else-if="status === 'declined'">{{ fmtDate(r.last_attempt_at) }}</template>
                <template v-else>{{ fmtDate(r.due_at) }}</template>
              </td>
              <td class="px-2 py-1 whitespace-nowrap text-gray-500">
                <span v-if="r.attempts" :title="r.last_attempt_note || ''">{{ r.attempts }} · {{ fmtDate(r.last_attempt_at) }}</span>
                <span v-else>—</span>
              </td>
              <td class="px-2 py-1 text-right whitespace-nowrap">
                <button @click="open(r)" class="btn-outline text-xs">
                  {{ status === 'due' || status === 'upcoming' ? 'Опросить' : 'Открыть' }}
                </button>
              </td>
            </tr>
            <tr v-if="!rows.data.length">
              <td colspan="8" class="px-4 py-8 text-center text-gray-400">Пусто</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="rows.last_page > 1" class="px-4 py-2 border-t border-gray-100 flex items-center gap-2 text-sm">
        <button :disabled="rows.current_page <= 1" @click="load(rows.current_page - 1)" class="px-3 py-0.5 rounded-lg hover:bg-gray-100 disabled:opacity-40">←</button>
        <span class="text-gray-500">{{ rows.current_page }} / {{ rows.last_page }}</span>
        <button :disabled="rows.current_page >= rows.last_page" @click="load(rows.current_page + 1)" class="px-3 py-0.5 rounded-lg hover:bg-gray-100 disabled:opacity-40">→</button>
      </div>
    </div>

    <!-- Форма опроса -->
    <div v-if="active" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="active = null">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[92vh] overflow-y-auto p-5">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-base font-semibold text-gray-800">Опрос абонента</h3>
          <button @click="active = null" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>
        <SurveyForm :act-id="active.act_id" @saved="onSaved" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import SurveyForm from '@/Components/Acts/SurveyForm.vue'

const statusTabs = [
  { id: 'due',       label: 'К обзвону' },
  { id: 'upcoming',  label: 'Ожидают срока' },
  { id: 'completed', label: 'Проведённые' },
  { id: 'declined',  label: 'Отказ' },
]

const status  = ref('due')
const counts  = reactive({})
const rows    = ref({ data: [], current_page: 1, last_page: 1 })
const loading = ref(false)
const active  = ref(null)

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('ru-RU') : '—'
const scoreCls = (v) => v == null ? 'text-gray-400' : v >= 4.5 ? 'text-green-600' : v >= 3.5 ? 'text-lime-600' : v >= 2.5 ? 'text-yellow-600' : 'text-red-600'
const fullAddress = (r) => [r.address_text, r.apartment ? 'кв. ' + r.apartment : null].filter(Boolean).join(', ') || '—'

async function load(page = 1) {
  loading.value = true
  try {
    const res = await axios.get(route('acts.surveys.queue'), { params: { status: status.value, page } })
    Object.assign(counts, res.data.counts)
    rows.value = res.data.rows
  } finally {
    loading.value = false
  }
}

function setStatus(id) { status.value = id; load() }
function open(r) { active.value = r }
function onSaved() { active.value = null; load(rows.value.current_page) }

onMounted(() => load())
</script>
