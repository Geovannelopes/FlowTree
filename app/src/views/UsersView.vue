<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api, errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { Paginated, User } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import UserModal from '@/components/UserModal.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const auth = useAuthStore()
const toast = useToastStore()

const users = ref<User[]>([])
const meta = ref<Paginated<User>['meta'] | null>(null)
const page = ref(1)
const search = ref('')
const loading = ref(true)
const error = ref('')

const modalOpen = ref(false)
const editing = ref<User | null>(null)
const deleting = ref<User | null>(null)
const deletingBusy = ref(false)

let searchTimer: number | null = null

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<Paginated<User>>('/users', { params: { search: search.value || undefined, page: page.value } })
    users.value = data.data
    meta.value = data.meta
  } catch (e) {
    error.value = errorMessage(e, 'Falha ao carregar os usuários.')
  } finally {
    loading.value = false
  }
}

watch(search, () => {
  if (searchTimer) window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(() => {
    page.value = 1
    void load()
  }, 300)
})

function openCreate() {
  editing.value = null
  modalOpen.value = true
}

function openEdit(u: User) {
  editing.value = u
  modalOpen.value = true
}

async function onSaved() {
  modalOpen.value = false
  await load()
}

async function confirmDelete() {
  if (!deleting.value) return
  deletingBusy.value = true
  try {
    await api.delete(`/users/${deleting.value.uuid}`)
    toast.success('Usuário excluído.')
    deleting.value = null
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao excluir o usuário.'))
  } finally {
    deletingBusy.value = false
  }
}

async function goTo(p: number) {
  page.value = p
  await load()
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">Administração</p>
        <h1 class="text-2xl font-extrabold text-content">Usuários</h1>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <label class="relative">
          <AppIcon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-subtle" />
          <input v-model="search" type="search" placeholder="Buscar por nome ou e-mail…" class="input w-64 pl-9" aria-label="Buscar usuários" />
        </label>
        <button class="btn-primary" @click="openCreate">
          <AppIcon name="plus" class="h-4 w-4" />
          Novo usuário
        </button>
      </div>
    </div>

    <p v-if="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">{{ error }}</p>

    <div class="panel overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-border bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
            <tr>
              <th class="px-4 py-2.5 font-medium">Usuário</th>
              <th class="px-4 py-2.5 font-medium">Cargo</th>
              <th class="px-4 py-2.5 font-medium">Módulos</th>
              <th class="px-4 py-2.5 font-medium">Status</th>
              <th class="px-4 py-2.5" />
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-if="loading">
              <td colspan="5" class="px-4 py-8 text-center text-muted">Carregando…</td>
            </tr>
            <tr v-else-if="!users.length">
              <td colspan="5" class="px-4 py-8 text-center text-muted">Nenhum usuário encontrado.</td>
            </tr>
            <tr v-for="u in users" v-else :key="u.uuid" class="hover:bg-surface-2/60">
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <UserAvatar :name="u.name" :src="u.avatar_url" :size="32" />
                  <div class="min-w-0">
                    <p class="truncate font-semibold text-content">{{ u.name }}</p>
                    <p class="truncate text-xs text-subtle">{{ u.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-muted">{{ u.position ?? '—' }}</td>
              <td class="px-4 py-3">
                <span v-if="u.is_admin" class="badge bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-100">Admin</span>
                <span v-else-if="!u.modules?.length" class="text-xs text-subtle">—</span>
                <span v-for="m in u.modules ?? []" v-else :key="m.slug" class="badge mr-1 bg-surface-2 text-muted">{{ m.name }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="badge" :class="u.is_active ? 'bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-100' : 'bg-surface-2 text-muted'">
                  {{ u.is_active ? 'Ativo' : 'Inativo' }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex gap-1">
                  <button class="rounded-lg p-1.5 text-muted hover:bg-surface-2 hover:text-content" aria-label="Editar usuário" @click="openEdit(u)">
                    <AppIcon name="edit" class="h-4 w-4" />
                  </button>
                  <button
                    class="rounded-lg p-1.5 text-muted hover:bg-surface-2 hover:text-red-700 disabled:opacity-40"
                    aria-label="Excluir usuário"
                    :disabled="u.uuid === auth.user?.uuid"
                    @click="deleting = u"
                  >
                    <AppIcon name="trash" class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between border-t border-border px-4 py-2.5 text-xs text-muted">
        <span>{{ meta.total }} usuários · página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <div class="flex gap-1">
          <button class="btn-ghost px-2 py-1" :disabled="meta.current_page <= 1" @click="goTo(meta.current_page - 1)">Anterior</button>
          <button class="btn-ghost px-2 py-1" :disabled="meta.current_page >= meta.last_page" @click="goTo(meta.current_page + 1)">Próxima</button>
        </div>
      </div>
    </div>

    <UserModal v-if="modalOpen" :user="editing" @close="modalOpen = false" @saved="onSaved" />

    <ConfirmDialog
      v-if="deleting"
      title="Excluir usuário"
      :message="`Excluir “${deleting.name}”? Os cards atribuídos a esta pessoa ficarão sem responsável.`"
      confirm-label="Excluir"
      danger
      :loading="deletingBusy"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    />
  </div>
</template>
