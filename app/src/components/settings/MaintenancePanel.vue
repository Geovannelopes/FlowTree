<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import { useMetaStore } from '@/stores/meta'
import { useNotificationsStore } from '@/stores/notifications'
import { useToastStore } from '@/stores/toast'
import AppIcon from '@/components/AppIcon.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import type { MaintenanceResetMode, MaintenanceResetResult, Wrapped } from '@/types'

const router = useRouter()
const meta = useMetaStore()
const notifications = useNotificationsStore()
const toast = useToastStore()

interface ResetOption {
  mode: MaintenanceResetMode
  title: string
  description: string
  action: string
  confirmTitle: string
  confirmBody: string
}

const OPTIONS: ResetOption[] = [
  {
    mode: 'demo',
    title: 'Restaurar dados de demonstração',
    description: 'Apaga tudo e recria a equipe fictícia, as flags e os cinco projetos de exemplo, com sprints, cards, comentários e anexos.',
    action: 'Restaurar demonstração',
    confirmTitle: 'Restaurar dados de demonstração?',
    confirmBody:
      'Todos os projetos, cards, flags, notificações e usuários atuais serão apagados e substituídos pelo cenário de demonstração. Só a sua conta de administrador é mantida.',
  },
  {
    mode: 'clean',
    title: 'Limpar tudo',
    description: 'Apaga todos os projetos, flags, notificações e usuários. Fica apenas a sua conta de administrador, com módulos e prioridades nos valores padrão.',
    action: 'Limpar banco de dados',
    confirmTitle: 'Limpar todo o banco de dados?',
    confirmBody: 'Todos os dados serão apagados permanentemente. Só a sua conta de administrador é mantida. Esta ação não pode ser desfeita.',
  },
]

const selected = ref<ResetOption | null>(null)
const running = ref(false)

function close() {
  if (running.value) return
  selected.value = null
}

async function confirm() {
  if (!selected.value || running.value) return
  running.value = true
  try {
    const { data } = await api.post<Wrapped<MaintenanceResetResult>>('/maintenance/reset', { mode: selected.value.mode })
    const { summary } = data.data
    selected.value = null
    notifications.reset()
    await meta.reload()
    toast.success(
      data.data.mode === 'demo'
        ? `Demonstração restaurada: ${summary.projects} projetos, ${summary.cards} cards e ${summary.users} usuários.`
        : 'Banco de dados limpo. Apenas a sua conta foi mantida.',
    )
    await router.push({ name: 'projects' })
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao resetar o banco de dados.'))
  } finally {
    running.value = false
  }
}
</script>

<template>
  <section class="panel border-red-200 p-5 lg:col-span-2 dark:border-red-900/50">
    <div class="flex items-start gap-3">
      <span class="mt-0.5 rounded-full bg-red-100 p-1.5 text-red-700 dark:bg-red-900/40 dark:text-red-300">
        <AppIcon name="alert" class="h-4 w-4" />
      </span>
      <div>
        <h2 class="text-base font-bold text-content">Banco de dados</h2>
        <p class="text-sm text-muted">Ações destrutivas, visíveis apenas para administradores. Não podem ser desfeitas.</p>
      </div>
    </div>

    <ul class="mt-4 grid gap-3 md:grid-cols-2">
      <li v-for="option in OPTIONS" :key="option.mode" class="flex flex-col rounded-xl border border-border bg-surface-2/60 p-4">
        <h3 class="text-sm font-semibold text-content">{{ option.title }}</h3>
        <p class="mt-1 flex-1 text-sm text-muted">{{ option.description }}</p>
        <button class="btn-danger mt-3 self-start" @click="selected = option">{{ option.action }}</button>
      </li>
    </ul>

    <ConfirmDialog
      v-if="selected"
      :title="selected.confirmTitle"
      :message="selected.confirmBody"
      :confirm-label="selected.action"
      danger
      :loading="running"
      @confirm="confirm"
      @cancel="close"
    />
  </section>
</template>
