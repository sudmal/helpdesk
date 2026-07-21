import { reactive, watch } from 'vue'
import api from '../api'

const STORAGE_KEY = 'pending_comments'

function load() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
  } catch {
    return []
  }
}

const state = reactive({ items: load(), flushing: false })

watch(
  () => state.items,
  (val) => localStorage.setItem(STORAGE_KEY, JSON.stringify(val)),
  { deep: true }
)

function add(ticketId, body) {
  const item = {
    id: `local-${Date.now()}-${Math.random().toString(36).slice(2)}`,
    ticketId: String(ticketId),
    body,
    createdAt: new Date().toISOString(),
  }
  state.items.push(item)
  return item
}

function pendingFor(ticketId) {
  return state.items.filter((i) => i.ticketId === String(ticketId))
}

async function flush() {
  if (state.flushing) return
  state.flushing = true
  try {
    // Копия -- очередь может пополниться, пока эта отправка ещё идёт
    for (const item of [...state.items]) {
      try {
        await api.post(`/tickets/${item.ticketId}/comments`, { body: item.body })
        state.items = state.items.filter((i) => i.id !== item.id)
      } catch (e) {
        if (e.response) {
          // Сервер ответил (напр. 422) -- повтор не поможет, выбрасываем из очереди
          state.items = state.items.filter((i) => i.id !== item.id)
        } else {
          // Сети всё ещё нет -- прекращаем попытки до следующего вызова flush()
          break
        }
      }
    }
  } finally {
    state.flushing = false
  }
}

if (typeof window !== 'undefined') {
  window.addEventListener('online', flush)
}

export const commentQueue = { state, add, pendingFor, flush }
