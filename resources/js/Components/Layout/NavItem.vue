<template>
  <InertiaLink :href="href" :title="collapsed ? label : undefined"
        :class="['flex items-center rounded-md text-[13px] leading-tight font-medium transition-colors cursor-pointer',
                 collapsed ? 'justify-center px-2 py-1.5' : 'gap-2 px-2.5 py-1',
                 isActive
                   ? 'bg-blue-600 text-white'
                   : 'text-white/70 hover:bg-white/10 hover:text-white']">
    <Icon :name="icon" :class="['w-3.5 h-3.5 shrink-0', iconClass]" />
    <span v-if="!collapsed" class="truncate">{{ label }}</span>
    <slot />
  </InertiaLink>
</template>

<script setup>
import { computed } from 'vue'
import { Link as InertiaLink, usePage } from '@inertiajs/vue3'
import Icon from '@/Components/UI/Icon.vue'

const props = defineProps({
  href:      { type: String, required: true },
  icon:      { type: String, required: true },
  label:     { type: String, required: true },
  iconClass: { type: [String, Array, Object], default: '' },
  collapsed: { type: Boolean, default: false },
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
