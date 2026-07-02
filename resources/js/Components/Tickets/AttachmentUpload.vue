<template>
  <div>
    <div @dragover.prevent="dragging = true"
         @dragleave="dragging = false"
         @drop.prevent="handleDrop"
         :class="['border-2 border-dashed rounded-xl px-4 py-3 text-center cursor-pointer transition-colors text-sm',
                  dragging ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300 text-gray-400']"
         @click="$refs.fileInput.click()">
      <Icon name="paperclip" class="w-4 h-4 inline mr-1" />
      {{ label || 'Прикрепить файлы (фото, видео, аудио, документы)' }}
    </div>
    <input ref="fileInput" type="file" multiple class="hidden"
           accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx"
           @change="handleSelect" />

    <!-- Превью прикреплённых файлов -->
    <div v-if="modelValue.length" class="flex flex-wrap gap-2 mt-2">
      <div v-for="(file, i) in modelValue" :key="i"
           class="relative flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5">
        <span class="text-lg">{{ fileIcon(file) }}</span>
        <span class="text-xs text-gray-600 max-w-[120px] truncate">{{ file.name }}</span>
        <button @click="remove(i)" type="button" class="text-gray-400 hover:text-red-500 ml-1">✕</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Icon from '@/Components/UI/Icon.vue'

const props  = defineProps({ modelValue: { type: Array, default: () => [] }, label: String })
const emit   = defineEmits(['update:modelValue'])
const dragging = ref(false)

function handleSelect(e) { addFiles([...e.target.files]) }
function handleDrop(e)   { dragging.value = false; addFiles([...e.dataTransfer.files]) }
function addFiles(files) { emit('update:modelValue', [...props.modelValue, ...files]) }
function remove(i)       { const f = [...props.modelValue]; f.splice(i, 1); emit('update:modelValue', f) }

function fileIcon(file) {
  if (file.type.startsWith('image/')) return '🖼'
  if (file.type.startsWith('video/')) return '🎬'
  if (file.type.startsWith('audio/')) return '🎵'
  if (file.type.includes('pdf'))      return '📄'
  return '📎'
}
</script>
