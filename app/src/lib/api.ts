import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { getSocketId } from '@/lib/socket'

const TOKEN_KEY = 'flowtree_token'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token: string | null): void {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

/**
 * Base das chamadas de API.
 *  - Vazio (padrão): caminho relativo `/api` (proxy do Vite em dev; nginx em produção).
 *  - `VITE_API_URL` definido: o navegador chama o backend direto naquele domínio (exige CORS).
 */
export const API_BASE_URL = (import.meta.env.VITE_API_URL?.replace(/\/+$/, '') ?? '') + '/api'

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: { Accept: 'application/json' },
})

// Anexa o token Bearer (JWT) e o socket id (p/ o `toOthers()` do broadcasting).
api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  const socketId = getSocketId()
  if (socketId) config.headers['X-Socket-ID'] = socketId
  return config
})

function redirectToLogin(): void {
  setToken(null)
  if (window.location.pathname !== '/login') {
    window.location.assign('/login')
  }
}

/*
 * Refresh automático do JWT:
 *  - no 401, tenta renovar via POST /refresh e repete a request original;
 *  - requests concorrentes durante o refresh entram numa fila (um refresh por vez);
 *  - se o refresh falhar, limpa e manda para o login.
 */
let isRefreshing = false
let queue: Array<(token: string | null) => void> = []

function flushQueue(token: string | null): void {
  queue.forEach((cb) => cb(token))
  queue = []
}

const AUTH_PATHS = ['/login', '/refresh', '/forgot-password', '/reset-password']

api.interceptors.response.use(
  (res) => res,
  async (error: AxiosError) => {
    const original = error.config as (InternalAxiosRequestConfig & { _retry?: boolean }) | undefined

    if (error.response?.status !== 401 || !original) {
      return Promise.reject(error)
    }

    const url = original.url ?? ''
    const isAuthPath = AUTH_PATHS.some((p) => url.includes(p))

    if (isAuthPath || original._retry || !getToken()) {
      if (!isAuthPath) redirectToLogin()
      return Promise.reject(error)
    }

    original._retry = true

    if (isRefreshing) {
      return new Promise((resolve, reject) => {
        queue.push((token) => {
          if (!token) return reject(error)
          original.headers.Authorization = `Bearer ${token}`
          resolve(api(original))
        })
      })
    }

    isRefreshing = true
    try {
      // axios "cru" para não recair neste interceptor.
      const { data } = await axios.post<{ token: string }>(`${API_BASE_URL}/refresh`, null, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${getToken()}` },
      })
      setToken(data.token)
      isRefreshing = false
      flushQueue(data.token)
      original.headers.Authorization = `Bearer ${data.token}`
      return api(original)
    } catch (e) {
      isRefreshing = false
      flushQueue(null)
      redirectToLogin()
      return Promise.reject(e)
    }
  },
)

/**
 * Extrai uma mensagem legível de um erro de API (422 → primeira mensagem de
 * validação; demais → `message` do backend; fallback informado).
 */
export function errorMessage(e: unknown, fallback = 'Algo deu errado.'): string {
  if (axios.isAxiosError(e)) {
    const data = e.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
    return first ?? data?.message ?? fallback
  }
  return fallback
}

/** Mapa campo → mensagem de um 422 (para exibir erros inline nos formulários). */
export function fieldErrors(e: unknown): Record<string, string> {
  if (!axios.isAxiosError(e) || e.response?.status !== 422) return {}
  const errors = (e.response.data as { errors?: Record<string, string[]> }).errors ?? {}
  const out: Record<string, string> = {}
  for (const [k, v] of Object.entries(errors)) out[k] = v[0] ?? ''
  return out
}
