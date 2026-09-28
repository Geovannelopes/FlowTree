<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { api, errorMessage } from '@/lib/api'
import { useMetaStore } from '@/stores/meta'
import { useToastStore } from '@/stores/toast'
import type { PriorityOption } from '@/types'

const meta = useMetaStore()
const toast = useToastStore()

const drafts = reactive<Record<string, { name: string; color: string }>>({})
const savingUuid = ref<string | null>(null)

function sync(list: PriorityOption[]) {
  for (const p of list) drafts[p.uuid] = { name: p.name, color: p.color }
}
watch(() => meta.priorities, sync, { immediate: true })

function dirty(p: PriorityOption): boolean {
  const d = drafts[p.uuid]
  return !!d && (d.name !== p.name || d.color !== p.color)
}

async function save(p: PriorityOption) {
  const d = drafts[p.uuid]
  if (!d) return
  savingUuid.value = p.uuid
  try {
    await api.put(`/priorities/${p.uuid}`, { name: d.name.trim(), color: d.color })
    await meta.reload()
    toast.success('Prioridade atualizada.')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao salvar a prioridade.'))
  } finally {
    savingUuid.value = null
  }
}
</script>

<template>
  <section class="panel p-5">
    <h2 class="text-base font-bold text-content">Prioridades</h2>
    <p class="mb-4 text-sm text-muted">Os quatro níveis são fixos; personalize o rótulo e a cor de cada um.</p>

    <ul class="divide-y divide-border">
      <li v-for="p in meta.priorities" :key="p.uuid" class="flex flex-wrap items-center gap-3 py-2">
        <span class="w-16 text-xs text-subtle">{{ p.slug }}</span>
        <input v-if="drafts[p.uuid]" v-model="drafts[p.uuid]!.color" type="color" class="h-8 w-9 cursor-pointer rounded-lg border border-border bg-transparent" :aria-label="`Cor de ${p.slug}`" />
        <input v-if="drafts[p.uuid]" v-model="drafts[p.uuid]!.name" class="input max-w-xs" :aria-label="`Nome de ${p.slug}`" maxlength="40" />
        <span class="badge" :style="{ backgroundColor: `${drafts[p.uuid]?.color ?? p.color}22`, color: drafts[p.uuid]?.color ?? p.color }">
          {{ drafts[p.uuid]?.name || p.name }}
        </span>
        <button class="btn-primary ml-auto px-3 py-1.5" :disabled="!dirty(p) || savingUuid === p.uuid" @click="save(p)">
          {{ savingUuid === p.uuid ? 'Salvando…' : 'Salvar' }}
        </button>
      </li>
    </ul>
  </section>
</template>
