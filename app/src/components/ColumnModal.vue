<script setup lang="ts">
import { computed, ref } from 'vue'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import { COLUMN_PALETTE, nextColumnColor } from '@/config/palette'
import type { BoardColumn, Wrapped } from '@/types'
import AppModal from '@/components/AppModal.vue'

const props = defineProps<{ projectUuid: string; column: BoardColumn | null; columnsCount: number }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const toast = useToastStore()

const name = ref(props.column?.name ?? '')
const color = ref(props.column?.color ?? nextColumnColor(props.columnsCount))
const wipLimit = ref<number | ''>(props.column?.wip_limit ?? '')
const isDone = ref(props.column?.is_done ?? false)
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const isEdit = computed(() => !!props.column)

async function submit() {
  saving.value = true
  errors.value = {}
  const payload = {
    name: name.value.trim(),
    color: color.value,
    wip_limit: wipLimit.value === '' ? null : Number(wipLimit.value),
    is_done: isDone.value,
  }
  try {
    if (isEdit.value) await api.put<Wrapped<BoardColumn>>(`/columns/${props.column!.uuid}`, payload)
    else await api.post<Wrapped<BoardColumn>>(`/projects/${props.projectUuid}/columns`, payload)
    toast.success(isEdit.value ? 'Coluna atualizada.' : 'Coluna criada.')
    emit('saved')
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.error(errorMessage(e, 'Falha ao salvar a coluna.'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal :title="isEdit ? 'Editar coluna' : 'Nova coluna'" size="sm" @close="emit('close')">
    <form id="column-form" class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="label" for="c-name">Nome</label>
        <input id="c-name" v-model="name" class="input" required maxlength="80" autofocus placeholder="Ex.: Em revisão" />
        <p v-if="errors.name" class="mt-1 text-xs text-red-700">{{ errors.name }}</p>
      </div>
      <div>
        <label class="label">Cor de destaque</label>
        <div class="flex flex-wrap items-center gap-2">
          <button
            v-for="c in COLUMN_PALETTE"
            :key="c"
            type="button"
            class="h-7 w-7 rounded-full ring-2 ring-offset-2 ring-offset-surface"
            :class="color === c ? 'ring-content' : 'ring-transparent hover:ring-border'"
            :style="{ backgroundColor: c }"
            :aria-label="`Cor ${c}`"
            @click="color = c"
          />
          <input v-model="color" type="color" class="h-7 w-9 cursor-pointer rounded border border-border bg-transparent" aria-label="Cor personalizada" />
        </div>
      </div>
      <div>
        <label class="label" for="c-wip">Limite de WIP (opcional)</label>
        <input id="c-wip" v-model="wipLimit" type="number" min="1" class="input" placeholder="Sem limite" />
        <p class="mt-1 text-xs text-subtle">Quantidade máxima de cards na coluna (todas as sprints).</p>
      </div>
      <label class="flex items-start gap-2 text-sm text-content">
        <input v-model="isDone" type="checkbox" class="mt-0.5 accent-brand-500" />
        <span>
          Coluna de conclusão
          <span class="block text-xs text-subtle">Cards que entram aqui contam como concluídos (métricas e sprint).</span>
        </span>
      </label>
    </form>

    <template #footer>
      <button type="button" class="btn-secondary" @click="emit('close')">Cancelar</button>
      <button type="submit" form="column-form" class="btn-primary" :disabled="saving">{{ saving ? 'Salvando…' : 'Salvar' }}</button>
    </template>
  </AppModal>
</template>
