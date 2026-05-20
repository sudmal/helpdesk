<template>
  <Head title="Р–СѓСЂРЅР°Р» Р·РІРѕРЅРєРѕРІ" />
  <AppLayout title="Р–СѓСЂРЅР°Р» Р·РІРѕРЅРєРѕРІ">

    <!-- Р¤РёР»СЊС‚СЂС‹ -->
    <div class="bg-white rounded-2xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
      <div class="flex-1 min-w-36">
        <label class="block text-xs text-gray-500 mb-1">РўРµР»РµС„РѕРЅ</label>
        <input v-model="f.phone" @keydown.enter="apply"
               class="field-input" placeholder="+7..." />
      </div>
      <div class="flex-1 min-w-48">
        <label class="block text-xs text-gray-500 mb-1">РђРґСЂРµСЃ (РёР· Р±РёР»Р»РёРЅРіР°)</label>
        <input v-model="f.address" @keydown.enter="apply"
               class="field-input" placeholder="Р–РµР»РµР·РЅРѕРґРѕСЂРѕР¶РЅС‹Р№..." />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Р”Р°С‚Р° СЃ</label>
        <input v-model="f.date_from" type="date" class="field-input" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Р”Р°С‚Р° РїРѕ</label>
        <input v-model="f.date_to" type="date" class="field-input" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">РђРґСЂРµСЃ СЃРјР°С‚С‡РµРЅ</label>
        <select v-model="f.matched" class="field-input">
          <option value="">Р’СЃРµ</option>
          <option value="yes">Р”Р°</option>
          <option value="no">РќРµС‚</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button @click="apply" class="btn-primary text-sm">РќР°Р№С‚Рё</button>
        <button @click="reset" class="btn-outline text-sm">РЎР±СЂРѕСЃ</button>
      </div>
    </div>

    <!-- РўР°Р±Р»РёС†Р° -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <span class="text-sm text-gray-500">Р’СЃРµРіРѕ: {{ calls.total }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
            <tr>
              <th class="px-3 py-2 text-left">Р’СЂРµРјСЏ</th>
              <th class="px-3 py-2 text-left">РўРµР»РµС„РѕРЅ</th>
              <th class="px-3 py-2 text-left">РђРґСЂРµСЃ РёР· Р±РёР»Р»РёРЅРіР°</th>
              <th class="px-3 py-2 text-left">РђРґСЂРµСЃ РІ Р±Р°Р·Рµ</th>
              <th class="px-3 py-2 text-left">РљРІ.</th>
              <th class="px-3 py-2 text-left">Р—Р°СЏРІРєРё</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-xs">
            <tr v-for="c in calls.data" :key="c.id" class="hover:bg-gray-50">
              <td class="px-3 py-0.5 whitespace-nowrap text-gray-500">
                {{ formatDate(c.called_at) }}
              </td>
              <td class="px-3 py-0.5 font-mono whitespace-nowrap">
                <a :href="route('tickets.index', { search: c.phone })"
                   class="text-blue-600 hover:underline">{{ c.phone }}</a>
              </td>
              <td class="px-3 py-0.5 text-gray-700">{{ c.address_string ?? 'вЂ”' }}</td>
              <td class="px-3 py-0.5">
                <span v-if="c.address" class="text-green-700">
                  вњ“ {{ c.address.full_address }}
                </span>
                <span v-else class="text-gray-400 text-xs">РЅРµ РЅР°Р№РґРµРЅ</span>
              </td>
              <td class="px-3 py-0.5 text-gray-600">{{ c.apartment ?? 'вЂ”' }}</td>
              <td class="px-3 py-0.5">
                <a v-if="c.address"
                   :href="route('tickets.index', { address_id: c.address.id, apartment: c.apartment })"
                   class="text-xs text-blue-500 hover:underline">Р·Р°СЏРІРєРё в†’</a>
              </td>
            </tr>
            <tr v-if="!calls.data.length">
              <td colspan="5" class="px-4 py-8 text-center text-gray-400">РќРµС‚ Р·Р°РїРёСЃРµР№</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- РџР°РіРёРЅР°С†РёСЏ -->
      <div v-if="calls.last_page > 1"
           class="px-5 py-3 border-t border-gray-100 flex items-center gap-2">
        <button v-for="link in calls.links" :key="link.label"
                :disabled="!link.url || link.active"
                @click="link.url && router.get(link.url, {}, { preserveState: true })"
                v-html="link.label"
                :class="['px-3 py-1 rounded-lg text-sm transition-colors',
                         link.active ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 text-gray-600 disabled:opacity-40 disabled:cursor-default']" />
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  calls:   Object,
  filters: Object,
})

const f = ref({
  phone:     props.filters?.phone     ?? '',
  address:   props.filters?.address   ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to:   props.filters?.date_to   ?? '',
  matched:   props.filters?.matched   ?? '',
})

function apply() {
  router.get(route('calls.index'), f.value, { preserveState: true })
}

function reset() {
  f.value = { phone: '', address: '', date_from: '', date_to: '', matched: '' }
  apply()
}

function formatDate(val) {
  if (!val) return 'вЂ”'
  const d = new Date(val)
  return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
}

let refreshTimer = null
onMounted(() => {
  refreshTimer = setInterval(() => {
    router.reload({ only: ['calls'], preserveState: true })
  }, 10000)
})
onUnmounted(() => clearInterval(refreshTimer))
</script>