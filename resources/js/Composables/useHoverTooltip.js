import { reactive } from 'vue'

// Общая логика тултипа при наведении на строку списка (2026-09-15) — вынесена
// из Dashboard/Index.vue, где этот же код уже был отдельно написан и
// проверен. Показывает EntityTooltip.vue с той же позиционной логикой
// (тултип уходит выше курсора, если снизу не хватает места).
export function useHoverTooltip() {
  const tooltip = reactive({ show: false, x: 0, y: 0, data: null })

  function showTooltip(e, data) {
    const tooltipH = 220
    const spaceBelow = window.innerHeight - e.clientY
    const y = spaceBelow < tooltipH
      ? Math.max(e.clientY - tooltipH - 10, 8)
      : e.clientY - 10
    tooltip.x = Math.min(e.clientX + 16, window.innerWidth - 300)
    tooltip.y = y
    tooltip.data = data
    tooltip.show = true
  }

  function hideTooltip() {
    tooltip.show = false
  }

  return { tooltip, showTooltip, hideTooltip }
}
