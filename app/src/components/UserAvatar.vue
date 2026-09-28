<script setup lang="ts">
import { computed } from 'vue'
import { initials } from '@/lib/format'

const props = withDefaults(
  defineProps<{ name: string; src?: string | null; size?: number; title?: string }>(),
  { src: null, size: 28, title: undefined },
)

const PALETTE = ['#3f6650', '#6f8a63', '#4c7570', '#8e744e', '#7d644a', '#5b6e9c', '#7a5c8a']

const color = computed(() => {
  let h = 0
  for (const ch of props.name) h = (h * 31 + ch.charCodeAt(0)) >>> 0
  return PALETTE[h % PALETTE.length]
})

const style = computed(() => ({
  width: `${props.size}px`,
  height: `${props.size}px`,
  fontSize: `${Math.max(10, Math.round(props.size * 0.38))}px`,
  backgroundColor: props.src ? undefined : color.value,
}))
</script>

<template>
  <span
    class="inline-flex shrink-0 select-none items-center justify-center overflow-hidden rounded-full font-semibold text-white ring-2 ring-surface"
    :style="style"
    :title="title ?? name"
  >
    <img v-if="src" :src="src" :alt="name" class="h-full w-full object-cover" />
    <template v-else>{{ initials(name) }}</template>
  </span>
</template>
