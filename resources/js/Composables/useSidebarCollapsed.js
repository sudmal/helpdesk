import { ref, computed, watch } from 'vue'

// Свёрнутое меню -- предпочтение пользователя для десктопа (узкие окна, где
// сайдбар отжирает место у широких таблиц), персистится в localStorage.
// На мобильном сайдбар всегда полной ширины (открывается поверх контента
// бургером) -- isDesktop гасит collapsed независимо от сохранённого значения.
const stored = ref(localStorage.getItem('sidebar-collapsed') === '1')
const isDesktop = ref(window.matchMedia('(min-width: 768px)').matches)

const mql = window.matchMedia('(min-width: 768px)')
mql.addEventListener('change', (e) => { isDesktop.value = e.matches })

watch(stored, (v) => {
  try { localStorage.setItem('sidebar-collapsed', v ? '1' : '0') } catch {}
})

const collapsed = computed(() => stored.value && isDesktop.value)

export function useSidebarCollapsed() {
  function toggle() { stored.value = !stored.value }
  return { collapsed, toggle }
}
