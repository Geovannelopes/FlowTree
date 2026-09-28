import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, getToken, setToken } from '@/lib/api'
import type { User, Wrapped } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const ready = ref(false)

  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => !!user.value?.is_admin)

  function hasModule(slug: string): boolean {
    if (!user.value) return false
    if (user.value.is_admin) return true
    return (user.value.modules ?? []).some((m) => m.slug === slug)
  }

  async function login(email: string, password: string): Promise<void> {
    const { data } = await api.post<{ token: string; user: User }>('/login', { email, password })
    setToken(data.token)
    user.value = data.user
  }

  /** Reidrata o usuário a partir do token salvo (chamado no boot). */
  async function fetchMe(): Promise<void> {
    if (!getToken()) {
      ready.value = true
      return
    }
    try {
      const { data } = await api.get<Wrapped<User>>('/me')
      user.value = data.data
    } catch {
      setToken(null)
      user.value = null
    } finally {
      ready.value = true
    }
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/logout')
    } catch {
      // limpa localmente de qualquer forma
    }
    setToken(null)
    user.value = null
  }

  function setUser(u: User): void {
    user.value = u
  }

  return { user, ready, isAuthenticated, isAdmin, hasModule, login, fetchMe, logout, setUser }
})
