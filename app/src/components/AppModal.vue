<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'
import AppIcon from '@/components/AppIcon.vue'

withDefaults(defineProps<{ title: string; subtitle?: string; size?: 'sm' | 'md' | 'lg' | 'xl' }>(), {
  subtitle: undefined,
  size: 'md',
})
const emit = defineEmits<{ close: [] }>()

const SIZES = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' }

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
}
onMounted(() => {
  window.addEventListener('keydown', onKey)
  document.body.style.overflow = 'hidden'
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey)
  document.body.style.overflow = ''
})
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/45 p-4 backdrop-blur-sm sm:items-center"
    @mousedown.self="emit('close')"
  >
    <div
      class="panel my-auto flex w-full max-h-[calc(100vh-2rem)] flex-col"
      :class="SIZES[size]"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
    >
      <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
        <div class="min-w-0">
          <h2 class="truncate text-lg font-bold text-content">{{ title }}</h2>
          <p v-if="subtitle" class="mt-0.5 text-xs text-muted">{{ subtitle }}</p>
        </div>
        <button class="rounded-full p-1.5 text-subtle hover:bg-surface-2 hover:text-content" aria-label="Fechar" @click="emit('close')">
          <AppIcon name="close" class="h-5 w-5" />
        </button>
      </div>
      <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
        <slot />
      </div>
      <div v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-border px-5 py-3">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>
