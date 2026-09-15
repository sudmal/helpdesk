<template>
  <Teleport to="body">
    <div v-if="show && data"
         :style="{ position: 'fixed', left: x + 'px', top: y + 'px', zIndex: 9999 }"
         class="bg-gray-900 text-white rounded-xl shadow-2xl p-3 w-72 pointer-events-none text-xs">
      <div class="flex items-center gap-2 mb-1.5">
        <span v-if="data.number" class="font-mono text-gray-400">{{ data.number }}</span>
        <span v-if="data.type"
              class="px-1.5 py-0.5 rounded text-[11px] font-medium"
              :style="{ backgroundColor: data.type.color + '33', color: data.type.color }">
          {{ data.type.name }}
        </span>
      </div>
      <p class="font-semibold text-sm mb-1 leading-tight">{{ data.address }}</p>
      <p v-if="data.subscriberName" class="text-gray-400 mb-1">👤 {{ data.subscriberName }}</p>
      <p v-if="data.description" class="text-gray-300 mb-1.5 leading-snug">{{ data.description }}</p>
      <p v-if="data.lastComment" class="text-amber-300 mb-1.5 leading-snug">💬 {{ data.lastComment }}</p>
      <template v-if="data.status?.is_final">
        <div class="border-t border-gray-700 pt-1.5 mt-1 flex flex-col gap-1">
          <div v-if="data.act?.number" class="flex gap-1.5">
            <span class="text-gray-500">Акт:</span>
            <span class="text-green-400 font-medium">{{ data.act.number }}</span>
          </div>
          <div v-if="data.closeNotes" class="flex gap-1.5">
            <span class="text-gray-500 shrink-0">Итог:</span>
            <span class="text-gray-300">{{ data.closeNotes }}</span>
          </div>
          <div v-if="data.act?.materials?.length" class="flex gap-1.5">
            <span class="text-gray-500 shrink-0">Матер.:</span>
            <span class="text-gray-300">{{ data.act.materials.map(m => m.material_name + (m.quantity > 1 ? ' ×' + m.quantity : '')).join(', ') }}</span>
          </div>
        </div>
      </template>
      <div v-if="data.phone" class="text-gray-400 mt-1 whitespace-nowrap">📞 {{ data.phone }}</div>
    </div>
  </Teleport>
</template>

<script setup>
// Общий тултип при наведении на строку списка (2026-09-15) — та же карточка,
// что уже была в Dashboard/Index.vue, вынесена сюда, чтобы не копировать
// в каждую страницу отдельно. Ожидаемая форма data (все поля опциональны,
// кроме address): { number, type:{name,color}, address, subscriberName,
// description, lastComment, status:{is_final}, act:{number,materials},
// closeNotes, phone }. Строит эту форму сама вызывающая страница под свою
// сущность (заявка/подключение/запрос услуги) — компонент про конкретную
// модель ничего не знает.
defineProps({
  show: { type: Boolean, default: false },
  x:    { type: Number, default: 0 },
  y:    { type: Number, default: 0 },
  data: { type: Object, default: null },
})
</script>
