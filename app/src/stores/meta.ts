import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'
import type { Flag, Priority, PriorityOption, User, Wrapped } from '@/types'

/**
 * Taxonomias globais do board (flags e prioridades) e usuários atribuíveis.
 * Carregadas uma vez; as telas de Configurações chamam `reload()` após editar.
 */
export const useMetaStore = defineStore('meta', () => {
  const flags = ref<Flag[]>([])
  const priorities = ref<PriorityOption[]>([])
  const assignees = ref<User[]>([])
  const loaded = ref(false)

  async function load(force = false) {
    if (loaded.value && !force) return
    const [f, p, u] = await Promise.all([
      api.get<Wrapped<Flag[]>>('/flags'),
      api.get<Wrapped<PriorityOption[]>>('/priorities'),
      api.get<Wrapped<User[]>>('/assignable-users'),
    ])
    flags.value = f.data.data
    priorities.value = p.data.data
    assignees.value = u.data.data
    loaded.value = true
  }

  const reload = () => load(true)

  const priorityBySlug = computed(() => {
    const map: Partial<Record<Priority, PriorityOption>> = {}
    for (const p of priorities.value) map[p.slug] = p
    return map
  })

  function priorityMeta(slug: Priority): { name: string; color: string } {
    const p = priorityBySlug.value[slug]
    return { name: p?.name ?? slug, color: p?.color ?? '#8c9a92' }
  }

  return { flags, priorities, assignees, loaded, load, reload, priorityBySlug, priorityMeta }
})
