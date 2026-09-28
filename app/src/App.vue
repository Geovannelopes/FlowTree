<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { initEcho, getEcho, teardownEcho } from '@/lib/echo'
import type { AppNotification } from '@/types'
import AppLayout from '@/components/AppLayout.vue'
import ToastHost from '@/components/ToastHost.vue'

const route = useRoute()
const auth = useAuthStore()
const notifications = useNotificationsStore()

// Login e telas públicas não usam o shell (sidebar).
const useChrome = computed(() => auth.isAuthenticated && route.meta.public !== true)

let notifChannel: string | null = null

function subscribeNotifications(uuid: string): void {
  const echo = initEcho()
  if (!echo) return
  notifChannel = `notifications.${uuid}`
  echo
    .private(notifChannel)
    .listen('.notification.created', (n: AppNotification) => notifications.pushRealtime(n))
}

function unsubscribeNotifications(): void {
  if (notifChannel) {
    getEcho()?.leave(notifChannel)
    notifChannel = null
  }
}

watch(
  () => auth.isAuthenticated,
  (yes) => {
    if (yes) {
      notifications.startPolling()
      if (auth.user) subscribeNotifications(auth.user.uuid)
    } else {
      notifications.reset()
      unsubscribeNotifications()
      teardownEcho()
    }
  },
  { immediate: true },
)
</script>

<template>
  <AppLayout v-if="useChrome">
    <router-view />
  </AppLayout>
  <router-view v-else />

  <ToastHost />
</template>
