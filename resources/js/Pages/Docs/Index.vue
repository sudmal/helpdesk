<template>
  <Head title="Документация разработчика" />
  <AppLayout title="Документация разработчика">
    <div class="flex gap-4 items-start">
      <!-- Список файлов -->
      <div class="w-64 shrink-0 bg-white border border-gray-200 rounded-xl p-2 space-y-1">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-2 py-1.5">docs/</p>
        <Link v-for="f in files" :key="f.name"
              :href="route('docs.index', { file: f.name })"
              :class="['block px-2.5 py-2 rounded-lg text-sm transition-colors truncate',
                       selected === f.name
                         ? 'bg-blue-50 text-blue-700 font-medium'
                         : 'text-gray-600 hover:bg-gray-50']"
              :title="f.name">
          {{ f.title }}
        </Link>
        <p v-if="!files.length" class="text-sm text-gray-400 px-2 py-1.5">Файлы не найдены</p>
      </div>

      <!-- Содержимое -->
      <div class="flex-1 min-w-0 bg-white border border-gray-200 rounded-xl p-6">
        <div v-if="content" class="prose prose-sm max-w-none prose-headings:scroll-mt-4" v-html="renderedHtml" />
        <p v-else class="text-sm text-gray-400 text-center py-12">
          Выберите файл слева
        </p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { marked } from 'marked'
import AppLayout from '@/Components/Layout/AppLayout.vue'

const props = defineProps({
  files:    { type: Array,  default: () => [] },
  selected: { type: String, default: null },
  content:  { type: String, default: null },
})

// Markdown-файлы ссылаются друг на друга относительными путями
// (напр. [ARCHITECTURE.md](ARCHITECTURE.md)) -- на GitHub/в редакторе это
// открывает соседний файл, здесь же такой href уводил бы в никуда. Переписываем
// голые ссылки вида "Имя.md" (с опциональным #якорем) на маршрут просмотрщика
// ещё в исходном markdown, до рендера -- надёжнее, чем настраивать кастомный
// renderer у marked (API рендерера отличается между мажорными версиями).
const rewrittenContent = computed(() => {
  if (!props.content) return ''
  return props.content.replace(
    /\]\(([A-Za-z0-9_-]+\.md)(#[^)]*)?\)/g,
    (_match, file, anchor) => `](${route('docs.index', { file })}${anchor ?? ''})`
  )
})

// Файлы полностью свои (пишутся только через репозиторий, не пользовательский
// ввод) -- рендерим как есть, без отдельной санитизации HTML.
const renderedHtml = computed(() => rewrittenContent.value ? marked.parse(rewrittenContent.value) : '')
</script>
