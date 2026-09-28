import { ref } from 'vue'

export type ThemeMode = 'light' | 'dark'

const KEY = 'flowtree_theme'

function initial(): ThemeMode {
  const stored = localStorage.getItem(KEY)
  if (stored === 'light' || stored === 'dark') return stored
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

// Estado compartilhado (singleton de módulo).
const theme = ref<ThemeMode>(initial())

const THEME_COLOR: Record<ThemeMode, string> = { light: '#eef2ea', dark: '#141a16' }

function apply(t: ThemeMode) {
  document.documentElement.dataset.theme = t
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content', THEME_COLOR[t])
}

apply(theme.value)

export function useTheme() {
  function setTheme(t: ThemeMode) {
    theme.value = t
    localStorage.setItem(KEY, t)
    apply(t)
  }

  function toggle() {
    setTheme(theme.value === 'dark' ? 'light' : 'dark')
  }

  return { theme, setTheme, toggle }
}
