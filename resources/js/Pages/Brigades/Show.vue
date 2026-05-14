<template>
  <Head :title="`Бригада — ${brigade.name}`" />
  <AppLayout :title="`Бригада: ${brigade.name}`">

    <div class="max-w-2xl space-y-4">

      <!-- Flash -->
      <div v-if="$page.props.flash?.success"
           class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
        {{ $page.props.flash.success }}
      </div>

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
          <span class="ml-1.5 text-xs font-normal text-gray-400">({{ form.member_ids.length }} чел.)</span>
        </h3>

        <div v-if="form.errors.member_ids"
             class="mb-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ form.errors.member_ids }}
        </div>

        <div class="divide-y divide-gray-100 mb-4">
          <label v-for="t in technicians" :key="t.id"
                 class="flex items-center gap-3 py-2.5"
                 :class="isDisabled(t) ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'">
            <input type="checkbox"
                   :value="t.id"
                   v-model="form.member_ids"
                   :disabled="isDisabled(t)"
                   class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ t.name }}</p>
              <p v-if="t.in_other_brigade" class="text-xs text-gray-400">Состоит в другой бригаде</p>
            </div>
            <span v-if="t.id === brigade.foreman_id"
                  class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">
              бригадир
            </span>
          </label>
        </div>

        <button @click="saveMembers"
                :disabled="form.processing"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-xl transition-colors">
          <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
          </svg>
          Сохранить состав
        </button>
      </div>

    </div>

  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  brigade:     Object,
  canManage:   Boolean,
  technicians: Array,
})

const currentUserId = computed(() => usePage().props.auth?.user?.id)

// Нельзя снять: бригадир или участник другой бригады
function isDisabled(t) {
  return t.id === props.brigade.foreman_id || t.in_other_brigade
}

const form = useForm({
  member_ids: props.brigade.members?.map(m => m.id) ?? [],
})

function saveMembers() {
  form.put(route('brigades.members.update', props.brigade.id))
}
</script>