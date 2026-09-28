<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, errorMessage } from '@/lib/api'
import { useToastStore } from '@/stores/toast'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()

const email = ref(typeof route.query.email === 'string' ? route.query.email : '')
const token = typeof route.query.token === 'string' ? route.query.token : ''
const password = ref('')
const confirmation = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await api.post('/reset-password', {
      token,
      email: email.value,
      password: password.value,
      password_confirmation: confirmation.value,
    })
    toast.success('Senha redefinida. Faça login com a nova senha.')
    await router.push({ name: 'login' })
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível redefinir a senha.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-full items-center justify-center p-4">
    <div class="panel w-full max-w-sm p-7">
      <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">FlowTree</p>
      <h1 class="mb-5 text-xl font-extrabold text-content">Nova senha</h1>

      <p v-if="!token" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">
        Link inválido: abra o endereço recebido por e-mail.
      </p>

      <form v-else class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label" for="email">E-mail</label>
          <input id="email" v-model="email" type="email" class="input" required />
        </div>
        <div>
          <label class="label" for="password">Nova senha</label>
          <input id="password" v-model="password" type="password" class="input" minlength="8" required autocomplete="new-password" />
        </div>
        <div>
          <label class="label" for="confirmation">Confirmar senha</label>
          <input id="confirmation" v-model="confirmation" type="password" class="input" minlength="8" required autocomplete="new-password" />
        </div>
        <p v-if="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">{{ error }}</p>
        <button type="submit" class="btn-primary w-full" :disabled="loading">{{ loading ? 'Salvando…' : 'Redefinir senha' }}</button>
      </form>

      <RouterLink :to="{ name: 'login' }" class="mt-4 block text-center text-sm text-muted hover:text-content">Voltar ao login</RouterLink>
    </div>
  </div>
</template>
