<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import draggable from 'vuedraggable'
import { isAxiosError } from 'axios'
import { api, errorMessage } from '@/lib/api'
import { getEcho, initEcho } from '@/lib/echo'
import { useMetaStore } from '@/stores/meta'
import { useToastStore } from '@/stores/toast'
import type { BoardColumn, BoardResponse, Card, PresenceMember, Priority, Project, Sprint, SprintStatus, Wrapped } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import AppSelect, { type SelectOption } from '@/components/AppSelect.vue'
import CardCard from '@/components/CardCard.vue'
import CardModal from '@/components/CardModal.vue'
import ColumnModal from '@/components/ColumnModal.vue'
import SprintManagerModal from '@/components/SprintManagerModal.vue'
import MetricsModal from '@/components/MetricsModal.vue'
import PresenceAvatars from '@/components/PresenceAvatars.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const route = useRoute()
const router = useRouter()
const meta = useMetaStore()
const toast = useToastStore()

const projectUuid = String(route.params.uuid)
const LANES_KEY = `flowtree_board_lanes_${projectUuid}`

const project = ref<Project | null>(null)
const columns = ref<BoardColumn[]>([])
const sprints = ref<Sprint[]>([])
const loading = ref(true)
const error = ref('')

const activeSprint = computed(() => sprints.value.find((s) => s.status === 'active') ?? null)

// --- Raias (visão empilhada, como no Gestão IC) ---
// Cada raia = uma sprint (ou o Backlog) com uma célula por coluna. `group`
// separa as sprints em andamento (ativa/planejadas) do Backlog e das concluídas,
// que ficam numa seção recolhível no fim.
interface Lane {
  key: string
  sprint: Sprint | null
  group: 'open' | 'backlog' | 'completed'
  cells: Record<string, Card[]>
}
const lanes = ref<Lane[]>([])

function makeLane(sprint: Sprint | null, group: Lane['group']): Lane {
  const sprintUuid = sprint?.uuid ?? null
  const cells: Record<string, Card[]> = {}
  for (const col of columns.value) {
    cells[col.uuid] = (col.cards ?? []).filter((c) => (c.sprint_uuid ?? null) === sprintUuid)
  }
  return { key: sprint ? sprint.uuid : 'backlog', sprint, group, cells }
}

function buildLanes() {
  const byPosition = (a: Sprint, b: Sprint) => a.position - b.position
  const open = sprints.value.filter((s) => s.status !== 'completed').sort(byPosition)
  const completed = sprints.value.filter((s) => s.status === 'completed').sort(byPosition)
  lanes.value = [
    ...open.map((s) => makeLane(s, 'open')),
    makeLane(null, 'backlog'),
    ...completed.map((s) => makeLane(s, 'completed')),
  ]
}

const completedCount = computed(() => lanes.value.filter((l) => l.group === 'completed').length)
const isFirstCompleted = (index: number) =>
  lanes.value[index].group === 'completed' && (index === 0 || lanes.value[index - 1].group !== 'completed')

// Raias recolhidas (lembradas por projeto) + seção de concluídas recolhida por padrão.
const collapsed = ref<Set<string>>(new Set(JSON.parse(localStorage.getItem(LANES_KEY) ?? '[]') as string[]))
const completedCollapsed = ref(true)

function toggleLane(key: string) {
  const set = new Set(collapsed.value)
  set.has(key) ? set.delete(key) : set.add(key)
  collapsed.value = set
  localStorage.setItem(LANES_KEY, JSON.stringify([...set]))
}

function laneCount(lane: Lane): number {
  return columns.value.reduce((n, c) => n + (lane.cells[c.uuid]?.length ?? 0), 0)
}
function laneDone(lane: Lane): number {
  return columns.value.filter((c) => c.is_done).reduce((n, c) => n + (lane.cells[c.uuid]?.length ?? 0), 0)
}
function laneProgress(lane: Lane): number {
  const total = laneCount(lane)
  return total ? Math.round((laneDone(lane) / total) * 100) : 0
}

// Total por coluna somando todas as raias (é o que o WIP conta).
function columnCount(column: BoardColumn): number {
  return lanes.value.reduce((n, l) => n + (l.cells[column.uuid]?.length ?? 0), 0)
}
const totalCards = computed(() => columns.value.reduce((n, c) => n + columnCount(c), 0))

const SPRINT_STATUS: Record<SprintStatus, { label: string; class: string }> = {
  planned: { label: 'Planejada', class: 'bg-surface-2 text-muted' },
  active: { label: 'Ativa', class: 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-100' },
  completed: { label: 'Concluída', class: 'bg-surface-2 text-subtle' },
}

// --- Filtros (no cliente) ---
const search = ref('')
const assigneeFilter = ref<string>('')
const priorityFilter = ref<string>('')
const flagFilter = ref<string>('')

const assigneeOptions = computed<SelectOption[]>(() => [
  { value: '', label: 'Responsável: todos' },
  { value: 'none', label: 'Sem responsável' },
  ...meta.assignees.map((u) => ({ value: u.uuid, label: u.name })),
])
const priorityOptions = computed<SelectOption[]>(() => [
  { value: '', label: 'Prioridade: todas' },
  ...meta.priorities.map((p) => ({ value: p.slug, label: p.name })),
])
const flagOptions = computed<SelectOption[]>(() => [
  { value: '', label: 'Flag: todas' },
  ...meta.flags.map((f) => ({ value: f.uuid, label: f.name })),
])

const filtersActive = computed(() => !!(search.value || assigneeFilter.value || priorityFilter.value || flagFilter.value))

function matches(card: Card): boolean {
  if (search.value) {
    const q = search.value.toLowerCase()
    if (!`${card.title} ${card.description ?? ''}`.toLowerCase().includes(q)) return false
  }
  if (assigneeFilter.value === 'none' && card.assignee) return false
  if (assigneeFilter.value && assigneeFilter.value !== 'none' && card.assignee?.uuid !== assigneeFilter.value) return false
  if (priorityFilter.value && card.priority !== priorityFilter.value) return false
  if (flagFilter.value && !card.flags.some((f) => f.uuid === flagFilter.value)) return false
  return true
}

function visibleCards(cards: Card[]): Card[] {
  return filtersActive.value ? cards.filter(matches) : cards
}

function clearFilters() {
  search.value = ''
  assigneeFilter.value = ''
  priorityFilter.value = ''
  flagFilter.value = ''
}

// --- Carregamento (sempre todas as sprints: as raias fazem o recorte) ---
async function loadBoard() {
  const { data } = await api.get<BoardResponse>(`/projects/${projectUuid}/board`, { params: { sprint: 'all' } })
  project.value = data.data
  columns.value = (data.data.columns ?? [])
    .slice()
    .sort((a, b) => a.position - b.position)
    .map((c) => ({ ...c, cards: (c.cards ?? []).slice().sort((x, y) => x.position - y.position) }))
  sprints.value = data.sprints ?? []
  buildLanes()
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    await Promise.all([loadBoard(), meta.load()])
  } catch (e) {
    error.value = errorMessage(e, 'Falha ao carregar o quadro.')
  } finally {
    loading.value = false
  }
}

async function reload() {
  try {
    await loadBoard()
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao atualizar o quadro.'))
  }
}

// --- Drag de cards: soltar noutra raia muda a sprint; noutra célula, a coluna ---
// eslint-disable-next-line @typescript-eslint/no-explicit-any
async function onCardChange(evt: any, column: BoardColumn, lane: Lane) {
  const change = evt.added ?? evt.moved
  if (!change) return
  const card: Card = change.element
  const position: number = change.newIndex
  try {
    const { data } = await api.patch<Wrapped<Card>>(`/cards/${card.uuid}/move`, {
      board_column_uuid: column.uuid,
      sprint_uuid: lane.sprint?.uuid ?? null,
      position,
    })
    Object.assign(card, data.data)
  } catch (e) {
    if (isAxiosError(e) && e.response?.status === 422) {
      const msg = (e.response.data as { errors?: Record<string, string[]> }).errors?.board_column_uuid?.[0]
      toast.error(msg ?? 'Movimento não permitido.')
    } else {
      toast.error('Falha ao mover o card.')
    }
    await reload()
  }
}

async function onColumnReorder() {
  columns.value.forEach((c, i) => (c.position = i))
  try {
    await api.patch(`/projects/${projectUuid}/columns/reorder`, { column_uuids: columns.value.map((c) => c.uuid) })
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao reordenar as colunas.'))
    await reload()
  }
}

async function changePriority(card: Card, priority: Priority) {
  const previous = card.priority
  card.priority = priority
  try {
    await api.put(`/cards/${card.uuid}`, { priority })
  } catch (e) {
    card.priority = previous
    toast.error(errorMessage(e, 'Falha ao alterar a prioridade.'))
  }
}

// --- Ações rápidas de sprint (o restante fica no gerenciador) ---
const sprintBusy = ref<string | null>(null)

async function activateSprint(sprint: Sprint) {
  sprintBusy.value = sprint.uuid
  try {
    await api.post(`/sprints/${sprint.uuid}/activate`)
    toast.success(`“${sprint.name}” ativada.`)
    await reload()
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao ativar a sprint.'))
  } finally {
    sprintBusy.value = null
  }
}

async function reopenSprint(sprint: Sprint) {
  sprintBusy.value = sprint.uuid
  try {
    await api.post(`/sprints/${sprint.uuid}/reopen`)
    toast.success(`“${sprint.name}” reaberta.`)
    await reload()
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao reabrir a sprint.'))
  } finally {
    sprintBusy.value = null
  }
}

// --- Modais ---
const cardModalOpen = ref(false)
const editingCard = ref<Card | null>(null)
const createColumnUuid = ref('')
const createSprintUuid = ref<string | null>(null)
const columnModalOpen = ref(false)
const editingColumn = ref<BoardColumn | null>(null)
const sprintsOpen = ref(false)
const metricsOpen = ref(false)
const deletingCard = ref<Card | null>(null)
const deletingColumn = ref<BoardColumn | null>(null)
const deletingBusy = ref(false)

function openCreateCard(column: BoardColumn, sprintUuid: string | null) {
  editingCard.value = null
  createColumnUuid.value = column.uuid
  createSprintUuid.value = sprintUuid
  cardModalOpen.value = true
}

function openEditCard(card: Card) {
  editingCard.value = card
  createColumnUuid.value = card.board_column_uuid
  createSprintUuid.value = card.sprint_uuid
  cardModalOpen.value = true
}

async function onCardSaved() {
  cardModalOpen.value = false
  await reload()
}

function openCreateColumn() {
  editingColumn.value = null
  columnModalOpen.value = true
}

function openEditColumn(column: BoardColumn) {
  editingColumn.value = column
  columnModalOpen.value = true
}

async function onColumnSaved() {
  columnModalOpen.value = false
  await reload()
}

async function confirmDeleteCard() {
  if (!deletingCard.value) return
  deletingBusy.value = true
  try {
    await api.delete(`/cards/${deletingCard.value.uuid}`)
    toast.success('Card excluído.')
    deletingCard.value = null
    await reload()
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao excluir o card.'))
  } finally {
    deletingBusy.value = false
  }
}

async function confirmDeleteColumn() {
  if (!deletingColumn.value) return
  deletingBusy.value = true
  try {
    await api.delete(`/columns/${deletingColumn.value.uuid}`)
    toast.success('Coluna excluída.')
    deletingColumn.value = null
    await reload()
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao excluir a coluna.'))
  } finally {
    deletingBusy.value = false
  }
}

// --- Pan do quadro (arrastar o fundo nos dois eixos) ---
const boardEl = ref<HTMLElement | null>(null)
let panning = false
let panStartX = 0
let panStartY = 0
let panStartLeft = 0
let panStartTop = 0

function onPanStart(e: MouseEvent) {
  if (e.button !== 0) return
  const target = e.target as HTMLElement
  if (target.closest('button, a, input, select, textarea, label, .col-handle, .card-item')) return
  const el = boardEl.value
  if (!el) return
  panning = true
  panStartX = e.clientX
  panStartY = e.clientY
  panStartLeft = el.scrollLeft
  panStartTop = el.scrollTop
  el.classList.add('is-panning')
  window.addEventListener('mousemove', onPanMove)
  window.addEventListener('mouseup', onPanEnd)
}
function onPanMove(e: MouseEvent) {
  if (!panning || !boardEl.value) return
  e.preventDefault()
  boardEl.value.scrollLeft = panStartLeft - (e.clientX - panStartX)
  boardEl.value.scrollTop = panStartTop - (e.clientY - panStartY)
}
function onPanEnd() {
  panning = false
  boardEl.value?.classList.remove('is-panning')
  window.removeEventListener('mousemove', onPanMove)
  window.removeEventListener('mouseup', onPanEnd)
}

// --- Deep-link ?card=uuid (vindo de notificação) ---
function findCard(uuid: string): Card | undefined {
  for (const col of columns.value) {
    const found = (col.cards ?? []).find((c) => c.uuid === uuid)
    if (found) return found
  }
  return undefined
}

async function openCardFromQuery() {
  const uuid = route.query.card
  if (typeof uuid !== 'string' || !uuid) return
  let card = findCard(uuid)
  if (!card) {
    try {
      const { data } = await api.get<Wrapped<Card>>(`/cards/${uuid}`)
      card = data.data
    } catch {
      toast.warning('Card não encontrado.')
    }
  }
  if (card) openEditCard(card)
  await router.replace({ query: { ...route.query, card: undefined } })
}

watch(
  () => route.query.card,
  (v) => {
    if (v && !loading.value) void openCardFromQuery()
  },
)

// --- Realtime (Echo/Reverb) + presença ---
const presence = ref<PresenceMember[]>([])
let reloadTimer: ReturnType<typeof setTimeout> | null = null
const channelName = `project.${projectUuid}`

function scheduleReload() {
  if (reloadTimer) clearTimeout(reloadTimer)
  reloadTimer = setTimeout(() => void reload(), 400)
}

function subscribeRealtime() {
  const echo = initEcho()
  if (!echo) return
  echo
    .private(channelName)
    .listen('.card.changed', scheduleReload)
    .listen('.column.changed', scheduleReload)
    .listen('.sprint.changed', scheduleReload)
    .listen('.comment.changed', scheduleReload)
    .listen('.attachment.changed', scheduleReload)

  echo
    .join(channelName)
    .here((members: PresenceMember[]) => (presence.value = members))
    .joining((m: PresenceMember) => {
      if (!presence.value.some((x) => x.uuid === m.uuid)) presence.value.push(m)
    })
    .leaving((m: PresenceMember) => (presence.value = presence.value.filter((x) => x.uuid !== m.uuid)))
}

onBeforeUnmount(() => {
  if (reloadTimer) clearTimeout(reloadTimer)
  getEcho()?.leave(channelName)
  window.removeEventListener('mousemove', onPanMove)
  window.removeEventListener('mouseup', onPanEnd)
})

onMounted(async () => {
  await load()
  subscribeRealtime()
  await openCardFromQuery()
})
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col">
    <!-- Cabeçalho -->
    <div class="mb-3 flex flex-wrap items-center gap-3">
      <RouterLink :to="{ name: 'projects' }" class="rounded-lg p-1.5 text-subtle hover:bg-surface-2 hover:text-content" aria-label="Voltar aos projetos">
        <AppIcon name="chevronLeft" class="h-5 w-5" />
      </RouterLink>
      <img v-if="project?.logo_url" :src="project.logo_url" alt="" class="h-9 w-9 rounded-xl border border-border object-cover" />
      <span v-else-if="project" class="h-9 w-2 rounded-full" :style="{ backgroundColor: project.color }" />
      <div class="min-w-0">
        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">FlowTree</p>
        <h1 class="truncate text-xl font-extrabold leading-tight text-content">{{ project?.name ?? 'Quadro' }}</h1>
      </div>
      <span v-if="!loading" class="hidden text-xs text-subtle sm:inline">{{ totalCards }} cards</span>
      <span v-if="activeSprint" class="badge gap-1 bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-100">
        <AppIcon name="flag" class="h-3 w-3" />
        {{ activeSprint.name }}
      </span>

      <div class="ml-auto flex items-center gap-3">
        <PresenceAvatars :members="presence" />
        <button class="btn-secondary px-3 py-1.5" @click="metricsOpen = true">
          <AppIcon name="chart" class="h-4 w-4" />
          <span class="hidden sm:inline">Métricas</span>
        </button>
        <button class="btn-secondary px-3 py-1.5" @click="openCreateColumn">
          <AppIcon name="columns" class="h-4 w-4" />
          <span class="hidden sm:inline">Nova coluna</span>
        </button>
        <button class="btn-primary px-3 py-1.5" @click="sprintsOpen = true">
          <AppIcon name="flag" class="h-4 w-4" />
          <span class="hidden sm:inline">Sprints</span>
        </button>
      </div>
    </div>

    <!-- Toolbar: busca + filtros -->
    <div v-if="!loading" class="mb-3 flex flex-wrap items-center gap-2">
      <label class="relative">
        <AppIcon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-subtle" />
        <input v-model="search" type="search" placeholder="Buscar tarefas…" class="input w-48 py-1.5 pl-8 text-xs" aria-label="Buscar tarefas" />
      </label>
      <AppSelect v-model="assigneeFilter" :options="assigneeOptions" size="sm" aria-label="Filtrar por responsável" class="w-44" />
      <AppSelect v-model="priorityFilter" :options="priorityOptions" size="sm" aria-label="Filtrar por prioridade" class="w-40" />
      <AppSelect v-model="flagFilter" :options="flagOptions" size="sm" aria-label="Filtrar por flag" class="w-36" />
      <button v-if="filtersActive" class="btn-ghost px-2 py-1 text-xs" @click="clearFilters">
        <AppIcon name="close" class="h-3.5 w-3.5" />
        Limpar
      </button>
      <span v-if="filtersActive" class="text-[11px] text-subtle">(arrastar desativado ao filtrar)</span>
    </div>

    <p v-if="loading" class="text-sm text-muted">Carregando quadro…</p>
    <p v-else-if="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">{{ error }}</p>

    <!-- Quadro: raias (sprints) empilhadas sobre as mesmas colunas -->
    <div v-else ref="boardEl" class="board-pan min-h-0 flex-1 overflow-auto overscroll-contain pb-2" @mousedown="onPanStart">
      <div class="min-w-max">
        <!-- Cabeçalho das colunas: fixo no topo, arrastável para reordenar -->
        <draggable
          v-model="columns"
          item-key="uuid"
          handle=".col-handle"
          class="sticky top-0 z-20 mb-2 flex items-start gap-3 bg-canvas pb-2 pt-0.5"
          ghost-class="sortable-ghost"
          @end="onColumnReorder"
        >
          <template #item="{ element: column }">
            <div class="w-[300px] shrink-0 overflow-hidden rounded-2xl border border-border bg-surface/80 shadow-sm backdrop-blur">
              <span class="block h-1.5 w-full" :style="{ backgroundColor: column.color }" />
              <header class="flex items-center gap-1.5 px-3 py-2">
                <button class="col-handle cursor-grab rounded p-0.5 text-subtle hover:text-muted active:cursor-grabbing" aria-label="Arrastar coluna">
                  <AppIcon name="grip" class="h-4 w-4" />
                </button>
                <h2 class="min-w-0 flex-1 truncate text-sm font-bold text-content">{{ column.name }}</h2>
                <AppIcon v-if="column.is_done" name="check" class="h-3.5 w-3.5 text-moss" aria-label="Coluna de conclusão" />
                <span
                  class="rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-semibold text-muted"
                  :class="{ 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300': column.wip_limit != null && columnCount(column) >= column.wip_limit }"
                  :title="column.wip_limit != null ? `Limite de WIP: ${column.wip_limit}` : undefined"
                >
                  {{ columnCount(column) }}{{ column.wip_limit != null ? ` / ${column.wip_limit}` : '' }}
                </span>
                <button class="rounded-lg p-1 text-subtle hover:bg-surface-2 hover:text-content" aria-label="Editar coluna" @click="openEditColumn(column)">
                  <AppIcon name="edit" class="h-3.5 w-3.5" />
                </button>
                <button class="rounded-lg p-1 text-subtle hover:bg-surface-2 hover:text-red-700" aria-label="Excluir coluna" @click="deletingColumn = column">
                  <AppIcon name="trash" class="h-3.5 w-3.5" />
                </button>
              </header>
            </div>
          </template>

          <template #footer>
            <button
              key="add-column"
              class="flex h-14 w-56 shrink-0 items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed border-border text-sm font-medium text-muted hover:border-brand-400 hover:text-content"
              @click="openCreateColumn"
            >
              <AppIcon name="plus" class="h-4 w-4" />
              Nova coluna
            </button>
          </template>
        </draggable>

        <!-- Uma raia por sprint (+ Backlog + seção de concluídas) -->
        <template v-for="(lane, index) in lanes" :key="lane.key">
          <!-- Divisória da seção "Sprints concluídas" -->
          <button
            v-if="isFirstCompleted(index)"
            class="mb-2 mt-5 flex w-full items-center gap-2 rounded-xl px-2 py-1.5 text-left text-muted hover:bg-surface-2/60"
            @click="completedCollapsed = !completedCollapsed"
          >
            <AppIcon name="chevronDown" class="h-4 w-4 transition-transform" :class="completedCollapsed ? '-rotate-90' : ''" />
            <AppIcon name="check" class="h-4 w-4 text-moss" />
            <span class="text-sm font-semibold">Sprints concluídas ({{ completedCount }})</span>
          </button>

          <section
            v-show="lane.group !== 'completed' || !completedCollapsed"
            class="mb-3 rounded-2xl border border-border bg-surface/70 p-2 shadow-sm backdrop-blur"
            :class="{ 'opacity-75': lane.group === 'completed', 'ring-1 ring-brand-400/40': lane.sprint?.status === 'active' }"
          >
            <!-- Cabeçalho da raia -->
            <div class="flex items-center gap-1 px-1 py-1">
              <button class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-1 py-0.5 text-left hover:bg-surface-2/70" @click="toggleLane(lane.key)">
                <AppIcon name="chevronDown" class="h-4 w-4 shrink-0 text-subtle transition-transform" :class="collapsed.has(lane.key) ? '-rotate-90' : ''" />
                <AppIcon :name="lane.sprint ? 'flag' : 'archive'" class="h-4 w-4 shrink-0" :class="lane.sprint?.status === 'active' ? 'text-brand-500' : 'text-subtle'" />
                <span class="truncate text-sm font-bold text-content">{{ lane.sprint?.name ?? 'Backlog' }}</span>
                <span class="shrink-0 rounded-full bg-surface-2 px-2 py-0.5 text-[10px] font-semibold text-muted">
                  {{ laneDone(lane) }}/{{ laneCount(lane) }}
                </span>
                <span v-if="lane.sprint" class="badge shrink-0" :class="SPRINT_STATUS[lane.sprint.status].class">
                  {{ SPRINT_STATUS[lane.sprint.status].label }}
                </span>
                <span v-if="lane.sprint?.starts_at || lane.sprint?.ends_at" class="hidden shrink-0 text-xs text-subtle md:inline">
                  {{ lane.sprint?.starts_at ?? '—' }} → {{ lane.sprint?.ends_at ?? '—' }}
                </span>
                <span v-if="lane.sprint?.goal" class="hidden min-w-0 truncate text-xs text-muted lg:inline" :title="lane.sprint.goal">
                  · {{ lane.sprint.goal }}
                </span>
              </button>

              <!-- Barra de progresso da raia -->
              <div v-if="laneCount(lane)" class="hidden h-1.5 w-24 shrink-0 overflow-hidden rounded-full bg-surface-2 sm:block" :title="`${laneProgress(lane)}% concluído`">
                <div class="h-full rounded-full bg-moss transition-all" :style="{ width: `${laneProgress(lane)}%` }" />
              </div>

              <!-- Ações rápidas -->
              <button
                v-if="lane.sprint?.status === 'planned'"
                class="btn-secondary px-2.5 py-1 text-xs"
                :disabled="sprintBusy !== null"
                @click="activateSprint(lane.sprint)"
              >
                <AppIcon name="play" class="h-3.5 w-3.5" />
                Ativar
              </button>
              <button
                v-if="lane.sprint?.status === 'completed'"
                class="btn-secondary px-2.5 py-1 text-xs"
                :disabled="sprintBusy !== null"
                @click="reopenSprint(lane.sprint)"
              >
                <AppIcon name="rotate" class="h-3.5 w-3.5" />
                Reabrir
              </button>
              <button
                v-if="lane.sprint"
                class="rounded-lg p-1 text-subtle hover:bg-surface-2 hover:text-content"
                aria-label="Gerenciar sprints"
                title="Gerenciar sprints"
                @click="sprintsOpen = true"
              >
                <AppIcon name="dots" class="h-4 w-4" />
              </button>
            </div>

            <!-- Células: uma por coluna -->
            <div v-if="!collapsed.has(lane.key)" class="mt-1 flex gap-3">
              <div v-for="column in columns" :key="column.uuid" class="w-[300px] shrink-0">
                <draggable
                  v-if="!filtersActive"
                  v-model="lane.cells[column.uuid]"
                  :group="{ name: 'cards' }"
                  item-key="uuid"
                  class="flex min-h-[64px] flex-col gap-2 rounded-xl bg-surface-2/60 p-1.5"
                  ghost-class="sortable-ghost"
                  @change="onCardChange($event, column, lane)"
                >
                  <template #item="{ element }">
                    <CardCard
                      :card="element"
                      :active-sprint-uuid="activeSprint?.uuid ?? null"
                      @open="openEditCard"
                      @edit="openEditCard"
                      @remove="deletingCard = $event"
                      @priority="changePriority"
                    />
                  </template>
                </draggable>

                <!-- Modo filtrado: somente leitura -->
                <div v-else class="flex min-h-[64px] flex-col gap-2 rounded-xl bg-surface-2/60 p-1.5">
                  <CardCard
                    v-for="card in visibleCards(lane.cells[column.uuid] ?? [])"
                    :key="card.uuid"
                    :card="card"
                    :active-sprint-uuid="activeSprint?.uuid ?? null"
                    @open="openEditCard"
                    @edit="openEditCard"
                    @remove="deletingCard = $event"
                    @priority="changePriority"
                  />
                </div>

                <button
                  v-if="!filtersActive && lane.group !== 'completed'"
                  class="mt-1 flex w-full items-center justify-center gap-1 rounded-lg px-2 py-1 text-[11px] font-medium text-subtle hover:bg-surface-2 hover:text-content"
                  @click="openCreateCard(column, lane.sprint?.uuid ?? null)"
                >
                  <AppIcon name="plus" class="h-3 w-3" />
                  Nova tarefa
                </button>
              </div>
            </div>
          </section>
        </template>
      </div>
    </div>

    <CardModal
      v-if="cardModalOpen"
      :project-uuid="projectUuid"
      :columns="columns"
      :sprints="sprints"
      :card="editingCard"
      :default-column-uuid="createColumnUuid"
      :default-sprint-uuid="createSprintUuid"
      @close="cardModalOpen = false"
      @saved="onCardSaved"
    />

    <ColumnModal
      v-if="columnModalOpen"
      :project-uuid="projectUuid"
      :column="editingColumn"
      :columns-count="columns.length"
      @close="columnModalOpen = false"
      @saved="onColumnSaved"
    />

    <SprintManagerModal v-if="sprintsOpen" :project-uuid="projectUuid" :sprints="sprints" @close="sprintsOpen = false" @changed="reload" />

    <MetricsModal v-if="metricsOpen" :project-uuid="projectUuid" :sprints="sprints" initial-scope="all" @close="metricsOpen = false" />

    <ConfirmDialog
      v-if="deletingCard"
      title="Excluir card"
      :message="`Excluir “${deletingCard.title}”?`"
      confirm-label="Excluir"
      danger
      :loading="deletingBusy"
      @confirm="confirmDeleteCard"
      @cancel="deletingCard = null"
    />

    <ConfirmDialog
      v-if="deletingColumn"
      title="Excluir coluna"
      :message="`Excluir “${deletingColumn.name}” e os ${columnCount(deletingColumn)} card(s) dela?`"
      confirm-label="Excluir"
      danger
      :loading="deletingBusy"
      @confirm="confirmDeleteColumn"
      @cancel="deletingColumn = null"
    />
  </div>
</template>
