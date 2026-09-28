---
name: frontend
description: Convenções de frontend do FlowTree (Vue 3 + TS + Vite + Pinia + Vue Router + Tailwind v4 + axios + Echo). Use ao criar/alterar telas, rotas, stores, chamadas de API, realtime ou componentes em app/.
---

# Frontend — FlowTree (app/)

SPA em **Vue 3 `<script setup lang="ts">` + Vite**. Consome a API Laravel em `api/` (contrato em `PRD.md`).
Para decisões visuais, siga também a skill **ux-ui**.

```
app/src/
  lib/api.ts           # axios: baseURL /api, Bearer JWT, refresh automático no 401, X-Socket-ID
  lib/echo.ts          # Echo/Reverb (mesma origem, path /app) — authorizer via POST /broadcasting/auth
  stores/auth.ts       # usuário logado, login/logout, hasModule()
  stores/meta.ts       # flags + prioridades (carregadas uma vez)
  stores/notifications.ts, stores/toast.ts
  router/index.ts      # rotas + guard (auth e módulo via meta.module)
  config/navigation.ts # menu lateral por módulo (fonte única)
  types/index.ts       # espelha os Resources da API (seção 6 do PRD)
  views/               # uma view por rota (PascalCase + sufixo View)
  components/          # AppLayout, AppSidebar, CardCard, CardModal, SprintManagerModal, MetricsModal…
```

## Regras

- Sempre a instância `api` de `@/lib/api` (nunca axios cru/fetch). Envelope `{ data }` (`Wrapped<T>`);
  `POST /login` e `/refresh` retornam `{ token, user }` sem envelope; listas paginadas `{ data, meta, links }`.
- Tudo é referenciado por `uuid`. Erros 422: `error.response.data.errors` (campo → string[]).
- Realtime: `getEcho()?.private('project.'+uuid)` para eventos (`.card.changed`, `.column.changed`,
  `.sprint.changed`, `.comment.changed`, `.attachment.changed`) com recarga em debounce, e
  `join('project.'+uuid)` para presença. Sem `VITE_REVERB_APP_KEY` o app funciona sem realtime.
- Board em **raias** (uma por sprint + Backlog + concluídas) sobre as mesmas colunas: `lanes[].cells[columnUuid]` são os arrays que o `vuedraggable` muta; soltar chama `PATCH /cards/{uuid}/move` com a coluna da célula e a sprint da raia. Recarregar (`loadBoard`) reconstrói as raias.
- Rotas protegidas declaram `meta.module` (`tasks` | `users` | `settings`); `meta.public: true` libera.
- Toda view trata loading / vazio / erro. Sem `any` (exceto eventos do vuedraggable). Textos em português.

## Como adicionar uma tela

1. `views/MinhaView.vue` buscando via `api` e tratando estados.
2. Rota em `router/index.ts` com `meta: { module, title }`.
3. Item em `config/navigation.ts` se for de menu.
4. Tipos novos em `types/index.ts`.

## Verificação

`npm run build` (vue-tsc + vite) limpo; integração real em http://localhost:5600 (`docker compose up -d --build app`).
