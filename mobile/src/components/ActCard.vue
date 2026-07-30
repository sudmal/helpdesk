<template>
  <div class="rounded-lg overflow-hidden flex mb-2 shadow-sm bg-[#2A2A2A]" @click="$emit('open')">
    <div class="w-1 shrink-0" :style="{ background: statusColor }"></div>

    <div class="flex-1 p-3 min-w-0">
      <div class="flex items-center gap-1.5">
        <span class="text-white font-bold text-[15px] flex-1 truncate">{{ act.number }}</span>
        <span class="text-white text-[11px] px-2 py-0.5 rounded shrink-0" :style="{ background: statusColor }">
          {{ statusLabel }}
        </span>
      </div>

      <div v-if="act.can.foreman_review || act.materials_changed_at" class="flex items-center gap-1.5 mt-1">
        <span v-if="act.can.foreman_review" class="text-white text-[10px] px-1.5 py-0.5 rounded shrink-0" style="background:#DC2626">
          требует утверждения
        </span>
        <span v-if="act.materials_changed_at" class="text-black text-[10px] px-1.5 py-0.5 rounded shrink-0" style="background:#FBBF24">
          есть правки
        </span>
      </div>

      <div class="text-[#E0E0E0] text-sm mt-1 truncate">{{ subjectLabel }}</div>
      <div class="text-[#9E9E9E] text-sm truncate">{{ act.address || 'Адрес не указан' }}</div>

      <div class="flex items-center gap-1.5 mt-1">
        <span class="text-[#9E9E9E] text-xs flex-1 truncate">{{ act.creator || '' }}</span>
        <span class="text-[#9E9E9E] text-[12px] shrink-0">{{ createdLabel }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  act: { type: Object, required: true },
})
defineEmits(['open'])

// Тот же набор, что и в ActDetail.vue -- держать в синхроне при правках статусов.
const statusLabels = { pending_foreman: 'Ждёт бригадира', approved: 'Утверждён', processing: 'В обработке', pending_subscriber_dept: 'Ждёт Абонотдел', completed: 'Завершён' }
const statusColors = { pending_foreman: '#CA8A04', approved: '#4F46E5', processing: '#4F46E5', pending_subscriber_dept: '#4F46E5', completed: '#16A34A' }
const typeLabels = { regular: 'Обычный', repair: 'Ремонт/Восстановление' }

const statusLabel = computed(() => statusLabels[props.act.status] || props.act.status)
const statusColor = computed(() => statusColors[props.act.status] || '#6B7280')

const subjectLabel = computed(() => {
  const type = typeLabels[props.act.type] || props.act.type
  const ref = props.act.ticket_number || props.act.connection_request_name || ''
  return [type, ref].filter(Boolean).join(' · ')
})

const createdLabel = computed(() => {
  if (!props.act.created_at) return ''
  const d = new Date(props.act.created_at)
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: '2-digit', year: '2-digit' })
})
</script>
