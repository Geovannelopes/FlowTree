<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/lib/api'
import AppIcon from '@/components/AppIcon.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

// Credenciais do admin já preenchidas em dev/demo (VITE_DEMO_LOGIN=1 no build).
const demo = import.meta.env.VITE_DEMO_LOGIN === '1'
const email = ref(demo ? 'admin@flowtree.test' : '')
const password = ref(demo ? 'password' : '')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/projects'
    await router.push(redirect)
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível entrar.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-full items-center justify-center p-4">
    <div class="panel w-full max-w-sm p-7">
      <div class="mb-6 flex items-center gap-3">
        <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-500 text-white shadow">
          <AppIcon name="tree" class="h-6 w-6" />
        </span>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">FlowTree</p>
          <h1 class="text-xl font-extrabold text-content">Entrar</h1>
        </div>
      </div>

      <form class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label" for="email">E-mail</label>
          <input id="email" v-model="email" type="email" class="input" autocomplete="email" required autofocus />
        </div>
        <div>
          <label class="label" for="password">Senha</label>
          <input id="password" v-model="password" type="password" class="input" autocomplete="current-password" required />
        </div>

        <p v-if="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">{{ error }}</p>

        <button type="submit" class="btn-primary w-full" :disabled="loading">
          {{ loading ? 'Entrando…' : 'Entrar' }}
        </button>

        <RouterLink :to="{ name: 'forgot-password' }" class="block text-center text-sm text-muted hover:text-content">
          Esqueci minha senha
        </RouterLink>
      </form>
    </div>
  </div>
</template>
