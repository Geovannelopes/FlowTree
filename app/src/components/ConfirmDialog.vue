<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue'

withDefaults(
  defineProps<{
    title?: string
    message?: string
    confirmLabel?: string
    cancelLabel?: string
    danger?: boolean
    loading?: boolean
  }>(),
  { title: 'Confirmar', message: '', confirmLabel: 'Confirmar', cancelLabel: 'Cancelar', danger: false, loading: false },
)
const emit = defineEmits<{ confirm: []; cancel: [] }>()

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('cancel')
  if (e.key === 'Enter') emit('confirm')
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" @mousedown.self="emit('cancel')">
    <div class="panel w-full max-w-sm overflow-hidden" role="alertdialog" aria-modal="true">
      <div class="px-5 py-4">
        <h3 class="text-base font-bold text-content">{{ title }}</h3>
        <p v-if="message" class="mt-1.5 text-sm text-muted">{{ message }}</p>
        <slot />
      </div>
      <div class="flex justify-end gap-2 border-t border-border px-5 py-3">
        <button class="btn-secondary" @click="emit('cancel')">{{ cancelLabel }}</button>
        <button :class="danger ? 'btn-danger' : 'btn-primary'" :disabled="loading" @click="emit('confirm')">
          {{ loading ? 'Aguarde…' : confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>
