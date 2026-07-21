import { reactive, watch } from 'vue'

const STORAGE_KEY = 'mobile_settings'
const defaults = { syncIntervalMinutes: 15, sortOrder: 'time' } // sortOrder: time | address | service

function load() {
  try {
    return { ...defaults, ...JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') }
  } catch {
    return { ...defaults }
  }
}

export const settings = reactive(load())

watch(
  () => ({ ...settings }),
  (val) => localStorage.setItem(STORAGE_KEY, JSON.stringify(val)),
  { deep: true }
)
