<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { api, errorMessage } from '@/lib/api'
import type { Metrics, Sprint, SprintScope, Wrapped } from '@/types'
import AppModal from '@/components/AppModal.vue'
import AppIcon, { type IconName } from '@/components/AppIcon.vue'
import AppSelect, { type SelectOption } from '@/components/AppSelect.vue'
import UserAvatar from '@/components/UserAvatar.vue'

const props = defineProps<{ projectUuid: string; sprints: Sprint[]; initialScope: SprintScope }>()
const emit = defineEmits<{ close: [] }>()

const scope = ref<string>(props.initialScope)
const metrics = ref<Metrics | null>(null)
const loading = ref(true)
const error = ref('')

const scopeOptions = computed<SelectOption[]>(() => [
  { value: 'all', label: 'Todo o projeto' },
  { value: 'backlog', label: 'Backlog' },
  ...props.sprints.map((s) => ({ value: s.uuid, label: s.name })),
])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<Wrapped<Metrics>>(`/projects/${props.projectUuid}/metrics`, { params: { sprint: scope.value } })
    metrics.value = data.data
  } catch (e) {
    error.value = errorMessage(e, 'Falha ao carregar as métricas.')
  } finally {
    loading.value = false
  }
}

watch(scope, load)
onMounted(load)

const kpis = computed(() => {
  const m = metrics.value
  if (!m) return []
  const days = (v: number | null) => (v === null ? '—' : `${v.toFixed(1)} d`)
  return [
    { label: 'Throughput', value: String(m.throughput), hint: 'cards concluídos', icon: 'chart' as IconName },
    { label: 'Ciclo médio', value: days(m.avg_cycle_time_days), hint: 'início → conclusão', icon: 'clock' as IconName },
    { label: 'Lead time médio', value: days(m.avg_lead_time_days), hint: 'criação → conclusão', icon: 'history' as IconName },
    { label: 'Concluídos', value: `${m.cards_done}/${m.cards_total}`, hint: 'do total', icon: 'check' as IconName },
    { label: 'Abertos', value: String(m.cards_open), hint: 'em andamento', icon: 'columns' as IconName },
    { label: 'Atrasados', value: String(m.cards_overdue), hint: 'prazo vencido', icon: 'alert' as IconName, danger: m.cards_overdue > 0 },
  ]
})

const maxColumn = computed(() => Math.max(1, ...(metrics.value?.by_column.map((c) => c.count) ?? [1])))
const maxPriority = computed(() => Math.max(1, ...(metrics.value?.by_priority.map((c) => c.count) ?? [1])))
const maxDay = computed(() => Math.max(1, ...(metrics.value?.completed_by_day.map((d) => d.count) ?? [1])))
const maxAssignee = computed(() => Math.max(1, ...(metrics.value?.by_assignee.map((a) => a.count) ?? [1])))

function dayLabel(date: string): string {
  return date.slice(8, 10)
}
</script>

<template>
  <AppModal title="Métricas" subtitle="Fluxo do quadro: throughput, tempo de ciclo e distribuição." size="xl" @close="emit('close')">
    <div class="mb-4 flex flex-wrap items-center gap-2">
      <span class="text-sm text-muted">Escopo</span>
      <AppSelect v-model="scope" :options="scopeOptions" size="sm" aria-label="Escopo das métricas" class="w-56" />
    </div>

    <p v-if="loading" class="text-sm text-muted">Calculando…</p>
    <p v-else-if="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">{{ error }}</p>

    <div v-else-if="metrics" class="space-y-6">
      <!-- KPIs -->
      <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div v-for="k in kpis" :key="k.label" class="rounded-2xl border border-border bg-surface-2/60 p-3">
          <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-muted">
            <AppIcon :name="k.icon" class="h-3.5 w-3.5" />
            {{ k.label }}
          </div>
          <p class="mt-1 text-2xl font-extrabold" :class="k.danger ? 'text-red-700 dark:text-red-400' : 'text-content'">{{ k.value }}</p>
          <p class="text-[11px] text-subtle">{{ k.hint }}</p>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <!-- Por coluna -->
        <section>
          <h3 class="mb-2 text-sm font-bold text-content">Cards por coluna</h3>
          <ul class="space-y-1.5">
            <li v-for="c in metrics.by_column" :key="c.uuid" class="flex items-center gap-2 text-xs">
              <span class="w-28 truncate text-muted" :title="c.name">{{ c.name }}</span>
              <div class="h-4 flex-1 overflow-hidden rounded-full bg-surface-2">
                <div class="h-full rounded-full transition-all" :style="{ width: `${(c.count / maxColumn) * 100}%`, backgroundColor: c.color }" />
              </div>
              <span class="w-6 text-right font-semibold text-content">{{ c.count }}</span>
            </li>
          </ul>
        </section>

        <!-- Por prioridade -->
        <section>
          <h3 class="mb-2 text-sm font-bold text-content">Cards por prioridade</h3>
          <ul class="space-y-1.5">
            <li v-for="p in metrics.by_priority" :key="p.slug" class="flex items-center gap-2 text-xs">
              <span class="w-28 truncate text-muted">{{ p.name }}</span>
              <div class="h-4 flex-1 overflow-hidden rounded-full bg-surface-2">
                <div class="h-full rounded-full transition-all" :style="{ width: `${(p.count / maxPriority) * 100}%`, backgroundColor: p.color }" />
              </div>
              <span class="w-6 text-right font-semibold text-content">{{ p.count }}</span>
            </li>
          </ul>
        </section>

        <!-- Concluídos por dia -->
        <section>
          <h3 class="mb-2 text-sm font-bold text-content">Concluídos por dia <span class="font-normal text-subtle">(últimos 30 dias)</span></h3>
          <div class="flex h-28 items-end gap-[3px] rounded-2xl border border-border bg-surface-2/40 p-2">
            <div
              v-for="d in metrics.completed_by_day"
              :key="d.date"
              class="group relative flex-1 rounded-t bg-brand-400/80 transition hover:bg-brand-500"
              :style="{ height: `${Math.max(4, (d.count / maxDay) * 100)}%`, opacity: d.count ? 1 : 0.25 }"
              :title="`${d.date}: ${d.count}`"
            />
          </div>
          <div class="mt-1 flex justify-between text-[10px] text-subtle">
            <span>{{ dayLabel(metrics.completed_by_day[0]?.date ?? '') }}</span>
            <span>{{ dayLabel(metrics.completed_by_day[metrics.completed_by_day.length - 1]?.date ?? '') }}</span>
          </div>
        </section>

        <!-- Por responsável -->
        <section>
          <h3 class="mb-2 text-sm font-bold text-content">Por responsável</h3>
          <p v-if="!metrics.by_assignee.length" class="text-xs text-subtle">Nenhum card atribuído.</p>
          <ul v-else class="space-y-1.5">
            <li v-for="(a, i) in metrics.by_assignee" :key="a.user?.uuid ?? `none-${i}`" class="flex items-center gap-2 text-xs">
              <UserAvatar :name="a.user?.name ?? 'Sem responsável'" :src="a.user?.avatar_url" :size="22" />
              <span class="w-24 truncate text-muted">{{ a.user?.name ?? 'Sem responsável' }}</span>
              <div class="h-4 flex-1 overflow-hidden rounded-full bg-surface-2">
                <div class="relative h-full rounded-full bg-brand-200 dark:bg-brand-700" :style="{ width: `${(a.count / maxAssignee) * 100}%` }">
                  <div class="h-full rounded-full bg-brand-500" :style="{ width: `${a.count ? (a.done / a.count) * 100 : 0}%` }" />
                </div>
              </div>
              <span class="w-12 text-right font-semibold text-content">{{ a.done }}/{{ a.count }}</span>
            </li>
          </ul>
        </section>
      </div>
    </div>

    <template #footer>
      <button class="btn-secondary" @click="emit('close')">Fechar</button>
    </template>
  </AppModal>
</template>
