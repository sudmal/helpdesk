<template>
  <Head title="Дашборд" />
  <AppLayout title="Дашборд">
    <div class="space-y-6">

      <!-- Приветствие -->
      <p class="text-sm text-gray-500">Добро пожаловать, {{ $page.props.auth.user.name }}</p>

      <!-- Статистика -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard icon="📋" :value="stats.open"           label="Открытых заявок"  bg-class="bg-blue-100" />
        <StatCard icon="⏰" :value="stats.scheduled_today" label="На сегодня"       bg-class="bg-amber-100" />
        <StatCard icon="✅" :value="stats.closed_today"   label="Закрыто сегодня"  bg-class="bg-green-100" />
        <StatCard icon="🚨" :value="stats.urgent"         label="Срочных"          bg-class="bg-red-100" />
      </div>

      <!-- Последние заявки -->
      <div class="bg-white rounded-2xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-semibold text-gray-800">Последние заявки</h2>
          <Link :href="route('tickets.index')"
                class="text-sm text-blue-600 hover:text-blue-700 font-medium">
            Все заявки →
          </Link>
        </div>

        <div class="divide-y divide-gray-100">
          <div v-if="recent.length === 0" class="px-6 py-10 text-center text-sm text-gray-400">
            Заявок пока нет
          </div>

          <Link v-for="ticket in recent" :key="ticket.id"
                :href="route('tickets.show', ticket.id)"
                class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 transition-colors">

            <!-- Номер + адрес -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-0.5">
                <span class="text-sm font-mono text-blue-600 font-medium">{{ ticket.number }}</span>
                <Badge v-if="ticket.type" :color="ticket.type.color" :label="ticket.type.name" />
              </div>
              <p class="text-sm text-gray-600 truncate">
                {{ ticket.address?.full_address ?? ticket.address?.street + ' ' + ticket.address?.building ?? '—' }}
              </p>
            </div>

            <!-- Бригада -->
            <div class="hidden md:block text-sm text-gray-500 min-w-[120px]">
              {{ ticket.brigade?.name ?? '—' }}
            </div>

            <!-- Статус -->
            <Badge v-if="ticket.status" :color="ticket.status.color" :label="ticket.status.name" />

            <!-- Дата -->
            <div class="text-xs text-gray-400 min-w-[110px] text-right">
              {{ formatDate(ticket.created_at) }}
            </div>
          </Link>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import dayjs from 'dayjs'
import AppLayout from '@/Components/Layout/AppLayout.vue'
import Badge from '@/Components/UI/Badge.vue'
import StatCard from '@/Components/UI/StatCard.vue'

defineProps({
  stats:  { type: Object, required: true },
  recent: { type: Array,  required: true },
})

function formatDate(d) {
  return d ? dayjs(d).format('DD.MM.YY HH:mm') : '—'
}
</script>
