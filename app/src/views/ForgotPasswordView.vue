<script setup lang="ts">
import { ref } from 'vue'
import { api, errorMessage } from '@/lib/api'

const email = ref('')
const loading = ref(false)
const done = ref('')
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.post<{ message: string }>('/forgot-password', { email: email.value })
    done.value = data.message
  } catch (e) {
    error.value = errorMessage(e, 'Não foi possível enviar o e-mail.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-full items-center justify-center p-4">
    <div class="panel w-full max-w-sm p-7">
      <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">FlowTree</p>
      <h1 class="mb-1 text-xl font-extrabold text-content">Recuperar senha</h1>
      <p class="mb-5 text-sm text-muted">Informe seu e-mail e enviaremos um link para redefinir a senha.</p>

      <p v-if="done" class="rounded-xl bg-brand-100 px-3 py-2 text-sm text-brand-700 dark:bg-brand-900/40 dark:text-brand-100">{{ done }}</p>

      <form v-else class="space-y-4" @submit.prevent="submit">
        <div>
          <label class="label" for="email">E-mail</label>
          <input id="email" v-model="email" type="email" class="input" required autofocus />
        </div>
        <p v-if="error" class="rounded-xl bg-red-100 px-3 py-2 text-sm text-red-800 dark:bg-red-900/40 dark:text-red-200">{{ error }}</p>
        <button type="submit" class="btn-primary w-full" :disabled="loading">{{ loading ? 'Enviando…' : 'Enviar link' }}</button>
      </form>

      <RouterLink :to="{ name: 'login' }" class="mt-4 block text-center text-sm text-muted hover:text-content">Voltar ao login</RouterLink>
    </div>
  </div>
</template>
