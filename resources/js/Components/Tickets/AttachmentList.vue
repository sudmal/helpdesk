<template>
  <div :class="['flex flex-wrap gap-2', className]">
    <button v-for="att in attachments" :key="att.id"
            @click="open(att)"
            class="flex items-center gap-1.5 bg-gray-50 border border-gray-200
                   hover:border-blue-300 rounded-lg px-3 py-1.5 text-xs text-gray-600
                   hover:text-blue-600 transition-colors cursor-pointer">
      <span>{{ icon(att) }}</span>
      <span class="max-w-[150px] truncate">{{ att.original_name }}</span>
      <span class="text-gray-400">{{ formatSize(att.size) }}</span>
    </button>
  </div>

  <!-- Модалка для изображений -->
  <Teleport to="body">
    <div v-if="preview"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
         @click.self="preview = null">
      <div class="relative max-w-[90vw] max-h-[90vh] flex flex-col">
        <!-- Заголовок -->
        <div class="flex items-center justify-between bg-gray-900 text-white px-4 py-2 rounded-t-xl">
          <span class="text-sm truncate max-w-[400px]">{{ preview.original_name }}</span>
          <div class="flex items-center gap-3 ml-4">
            <a :href="preview.url" target="_blank" download
               class="text-xs text-gray-400 hover:text-white transition-colors">
              ⬇ Скачать
            </a>
            <button @click="preview = null"
                    class="text-gray-400 hover:text-white transition-colors text-lg leading-none">
              ✕
            </button>
          </div>
        </div>
        <!-- Контент -->
        <div class="bg-black rounded-b-xl overflow-hidden flex items-center justify-center
                    max-h-[80vh]">
          <!-- Изображение -->
          <img v-if="isImage(preview)"
               :src="preview.url"
               :alt="preview.original_name"
               class="max-w-full max-h-[80vh] object-contain" />
          <!-- Видео -->
          <video v-else-if="isVideo(preview)"
                 :src="preview.url"
                 controls autoplay
                 class="max-w-full max-h-[80vh]" />
          <!-- Аудио -->
          <div v-else-if="isAudio(preview)" class="p-8">
            <p class="text-white text-sm mb-4">{{ preview.original_name }}</p>
            <audio :src="preview.url" controls autoplay class="w-full" />
          </div>
          <!-- PDF -->
          <iframe v-else-if="isPdf(preview)"
                  :src="preview.url"
                  class="w-[80vw] h-[80vh]" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({ attachments: Array, class: String })
const className = props.class ?? ''
const preview   = ref(null)

function isImage(a) { return a.mime_type?.startsWith('image/') }
function isVideo(a) { return a.mime_type?.startsWith('video/') }
function isAudio(a) { return a.mime_type?.startsWith('audio/') }
function isPdf(a)   { return a.mime_type?.includes('pdf') }

function open(att) {
  if (isImage(att) || isVideo(att) || isAudio(att) || isPdf(att)) {
    preview.value = att
  } else {
    window.open(att.url, '_blank')
  }
}

function icon(a) {
  if (isImage(a)) return '🖼'
  if (isVideo(a)) return '🎬'
  if (isAudio(a)) return '🎵'
  if (isPdf(a))   return '📄'
  return '📎'
}

function formatSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024)    return bytes + ' B'
  if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
</script>
