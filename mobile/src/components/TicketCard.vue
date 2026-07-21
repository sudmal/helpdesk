<template>
  <div class="rounded-lg overflow-hidden flex mb-2 shadow-sm" :style="{ background: cardBg }" @click="$emit('open')">
    <div class="w-1 shrink-0" :style="{ background: stripColor }"></div>

    <div class="flex-1 p-3 min-w-0">
      <!-- Строка 1: номер, тип услуги, бейджи, звонок, время -->
      <div class="flex items-center gap-1.5">
        <span class="text-white font-bold text-[15px] flex-1 truncate">{{ ticket.number }}</span>

        <span v-if="ticket.service_type?.name"
              class="text-white text-[11px] px-2 py-0.5 rounded shrink-0"
              :style="{ background: ticket.service_type?.color || '#6B7280' }">
          {{ ticket.service_type.name }}
        </span>

        <span v-if="props.isNew" class="text-white text-[10px] px-1.5 py-0.5 rounded shrink-0" style="background:#F97316">
          добавлена в {{ createdTime }}
        </span>

        <a v-if="phone" :href="'tel:' + phone" @click.stop
           class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full active:bg-white/10">
          <svg class="w-4 h-4 text-[#4ADE80]" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
          </svg>
        </a>

        <span class="text-[#9E9E9E] text-[13px] shrink-0">{{ timeLabel }}</span>
      </div>

      <!-- Строка 2: адрес -->
      <div class="text-[#E0E0E0] text-sm mt-1 truncate">{{ addressLine }}</div>

      <!-- Строка 3: тип + акт + статус -->
      <div class="flex items-center gap-1.5 mt-1.5">
        <span class="text-[#9E9E9E] text-xs flex-1 truncate">{{ ticket.type || '—' }}</span>
        <span v-if="ticket.act" @click.stop="$emit('open-act', ticket.act.id)"
              class="text-[11px] px-2 py-0.5 rounded shrink-0"
              :class="ticket.act.materials_changed_at ? 'text-black' : 'text-white'"
              :style="{ background: ticket.act.materials_changed_at ? '#FBBF24' : actColor }">
          Акт{{ ticket.act.materials_changed_at ? ' ⚠' : '' }}
        </span>
        <span class="text-white text-[11px] px-2 py-0.5 rounded shrink-0"
              :style="{ background: ticket.status?.color || '#6B7280' }">
          {{ ticket.status?.name || '—' }}
        </span>
      </div>

      <!-- Строка 4: превью описания -->
      <div v-if="descPreview" class="text-[#F59E0B] text-xs mt-1 truncate">{{ descPreview }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  ticket: { type: Object, required: true },
  group: { type: String, default: '' }, // overdue | today | tomorrow -- влияет только на формат времени
  isNew: { type: Boolean, default: false }, // заявка есть в списке new_today (создана сегодня)
})
defineEmits(['open', 'open-act'])

const phone = computed(() => props.ticket.phone?.trim() || null)

const addressLine = computed(() => {
  const parts = []
  if (props.ticket.address?.full) parts.push(props.ticket.address.full)
  if (props.ticket.apartment) parts.push(`кв.${props.ticket.apartment}`)
  return parts.join(', ') || 'Адрес не указан'
})

const descPreview = computed(() => {
  const first = (props.ticket.description || '').split('\n').find((l) => l.trim())
  return first?.trim() || ''
})

const createdTime = computed(() => {
  const src = props.ticket.created_at || props.ticket.scheduled_at
  if (!src) return ''
  return new Date(src).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
})

const timeLabel = computed(() => {
  if (!props.ticket.scheduled_at) return '—'
  const d = new Date(props.ticket.scheduled_at)
  if (props.group === 'today' || props.group === 'new_today') {
    return d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  }
  const date = d.toLocaleDateString('ru-RU', { day: 'numeric', month: '2-digit' })
  const time = d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  return `${date} ${time}`
})

const actColors = { pending_foreman: '#CA8A04', approved: '#4F46E5', processing: '#4F46E5', pending_subscriber_dept: '#4F46E5', completed: '#16A34A' }
const actColor = computed(() => actColors[props.ticket.act?.status] || '#6B7280')

function hexToRgb(hex) {
  const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '')
  return m ? { r: parseInt(m[1], 16), g: parseInt(m[2], 16), b: parseInt(m[3], 16) } : null
}

// Фон карточки: тёмная база (28,28,28), подмешано 20% цвета участка --
// точное повторение логики TicketAdapter.kt (Android)
const cardBg = computed(() => {
  const rgb = hexToRgb(props.ticket.service_type?.color)
  if (!rgb) return '#2A2A2A'
  const darkBase = 28
  const r = Math.round(rgb.r * 0.2 + darkBase * 0.8)
  const g = Math.round(rgb.g * 0.2 + darkBase * 0.8)
  const b = Math.round(rgb.b * 0.2 + darkBase * 0.8)
  return `rgb(${r},${g},${b})`
})

const stripColor = computed(() => props.ticket.service_type?.color || '#6B7280')
</script>
