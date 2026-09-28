import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/lib/api'
import type { AppNotification, Paginated, Wrapped } from '@/types'

const POLL_MS = 30_000

/** Sininho: lista recente + contador; polling a cada 30s e push via Reverb. */
export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([])
  const unread = ref(0)
  const loading = ref(false)
  let timer: number | null = null

  const hasUnread = computed(() => unread.value > 0)

  async function fetchUnreadCount() {
    try {
      const { data } = await api.get<{ count: number }>('/notifications/unread-count')
      unread.value = data.count
    } catch {
      // silencioso — o polling tenta de novo
    }
  }

  async function fetchList() {
    loading.value = true
    try {
      const { data } = await api.get<Paginated<AppNotification>>('/notifications')
      items.value = data.data
    } finally {
      loading.value = false
    }
  }

  /** Recebida em tempo real: entra no topo e incrementa o contador. */
  function pushRealtime(n: AppNotification) {
    if (items.value.some((i) => i.id === n.id)) return
    items.value.unshift(n)
    unread.value += 1
  }

  async function markRead(id: string) {
    const { data } = await api.post<Wrapped<AppNotification>>(`/notifications/${id}/read`)
    const i = items.value.findIndex((n) => n.id === id)
    if (i !== -1) {
      const wasUnread = !items.value[i]!.read
      items.value[i] = data.data
      if (wasUnread) unread.value = Math.max(0, unread.value - 1)
    }
  }

  async function markAllRead() {
    await api.post('/notifications/read-all')
    items.value = items.value.map((n) => ({ ...n, read: true }))
    unread.value = 0
  }

  async function remove(id: string) {
    await api.delete(`/notifications/${id}`)
    const i = items.value.findIndex((n) => n.id === id)
    if (i !== -1) {
      if (!items.value[i]!.read) unread.value = Math.max(0, unread.value - 1)
      items.value.splice(i, 1)
    }
  }

  function startPolling() {
    void fetchUnreadCount()
    if (timer) return
    timer = window.setInterval(() => void fetchUnreadCount(), POLL_MS)
  }

  function reset() {
    if (timer) window.clearInterval(timer)
    timer = null
    items.value = []
    unread.value = 0
  }

  return {
    items,
    unread,
    loading,
    hasUnread,
    fetchUnreadCount,
    fetchList,
    pushRealtime,
    markRead,
    markAllRead,
    remove,
    startPolling,
    reset,
  }
})
