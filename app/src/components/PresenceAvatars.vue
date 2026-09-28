<script setup lang="ts">
import type { PresenceMember } from '@/types'
import UserAvatar from '@/components/UserAvatar.vue'

defineProps<{ members: PresenceMember[] }>()
</script>

<template>
  <div v-if="members.length" class="flex items-center" :title="`No quadro agora: ${members.map((m) => m.name).join(', ')}`">
    <span class="mr-2 hidden text-[11px] font-medium text-subtle sm:inline">Online</span>
    <div class="flex -space-x-2">
      <UserAvatar v-for="m in members.slice(0, 6)" :key="m.uuid" :name="m.name" :src="m.avatar_url" :size="26" />
      <span
        v-if="members.length > 6"
        class="inline-flex h-[26px] w-[26px] items-center justify-center rounded-full bg-surface-2 text-[10px] font-semibold text-muted ring-2 ring-surface"
      >
        +{{ members.length - 6 }}
      </span>
    </div>
  </div>
</template>
