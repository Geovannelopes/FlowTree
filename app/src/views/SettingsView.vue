<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useMetaStore } from '@/stores/meta'
import { errorMessage } from '@/lib/api'
import FlagsManager from '@/components/settings/FlagsManager.vue'
import PrioritiesManager from '@/components/settings/PrioritiesManager.vue'
import MaintenancePanel from '@/components/settings/MaintenancePanel.vue'

const auth = useAuthStore()
const meta = useMetaStore()
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    await meta.load(true)
  } catch (e) {
    error.value = errorMessage(e, 'Falha ao carregar as configurações.')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="space-y-5">
    <div>
      <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">Sistema</p>
      <h1 class="text-2xl font-extrabold text-content">Configurações</h1>
    </div>

    <p v-if="loading" class="text-sm text-muted">Carregando…</p>
    <p v-else-if="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">{{ error }}</p>
    <div v-else class="grid gap-6 lg:grid-cols-2">
      <FlagsManager />
      <PrioritiesManager />
      <MaintenancePanel v-if="auth.isAdmin" />
    </div>
  </div>
</template>
