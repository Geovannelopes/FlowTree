<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useTheme } from '@/composables/useTheme'
import { navigation } from '@/config/navigation'
import AppIcon from '@/components/AppIcon.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import NotificationBell from '@/components/NotificationBell.vue'

const props = defineProps<{ collapsed: boolean; mobile: boolean }>()
const emit = defineEmits<{ toggle: []; navigate: [] }>()

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const { theme, toggle: toggleTheme } = useTheme()

const groups = computed(() => navigation.filter((g) => !g.module || auth.hasModule(g.module)))

function isActive(prefix?: string): boolean {
  return !!prefix && route.path.startsWith(prefix)
}

async function logout() {
  await auth.logout()
  await router.push({ name: 'login' })
}

// No mobile a sidebar é um drawer aberto: nunca colapsa em rail.
const rail = computed(() => props.collapsed && !props.mobile)
</script>

<template>
  <aside
    class="flex h-full flex-col bg-brand-800 text-brand-100 transition-[width] duration-200"
    :class="rail ? 'w-[68px]' : 'w-64'"
  >
    <!-- Marca -->
    <div class="flex items-center gap-3 px-4 py-4">
      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-500 text-white shadow">
        <AppIcon name="tree" class="h-5 w-5" />
      </span>
      <div v-if="!rail" class="min-w-0">
        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-300">FlowTree</p>
        <p class="truncate text-sm font-bold text-white">Kanban</p>
      </div>
      <button
        v-if="!mobile"
        class="ml-auto rounded-lg p-1.5 text-brand-300 hover:bg-white/10 hover:text-white"
        :class="{ 'ml-0': rail }"
        :aria-label="collapsed ? 'Expandir menu' : 'Recolher menu'"
        @click="emit('toggle')"
      >
        <AppIcon :name="collapsed ? 'chevronRight' : 'chevronLeft'" class="h-4 w-4" />
      </button>
      <button v-else class="ml-auto rounded-lg p-1.5 text-brand-300 hover:bg-white/10 hover:text-white" aria-label="Fechar menu" @click="emit('toggle')">
        <AppIcon name="close" class="h-4 w-4" />
      </button>
    </div>

    <!-- Navegação por módulo -->
    <nav class="flex-1 space-y-4 overflow-y-auto px-3 py-2">
      <div v-for="g in groups" :key="g.key">
        <p v-if="!rail" class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-brand-300/80">{{ g.label }}</p>
        <RouterLink
          v-for="item in g.items"
          :key="item.label"
          :to="item.to"
          class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition"
          :class="[
            isActive(item.matchPrefix) ? 'bg-brand-500 text-white shadow' : 'text-brand-100/80 hover:bg-white/10 hover:text-white',
            rail ? 'justify-center' : '',
          ]"
          :title="rail ? item.label : undefined"
          @click="emit('navigate')"
        >
          <AppIcon :name="item.icon" class="h-5 w-5 shrink-0" />
          <span v-if="!rail" class="truncate">{{ item.label }}</span>
        </RouterLink>
      </div>
    </nav>

    <!-- Rodapé: notificações, perfil, tema, sair -->
    <div class="space-y-1 border-t border-white/10 px-3 py-3">
      <NotificationBell :collapsed="rail" @navigate="emit('navigate')" />

      <RouterLink
        :to="{ name: 'profile' }"
        class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm hover:bg-white/10"
        :class="[rail ? 'justify-center' : '', route.name === 'profile' ? 'bg-white/10 text-white' : 'text-brand-100/80']"
        :title="rail ? 'Meu perfil' : undefined"
        @click="emit('navigate')"
      >
        <UserAvatar v-if="auth.user" :name="auth.user.name" :src="auth.user.avatar_url" :size="28" />
        <span v-if="!rail && auth.user" class="min-w-0 flex-1">
          <span class="block truncate text-sm font-semibold text-white">{{ auth.user.name }}</span>
          <span class="block truncate text-[11px] text-brand-300">{{ auth.user.position ?? auth.user.email }}</span>
        </span>
      </RouterLink>

      <div class="flex gap-1" :class="rail ? 'flex-col' : ''">
        <button
          class="flex flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-brand-100/80 hover:bg-white/10 hover:text-white"
          :aria-label="theme === 'dark' ? 'Tema claro' : 'Tema escuro'"
          :title="theme === 'dark' ? 'Tema claro' : 'Tema escuro'"
          @click="toggleTheme"
        >
          <AppIcon :name="theme === 'dark' ? 'sun' : 'moon'" class="h-4 w-4" />
          <span v-if="!rail">{{ theme === 'dark' ? 'Claro' : 'Escuro' }}</span>
        </button>
        <button
          class="flex flex-1 items-center justify-center gap-2 rounded-xl px-3 py-2 text-xs font-medium text-brand-100/80 hover:bg-white/10 hover:text-white"
          aria-label="Sair"
          title="Sair"
          @click="logout"
        >
          <AppIcon name="logout" class="h-4 w-4" />
          <span v-if="!rail">Sair</span>
        </button>
      </div>
    </div>
  </aside>
</template>
