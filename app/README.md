# FlowTree — app (Vue 3 + TypeScript + Vite)

SPA do FlowTree. Consome a API Laravel em `../api`.

```bash
npm install
npm run dev     # http://localhost:5173 (proxy /api, /storage e /app para VITE_API_PROXY_TARGET)
npm run build   # vue-tsc + vite build → dist/
```

Estrutura em `src/`: `lib/` (axios, echo), `stores/` (Pinia), `router/`, `config/navigation.ts`,
`types/`, `views/`, `components/`. Convenções e contrato da API: `../PRD.md`.
