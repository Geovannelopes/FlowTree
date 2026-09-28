<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { api, API_BASE_URL, errorMessage, fieldErrors, getToken } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import { formatDate } from '@/lib/format'
import type { Sprint, SprintStatus, Wrapped } from '@/types'
import AppModal from '@/components/AppModal.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSelect, { type SelectOption } from '@/components/AppSelect.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const props = defineProps<{ projectUuid: string; sprints: Sprint[] }>()
const emit = defineEmits<{ close: []; changed: [] }>()

const toast = useToastStore()

const list = ref<Sprint[]>([...props.sprints])
watch(
  () => props.sprints,
  (v) => (list.value = [...v]),
)

const STATUS: Record<SprintStatus, { label: string; class: string }> = {
  planned: { label: 'Planejada', class: 'bg-surface-2 text-muted' },
  active: { label: 'Ativa', class: 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-100' },
  completed: { label: 'Concluída', class: 'bg-surface-2 text-subtle' },
}

// --- Formulário ---
const formOpen = ref(false)
const editing = ref<Sprint | null>(null)
const name = ref('')
const goal = ref('')
const startsAt = ref('')
const endsAt = ref('')
const saving = ref(false)
const errors = ref<Record<string, string>>({})

function openCreate() {
  editing.value = null
  name.value = `Sprint ${list.value.length + 1}`
  goal.value = ''
  startsAt.value = ''
  endsAt.value = ''
  errors.value = {}
  formOpen.value = true
}

function openEdit(s: Sprint) {
  editing.value = s
  name.value = s.name
  goal.value = s.goal ?? ''
  startsAt.value = s.starts_at ?? ''
  endsAt.value = s.ends_at ?? ''
  errors.value = {}
  formOpen.value = true
}

async function submit() {
  saving.value = true
  errors.value = {}
  const payload = { name: name.value.trim(), goal: goal.value || null, starts_at: startsAt.value || null, ends_at: endsAt.value || null }
  try {
    if (editing.value) await api.put(`/sprints/${editing.value.uuid}`, payload)
    else await api.post(`/projects/${props.projectUuid}/sprints`, payload)
    toast.success(editing.value ? 'Sprint atualizada.' : 'Sprint criada.')
    formOpen.value = false
    emit('changed')
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.error(errorMessage(e, 'Falha ao salvar a sprint.'))
  } finally {
    saving.value = false
  }
}

// --- Ações ---
const busy = ref<string | null>(null)

async function run(s: Sprint, action: string, fn: () => Promise<unknown>, ok: string) {
  busy.value = `${s.uuid}:${action}`
  try {
    await fn()
    toast.success(ok)
    emit('changed')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha na operação.'))
  } finally {
    busy.value = null
  }
}

const activate = (s: Sprint) => run(s, 'activate', () => api.post(`/sprints/${s.uuid}/activate`), `“${s.name}” ativada.`)
const reopen = (s: Sprint) => run(s, 'reopen', () => api.post(`/sprints/${s.uuid}/reopen`), `“${s.name}” reaberta.`)
const assignBacklog = (s: Sprint) =>
  run(
    s,
    'assign',
    async () => {
      const { data } = await api.post<{ assigned: number }>(`/sprints/${s.uuid}/assign-backlog`)
      toast.info(`${data.assigned} card(s) do backlog atribuído(s).`)
    },
    'Backlog atribuído.',
  )

// Concluir: escolhe o destino dos cards abertos.
const completing = ref<Sprint | null>(null)
const moveOpenTo = ref<string>('keep')
const moveOptions = computed<SelectOption[]>(() => [
  { value: 'keep', label: 'Manter na sprint' },
  { value: 'backlog', label: 'Mover para o Backlog' },
  ...list.value.filter((s) => s.uuid !== completing.value?.uuid && s.status !== 'completed').map((s) => ({ value: s.uuid, label: `Mover para ${s.name}` })),
])

async function confirmComplete() {
  const s = completing.value
  if (!s) return
  const move = moveOpenTo.value === 'keep' ? null : moveOpenTo.value
  await run(s, 'complete', () => api.post(`/sprints/${s.uuid}/complete`, { move_open_to: move }), `“${s.name}” concluída.`)
  completing.value = null
}

const deleting = ref<Sprint | null>(null)
async function confirmDelete() {
  const s = deleting.value
  if (!s) return
  await run(s, 'delete', () => api.delete(`/sprints/${s.uuid}`), 'Sprint excluída. Os cards voltaram ao backlog.')
  deleting.value = null
}

/** Exporta o CSV com o token (não dá para usar um link simples: exige Bearer). */
async function exportCsv(s: Sprint) {
  busy.value = `${s.uuid}:export`
  try {
    const res = await fetch(`${API_BASE_URL}/sprints/${s.uuid}/export`, {
      headers: { Authorization: `Bearer ${getToken() ?? ''}`, Accept: 'text/csv' },
    })
    if (!res.ok) throw new Error('export')
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `sprint-${s.name.toLowerCase().replace(/[^a-z0-9]+/g, '-')}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
  } catch {
    toast.error('Falha ao exportar o CSV.')
  } finally {
    busy.value = null
  }
}

/** Reordenação por drag (só as não concluídas mudam de lugar). */
async function onReorder() {
  try {
    await api.patch<Wrapped<Sprint[]>>(`/projects/${props.projectUuid}/sprints/reorder`, { sprint_uuids: list.value.map((s) => s.uuid) })
    emit('changed')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao reordenar.'))
    list.value = [...props.sprints]
  }
}

function statusMeta(s: Sprint): { label: string; class: string } {
  return STATUS[s.status]
}

function progress(s: Sprint): number {
  if (!s.cards_count) return 0
  return Math.round(((s.done_count ?? 0) / s.cards_count) * 100)
}
</script>

<template>
  <AppModal title="Sprints" subtitle="Planeje, ative e conclua as sprints do projeto." size="lg" @close="emit('close')">
    <div class="mb-4 flex items-center justify-between gap-2">
      <p class="text-sm text-muted">{{ list.length }} sprint(s) · arraste para reordenar</p>
      <button v-if="!formOpen" class="btn-primary px-3 py-1.5" @click="openCreate">
        <AppIcon name="plus" class="h-4 w-4" />
        Nova sprint
      </button>
    </div>

    <form v-if="formOpen" class="mb-5 space-y-3 rounded-2xl border border-border bg-surface-2/60 p-4" @submit.prevent="submit">
      <h3 class="text-sm font-bold text-content">{{ editing ? 'Editar sprint' : 'Nova sprint' }}</h3>
      <div class="grid gap-3 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="label" for="s-name">Nome</label>
          <input id="s-name" v-model="name" class="input" required maxlength="120" autofocus />
          <p v-if="errors.name" class="mt-1 text-xs text-red-700">{{ errors.name }}</p>
        </div>
        <div class="sm:col-span-2">
          <label class="label" for="s-goal">Objetivo</label>
          <textarea id="s-goal" v-model="goal" class="input" rows="2" placeholder="O que esta sprint entrega?" />
        </div>
        <div>
          <label class="label" for="s-start">Início</label>
          <input id="s-start" v-model="startsAt" type="date" class="input" />
        </div>
        <div>
          <label class="label" for="s-end">Término</label>
          <input id="s-end" v-model="endsAt" type="date" class="input" :min="startsAt || undefined" />
          <p v-if="errors.ends_at" class="mt-1 text-xs text-red-700">{{ errors.ends_at }}</p>
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" class="btn-secondary px-3 py-1.5" @click="formOpen = false">Cancelar</button>
        <button type="submit" class="btn-primary px-3 py-1.5" :disabled="saving">{{ saving ? 'Salvando…' : 'Salvar' }}</button>
      </div>
    </form>

    <p v-if="!list.length" class="rounded-xl bg-surface-2 px-4 py-8 text-center text-sm text-muted">
      Nenhuma sprint. Crie a primeira para agrupar os cards do backlog.
    </p>

    <draggable v-else v-model="list" item-key="uuid" handle=".sprint-handle" class="space-y-2" ghost-class="sortable-ghost" @end="onReorder">
      <template #item="{ element: s }">
        <div class="rounded-2xl border border-border bg-surface p-3" :class="{ 'opacity-70': s.status === 'completed' }">
          <div class="flex items-start gap-2">
            <button class="sprint-handle mt-1 cursor-grab text-subtle hover:text-muted active:cursor-grabbing" aria-label="Arrastar sprint">
              <AppIcon name="grip" class="h-4 w-4" />
            </button>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-content">{{ s.name }}</span>
                <span class="badge" :class="statusMeta(s).class">{{ statusMeta(s).label }}</span>
                <span class="text-xs text-subtle">{{ formatDate(s.starts_at) }} → {{ formatDate(s.ends_at) }}</span>
              </div>
              <p v-if="s.goal" class="mt-0.5 line-clamp-2 text-xs text-muted">{{ s.goal }}</p>
              <div class="mt-2 flex items-center gap-2">
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-2">
                  <div class="h-full rounded-full bg-brand-500 transition-all" :style="{ width: `${progress(s)}%` }" />
                </div>
                <span class="text-[11px] text-subtle">{{ s.done_count ?? 0 }}/{{ s.cards_count ?? 0 }} concluídos</span>
              </div>
            </div>
          </div>

          <div class="mt-2 flex flex-wrap gap-1 pl-6">
            <button v-if="s.status === 'planned'" class="btn-primary px-2.5 py-1 text-xs" :disabled="busy !== null" @click="activate(s)">
              <AppIcon name="play" class="h-3.5 w-3.5" />
              Ativar
            </button>
            <button v-if="s.status !== 'completed'" class="btn-secondary px-2.5 py-1 text-xs" :disabled="busy !== null" @click="completing = s; moveOpenTo = 'keep'">
              <AppIcon name="check" class="h-3.5 w-3.5" />
              Concluir
            </button>
            <button v-if="s.status === 'completed'" class="btn-secondary px-2.5 py-1 text-xs" :disabled="busy !== null" @click="reopen(s)">
              <AppIcon name="rotate" class="h-3.5 w-3.5" />
              Reabrir
            </button>
            <button v-if="s.status !== 'completed'" class="btn-ghost px-2.5 py-1 text-xs" :disabled="busy !== null" @click="assignBacklog(s)">
              <AppIcon name="flag" class="h-3.5 w-3.5" />
              Atribuir backlog
            </button>
            <button class="btn-ghost px-2.5 py-1 text-xs" :disabled="busy !== null" @click="exportCsv(s)">
              <AppIcon name="download" class="h-3.5 w-3.5" />
              CSV
            </button>
            <button class="btn-ghost px-2.5 py-1 text-xs" @click="openEdit(s)">
              <AppIcon name="edit" class="h-3.5 w-3.5" />
              Editar
            </button>
            <button class="btn-ghost px-2.5 py-1 text-xs text-red-700" :disabled="busy !== null" @click="deleting = s">
              <AppIcon name="trash" class="h-3.5 w-3.5" />
              Excluir
            </button>
          </div>
        </div>
      </template>
    </draggable>

    <template #footer>
      <button class="btn-secondary" @click="emit('close')">Fechar</button>
    </template>
  </AppModal>

  <ConfirmDialog
    v-if="completing"
    title="Concluir sprint"
    :message="`Concluir “${completing.name}”? Escolha o que fazer com os cards ainda abertos.`"
    confirm-label="Concluir"
    :loading="busy !== null"
    @confirm="confirmComplete"
    @cancel="completing = null"
  >
    <div class="mt-3">
      <label class="label">Cards abertos</label>
      <AppSelect v-model="moveOpenTo" :options="moveOptions" aria-label="Destino dos cards abertos" />
    </div>
  </ConfirmDialog>

  <ConfirmDialog
    v-if="deleting"
    title="Excluir sprint"
    :message="`Excluir “${deleting.name}”? Os cards dela voltam para o Backlog.`"
    confirm-label="Excluir"
    danger
    :loading="busy !== null"
    @confirm="confirmDelete"
    @cancel="deleting = null"
  />
</template>
