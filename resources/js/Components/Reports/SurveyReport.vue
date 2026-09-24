<template>
  <div class="space-y-3">
    <RangePicker :range="range" />

    <p class="text-xs text-gray-400">
      Средние оценки (1–5) по проведённым опросам за период — по бригадам и вопросам. Период — по дате опроса,
      бригада — та, что выполняла заявку. Вопросы, на которые абонент не ответил, в среднее не входят.
    </p>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div v-if="range.state.loading" class="text-center py-10 text-gray-400 text-sm">Загрузка…</div>
      <div v-else-if="!data.brigades.length" class="text-center py-10 text-gray-400 text-sm">За выбранный период проведённых опросов нет</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 text-xs text-gray-500 border-b border-gray-100 font-medium">
              <th class="text-left px-4 py-2.5 whitespace-nowrap">Бригада</th>
              <th class="text-right px-3 py-2.5 whitespace-nowrap">Опросов</th>
              <th class="text-right px-3 py-2.5 whitespace-nowrap">Средняя</th>
              <th v-for="q in data.questions" :key="q.key" class="text-right px-3 py-2.5 min-w-[110px]">{{ q.text }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in data.brigades" :key="b.key" class="border-b border-gray-50 hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-800 whitespace-nowrap">{{ b.name }}</td>
              <td class="px-3 py-2 text-right text-gray-600">{{ b.surveys }}</td>
              <td class="px-3 py-2 text-right font-semibold" :class="cls(b.avg)">{{ fmt(b.avg) }}</td>
              <td v-for="q in data.questions" :key="q.key" class="px-3 py-2 text-right" :class="cls(b.by_question[q.key]?.avg)">
                <template v-if="b.by_question[q.key]">{{ fmt(b.by_question[q.key].avg) }} <span class="text-[10px] text-gray-400">({{ b.by_question[q.key].n }})</span></template>
                <span v-else class="text-gray-300">—</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-50 border-t border-gray-200 font-semibold text-gray-800">
              <td class="px-4 py-2.5">Итого</td>
              <td class="px-3 py-2.5 text-right">{{ data.total.surveys }}</td>
              <td class="px-3 py-2.5 text-right" :class="cls(data.total.avg)">{{ fmt(data.total.avg) }}</td>
              <td v-for="q in data.questions" :key="q.key" class="px-3 py-2.5 text-right" :class="cls(q.avg)">{{ fmt(q.avg) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div v-if="data.comments.length" class="bg-white rounded-2xl border border-gray-200 p-4 space-y-3">
      <h3 class="font-medium text-sm text-gray-700">Комментарии абонентов</h3>
      <div v-for="c in data.comments" :key="c.act_id" class="border-b border-gray-100 pb-2 text-sm">
        <div class="flex flex-wrap items-center gap-x-3 text-xs text-gray-400">
          <Link :href="route('acts.show', c.act_id)" class="text-blue-600 hover:underline font-mono">{{ c.act_number }}</Link>
          <span>{{ c.brigade }}</span>
          <span>{{ new Date(c.completed_at).toLocaleDateString('ru-RU') }}</span>
          <span v-if="c.avg" :class="cls(c.avg)" class="font-semibold">{{ c.avg }}</span>
        </div>
        <div v-if="c.overall_comment" class="text-gray-700 mt-0.5">{{ c.overall_comment }}</div>
        <div v-for="a in c.answers" :key="a.q" class="text-gray-600 mt-0.5">
          <span class="text-gray-400">{{ a.q }}<template v-if="a.rating"> ({{ a.rating }})</template>:</span> {{ a.comment }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import RangePicker from '@/Components/Reports/RangePicker.vue'
import { useReportRange } from '@/Composables/useReportRange'

const range = useReportRange('reports.survey', { brigades: [], questions: [], total: { avg: null, surveys: 0 }, comments: [], period: null })
const data  = computed(() => range.state.data)

const fmt = (v) => v == null ? '—' : Number(v).toFixed(2)
const cls = (v) => v == null ? '' : v >= 4.5 ? 'text-green-600' : v >= 3.5 ? 'text-lime-600' : v >= 2.5 ? 'text-yellow-600' : 'text-red-600'

onMounted(() => {
  range.state.periodMode = 'month'
  range.ensureLoaded()
})
</script>
