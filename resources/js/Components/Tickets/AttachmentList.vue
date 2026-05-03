<template>
  <div :class="['flex flex-wrap gap-2', className]">
    <a v-for="att in attachments" :key="att.id"
       :href="att.url" target="_blank" download
       class="flex items-center gap-1.5 bg-gray-50 border border-gray-200 hover:border-blue-300
              rounded-lg px-3 py-1.5 text-xs text-gray-600 hover:text-blue-600 transition-colors">
      <span>{{ icon(att) }}</span>
      <span class="max-w-[150px] truncate">{{ att.original_name }}</span>
      <span class="text-gray-400">{{ formatSize(att.size) }}</span>
    </a>
  </div>
</template>

<script setup>
const props = defineProps({ attachments: Array, class: String })
const className = props.class ?? ''

function icon(a) {
  if (a.is_image || a.mime_type?.startsWith('image/')) return '🖼'
  if (a.is_video || a.mime_type?.startsWith('video/')) return '🎬'
  if (a.is_audio || a.mime_type?.startsWith('audio/')) return '🎵'
  if (a.mime_type?.includes('pdf')) return '📄'
  return '📎'
}
function formatSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
</script>
