<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '@/stores/notifications'
import { timeAgo } from '@/lib/format'
import type { AppNotification } from '@/types'
import AppIcon from '@/components/AppIcon.vue'

const props = withDefaults(defineProps<{ collapsed?: boolean }>(), { collapsed: false })
const emit = defineEmits<{ navigate: [] }>()

const notifications = useNotificationsStore()
const router = useRouter()
const open = ref(false)
const root = ref<HTMLElement | null>(null)

async function toggle() {
  open.value = !open.value
  if (open.value) await notifications.fetchList()
}

function describe(n: AppNotification): string {
  if (n.type === 'card_assigned') return `${n.data.actor_name} atribuiu “${n.data.card_title}” a você`
  return `${n.data.actor_name} comentou em “${n.data.card_title}”`
}

async function openNotification(n: AppNotification) {
  if (!n.read) {
    try {
      await notifications.markRead(n.id)
    } catch {
      // segue navegando
    }
  }
  open.value = false
  emit('navigate')
  await router.push({ name: 'board', params: { uuid: n.data.project_uuid }, query: { card: n.data.card_uuid } })
}

function onClickOutside(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) open.value = false
}
onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <div ref="root" class="relative">
    <button
      class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm text-brand-100/80 hover:bg-white/10 hover:text-white"
      :class="{ 'justify-center': props.collapsed }"
      aria-label="Notificações"
      @click="toggle"
    >
      <span class="relative">
        <AppIcon name="bell" class="h-5 w-5" />
        <span
          v-if="notifications.hasUnread"
          class="absolute -right-1.5 -top-1.5 inline-flex min-w-[16px] items-center justify-center rounded-full bg-amber px-1 text-[10px] font-bold text-white"
        >
          {{ notifications.unread > 99 ? '99+' : notifications.unread }}
        </span>
      </span>
      <span v-if="!props.collapsed" class="flex-1 text-left">Notificações</span>
    </button>

    <div
      v-if="open"
      class="panel absolute bottom-0 left-full z-50 ml-2 w-80 overflow-hidden text-content"
    >
      <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
        <span class="text-sm font-semibold">Notificações</span>
        <button v-if="notifications.hasUnread" class="text-xs font-medium text-brand-500 hover:underline" @click="notifications.markAllRead()">
          Marcar todas como lidas
        </button>
      </div>
      <div class="max-h-96 overflow-y-auto">
        <p v-if="notifications.loading && !notifications.items.length" class="px-4 py-6 text-center text-sm text-muted">Carregando…</p>
        <p v-else-if="!notifications.items.length" class="px-4 py-6 text-center text-sm text-muted">Nenhuma notificação.</p>
        <button
          v-for="n in notifications.items"
          :key="n.id"
          class="flex w-full items-start gap-3 border-b border-border px-4 py-3 text-left hover:bg-surface-2"
          :class="{ 'bg-brand-50/60 dark:bg-brand-900/30': !n.read }"
          @click="openNotification(n)"
        >
          <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.read ? 'bg-transparent' : 'bg-brand-500'" />
          <span class="min-w-0 flex-1">
            <span class="block text-sm text-content">{{ describe(n) }}</span>
            <span v-if="n.data.comment_excerpt" class="mt-0.5 block truncate text-xs text-muted">“{{ n.data.comment_excerpt }}”</span>
            <span class="mt-0.5 block text-[11px] text-subtle">{{ n.data.project_name }} · {{ timeAgo(n.created_at) }}</span>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
