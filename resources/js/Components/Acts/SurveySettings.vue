<template>
  <div class="space-y-4 max-w-3xl">
    <div v-if="loading && !loaded" class="text-sm text-gray-400 py-6">Загрузка…</div>

    <template v-if="loaded">
      <!-- Вопросы -->
      <section class="bg-white rounded-xl border border-gray-200 p-4">
        <h3 class="font-medium text-sm text-gray-800 mb-1">Вопросы опросного листа</h3>
        <p class="text-xs text-gray-400 mb-3">
          Оператор ставит оценку от 1 до 5 по каждому активному вопросу. Вопрос, по которому уже есть ответы,
          не удаляется, а отключается — история оценок в отчётах сохраняется.
        </p>

        <div v-for="(q, i) in questions" :key="q.id"
             :class="['flex items-center gap-2 py-1.5 border-b border-gray-100', !q.is_active && 'opacity-60']">
          <div class="flex flex-col">
            <button @click="move(i, -1)" :disabled="i === 0" class="text-gray-400 hover:text-gray-700 disabled:opacity-20 text-xs leading-none">▲</button>
            <button @click="move(i, 1)" :disabled="i === questions.length - 1" class="text-gray-400 hover:text-gray-700 disabled:opacity-20 text-xs leading-none">▼</button>
          </div>
          <input v-model="q.text" @change="saveQuestion(q)" class="field-input flex-1 text-sm" />
          <label class="flex items-center gap-1 text-xs text-gray-500 whitespace-nowrap">
            <input type="checkbox" v-model="q.is_active" @change="saveQuestion(q)" /> активен
          </label>
          <span class="text-[11px] text-gray-400 w-16 text-right">{{ q.answers_count }} отв.</span>
          <button @click="remove(q)" class="text-red-500 hover:text-red-700 text-sm" title="Удалить">✕</button>
        </div>
        <div v-if="!questions.length" class="text-sm text-gray-400 py-3">Вопросов пока нет.</div>

        <div class="flex gap-2 mt-3">
          <input v-model="newText" @keydown.enter="add" class="field-input flex-1 text-sm" placeholder="Новый вопрос…" />
          <button @click="add" :disabled="!newText.trim()" class="btn-primary text-sm">Добавить</button>
        </div>
      </section>

      <!-- К каким актам -->
      <section class="bg-white rounded-xl border border-gray-200 p-4">
        <h3 class="font-medium text-sm text-gray-800 mb-1">К каким актам нужен опрос</h3>
        <p class="text-xs text-gray-400 mb-3">По умолчанию — «Подключение» (и обычные заявки этого типа, и заявки на подключение).</p>

        <div class="grid sm:grid-cols-2 gap-x-6 gap-y-1">
          <label v-for="t in ticketTypes" :key="'t' + t.id" class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" v-model="t.selected" /> {{ t.name }}
          </label>
          <label v-for="k in kinds" :key="k.key" class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" v-model="k.selected" /> {{ k.label }}
          </label>
        </div>
        <button @click="saveTargets" class="btn-primary text-sm mt-3">Сохранить</button>
        <span v-if="savedTargets" class="text-xs text-green-600 ml-2">сохранено</span>
      </section>

      <!-- Общее -->
      <section class="bg-white rounded-xl border border-gray-200 p-4">
        <h3 class="font-medium text-sm text-gray-800 mb-3">Сроки</h3>
        <div class="flex flex-wrap items-end gap-4">
          <div>
            <label class="field-label">Звонить через (дней после акта)</label>
            <input v-model.number="delayDays" type="number" min="0" max="90" class="field-input w-28" />
          </div>
          <div>
            <label class="field-label">Опрашивать акты, созданные с</label>
            <input v-model="startDate" type="date" class="field-input" />
          </div>
          <button @click="saveGeneral" class="btn-primary text-sm">Сохранить</button>
          <span v-if="savedGeneral" class="text-xs text-green-600">сохранено</span>
        </div>
        <p class="text-xs text-gray-400 mt-2">Более старые акты в очередь обзвона не попадают.</p>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const loading = ref(false)
const loaded  = ref(false)
const questions   = ref([])
const ticketTypes = ref([])
const kinds       = ref([])
const delayDays   = ref(3)
const startDate   = ref('')
const newText     = ref('')
const savedTargets = ref(false)
const savedGeneral = ref(false)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get(route('acts.surveys.settings'))
    questions.value   = data.questions
    ticketTypes.value = data.ticket_types
    kinds.value       = data.kinds
    delayDays.value   = data.delay_days
    startDate.value   = data.start_date
    loaded.value = true
  } finally {
    loading.value = false
  }
}

async function add() {
  const text = newText.value.trim()
  if (!text) return
  await axios.post(route('acts.surveys.questions.store'), { text })
  newText.value = ''
  await load()
}

async function saveQuestion(q) {
  if (!q.text.trim()) return load()
  await axios.put(route('acts.surveys.questions.update', q.id), { text: q.text, is_active: q.is_active })
}

async function remove(q) {
  const msg = q.answers_count
    ? `По вопросу уже есть ответы (${q.answers_count}) — он будет отключён, а не удалён. Продолжить?`
    : 'Удалить вопрос?'
  if (!confirm(msg)) return
  await axios.delete(route('acts.surveys.questions.destroy', q.id))
  await load()
}

async function move(i, dir) {
  const list = [...questions.value]
  const j = i + dir
  if (j < 0 || j >= list.length) return
  ;[list[i], list[j]] = [list[j], list[i]]
  questions.value = list
  await axios.post(route('acts.surveys.questions.reorder'), { ids: list.map(q => q.id) })
}

function flash(flag) {
  flag.value = true
  setTimeout(() => { flag.value = false }, 2000)
}

async function saveTargets() {
  await axios.put(route('acts.surveys.targets'), {
    ticket_type_ids:  ticketTypes.value.filter(t => t.selected).map(t => t.id),
    connection_kinds: kinds.value.filter(k => k.selected).map(k => k.key),
  })
  flash(savedTargets)
}

async function saveGeneral() {
  await axios.put(route('acts.surveys.general'), { delay_days: delayDays.value, start_date: startDate.value })
  flash(savedGeneral)
}

onMounted(load)
</script>
