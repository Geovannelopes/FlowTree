<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import { api, errorMessage } from '@/lib/api'
import type { User, Wrapped } from '@/types'
import UserAvatar from '@/components/UserAvatar.vue'

const auth = useAuthStore()
const toast = useToastStore()

const name = ref(auth.user?.name ?? '')
const position = ref(auth.user?.position ?? '')
const savingProfile = ref(false)

const currentPassword = ref('')
const password = ref('')
const confirmation = ref('')
const savingPassword = ref(false)

const avatarInput = ref<HTMLInputElement | null>(null)
const savingAvatar = ref(false)

async function saveProfile() {
  savingProfile.value = true
  try {
    const { data } = await api.put<Wrapped<User>>('/me', { name: name.value, position: position.value || null })
    auth.setUser(data.data)
    toast.success('Perfil atualizado.')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao salvar o perfil.'))
  } finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  savingPassword.value = true
  try {
    await api.put('/me/password', {
      current_password: currentPassword.value,
      password: password.value,
      password_confirmation: confirmation.value,
    })
    currentPassword.value = password.value = confirmation.value = ''
    toast.success('Senha alterada.')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao alterar a senha.'))
  } finally {
    savingPassword.value = false
  }
}

async function uploadAvatar(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  savingAvatar.value = true
  try {
    const form = new FormData()
    form.append('avatar', file)
    const { data } = await api.post<Wrapped<User>>('/me/avatar', form)
    auth.setUser(data.data)
    toast.success('Foto atualizada.')
  } catch (err) {
    toast.error(errorMessage(err, 'Falha ao enviar a foto.'))
  } finally {
    savingAvatar.value = false
    if (avatarInput.value) avatarInput.value.value = ''
  }
}

async function removeAvatar() {
  savingAvatar.value = true
  try {
    const { data } = await api.delete<Wrapped<User>>('/me/avatar')
    auth.setUser(data.data)
    toast.success('Foto removida.')
  } catch (e) {
    toast.error(errorMessage(e, 'Falha ao remover a foto.'))
  } finally {
    savingAvatar.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">FlowTree</p>
      <h1 class="text-2xl font-extrabold text-content">Meu perfil</h1>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="panel p-5">
        <h2 class="mb-4 text-base font-bold text-content">Foto</h2>
        <div v-if="auth.user" class="flex items-center gap-4">
          <UserAvatar :name="auth.user.name" :src="auth.user.avatar_url" :size="72" />
          <div class="flex flex-wrap gap-2">
            <label class="btn-secondary" for="avatar">Enviar foto</label>
            <input id="avatar" ref="avatarInput" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" :disabled="savingAvatar" @change="uploadAvatar" />
            <button v-if="auth.user.avatar_url" class="btn-ghost" :disabled="savingAvatar" @click="removeAvatar">Remover</button>
          </div>
        </div>
        <p class="mt-3 text-xs text-subtle">JPG, PNG ou WebP até 5 MB.</p>
      </section>

      <section class="panel p-5">
        <h2 class="mb-4 text-base font-bold text-content">Dados</h2>
        <form class="space-y-4" @submit.prevent="saveProfile">
          <div>
            <label class="label" for="name">Nome</label>
            <input id="name" v-model="name" class="input" required />
          </div>
          <div>
            <label class="label" for="position">Cargo</label>
            <input id="position" v-model="position" class="input" placeholder="Ex.: Desenvolvedora" />
          </div>
          <div>
            <label class="label">E-mail</label>
            <input class="input opacity-70" :value="auth.user?.email" disabled />
          </div>
          <div class="flex justify-end">
            <button type="submit" class="btn-primary" :disabled="savingProfile">{{ savingProfile ? 'Salvando…' : 'Salvar' }}</button>
          </div>
        </form>
      </section>

      <section class="panel p-5 lg:col-span-2">
        <h2 class="mb-4 text-base font-bold text-content">Senha</h2>
        <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="savePassword">
          <div>
            <label class="label" for="current">Senha atual</label>
            <input id="current" v-model="currentPassword" type="password" class="input" required autocomplete="current-password" />
          </div>
          <div>
            <label class="label" for="new">Nova senha</label>
            <input id="new" v-model="password" type="password" class="input" minlength="8" required autocomplete="new-password" />
          </div>
          <div>
            <label class="label" for="confirm">Confirmar</label>
            <input id="confirm" v-model="confirmation" type="password" class="input" minlength="8" required autocomplete="new-password" />
          </div>
          <div class="flex justify-end sm:col-span-3">
            <button type="submit" class="btn-primary" :disabled="savingPassword">{{ savingPassword ? 'Salvando…' : 'Alterar senha' }}</button>
          </div>
        </form>
      </section>
    </div>
  </div>
</template>
