<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { useToastStore } from '@/stores/toast'
import { formatBytes, formatDateTime, timeAgo } from '@/lib/format'
import type { Attachment, BoardColumn, Card, Comment, Priority, Sprint, Wrapped } from '@/types'
import AppModal from '@/components/AppModal.vue'
import AppIcon from '@/components/AppIcon.vue'
import AppSelect, { type SelectOption } from '@/components/AppSelect.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const props = defineProps<{
  projectUuid: string
  columns: BoardColumn[]
  sprints: Sprint[]
  card: Card | null
  defaultColumnUuid: string
  defaultSprintUuid: string | null
}>()
const emit = defineEmits<{ close: []; saved: [] }>()

const auth = useAuthStore()
const meta = useMetaStore()
const toast = useToastStore()

type Tab = 'details' | 'comments' | 'attachments' | 'activity'
const tab = ref<Tab>('details')
const isEdit = computed(() => !!props.card)

// Card completo (com comentários/anexos/movimentos) carregado ao abrir em edição.
const full = ref<Card | null>(null)
const loadingFull = ref(false)

const title = ref(props.card?.title ?? '')
const description = ref(props.card?.description ?? '')
const columnUuid = ref(props.card?.board_column_uuid ?? props.defaultColumnUuid)
const sprintUuid = ref<string>(props.card?.sprint_uuid ?? props.defaultSprintUuid ?? '')
const assigneeUuid = ref<string>(props.card?.assignee?.uuid ?? '')
const priority = ref<Priority>(props.card?.priority ?? 'medium')
const dueDate = ref(props.card?.due_date ?? '')
const flagUuids = ref<string[]>((props.card?.flags ?? []).map((f) => f.uuid))
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const deleting = ref(false)
const deletingBusy = ref(false)

const columnOptions = computed<SelectOption[]>(() => props.columns.map((c) => ({ value: c.uuid, label: c.name })))
const sprintOptions = computed<SelectOption[]>(() => [
  { value: '', label: 'Backlog (sem sprint)' },
  ...props.sprints.filter((s) => s.status !== 'completed' || s.uuid === sprintUuid.value).map((s) => ({ value: s.uuid, label: `${s.name}${s.status === 'active' ? ' · ativa' : ''}` })),
])
const assigneeOptions = computed<SelectOption[]>(() => [
  { value: '', label: 'Sem responsável' },
  ...meta.assignees.map((u) => ({ value: u.uuid, label: u.name })),
])
const priorityOptions = computed<SelectOption[]>(() => meta.priorities.map((p) => ({ value: p.slug, label: p.name })))

function toggleFlag(uuid: string) {
  const i = flagUuids.value.indexOf(uuid)
  if (i === -1) flagUuids.value.push(uuid)
  else flagUuids.value.splice(i, 1)
}

async function loadFull() {
  if (!props.card) return
  loadingFull.value = true
  try {
    const { data } = await api.get<Wrapped<Card>>(`/cards/${props.card.uuid}`)
    full.value = data.data
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao carregar o card.'))
  } finally {
    loadingFull.value = false
  }
}

async function submit() {
  saving.value = true
  errors.value = {}
  const payload = {
    title: title.value.trim(),
    description: description.value || null,
    board_column_uuid: columnUuid.value,
    sprint_uuid: sprintUuid.value || null,
    assignee_uuid: assigneeUuid.value || null,
    priority: priority.value,
    due_date: dueDate.value || null,
    flag_uuids: flagUuids.value,
  }
  try {
    if (isEdit.value) await api.put(`/cards/${props.card!.uuid}`, payload)
    else await api.post(`/projects/${props.projectUuid}/cards`, payload)
    toast.success(isEdit.value ? 'Card atualizado.' : 'Card criado.')
    emit('saved')
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.error(errorMessage(e, 'Falha ao salvar o card.'))
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!props.card) return
  deletingBusy.value = true
  try {
    await api.delete(`/cards/${props.card.uuid}`)
    toast.success('Card excluído.')
    emit('saved')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao excluir o card.'))
  } finally {
    deletingBusy.value = false
    deleting.value = false
  }
}

// --- Comentários ---
const commentBody = ref('')
const sendingComment = ref(false)
const editingComment = ref<Comment | null>(null)
const editingBody = ref('')

function canManage(c: Comment): boolean {
  return auth.isAdmin || c.user?.uuid === auth.user?.uuid
}

async function sendComment() {
  if (!props.card || !commentBody.value.trim()) return
  sendingComment.value = true
  try {
    const { data } = await api.post<Wrapped<Comment>>(`/cards/${props.card.uuid}/comments`, { body: commentBody.value.trim() })
    commentBody.value = ''
    if (full.value) {
      full.value.comments = [data.data, ...(full.value.comments ?? [])]
      full.value.comments_count = (full.value.comments_count ?? 0) + 1
    }
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao comentar.'))
  } finally {
    sendingComment.value = false
  }
}

async function saveComment() {
  if (!editingComment.value) return
  try {
    const { data } = await api.put<Wrapped<Comment>>(`/comments/${editingComment.value.uuid}`, { body: editingBody.value.trim() })
    if (full.value?.comments) {
      const i = full.value.comments.findIndex((c) => c.uuid === data.data.uuid)
      if (i !== -1) full.value.comments[i] = data.data
    }
    editingComment.value = null
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao editar o comentário.'))
  }
}

async function removeComment(c: Comment) {
  try {
    await api.delete(`/comments/${c.uuid}`)
    if (full.value?.comments) full.value.comments = full.value.comments.filter((x) => x.uuid !== c.uuid)
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao excluir o comentário.'))
  }
}

// --- Anexos ---
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)

function isImage(a: Attachment): boolean {
  return a.mime_type.startsWith('image/')
}

async function upload(e: Event) {
  const files = (e.target as HTMLInputElement).files
  if (!files?.length || !props.card) return
  uploading.value = true
  try {
    for (const file of Array.from(files)) {
      const form = new FormData()
      form.append('file', file)
      const { data } = await api.post<Wrapped<Attachment>>(`/cards/${props.card.uuid}/attachments`, form)
      if (full.value) full.value.attachments = [data.data, ...(full.value.attachments ?? [])]
    }
    toast.success('Anexo enviado.')
  } catch (err) {
    toast.error(errorMessage(err, 'Falha ao enviar o anexo.'))
  } finally {
    uploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function removeAttachment(a: Attachment) {
  try {
    await api.delete(`/attachments/${a.uuid}`)
    if (full.value?.attachments) full.value.attachments = full.value.attachments.filter((x) => x.uuid !== a.uuid)
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao remover o anexo.'))
  }
}

onMounted(loadFull)
</script>

<template>
  <AppModal :title="isEdit ? 'Editar card' : 'Novo card'" :subtitle="isEdit ? `Criado ${card?.created_at ? timeAgo(card.created_at) : ''}` : undefined" size="lg" @close="emit('close')">
    <!-- Abas (só em edição) -->
    <div v-if="isEdit" class="mb-4 flex gap-1 rounded-full bg-surface-2 p-1 text-sm">
      <button
        v-for="t in ([['details', 'Detalhes'], ['comments', 'Comentários'], ['attachments', 'Anexos'], ['activity', 'Atividade']] as [Tab, string][])"
        :key="t[0]"
        type="button"
        class="flex-1 rounded-full px-3 py-1.5 font-medium transition"
        :class="tab === t[0] ? 'bg-surface text-content shadow-sm' : 'text-muted hover:text-content'"
        @click="tab = t[0]"
      >
        {{ t[1] }}
        <span v-if="t[0] === 'comments' && full?.comments?.length" class="ml-1 text-xs text-subtle">{{ full.comments.length }}</span>
        <span v-if="t[0] === 'attachments' && full?.attachments?.length" class="ml-1 text-xs text-subtle">{{ full.attachments.length }}</span>
      </button>
    </div>

    <!-- Detalhes -->
    <form v-show="tab === 'details'" id="card-form" class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="label" for="k-title">Título</label>
        <input id="k-title" v-model="title" class="input" required maxlength="255" autofocus placeholder="O que precisa ser feito?" />
        <p v-if="errors.title" class="mt-1 text-xs text-red-700">{{ errors.title }}</p>
      </div>
      <div>
        <label class="label" for="k-desc">Descrição</label>
        <textarea id="k-desc" v-model="description" class="input min-h-[110px]" rows="4" placeholder="Detalhes, critérios de aceite, links…" />
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Coluna</label>
          <AppSelect v-model="columnUuid" :options="columnOptions" aria-label="Coluna" />
          <p v-if="errors.board_column_uuid" class="mt-1 text-xs text-red-700">{{ errors.board_column_uuid }}</p>
        </div>
        <div>
          <label class="label">Sprint</label>
          <AppSelect v-model="sprintUuid" :options="sprintOptions" aria-label="Sprint" />
        </div>
        <div>
          <label class="label">Responsável</label>
          <AppSelect v-model="assigneeUuid" :options="assigneeOptions" aria-label="Responsável" />
        </div>
        <div>
          <label class="label">Prioridade</label>
          <AppSelect v-model="priority" :options="priorityOptions" aria-label="Prioridade" />
        </div>
        <div>
          <label class="label" for="k-due">Prazo</label>
          <input id="k-due" v-model="dueDate" type="date" class="input" />
          <p v-if="errors.due_date" class="mt-1 text-xs text-red-700">{{ errors.due_date }}</p>
        </div>
        <div>
          <label class="label">Flags</label>
          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="f in meta.flags"
              :key="f.uuid"
              type="button"
              class="badge border transition"
              :class="flagUuids.includes(f.uuid) ? 'border-transparent text-white' : 'border-border bg-surface text-muted hover:text-content'"
              :style="flagUuids.includes(f.uuid) ? { backgroundColor: f.color } : {}"
              :aria-pressed="flagUuids.includes(f.uuid)"
              @click="toggleFlag(f.uuid)"
            >
              {{ f.name }}
            </button>
            <span v-if="!meta.flags.length" class="text-xs text-subtle">Nenhuma flag cadastrada.</span>
          </div>
        </div>
      </div>
      <p v-if="full?.completed_at" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-100 px-3 py-1.5 text-xs font-medium text-brand-700 dark:bg-brand-900/40 dark:text-brand-100">
        <AppIcon name="check" class="h-3.5 w-3.5" />
        Concluído em {{ formatDateTime(full.completed_at) }}
      </p>
    </form>

    <!-- Comentários -->
    <div v-if="tab === 'comments'" class="space-y-4">
      <form class="flex gap-2" @submit.prevent="sendComment">
        <UserAvatar v-if="auth.user" :name="auth.user.name" :src="auth.user.avatar_url" :size="32" />
        <div class="flex-1">
          <textarea v-model="commentBody" class="input min-h-[72px]" rows="2" placeholder="Escreva um comentário…" @keydown.ctrl.enter.prevent="sendComment" @keydown.meta.enter.prevent="sendComment" />
          <div class="mt-2 flex justify-end">
            <button type="submit" class="btn-primary px-3 py-1.5" :disabled="sendingComment || !commentBody.trim()">
              {{ sendingComment ? 'Enviando…' : 'Comentar' }}
            </button>
          </div>
        </div>
      </form>

      <p v-if="loadingFull" class="text-sm text-muted">Carregando…</p>
      <p v-else-if="!full?.comments?.length" class="rounded-xl bg-surface-2 px-4 py-6 text-center text-sm text-muted">Nenhum comentário ainda.</p>
      <ul v-else class="space-y-3">
        <li v-for="c in full.comments" :key="c.uuid" class="flex gap-2">
          <UserAvatar :name="c.user?.name ?? 'Usuário'" :src="c.user?.avatar_url" :size="32" />
          <div class="min-w-0 flex-1 rounded-2xl bg-surface-2 px-3.5 py-2.5">
            <div class="flex items-center gap-2 text-xs">
              <span class="font-semibold text-content">{{ c.user?.name ?? 'Usuário removido' }}</span>
              <span class="text-subtle">{{ timeAgo(c.created_at) }}</span>
              <span v-if="canManage(c)" class="ml-auto flex gap-0.5">
                <button type="button" class="rounded p-1 text-subtle hover:text-content" aria-label="Editar comentário" @click="editingComment = c; editingBody = c.body">
                  <AppIcon name="edit" class="h-3.5 w-3.5" />
                </button>
                <button type="button" class="rounded p-1 text-subtle hover:text-red-700" aria-label="Excluir comentário" @click="removeComment(c)">
                  <AppIcon name="trash" class="h-3.5 w-3.5" />
                </button>
              </span>
            </div>
            <div v-if="editingComment?.uuid === c.uuid" class="mt-2">
              <textarea v-model="editingBody" class="input" rows="2" />
              <div class="mt-1.5 flex justify-end gap-1">
                <button type="button" class="btn-ghost px-2 py-1 text-xs" @click="editingComment = null">Cancelar</button>
                <button type="button" class="btn-primary px-2 py-1 text-xs" @click="saveComment">Salvar</button>
              </div>
            </div>
            <p v-else class="mt-1 whitespace-pre-wrap text-sm text-content">{{ c.body }}</p>
          </div>
        </li>
      </ul>
    </div>

    <!-- Anexos -->
    <div v-if="tab === 'attachments'" class="space-y-4">
      <label
        class="flex cursor-pointer flex-col items-center gap-1 rounded-2xl border-2 border-dashed border-border px-4 py-6 text-center text-sm text-muted hover:border-brand-400 hover:text-content"
        for="k-file"
      >
        <AppIcon name="paperclip" class="h-6 w-6" />
        <span>{{ uploading ? 'Enviando…' : 'Clique para anexar arquivos (até 20 MB cada)' }}</span>
        <input id="k-file" ref="fileInput" type="file" multiple class="hidden" :disabled="uploading" @change="upload" />
      </label>

      <p v-if="loadingFull" class="text-sm text-muted">Carregando…</p>
      <p v-else-if="!full?.attachments?.length" class="rounded-xl bg-surface-2 px-4 py-6 text-center text-sm text-muted">Nenhum anexo.</p>
      <ul v-else class="grid gap-2 sm:grid-cols-2">
        <li v-for="a in full.attachments" :key="a.uuid" class="flex items-center gap-3 rounded-2xl border border-border p-2.5">
          <a :href="a.url ?? '#'" target="_blank" rel="noopener" class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface-2 text-subtle">
            <img v-if="isImage(a) && a.url" :src="a.url" :alt="a.filename" class="h-full w-full object-cover" />
            <AppIcon v-else name="paperclip" class="h-5 w-5" />
          </a>
          <div class="min-w-0 flex-1">
            <a :href="a.url ?? '#'" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-content hover:underline">{{ a.filename }}</a>
            <p class="text-xs text-subtle">{{ formatBytes(a.size) }} · {{ a.user?.name ?? '—' }} · {{ timeAgo(a.created_at) }}</p>
          </div>
          <button type="button" class="rounded-lg p-1.5 text-subtle hover:bg-surface-2 hover:text-red-700" aria-label="Remover anexo" @click="removeAttachment(a)">
            <AppIcon name="trash" class="h-4 w-4" />
          </button>
        </li>
      </ul>
    </div>

    <!-- Atividade -->
    <div v-if="tab === 'activity'">
      <p v-if="loadingFull" class="text-sm text-muted">Carregando…</p>
      <p v-else-if="!full?.movements?.length" class="rounded-xl bg-surface-2 px-4 py-6 text-center text-sm text-muted">Sem movimentações registradas.</p>
      <ol v-else class="relative ml-3 space-y-4 border-l border-border pl-5">
        <li v-for="m in full.movements" :key="m.id" class="relative text-sm">
          <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full ring-4 ring-surface" :style="{ backgroundColor: m.to_column.color }" />
          <p class="text-content">
            <span class="font-semibold">{{ m.user?.name ?? 'Sistema' }}</span>
            <template v-if="m.from_column"> moveu de <span class="font-medium">{{ m.from_column.name }}</span> para</template>
            <template v-else> criou o card em</template>
            <span class="font-medium" :style="{ color: m.to_column.color }"> {{ m.to_column.name }}</span>
          </p>
          <p class="text-xs text-subtle">{{ formatDateTime(m.created_at) }}</p>
        </li>
      </ol>
      <div v-if="full" class="mt-5 grid gap-2 rounded-2xl bg-surface-2 p-3 text-xs text-muted sm:grid-cols-3">
        <span>Criado: <strong class="text-content">{{ formatDateTime(full.created_at) }}</strong></span>
        <span>Iniciado: <strong class="text-content">{{ formatDateTime(full.started_at) }}</strong></span>
        <span>Concluído: <strong class="text-content">{{ formatDateTime(full.completed_at) }}</strong></span>
      </div>
    </div>

    <template #footer>
      <button v-if="isEdit" type="button" class="btn-ghost mr-auto text-red-700" @click="deleting = true">
        <AppIcon name="trash" class="h-4 w-4" />
        Excluir
      </button>
      <button type="button" class="btn-secondary" @click="emit('close')">{{ tab === 'details' ? 'Cancelar' : 'Fechar' }}</button>
      <button v-if="tab === 'details'" type="submit" form="card-form" class="btn-primary" :disabled="saving">{{ saving ? 'Salvando…' : isEdit ? 'Salvar' : 'Criar card' }}</button>
    </template>
  </AppModal>

  <ConfirmDialog
    v-if="deleting"
    title="Excluir card"
    :message="`Excluir “${card?.title}”?`"
    confirm-label="Excluir"
    danger
    :loading="deletingBusy"
    @confirm="confirmDelete"
    @cancel="deleting = false"
  />
</template>
