<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from '@/components/AppSidebar.vue'
import AppIcon from '@/components/AppIcon.vue'

const COLLAPSE_KEY = 'flowtree_sidebar_collapsed'

const route = useRoute()
const collapsed = ref(localStorage.getItem(COLLAPSE_KEY) === '1')
const drawerOpen = ref(false)
const isMobile = ref(window.innerWidth < 1024)

function onResize() {
  isMobile.value = window.innerWidth < 1024
  if (!isMobile.value) drawerOpen.value = false
}
onMounted(() => window.addEventListener('resize', onResize))
onBeforeUnmount(() => window.removeEventListener('resize', onResize))

function toggleCollapsed() {
  if (isMobile.value) {
    drawerOpen.value = !drawerOpen.value
    return
  }
  collapsed.value = !collapsed.value
  localStorage.setItem(COLLAPSE_KEY, collapsed.value ? '1' : '0')
}

// Fecha o drawer ao navegar.
watch(() => route.fullPath, () => (drawerOpen.value = false))

// Telas "fluid" (board) usam toda a largura e controlam a própria rolagem.
const fluid = computed(() => route.meta.fluid === true)
const title = computed(() => route.meta.title as string | undefined)
</script>

<template>
  <div class="flex h-full overflow-hidden">
    <!-- Sidebar desktop -->
    <div class="hidden h-full shrink-0 lg:block">
      <AppSidebar :collapsed="collapsed" :mobile="false" @toggle="toggleCollapsed" />
    </div>

    <!-- Drawer mobile -->
    <div v-if="drawerOpen" class="fixed inset-0 z-40 lg:hidden">
      <div class="absolute inset-0 bg-black/50" @click="drawerOpen = false" />
      <div class="absolute inset-y-0 left-0 shadow-2xl">
        <AppSidebar :collapsed="false" :mobile="true" @toggle="drawerOpen = false" @navigate="drawerOpen = false" />
      </div>
    </div>

    <div class="flex min-w-0 flex-1 flex-col">
      <!-- Barra mobile -->
      <header class="flex items-center gap-3 border-b border-border bg-surface px-4 py-2.5 lg:hidden">
        <button class="rounded-lg p-1.5 text-muted hover:bg-surface-2" aria-label="Abrir menu" @click="drawerOpen = true">
          <AppIcon name="menu" class="h-5 w-5" />
        </button>
        <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-moss">FlowTree</span>
        <span v-if="title" class="truncate text-sm font-semibold text-content">· {{ title }}</span>
      </header>

      <main class="min-h-0 flex-1" :class="fluid ? 'flex flex-col overflow-hidden p-4 sm:p-5' : 'overflow-y-auto px-4 py-6 sm:px-8'">
        <div :class="fluid ? 'flex min-h-0 flex-1 flex-col' : 'mx-auto w-full max-w-6xl'">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
