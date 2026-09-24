<template>
  <div v-if="show" class="bg-white rounded-xl border border-gray-200 p-3.5">
    <div class="flex items-center justify-between gap-2 flex-wrap">
      <h3 class="font-medium text-sm text-gray-700">Опрос абонента</h3>
      <div class="flex items-center gap-2">
        <span :class="['text-xs px-2 py-0.5 rounded-full', chip.cls]">{{ chip.text }}</span>
        <button v-if="canOpen" @click="expanded = !expanded" class="btn-act-outline text-xs">
          {{ expanded ? 'Свернуть' : buttonLabel }}
        </button>
      </div>
    </div>
    <div v-if="expanded" class="mt-3">
      <SurveyForm :act-id="actId" @saved="refresh" />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import SurveyForm from '@/Components/Acts/SurveyForm.vue'

const props = defineProps({ actId: { type: Number, required: true } })

const meta     = ref(null)
const expanded = ref(false)

const show = computed(() => meta.value && (meta.value.eligible || meta.value.survey))
const s    = computed(() => meta.value?.survey || null)
const canOpen = computed(() => !!meta.value && (meta.value.can_conduct || s.value?.status === 'completed' || s.value?.status === 'declined'))
const buttonLabel = computed(() => s.value?.status === 'completed' ? 'Показать ответы' : (meta.value?.can_conduct ? 'Провести опрос' : 'Подробнее'))

const fmt = (d) => d ? new Date(d).toLocaleDateString('ru-RU') : ''

const chip = computed(() => {
  if (s.value?.status === 'completed') {
    const rated = s.value.answers.filter(a => a.rating)
    const avg = rated.length ? (rated.reduce((x, a) => x + a.rating, 0) / rated.length).toFixed(1) : '—'
    return { text: `Проведён ${fmt(s.value.completed_at)} · средняя ${avg}`, cls: 'bg-green-100 text-green-700' }
  }
  if (s.value?.status === 'declined') return { text: 'Абонент отказался', cls: 'bg-gray-100 text-gray-600' }
  if (s.value?.attempts) return { text: `Не дозвонились: ${s.value.attempts}`, cls: 'bg-amber-100 text-amber-700' }
  const due = meta.value?.info?.due_at
  const overdue = due && new Date(due) <= new Date()
  return { text: overdue ? 'Пора звонить' : `Звонок с ${fmt(due)}`, cls: overdue ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }
})

async function refresh() {
  const { data } = await axios.get(route('acts.surveys.act', props.actId))
  meta.value = data
}

onMounted(refresh)
</script>
