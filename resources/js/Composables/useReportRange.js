import { reactive } from 'vue'
import axios from 'axios'

function toIso(d) { return d.toISOString().split('T')[0] }

function getMondayOfWeek() {
  const d = new Date()
  const day = d.getDay()
  d.setDate(d.getDate() - (day === 0 ? 6 : day - 1))
  return toIso(d)
}

function monthStart() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`
}

function quarterStart() {
  const d = new Date()
  const qStartMonth = Math.floor(d.getMonth() / 3) * 3
  return `${d.getFullYear()}-${String(qStartMonth + 1).padStart(2, '0')}-01`
}

// Независимое состояние диапазона дат + подгрузка данных для одной вкладки отчёта.
// getExtraParams — опциональная функция, возвращающая доп. query-параметры (например { dimension }),
// подмешиваемые в каждый запрос помимо from/to.
export function useReportRange(routeName, defaultData, getExtraParams = () => ({})) {
  const today = toIso(new Date())

  const state = reactive({
    periodMode: 'day', // 'day' | 'week' | 'month' | 'period'
    singleDay: today,
    localFrom: today,
    localTo: today,
    data: defaultData,
    loading: false,
    loaded: false,
  })

  function rangeFor(mode) {
    const now = toIso(new Date())
    if (mode === 'day')     return { from: state.singleDay, to: state.singleDay }
    if (mode === 'week')    return { from: getMondayOfWeek(), to: now }
    if (mode === 'month')   return { from: monthStart(), to: now }
    if (mode === 'quarter') return { from: quarterStart(), to: now }
    return { from: state.localFrom, to: state.localTo }
  }

  async function fetchData(from, to) {
    state.loading = true
    try {
      const res = await axios.get(route(routeName), { params: { from, to, ...getExtraParams() } })
      state.data = res.data
    } finally {
      state.loading = false
      state.loaded = true
    }
  }

  function setMode(m) {
    state.periodMode = m
    if (m === 'period') return // просто показываем пикеры, ждём "Применить"
    const r = rangeFor(m)
    fetchData(r.from, r.to)
  }

  function applyDay()    { fetchData(state.singleDay, state.singleDay) }
  function applyPeriod() { fetchData(state.localFrom, state.localTo) }

  function ensureLoaded() {
    if (state.loaded) return
    const r = rangeFor(state.periodMode)
    fetchData(r.from, r.to)
  }

  // Перезапросить данные с текущим диапазоном (например, после смены доп. параметра типа dimension)
  function refresh() {
    const r = rangeFor(state.periodMode)
    fetchData(r.from, r.to)
  }

  // Текущий разрешённый диапазон { from, to } независимо от periodMode — удобно для экспорта/ссылок
  function currentRange() {
    return rangeFor(state.periodMode)
  }

  return { state, setMode, applyDay, applyPeriod, ensureLoaded, refresh, currentRange }
}
