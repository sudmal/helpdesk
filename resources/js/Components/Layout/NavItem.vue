<template>
  <Link :href="href"
        :class="['flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                 isActive
                   ? 'bg-blue-600 text-white'
                   : 'text-white/70 hover:bg-white/10 hover:text-white']">
    <Icon :name="icon" class="w-4 h-4 shrink-0" />
    {{ label }}
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '@/Components/UI/Icon.vue'

const props = defineProps({
  href:  { type: String, required: true },
  icon:  { type: String, required: true },
  label: { type: String, required: true },
})

const page = usePage()
const isActive = computed(() => page.url.startsWith(new URL(props.href, window.location.origin).pathname))
</script>
