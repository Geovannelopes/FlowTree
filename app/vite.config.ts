import { defineConfig, loadEnv } from 'vite'
import { fileURLToPath, URL } from 'node:url'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  // Lê o .env do app (prefixo '' = todas as chaves, inclusive as sem VITE_).
  const env = loadEnv(mode, process.cwd(), '')

  // Alvo do proxy em dev: a API Laravel. Padrão = api publicada em :5100 pelo
  // Docker Compose; dentro do Compose (perfil dev) use http://api:80.
  const apiTarget = env.VITE_API_PROXY_TARGET || 'http://localhost:5100'

  return {
    plugins: [vue(), tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      host: true,
      port: 5173,
      allowedHosts: true,
      proxy: {
        // API, Swagger (/docs), mídia (/storage) e WebSocket do Reverb (/app)
        // na mesma origem — sem CORS, igual ao nginx de produção.
        '/api': { target: apiTarget, changeOrigin: true },
        '/docs': { target: apiTarget, changeOrigin: true },
        '/storage': { target: apiTarget, changeOrigin: true },
        '/app': { target: apiTarget, changeOrigin: true, ws: true },
      },
    },
  }
})
