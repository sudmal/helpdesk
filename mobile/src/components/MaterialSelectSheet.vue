<!-- Заменяет плоский <select> для выбора материала -- строки "[код] название —
     цена₽/ед" сливались в одну нечитаемую строку в нативном <option>, особенно
     на узком экране. Тот же приём, что и в Android (MaterialSelectDialog,
     2026-09-05): название/код/цена визуально разделены. См. API_MOBILE.md. -->
<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex flex-col justify-end bg-black/60" @click.self="$emit('close')">
      <div class="bg-[#1E1E1E] rounded-t-2xl max-h-[75vh] flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b border-white/10 shrink-0">
          <span class="text-white font-semibold text-sm">Выберите материал</span>
          <button @click="$emit('close')" class="text-[#9E9E9E] w-8 h-8 flex items-center justify-center text-xl leading-none">✕</button>
        </div>
        <div class="overflow-y-auto flex-1">
          <button v-for="m in materials" :key="m.id" @click="$emit('select', m)"
                  class="w-full text-left px-4 py-2.5 border-b border-white/5 last:border-0 active:bg-white/5">
            <div class="text-white text-[15px] font-semibold">{{ m.name }}</div>
            <div class="flex items-center gap-2 mt-1">
              <span v-if="m.code" class="text-[#BBBBBB] text-[11px] font-mono bg-white/10 rounded px-1.5 py-0.5">{{ m.code }}</span>
              <span class="flex-1"></span>
              <span class="text-[#4ADE80] text-[13px] shrink-0">{{ m.price }}₽/{{ m.unit }}</span>
            </div>
          </button>
          <div v-if="!materials.length" class="text-[#666] text-sm text-center py-6">Справочник пуст</div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
defineProps({
  open:      { type: Boolean, default: false },
  materials: { type: Array,   default: () => [] },
})
defineEmits(['select', 'close'])
</script>
