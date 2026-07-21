<template>
  <div class="rounded-lg overflow-hidden flex mb-2 shadow-sm bg-[#2A2A2A]" @click="$emit('open')">
    <div class="w-1 shrink-0" :style="{ background: request.service_type?.color || '#6B7280' }"></div>

    <div class="flex-1 p-3 min-w-0">
      <div class="flex items-center gap-1.5">
        <span class="text-white font-bold text-[15px] flex-1 truncate">{{ request.name || 'Без имени' }}</span>

        <span v-if="request.needs_callback" class="shrink-0 text-[#FBBF24] animate-bounce" title="Требуется прозвон">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
          </svg>
        </span>

        <a v-if="request.phone" :href="'tel:' + request.phone" @click.stop
           class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full active:bg-white/10">
          <svg class="w-4 h-4 text-[#4ADE80]" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
          </svg>
        </a>
      </div>

      <div class="text-[#E0E0E0] text-sm mt-1 truncate">{{ request.address_string || 'Адрес не указан' }}</div>

      <div class="flex items-center gap-1.5 mt-1.5">
        <span v-if="request.service_type?.name" class="text-[#9E9E9E] text-xs flex-1 truncate">{{ request.service_type.name }}</span>
        <span v-else class="flex-1"></span>
        <span class="text-[#9E9E9E] text-[13px] shrink-0">{{ scheduledLabel }}</span>
        <span v-if="request.act" @click.stop="$emit('open-act', request.act.id)"
              class="text-[11px] px-2 py-0.5 rounded shrink-0"
              :class="request.act.materials_changed_at ? 'text-black' : 'text-white'"
              :style="{ background: request.act.materials_changed_at ? '#FBBF24' : actColor }">
          Акт{{ request.act.materials_changed_at ? ' ⚠' : '' }}
        </span>
        <span class="text-white text-[11px] px-2 py-0.5 rounded shrink-0" :style="{ background: statusColor }">
          {{ statusLabel }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  request: { type: Object, required: true },
})
defineEmits(['open', 'open-act'])

const statusLabels = { pending: 'Ожидает', scheduled: 'Назначено', rejected: 'Отклонено', closed: 'Выполнено' }
const statusColors = { pending: '#CA8A04', scheduled: '#2563EB', rejected: '#DC2626', closed: '#16A34A' }
const actColors = { pending_foreman: '#CA8A04', approved: '#4F46E5', processing: '#4F46E5', pending_subscriber_dept: '#4F46E5', completed: '#16A34A' }

const statusLabel = computed(() => statusLabels[props.request.status] || props.request.status)
const statusColor = computed(() => statusColors[props.request.status] || '#6B7280')
const actColor = computed(() => actColors[props.request.act?.status] || '#6B7280')

const scheduledLabel = computed(() => {
  if (!props.request.scheduled_at) return '—'
  const d = new Date(props.request.scheduled_at)
  return d.toLocaleDateString('ru-RU', { day: 'numeric', month: '2-digit' }) + ' ' +
         d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
})
</script>
