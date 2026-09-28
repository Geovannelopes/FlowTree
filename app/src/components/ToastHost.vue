<script setup lang="ts">
import { useToastStore, type ToastType } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'

const toast = useToastStore()

const STYLES: Record<ToastType, string> = {
  success: 'border-brand-400/40 bg-brand-500 text-white',
  error: 'border-red-800/40 bg-red-700 text-white',
  warning: 'border-amber/40 bg-amber text-white',
  info: 'border-water/40 bg-water text-white',
}
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[70] flex flex-col items-center gap-2 px-4 sm:items-end sm:pr-6">
    <TransitionGroup name="toast">
      <div
        v-for="t in toast.toasts"
        :key="t.id"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border px-4 py-3 text-sm shadow-lg"
        :class="STYLES[t.type]"
        role="status"
      >
        <span class="flex-1">{{ t.message }}</span>
        <button class="rounded-full p-0.5 opacity-80 hover:opacity-100" aria-label="Fechar" @click="toast.dismiss(t.id)">
          <AppIcon name="close" class="h-4 w-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
