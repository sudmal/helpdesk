<template>
  <InertiaLink :href="href"
        :class="['flex items-center gap-2 px-2.5 py-1 rounded-md text-[13px] leading-tight font-medium transition-colors cursor-pointer',
                 isActive
                   ? 'bg-blue-600 text-white'
                   : 'text-white/70 hover:bg-white/10 hover:text-white']">
    <Icon :name="icon" class="w-3.5 h-3.5 shrink-0" />
    <span class="truncate">{{ label }}</span>
    <slot />
  </InertiaLink>
</template>

<script setup>
import { computed } from 'vue'
import { Link as InertiaLink, usePage } from '@inertiajs/vue3'
import Icon from '@/Components/UI/Icon.vue'

const props = defineProps({
  href:  { type: String, required: true },
  icon:  { type: String, required: true },
  label: { type: String, required: true },
})

const page = usePage()
const isActive = computed(() => {
  try {
    return page.url.startsWith(new URL(props.href, window.location.origin).pathname)
  } catch {
    return false
  }
})
</script>
