<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, errorMessage } from '@/lib/api'
import { useMetaStore } from '@/stores/meta'
import { useToastStore } from '@/stores/toast'
import { formatDate } from '@/lib/format'
import type { Project, Wrapped } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import ProjectModal from '@/components/ProjectModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const meta = useMetaStore()
const toast = useToastStore()

const projects = ref<Project[]>([])
const loading = ref(true)
const error = ref('')
const showArchived = ref(false)
const search = ref('')

const modalOpen = ref(false)
const editing = ref<Project | null>(null)
const deleting = ref<Project | null>(null)
const deletingBusy = ref(false)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return projects.value
  return projects.value.filter((p) => `${p.name} ${p.description ?? ''}`.toLowerCase().includes(q))
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<Wrapped<Project[]>>('/projects', { params: { archived: showArchived.value ? 1 : 0 } })
    projects.value = data.data
  } catch (e) {
    error.value = errorMessage(e, 'Falha ao carregar os projetos.')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  modalOpen.value = true
}

async function openEdit(p: Project) {
  try {
    const { data } = await api.get<Wrapped<Project>>(`/projects/${p.uuid}`)
    editing.value = data.data
    modalOpen.value = true
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao abrir o projeto.'))
  }
}

async function onSaved() {
  modalOpen.value = false
  await load()
}

async function confirmDelete() {
  if (!deleting.value) return
  deletingBusy.value = true
  try {
    await api.delete(`/projects/${deleting.value.uuid}`)
    toast.success('Projeto excluído.')
    deleting.value = null
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao excluir o projeto.'))
  } finally {
    deletingBusy.value = false
  }
}

async function toggleArchived() {
  showArchived.value = !showArchived.value
  await load()
}

onMounted(async () => {
  await Promise.all([load(), meta.load()])
})
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">FlowTree</p>
        <h1 class="text-2xl font-extrabold text-content">Projetos</h1>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <label class="relative">
          <AppIcon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-subtle" />
          <input v-model="search" type="search" placeholder="Buscar projetos…" class="input w-56 pl-9" aria-label="Buscar projetos" />
        </label>
        <button class="btn-secondary" :class="{ 'bg-surface-2 text-content': showArchived }" @click="toggleArchived">
          <AppIcon name="archive" class="h-4 w-4" />
          {{ showArchived ? 'Ver ativos' : 'Arquivados' }}
        </button>
        <button class="btn-primary" @click="openCreate">
          <AppIcon name="plus" class="h-4 w-4" />
          Novo projeto
        </button>
      </div>
    </div>

    <p v-if="loading" class="text-sm text-muted">Carregando…</p>
    <p v-else-if="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">{{ error }}</p>

    <div v-else-if="!filtered.length" class="panel flex flex-col items-center gap-3 px-6 py-14 text-center">
      <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-2 text-moss">
        <AppIcon name="projects" class="h-7 w-7" />
      </span>
      <p class="text-base font-semibold text-content">
        {{ showArchived ? 'Nenhum projeto arquivado.' : 'Nenhum projeto ainda.' }}
      </p>
      <p class="text-sm text-muted">Crie um projeto para montar o primeiro quadro Kanban.</p>
      <button v-if="!showArchived" class="btn-primary" @click="openCreate">Criar projeto</button>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="p in filtered"
        :key="p.uuid"
        class="panel group relative flex flex-col overflow-hidden transition hover:shadow-md"
      >
        <span class="h-1.5 w-full" :style="{ backgroundColor: p.color }" />
        <RouterLink :to="{ name: 'board', params: { uuid: p.uuid } }" class="flex flex-1 flex-col gap-3 p-5">
          <div class="flex items-start gap-3">
            <img v-if="p.logo_url" :src="p.logo_url" alt="" class="h-10 w-10 shrink-0 rounded-xl border border-border object-cover" />
            <span v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-white" :style="{ backgroundColor: p.color }">
              <AppIcon name="board" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
              <h2 class="truncate text-base font-bold text-content">{{ p.name }}</h2>
              <p class="line-clamp-2 text-sm text-muted">{{ p.description || 'Sem descrição.' }}</p>
            </div>
          </div>

          <div class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-subtle">
            <span>{{ p.cards_count ?? 0 }} cards</span>
            <span v-if="p.active_sprint" class="inline-flex items-center gap-1 text-moss">
              <AppIcon name="flag" class="h-3.5 w-3.5" />
              {{ p.active_sprint.name }}
              <span v-if="p.active_sprint.ends_at">· até {{ formatDate(p.active_sprint.ends_at) }}</span>
            </span>
            <span v-else>Sem sprint ativa</span>
            <span v-if="p.owner" class="ml-auto inline-flex items-center gap-1.5">
              <UserAvatar :name="p.owner.name" :src="p.owner.avatar_url" :size="20" />
              {{ p.owner.name.split(' ')[0] }}
            </span>
          </div>
        </RouterLink>

        <div class="absolute right-3 top-4 flex gap-1 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
          <button class="rounded-lg bg-surface/90 p-1.5 text-muted shadow hover:text-content" aria-label="Editar projeto" @click.prevent="openEdit(p)">
            <AppIcon name="edit" class="h-4 w-4" />
          </button>
          <button class="rounded-lg bg-surface/90 p-1.5 text-muted shadow hover:text-red-700" aria-label="Excluir projeto" @click.prevent="deleting = p">
            <AppIcon name="trash" class="h-4 w-4" />
          </button>
        </div>
      </article>
    </div>

    <ProjectModal v-if="modalOpen" :project="editing" @close="modalOpen = false" @saved="onSaved" />

    <ConfirmDialog
      v-if="deleting"
      title="Excluir projeto"
      :message="`Excluir “${deleting.name}” e todos os seus cards, colunas e sprints? Esta ação não pode ser desfeita.`"
      confirm-label="Excluir"
      danger
      :loading="deletingBusy"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    />
  </div>
</template>
