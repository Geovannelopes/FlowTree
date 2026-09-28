<script setup lang="ts">
import { ref } from 'vue'
import { api, errorMessage } from '@/lib/api'
import { useMetaStore } from '@/stores/meta'
import { useToastStore } from '@/stores/toast'
import { ACCENT_PALETTE } from '@/config/palette'
import type { Flag } from '@/types'
import AppIcon from '@/components/AppIcon.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const meta = useMetaStore()
const toast = useToastStore()

const name = ref('')
const color = ref(ACCENT_PALETTE[5]!)
const saving = ref(false)
const editing = ref<Flag | null>(null)
const editName = ref('')
const editColor = ref('')
const deleting = ref<Flag | null>(null)
const deletingBusy = ref(false)

async function create() {
  if (!name.value.trim()) return
  saving.value = true
  try {
    await api.post('/flags', { name: name.value.trim(), color: color.value })
    name.value = ''
    await meta.reload()
    toast.success('Flag criada.')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao criar a flag.'))
  } finally {
    saving.value = false
  }
}

function startEdit(f: Flag) {
  editing.value = f
  editName.value = f.name
  editColor.value = f.color
}

async function saveEdit() {
  if (!editing.value) return
  saving.value = true
  try {
    await api.put(`/flags/${editing.value.uuid}`, { name: editName.value.trim(), color: editColor.value })
    editing.value = null
    await meta.reload()
    toast.success('Flag atualizada.')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao salvar a flag.'))
  } finally {
    saving.value = false
  }
}

async function confirmDelete() {
  if (!deleting.value) return
  deletingBusy.value = true
  try {
    await api.delete(`/flags/${deleting.value.uuid}`)
    deleting.value = null
    await meta.reload()
    toast.success('Flag excluída.')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao excluir a flag.'))
  } finally {
    deletingBusy.value = false
  }
}
</script>

<template>
  <section class="panel p-5">
    <h2 class="text-base font-bold text-content">Flags</h2>
    <p class="mb-4 text-sm text-muted">Etiquetas coloridas aplicadas aos cards (Bug, Melhoria, Bloqueado…).</p>

    <form class="mb-4 flex flex-wrap items-center gap-2" @submit.prevent="create">
      <input v-model="name" class="input w-56" placeholder="Nova flag" maxlength="60" aria-label="Nome da flag" />
      <input v-model="color" type="color" class="h-9 w-10 cursor-pointer rounded-lg border border-border bg-transparent" aria-label="Cor da flag" />
      <button type="submit" class="btn-primary" :disabled="saving || !name.trim()">
        <AppIcon name="plus" class="h-4 w-4" />
        Adicionar
      </button>
    </form>

    <ul class="divide-y divide-border">
      <li v-if="!meta.flags.length" class="py-3 text-sm text-subtle">Nenhuma flag cadastrada.</li>
      <li v-for="f in meta.flags" :key="f.uuid" class="flex items-center gap-3 py-2">
        <template v-if="editing?.uuid === f.uuid">
          <input v-model="editColor" type="color" class="h-8 w-9 cursor-pointer rounded-lg border border-border bg-transparent" aria-label="Cor" />
          <input v-model="editName" class="input max-w-xs" aria-label="Nome" @keydown.enter.prevent="saveEdit" @keydown.esc="editing = null" />
          <button class="btn-primary px-3 py-1.5" :disabled="saving" @click="saveEdit">Salvar</button>
          <button class="btn-ghost px-3 py-1.5" @click="editing = null">Cancelar</button>
        </template>
        <template v-else>
          <span class="badge text-white" :style="{ backgroundColor: f.color }">{{ f.name }}</span>
          <span class="text-xs text-subtle">{{ f.slug }}</span>
          <span class="ml-auto flex gap-1">
            <button class="rounded-lg p-1.5 text-muted hover:bg-surface-2 hover:text-content" aria-label="Editar flag" @click="startEdit(f)">
              <AppIcon name="edit" class="h-4 w-4" />
            </button>
            <button class="rounded-lg p-1.5 text-muted hover:bg-surface-2 hover:text-red-700" aria-label="Excluir flag" @click="deleting = f">
              <AppIcon name="trash" class="h-4 w-4" />
            </button>
          </span>
        </template>
      </li>
    </ul>

    <ConfirmDialog
      v-if="deleting"
      title="Excluir flag"
      :message="`Excluir a flag “${deleting.name}”? Ela será removida de todos os cards.`"
      confirm-label="Excluir"
      danger
      :loading="deletingBusy"
      @confirm="confirmDelete"
      @cancel="deleting = null"
    />
  </section>
</template>
