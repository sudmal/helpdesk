<template>
  <div>
    <div v-if="loading" class="text-center py-8 text-sm text-gray-400">Загрузка…</div>
    <div v-else-if="!data || !data.eligible && !data.survey" class="text-sm text-gray-400 py-4">
      Для этого акта опрос не предусмотрен.
    </div>

    <div v-else class="space-y-4">
      <!-- Кому звонить -->
      <div v-if="data.info" class="rounded-xl bg-gray-50 border border-gray-200 px-3.5 py-2.5 text-sm grid sm:grid-cols-2 gap-x-6 gap-y-1">
        <div><span class="text-gray-400">Абонент: </span><span class="font-medium text-gray-800">{{ data.info.subscriber_name || '—' }}</span></div>
        <div>
          <span class="text-gray-400">Телефон: </span>
          <a v-if="data.info.phone" :href="`tel:${data.info.phone}`" class="font-medium text-blue-600">{{ data.info.phone }}</a>
          <span v-else>—</span>
        </div>
        <div class="sm:col-span-2"><span class="text-gray-400">Адрес: </span>{{ address }}</div>
        <div><span class="text-gray-400">Бригада: </span>{{ data.info.brigade_name || '—' }}</div>
        <div><span class="text-gray-400">Акт: </span>{{ data.info.act_number }} от {{ fmtDate(data.info.act_created_at) }}</div>
      </div>

      <!-- Уже проведён: только чтение -->
      <div v-if="s && s.status === 'completed' && !editing" class="space-y-3">
        <div class="text-sm text-gray-500">
          Опрос проведён {{ fmtDateTime(s.completed_at) }}<span v-if="s.completed_by">, {{ s.completed_by }}</span>
        </div>
        <div v-for="a in s.answers" :key="a.question_id + a.question_text" class="border-b border-gray-100 pb-2">
          <div class="text-sm text-gray-800">{{ a.question_text }}</div>
          <div class="mt-0.5 flex items-center gap-2 text-sm">
            <span v-if="a.rating" :class="['inline-flex w-7 h-7 items-center justify-center rounded-lg text-white font-semibold', ratingBg(a.rating)]">{{ a.rating }}</span>
            <span v-else class="text-gray-400">не ответил</span>
            <span v-if="a.comment" class="text-gray-600">— {{ a.comment }}</span>
          </div>
        </div>
        <div v-if="s.overall_comment" class="text-sm"><span class="text-gray-400">Комментарий: </span>{{ s.overall_comment }}</div>
        <button v-if="data.can_conduct" @click="startEdit" class="btn-outline text-sm">Изменить ответы</button>
      </div>

      <div v-else-if="s && s.status === 'declined' && !editing" class="space-y-2 text-sm">
        <div class="text-gray-600">Абонент отказался от опроса<span v-if="s.last_attempt_note"> — {{ s.last_attempt_note }}</span>.</div>
        <button v-if="data.can_conduct" @click="startEdit" class="btn-outline text-sm">Провести опрос</button>
      </div>

      <!-- Форма -->
      <div v-else-if="data.can_conduct" class="space-y-4">
        <div v-if="s && s.attempts" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-1.5">
          Не дозвонились {{ s.attempts }} раз(а), последний {{ fmtDateTime(s.last_attempt_at) }}<span v-if="s.last_attempt_note"> — {{ s.last_attempt_note }}</span>
        </div>

        <div v-for="q in data.questions" :key="q.id" class="border-b border-gray-100 pb-3">
          <div class="text-sm text-gray-800 mb-1.5">{{ q.text }}</div>
          <div class="flex flex-wrap items-center gap-1.5">
            <button v-for="n in 5" :key="n" type="button" @click="setRating(q.id, n)"
                    :class="['w-9 h-9 rounded-lg text-sm font-semibold border transition-colors',
                             form[q.id].rating === n ? ratingBg(n) + ' text-white border-transparent' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50']">
              {{ n }}
            </button>
            <button type="button" @click="form[q.id].showComment = !form[q.id].showComment"
                    class="ml-2 text-xs text-blue-600 hover:text-blue-800">
              {{ form[q.id].comment || form[q.id].showComment ? 'комментарий' : '+ комментарий' }}
            </button>
          </div>
          <textarea v-if="form[q.id].showComment" v-model="form[q.id].comment" rows="2"
                    class="field-input w-full mt-1.5" placeholder="Комментарий абонента (необязательно)"></textarea>
        </div>
        <div class="text-[11px] text-gray-400 -mt-2">1 — очень плохо · 5 — отлично. Если абонент не ответил на вопрос — оставьте без оценки.</div>

        <div>
          <label class="field-label">Общий комментарий</label>
          <textarea v-model="overall" rows="2" class="field-input w-full" placeholder="Необязательно"></textarea>
        </div>

        <div v-if="error" class="text-sm text-red-600">{{ error }}</div>

        <div class="flex flex-wrap items-end gap-2 pt-1">
          <button @click="save" :disabled="submitting" class="btn-primary text-sm">Сохранить опрос</button>
          <div class="flex-1"></div>
          <input v-model="note" class="field-input text-sm w-56" placeholder="Примечание (необязательно)" />
          <button @click="attempt" :disabled="submitting" class="btn-outline text-sm">Не дозвонился</button>
          <button @click="decline" :disabled="submitting" class="btn-outline text-sm">Отказался</button>
        </div>
      </div>

      <div v-else class="text-sm text-gray-500">
        Опрос ещё не проведён<span v-if="data.info">, звонок запланирован на {{ fmtDate(data.info.due_at) }}</span>.
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({ actId: { type: Number, required: true } })
const emit  = defineEmits(['saved'])

const loading    = ref(true)
const data       = ref(null)
const editing    = ref(false)
const submitting = ref(false)
const error      = ref('')
const note       = ref('')
const overall    = ref('')
const form       = reactive({})

const s = computed(() => data.value?.survey || null)

const address = computed(() => {
  const i = data.value?.info
  if (!i) return '—'
  return [i.address_text, i.apartment ? 'кв. ' + i.apartment : null].filter(Boolean).join(', ') || '—'
})

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('ru-RU') : '—'
const fmtDateTime = (d) => d ? new Date(d).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' }) : '—'
const ratingBg = (n) => ({ 1: 'bg-red-500', 2: 'bg-orange-500', 3: 'bg-yellow-500', 4: 'bg-lime-500', 5: 'bg-green-600' }[n])

function initForm(prefill = null) {
  Object.keys(form).forEach(k => delete form[k])
  for (const q of data.value.questions) {
    const a = prefill?.find(x => x.question_id === q.id)
    form[q.id] = { rating: a?.rating ?? null, comment: a?.comment ?? '', showComment: !!a?.comment }
  }
  overall.value = prefill ? (s.value?.overall_comment ?? '') : ''
}

function setRating(qid, n) {
  form[qid].rating = form[qid].rating === n ? null : n
}

function startEdit() {
  initForm(s.value?.status === 'completed' ? s.value.answers : null)
  editing.value = true
}

async function load() {
  loading.value = true
  try {
    const res = await axios.get(route('acts.surveys.act', props.actId))
    data.value = res.data
    if (data.value.questions) initForm()
  } finally {
    loading.value = false
  }
}

async function call(routeName, payload) {
  submitting.value = true
  error.value = ''
  try {
    await axios.post(route(routeName, props.actId), payload)
    editing.value = false
    await load()
    emit('saved')
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = errs ? Object.values(errs)[0][0] : (e.response?.data?.message || 'Не удалось сохранить')
  } finally {
    submitting.value = false
  }
}

const save = () => call('acts.surveys.complete', {
  answers: data.value.questions.map(q => ({ question_id: q.id, rating: form[q.id].rating, comment: form[q.id].comment || null })),
  overall_comment: overall.value || null,
})
const attempt = () => call('acts.surveys.attempt', { note: note.value || null })
const decline = () => call('acts.surveys.decline', { note: note.value || null })

onMounted(load)
</script>
