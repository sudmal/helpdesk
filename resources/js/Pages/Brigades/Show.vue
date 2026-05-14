<template>
  <Head :title="`Бригада — ${brigade.name}`" />
  <AppLayout :title="`Бригада: ${brigade.name}`">

    <div class="max-w-2xl space-y-4">

      <!-- Общая инфо -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-12 h-12 rounded-xl bg-green-100 text-green-700 flex items-center justify-center text-2xl">👷</div>
          <div>
            <h2 class="text-lg font-semibold text-gray-800">{{ brigade.name }}</h2>
            <p class="text-sm text-gray-500">Бригадир: <span class="font-medium text-gray-700">{{ brigade.foreman?.name ?? '—' }}</span></p>
          </div>
        </div>

        <a :href="route('brigades.schedule.show', brigade.id)"
           class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Расписание
        </a>
      </div>

      <!-- Территории -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Территории</h3>
        <div v-if="brigade.territories?.length" class="flex flex-wrap gap-2">
          <span v-for="t in brigade.territories" :key="t.id"
                class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-50 text-blue-700 border border-blue-100">
            {{ t.name }}
          </span>
        </div>
        <p v-else class="text-sm text-gray-400">Не назначены</p>
      </div>

      <!-- Состав -->
      <div class="bg-white rounded-2xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">
          Состав
          <span class="ml-1.5 text-xs font-normal text-gray-400">({{ brigade.members?.length ?? 0 }} чел.)</span>
        </h3>
        <div v-if="brigade.members?.length" class="divide-y divide-gray-100">
          <div v-for="m in brigade.members" :key="m.id"
               class="flex items-center gap-3 py-2.5">
            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-sm font-semibold text-gray-600">
              {{ m.name.charAt(0) }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ m.name }}</p>
              <p class="text-xs text-gray-400">{{ m.role?.name ?? '—' }}</p>
            </div>
            <span v-if="m.id === brigade.foreman_id"
                  class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">
              бригадир
            </span>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400">Нет участников</p>
      </div>

    </div>

  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

defineProps({
  brigade:   Object,
  canManage: Boolean,
})
</script>
