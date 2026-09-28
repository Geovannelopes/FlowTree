import Echo from 'laravel-echo'
import Pusher, { type ChannelAuthorizationCallback } from 'pusher-js'
import { api } from '@/lib/api'
import { setSocketId } from '@/lib/socket'

// laravel-echo (broadcaster reverb) espera o Pusher no escopo global.
;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

let echo: Echo<'reverb'> | null = null

/**
 * Inicializa o Echo/Reverb (idempotente). Sem `VITE_REVERB_APP_KEY` não conecta —
 * o app segue funcionando sem tempo real. Host/porta/esquema vazios = mesma
 * origem da página (o nginx/Vite faz proxy de `/app` para o Reverb).
 */
export function initEcho(): Echo<'reverb'> | null {
  if (echo) return echo
  const key = import.meta.env.VITE_REVERB_APP_KEY
  if (!key) return null

  const isHttps = window.location.protocol === 'https:'
  const defaultPort = window.location.port ? Number(window.location.port) : isHttps ? 443 : 80
  const scheme = import.meta.env.VITE_REVERB_SCHEME || (isHttps ? 'https' : 'http')
  const port = import.meta.env.VITE_REVERB_PORT ? Number(import.meta.env.VITE_REVERB_PORT) : defaultPort

  echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    // Autoriza canais privados/presence via nossa instância axios (JWT + refresh).
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: ChannelAuthorizationCallback) => {
        api
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((res) => callback(null, res.data))
          .catch((err: Error) => callback(err, null))
      },
    }),
  })

  // Mantém o socket id atual para o header X-Socket-ID (toOthers()).
  const connection = (echo.connector as { pusher: { connection: Pusher['connection'] } }).pusher
    .connection
  connection.bind('connected', () => setSocketId(echo?.socketId() ?? null))
  connection.bind('disconnected', () => setSocketId(null))

  return echo
}

export function getEcho(): Echo<'reverb'> | null {
  return echo
}

/** Desconecta o Echo (logout). */
export function teardownEcho(): void {
  if (echo) {
    echo.disconnect()
    echo = null
    setSocketId(null)
  }
}
