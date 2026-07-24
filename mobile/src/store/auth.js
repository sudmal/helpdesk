import { reactive } from 'vue'
import api from '../api'

const IDLE_LIMIT_MS = 8 * 60 * 60 * 1000 // 8 часов бездействия -- как в Android-приложении

const state = reactive({
  token: localStorage.getItem('token') || null,
  user: JSON.parse(localStorage.getItem('user') || 'null'),
})

let idleTimer = null

function resetIdleTimer() {
  if (idleTimer) clearTimeout(idleTimer)
  if (!state.token) return
  idleTimer = setTimeout(() => {
    logout()
    location.href = '/login'
  }, IDLE_LIMIT_MS)
}

if (typeof window !== 'undefined') {
  ;['click', 'touchstart', 'keydown', 'scroll'].forEach((ev) =>
    window.addEventListener(ev, resetIdleTimer, { passive: true })
  )
  resetIdleTimer()
}

async function login(login, password) {
  const { data } = await api.post('/auth/login', { login, password, client: 'pwa' })
  state.token = data.token
  state.user = data.user
  localStorage.setItem('token', data.token)
  localStorage.setItem('user', JSON.stringify(data.user))
  resetIdleTimer()
  return data.user
}

async function logout() {
  try {
    await api.post('/auth/logout')
  } catch {
    // сервер недоступен -- всё равно чистим локальную сессию
  }
  state.token = null
  state.user = null
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  if (idleTimer) clearTimeout(idleTimer)
}

function isAuthenticated() {
  return !!state.token
}

export const auth = { state, login, logout, isAuthenticated }
