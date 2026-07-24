<template>
  <div class="rounded-lg overflow-hidden flex mb-2 shadow-sm bg-[#2A2A2A]" @click="$emit('open')">
    <div class="w-1 shrink-0" :style="{ background: statusColor }"></div>

    <div class="flex-1 p-3 min-w-0">
      <!-- Строка 1: имя + статус + звонок -->
      <div class="flex items-center gap-1.5">
        <span class="text-white font-bold text-[15px] flex-1 truncate">{{ request.name || 'Без имени' }}</span>
        <span class="text-white text-[11px] px-2 py-0.5 rounded shrink-0" :style="{ background: statusColor }">
          {{ statusLabel }}
        </span>
        <a v-if="request.phone" :href="'tel:' + request.phone" @click.stop
           class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full active:bg-white/10">
          <svg class="w-4 h-4 text-[#4ADE80]" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
          </svg>
        </a>
      </div>

      <!-- Строка 1b: бейджи (акт с правками, статус возможности) --
           строго по образцу Android (ConnectionAdapter), см. память проекта. -->
      <div v-if="showActBadge || feasibilityBadge" class="flex items-center gap-1.5 mt-1">
        <span v-if="showActBadge" class="text-black text-[10px] px-1.5 py-0.5 rounded shrink-0" style="background:#FBBF24">
          есть правки акта
        </span>
        <span v-if="feasibilityBadge" class="text-white text-[10px] px-1.5 py-0.5 rounded shrink-0"
              :style="{ background: feasibilityBadge.color }">
          {{ feasibilityBadge.text }}
        </span>
      </div>

      <div class="text-[#E0E0E0] text-sm mt-1 truncate">{{ request.address_string || 'Адрес не указан' }}</div>

      <div class="flex items-center gap-1.5 mt-1">
        <span class="text-[#9E9E9E] text-xs flex-1 truncate">{{ request.territory?.name || '' }}</span>
        <span class="text-[#9E9E9E] text-[12px] shrink-0">{{ createdLabel }}</span>
      </div>

      <div v-if="request.description" class="text-[#F59E0B] text-xs mt-1 truncate">{{ request.description }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  request: { type: Object, required: true },
})
defineEmits(['open'])

// Строго по образцу Android (ConnectionAdapter.statusDisplay), см. память
// проекта, project-connection-feasibility.
const statusLabels = { pending: 'Ожидает', scheduled: 'Запланировано', rejected: 'Отклонено', closed: 'Выполнено' }
const statusColors = { pending: '#F59E0B', scheduled: '#3B82F6', rejected: '#EF4444', closed: '#10B981' }

const statusLabel = computed(() => statusLabels[props.request.status] || props.request.status)
const statusColor = computed(() => statusColors[props.request.status] || '#6B7280')

const showActBadge = computed(() => !!props.request.act?.materials_changed_at)

// Отклонённые и "невозможные" заявки в приложении вообще не показываются
// (см. Connections.vue) -- бейдж тут нужен только для двух состояний pending.
const feasibilityBadge = computed(() => {
  if (props.request.status !== 'pending') return null
  if (props.request.feasibility === 'possible') return { text: 'Возможно · на согласовании', color: '#10B981' }
  if (!props.request.feasibility) return { text: 'нужен ответ монтажника', color: '#8B5CF6' }
  return null
})

const createdLabel = computed(() => {
  if (!props.request.created_at) return ''
  const d = new Date(props.request.created_at)
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: '2-digit', year: '2-digit' })
})
</script>
