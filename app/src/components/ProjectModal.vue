<script setup lang="ts">
import { computed, ref } from 'vue'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { useMetaStore } from '@/stores/meta'
import { useToastStore } from '@/stores/toast'
import { ACCENT_PALETTE } from '@/config/palette'
import type { Project, Wrapped } from '@/types'
import AppModal from '@/components/AppModal.vue'
import UserAvatar from '@/components/UserAvatar.vue'

const props = defineProps<{ project: Project | null }>()
const emit = defineEmits<{ close: []; saved: [project: Project] }>()

const meta = useMetaStore()
const toast = useToastStore()

const name = ref(props.project?.name ?? '')
const description = ref(props.project?.description ?? '')
const color = ref(props.project?.color ?? ACCENT_PALETTE[0]!)
const isArchived = ref(props.project?.is_archived ?? false)
const memberUuids = ref<string[]>((props.project?.members ?? []).map((m) => m.uuid))
const saving = ref(false)
const errors = ref<Record<string, string>>({})
const logoInput = ref<HTMLInputElement | null>(null)
const logoUrl = ref(props.project?.logo_url ?? null)

const isEdit = computed(() => !!props.project)

function toggleMember(uuid: string) {
  const i = memberUuids.value.indexOf(uuid)
  if (i === -1) memberUuids.value.push(uuid)
  else memberUuids.value.splice(i, 1)
}

async function submit() {
  saving.value = true
  errors.value = {}
  const payload = {
    name: name.value,
    description: description.value || null,
    color: color.value,
    member_uuids: memberUuids.value,
    ...(isEdit.value ? { is_archived: isArchived.value } : {}),
  }
  try {
    const res = isEdit.value
      ? await api.put<Wrapped<Project>>(`/projects/${props.project!.uuid}`, payload)
      : await api.post<Wrapped<Project>>('/projects', payload)
    toast.success(isEdit.value ? 'Projeto atualizado.' : 'Projeto criado.')
    emit('saved', res.data.data)
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.error(errorMessage(e, 'Falha ao salvar o projeto.'))
  } finally {
    saving.value = false
  }
}

async function uploadLogo(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !props.project) return
  const form = new FormData()
  form.append('logo', file)
  try {
    const { data } = await api.post<Wrapped<Project>>(`/projects/${props.project.uuid}/logo`, form)
    logoUrl.value = data.data.logo_url
    toast.success('Logo atualizado.')
  } catch (err) {
    toast.error(errorMessage(err, 'Falha ao enviar o logo.'))
  } finally {
    if (logoInput.value) logoInput.value.value = ''
  }
}

async function removeLogo() {
  if (!props.project) return
  try {
    const { data } = await api.delete<Wrapped<Project>>(`/projects/${props.project.uuid}/logo`)
    logoUrl.value = data.data.logo_url
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao remover o logo.'))
  }
}
</script>

<template>
  <AppModal :title="isEdit ? 'Editar projeto' : 'Novo projeto'" @close="emit('close')">
    <form id="project-form" class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="label" for="p-name">Nome</label>
        <input id="p-name" v-model="name" class="input" required maxlength="255" autofocus />
        <p v-if="errors.name" class="mt-1 text-xs text-red-700">{{ errors.name }}</p>
      </div>
      <div>
        <label class="label" for="p-desc">Descrição</label>
        <textarea id="p-desc" v-model="description" class="input min-h-[80px]" rows="3" />
      </div>
      <div>
        <label class="label">Cor</label>
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-for="c in ACCENT_PALETTE"
            :key="c"
            type="button"
            class="h-7 w-7 rounded-full ring-2 ring-offset-2 ring-offset-surface transition"
            :class="color === c ? 'ring-content' : 'ring-transparent hover:ring-border'"
            :style="{ backgroundColor: c }"
            :aria-label="`Cor ${c}`"
            @click="color = c"
          />
          <input v-model="color" type="color" class="h-7 w-9 cursor-pointer rounded border border-border bg-transparent" aria-label="Cor personalizada" />
        </div>
      </div>

      <div v-if="isEdit">
        <label class="label">Logo</label>
        <div class="flex items-center gap-3">
          <img v-if="logoUrl" :src="logoUrl" alt="" class="h-12 w-12 rounded-xl border border-border object-cover" />
          <span v-else class="flex h-12 w-12 items-center justify-center rounded-xl border border-dashed border-border text-xs text-subtle">—</span>
          <label class="btn-secondary" for="p-logo">Enviar</label>
          <input id="p-logo" ref="logoInput" type="file" accept="image/*" class="hidden" @change="uploadLogo" />
          <button v-if="logoUrl" type="button" class="btn-ghost" @click="removeLogo">Remover</button>
        </div>
      </div>

      <div>
        <label class="label">Membros</label>
        <div class="max-h-44 space-y-1 overflow-y-auto rounded-xl border border-border p-2">
          <label
            v-for="u in meta.assignees"
            :key="u.uuid"
            class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-surface-2"
          >
            <input type="checkbox" class="accent-brand-500" :checked="memberUuids.includes(u.uuid)" @change="toggleMember(u.uuid)" />
            <UserAvatar :name="u.name" :src="u.avatar_url" :size="22" />
            <span class="truncate text-content">{{ u.name }}</span>
            <span class="ml-auto truncate text-xs text-subtle">{{ u.position }}</span>
          </label>
          <p v-if="!meta.assignees.length" class="px-2 py-1 text-xs text-subtle">Nenhum usuário disponível.</p>
        </div>
      </div>

      <label v-if="isEdit" class="flex items-center gap-2 text-sm text-content">
        <input v-model="isArchived" type="checkbox" class="accent-brand-500" />
        Projeto arquivado
      </label>
    </form>

    <template #footer>
      <button type="button" class="btn-secondary" @click="emit('close')">Cancelar</button>
      <button type="submit" form="project-form" class="btn-primary" :disabled="saving">{{ saving ? 'Salvando…' : 'Salvar' }}</button>
    </template>
  </AppModal>
</template>
