<script setup lang="ts">
import AppIcon from '@/components/AppIcon.vue'

export interface SelectOption {
  value: string | number
  label: string
  color?: string
}

withDefaults(
  defineProps<{ options: SelectOption[]; size?: 'sm' | 'md'; ariaLabel?: string; disabled?: boolean }>(),
  { size: 'md', ariaLabel: undefined, disabled: false },
)

const model = defineModel<string | number>({ default: '' })
</script>

<template>
  <span class="relative inline-flex w-full items-center">
    <select
      v-model="model"
      class="input appearance-none pr-8"
      :class="size === 'sm' ? 'py-1.5 text-xs' : ''"
      :aria-label="ariaLabel"
      :disabled="disabled"
    >
      <option v-for="o in options" :key="String(o.value)" :value="o.value">{{ o.label }}</option>
    </select>
    <AppIcon name="chevronDown" class="pointer-events-none absolute right-2.5 h-4 w-4 text-subtle" />
  </span>
</template>
