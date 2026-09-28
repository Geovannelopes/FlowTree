<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useMetaStore } from '@/stores/meta'
import { formatDate } from '@/lib/format'
import type { Card, Priority, Sprint } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import UserAvatar from '@/components/UserAvatar.vue'

const props = defineProps<{ card: Card; sprint?: Sprint | null; activeSprintUuid?: string | null }>()
const emit = defineEmits<{ open: [card: Card]; edit: [card: Card]; remove: [card: Card]; priority: [card: Card, priority: Priority] }>()

const meta = useMetaStore()
const popover = ref(false)
const root = ref<HTMLElement | null>(null)

const priority = computed(() => meta.priorityMeta(props.card.priority))
const inActiveSprint = computed(() => !!props.activeSprintUuid && props.card.sprint_uuid === props.activeSprintUuid)
const isDone = computed(() => !!props.card.completed_at)

function choose(p: Priority) {
  popover.value = false
  if (p !== props.card.priority) emit('priority', props.card, p)
}

function onClickOutside(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) popover.value = false
}
onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
  <article
    ref="root"
    class="card-item group relative flex cursor-grab flex-col gap-2.5 rounded-2xl border border-border bg-surface p-3.5 shadow-sm transition hover:shadow-md active:cursor-grabbing"
    :class="{ 'ring-1 ring-brand-400/50': inActiveSprint, 'opacity-80': isDone }"
    @click="emit('open', card)"
  >
    <div class="flex items-center justify-between gap-2">
      <!-- Badge de prioridade: clique abre o popover para trocar rápido -->
      <div class="relative">
        <button
          type="button"
          class="badge gap-1"
          :style="{ backgroundColor: `${priority.color}22`, color: priority.color }"
          :aria-label="`Prioridade: ${priority.name}. Alterar`"
          @click.stop="popover = !popover"
        >
          {{ priority.name }}
          <AppIcon name="chevronDown" class="h-3 w-3" />
        </button>
        <div v-if="popover" class="panel absolute left-0 top-full z-30 mt-1 min-w-[150px] p-1" @click.stop>
          <button
            v-for="p in meta.priorities"
            :key="p.slug"
            type="button"
            class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs font-medium hover:bg-surface-2"
            :class="{ 'bg-surface-2': p.slug === card.priority }"
            @click="choose(p.slug)"
          >
            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: p.color }" />
            {{ p.name }}
          </button>
        </div>
      </div>

      <div class="flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
        <button type="button" class="rounded-lg p-1 text-subtle hover:bg-surface-2 hover:text-content" aria-label="Editar card" @click.stop="emit('edit', card)">
          <AppIcon name="edit" class="h-3.5 w-3.5" />
        </button>
        <button type="button" class="rounded-lg p-1 text-subtle hover:bg-surface-2 hover:text-red-700" aria-label="Excluir card" @click.stop="emit('remove', card)">
          <AppIcon name="trash" class="h-3.5 w-3.5" />
        </button>
      </div>
    </div>

    <p class="text-sm font-semibold leading-snug text-content" :class="{ 'line-through decoration-subtle': isDone }">{{ card.title }}</p>

    <div v-if="sprint || card.flags.length" class="flex flex-wrap gap-1">
      <span v-if="sprint" class="badge gap-1 bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-100">
        <AppIcon name="flag" class="h-3 w-3" />
        {{ sprint.name }}
      </span>
      <span v-for="f in card.flags" :key="f.uuid" class="badge text-white" :style="{ backgroundColor: f.color }">{{ f.name }}</span>
    </div>

    <div class="flex items-center gap-3 text-[11px] text-subtle">
      <span
        v-if="card.due_date"
        class="inline-flex items-center gap-1"
        :class="{ 'font-semibold text-red-700 dark:text-red-400': card.is_overdue }"
        :title="card.is_overdue ? 'Atrasado' : 'Prazo'"
      >
        <AppIcon name="calendar" class="h-3.5 w-3.5" />
        {{ formatDate(card.due_date) }}
      </span>
      <span v-if="card.attachments_count" class="inline-flex items-center gap-1" title="Anexos">
        <AppIcon name="paperclip" class="h-3.5 w-3.5" />
        {{ card.attachments_count }}
      </span>
      <span v-if="card.comments_count" class="inline-flex items-center gap-1" title="Comentários">
        <AppIcon name="comment" class="h-3.5 w-3.5" />
        {{ card.comments_count }}
      </span>
      <span class="ml-auto">
        <UserAvatar v-if="card.assignee" :name="card.assignee.name" :src="card.assignee.avatar_url" :size="24" />
      </span>
    </div>
  </article>
</template>
