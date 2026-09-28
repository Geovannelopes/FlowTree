<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { useToastStore } from '@/stores/toast'
import type { Module, User, Wrapped } from '@/types'
import AppModal from '@/components/AppModal.vue'

const props = defineProps<{ user: User | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const toast = useToastStore()

const name = ref(props.user?.name ?? '')
const email = ref(props.user?.email ?? '')
const password = ref('')
const position = ref(props.user?.position ?? '')
const isAdmin = ref(props.user?.is_admin ?? false)
const isActive = ref(props.user?.is_active ?? true)
const moduleSlugs = ref<string[]>((props.user?.modules ?? []).map((m) => m.slug))
const modules = ref<Module[]>([])
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const isEdit = computed(() => !!props.user)

function toggleModule(slug: string) {
  const i = moduleSlugs.value.indexOf(slug)
  if (i === -1) moduleSlugs.value.push(slug)
  else moduleSlugs.value.splice(i, 1)
}

async function submit() {
  saving.value = true
  errors.value = {}
  const payload: Record<string, unknown> = {
    name: name.value,
    email: email.value,
    position: position.value || null,
    is_admin: isAdmin.value,
    is_active: isActive.value,
    module_slugs: moduleSlugs.value,
  }
  if (password.value || !isEdit.value) payload.password = password.value
  try {
    if (isEdit.value) await api.put(`/users/${props.user!.uuid}`, payload)
    else await api.post('/users', payload)
    toast.success(isEdit.value ? 'Usuário atualizado.' : 'Usuário criado.')
    emit('saved')
  } catch (e) {
    errors.value = fieldErrors(e)
    toast.error(errorMessage(e, 'Falha ao salvar o usuário.'))
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get<Wrapped<Module[]>>('/modules')
    modules.value = data.data
  } catch {
    toast.error('Falha ao carregar os módulos.')
  }
})
</script>

<template>
  <AppModal :title="isEdit ? 'Editar usuário' : 'Novo usuário'" @close="emit('close')">
    <form id="user-form" class="space-y-4" @submit.prevent="submit">
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label" for="u-name">Nome</label>
          <input id="u-name" v-model="name" class="input" required autofocus />
          <p v-if="errors.name" class="mt-1 text-xs text-red-700">{{ errors.name }}</p>
        </div>
        <div>
          <label class="label" for="u-email">E-mail</label>
          <input id="u-email" v-model="email" type="email" class="input" required />
          <p v-if="errors.email" class="mt-1 text-xs text-red-700">{{ errors.email }}</p>
        </div>
        <div>
          <label class="label" for="u-password">{{ isEdit ? 'Nova senha (opcional)' : 'Senha' }}</label>
          <input id="u-password" v-model="password" type="password" class="input" :required="!isEdit" minlength="8" autocomplete="new-password" />
          <p v-if="errors.password" class="mt-1 text-xs text-red-700">{{ errors.password }}</p>
        </div>
        <div>
          <label class="label" for="u-position">Cargo</label>
          <input id="u-position" v-model="position" class="input" />
        </div>
      </div>

      <div class="flex flex-wrap gap-4">
        <label class="flex items-center gap-2 text-sm text-content">
          <input v-model="isAdmin" type="checkbox" class="accent-brand-500" />
          Administrador (acesso a tudo)
        </label>
        <label class="flex items-center gap-2 text-sm text-content">
          <input v-model="isActive" type="checkbox" class="accent-brand-500" />
          Ativo
        </label>
      </div>

      <div>
        <label class="label">Módulos</label>
        <div class="grid gap-2 sm:grid-cols-3">
          <label
            v-for="m in modules"
            :key="m.slug"
            class="flex cursor-pointer items-start gap-2 rounded-xl border border-border px-3 py-2 text-sm hover:bg-surface-2"
            :class="{ 'opacity-60': isAdmin }"
          >
            <input type="checkbox" class="mt-0.5 accent-brand-500" :checked="isAdmin || moduleSlugs.includes(m.slug)" :disabled="isAdmin" @change="toggleModule(m.slug)" />
            <span>
              <span class="block font-medium text-content">{{ m.name }}</span>
              <span class="block text-xs text-subtle">{{ m.description }}</span>
            </span>
          </label>
        </div>
      </div>
    </form>

    <template #footer>
      <button type="button" class="btn-secondary" @click="emit('close')">Cancelar</button>
      <button type="submit" form="user-form" class="btn-primary" :disabled="saving">{{ saving ? 'Salvando…' : 'Salvar' }}</button>
    </template>
  </AppModal>
</template>
