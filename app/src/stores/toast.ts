import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: number
  type: ToastType
  message: string
}

/** Notificações transitórias (toasts) no canto da tela. */
export const useToastStore = defineStore('toast', () => {
  const toasts = ref<Toast[]>([])
  let seq = 0

  function push(type: ToastType, message: string, timeout = 4000): number {
    const id = ++seq
    toasts.value.push({ id, type, message })
    if (timeout > 0) window.setTimeout(() => dismiss(id), timeout)
    return id
  }

  function dismiss(id: number): void {
    const i = toasts.value.findIndex((t) => t.id === id)
    if (i !== -1) toasts.value.splice(i, 1)
  }

  const success = (m: string) => push('success', m)
  const error = (m: string) => push('error', m, 6000)
  const warning = (m: string) => push('warning', m, 5000)
  const info = (m: string) => push('info', m)

  return { toasts, push, dismiss, success, error, warning, info }
})
