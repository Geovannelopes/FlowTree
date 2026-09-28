# FlowTree — PRD e contrato da API

Kanban com sprints, métricas e tempo real, inspirado no FlowTree
(https://github.com/Geovannelopes/FlowTree) e estruturado como o Gestão IC.

```
project.geovane/
├── api/   # Laravel 12 (JWT, Reverb, MySQL, Redis) — arquitetura Controller → Service → Repository (DTO)
├── app/   # Vue 3 + TypeScript + Vite + Pinia + Vue Router + Tailwind v4 + vuedraggable + Laravel Echo
├── docker-compose.yml   # api, app, mysql, redis
├── deploy.sh
└── PRD.md (este arquivo)
```

## 1. Infra (Docker)

| Serviço | Container       | Imagem                                 | Porta host → container |
|---------|-----------------|----------------------------------------|------------------------|
| api     | flowtree-api    | build ./api (inventorscompany/phpfpm-nginx:php8.2 + supervisor) | 5100 → 80; 127.0.0.1:6100 → 6100 (Reverb, debug) |
| app     | flowtree-app    | build ./app (node build → nginx)       | 5600 → 80              |
| mysql   | flowtree-mysql  | mysql:8.0                              | 3310 → 3306            |
| redis   | flowtree-redis  | redis:7-alpine                         | 6380 → 6379            |

- O **front (nginx :5600)** faz proxy de `/api`, `/storage` e `/app` (WebSocket do Reverb) para `api:80` — mesma origem, sem CORS.
- Dentro do container `api`, o nginx faz proxy de `/app` para `127.0.0.1:6100` (Reverb). O Laravel publica eventos em `REVERB_HOST=127.0.0.1:6100`.
- Supervisor no `api`: `cron`, `queue:work --queue=default` (Redis) e `reverb:start --host=0.0.0.0 --port=6100`.
- Entrypoint do `api`: espera o MySQL, `composer install` se faltar `vendor`, `key:generate`/`jwt:secret` se vazios, `migrate --force`, `db:seed` só na primeira vez (tabela `users` vazia), `storage:link`, `optimize`, e então `exec supervisord`.
- `.env` do api no Docker: `DB_HOST=mysql`, `REDIS_HOST=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=array`, `BROADCAST_CONNECTION=reverb`, `FILESYSTEM_DISK=public`.
- Hosts do dev: 3306/3309 (mysql) e 6379 (redis) já estão ocupados por outros containers — por isso 3310 e 6380.

## 2. Backend — convenções (obrigatórias)

- PHP 8.2, Laravel 12, `php-open-source-saver/jwt-auth` (guard `api`, driver `jwt`), `laravel/reverb`.
- **Fluxo:** `routes/api.php` → Controller (recebe FormRequest e `string $uuid`) → Service (regra de negócio, retorna `JsonResponse`) → Repository (Eloquent) usando DTO.
- **Pastas por domínio** e **classes com prefixo do domínio**:
  - `app/Http/Controllers/{Domain}/{Domain}Controller.php` (ex.: `Card/CardController`, `Sprint/SprintController`).
  - `app/Http/Requests/{Domain}/{Domain}{Action}Request.php` (ex.: `Card/CardCreateRequest`, `Card/CardUpdateRequest`, `Card/CardMoveRequest`).
  - `app/DTOs/{Domain}/{Domain}{Action}DTO.php` com `fromRequest(array $validated): self` e `toArray(): array`.
  - `app/Services/{Domain}/{Domain}Service.php`, `app/Repositories/{Domain}/{Domain}Repository.php`.
  - `app/Http/Resources/{Domain}Resource.php`.
  - `app/Events/{Domain}/...`, `app/Notifications/...`.
- Validação com **sintaxe de array** e `bail` como primeira regra; mensagens em português no `messages()`.
- Modelos usam o trait `HasUuid` (uuid gerado no `creating`, `getRouteKeyName() = uuid`). O front referencia tudo por `uuid`; `id` é interno.
- Não usar `is_string()` defensivo; sem verificação de tipo além das rules.
- 404 e regras de negócio: `throw new HttpException(404, 'Card não encontrado!')` / `HttpException(422, ...)` no Service. Erros de validação de campo (ex.: WIP) via `ValidationException::withMessages([...])`.
- `bootstrap/app.php`: `api: routes/api.php`, `channels: routes/channels.php`, `shouldRenderJsonWhen(fn () => true)`, `JWTException` → 401 `{message}`; alias de middleware `module` → `EnsureModuleAccess`; `trustProxies(at: '*')`.
- Rate limit: `throttle:auth` (6/min por IP) para login/senha; `throttle:api` (120/min por usuário) nas autenticadas.
- Testes (`tests/Feature`) com sqlite `:memory:` + `RefreshDatabase`. Migrations precisam rodar em sqlite e MySQL.
- Swagger em `api/docs/swagger/api.yaml` (YAML único, sem anotações @OA), servido em `GET /docs` (Swagger UI via CDN) e `GET /docs/api.yaml`.
- Requisições de exemplo em `api/requests/*.http` (formato REST Client do VS Code).

## 3. Modelo de dados (migrations)

Todas as tabelas: `id`, `uuid` (unique), `timestamps`. FKs internas por `id`.

- **users**: name, email (unique), password, position (cargo, nullable), avatar (path, nullable), is_admin (bool), is_active (bool, default true), email_verified_at, remember_token. + `password_reset_tokens`, `sessions`.
- **modules**: name, slug (unique: `tasks`, `users`, `settings`), description. **module_user**: module_id, user_id.
- **priorities**: slug (unique: low, medium, high, urgent), name, color, position. Semeadas na migration.
- **flags**: name, slug (unique), color.
- **projects**: name, description, color (hex, default `#3f6650`), logo (nullable), is_archived (bool), created_by (users, nullOnDelete). **project_user**: project_id, user_id, role (`owner`|`member`).
- **board_columns**: project_id (cascade), name, color (hex), position (uint), wip_limit (uint, nullable), is_done (bool, default false — coluna que conta como "Concluído").
- **sprints**: project_id (cascade), name, goal (text, nullable), starts_at (date), ends_at (date), status enum(`planned`,`active`,`completed`) default planned, position (uint), completed_at (timestamp nullable).
- **cards**: project_id (cascade), board_column_id (cascade), sprint_id (nullable, nullOnDelete — null = Backlog), assignee_id (users, nullOnDelete), created_by (users, nullOnDelete), title, description (text), priority (string slug, default medium), due_date (date), started_at (timestamp), completed_at (timestamp), position (uint — **por célula coluna × sprint**), softDeletes. **card_flag**: card_id, flag_id.
- **card_movements**: card_id (cascade), from_column_id (nullable, board_columns nullOnDelete), to_column_id (board_columns cascade), user_id (nullable), created_at. Registrado na criação (from null) e em toda troca de coluna. Base do histórico e das métricas.
- **comments**: card_id (cascade), user_id (nullOnDelete), body (text).
- **attachments**: card_id (cascade), comment_id (nullable), user_id, disk, path, filename, mime_type, size.
- **notifications** (padrão Laravel, id uuid), **cache**, **jobs**.

Regras de posição: `position` de card é única dentro da célula (board_column_id × sprint_id|null). Mover fecha o vão na origem e abre espaço no destino (mesma lógica do Gestão IC). Colunas e sprints têm `position` por projeto.

Colunas padrão ao criar projeto (paleta do FlowTree):
`Backlog #64748b`, `Pendente #f59e0b`, `Em front-end #3b82f6`, `Em back-end #8b5cf6`, `Em teste #f97316`, `Concluído #22c55e (is_done=true)`.

Cores de **dados** (colunas, prioridades, flags, projetos) são **vivas** (Tailwind 500: slate, amber, blue, violet, orange, green, sky, pink…) para contrastar com o layout verde e sóbrio; a paleta de sugestão fica em `app/src/config/palette.ts`.

## 4. Autenticação e autorização

- JWT Bearer (`Authorization: Bearer <token>`). TTL 60 min, refresh 14 dias, blacklist no cache (Redis).
- Módulos: `tasks` (projetos/board), `users` (gestão de usuários), `settings` (flags/prioridades). `is_admin` passa em tudo. Middleware `module:{slug}` → 403 `{message}`; usuário inativo → 401.
- Canais de broadcasting autorizados via `POST /api/broadcasting/auth` (JWT, sem sessão).

## 5. Rotas (prefixo `/api`)

Envelope: recursos e coleções vêm em `{ "data": ... }` (JsonResource). Exceções: `POST /login` e `POST /refresh` retornam `{ token, token_type, expires_in, user }` (user = UserResource sem envelope); listas paginadas trazem `{ data, links, meta }`.

### Públicas
| Método | Rota | Body | Resposta |
|---|---|---|---|
| POST | /login | `{email, password}` | `{token, token_type:"Bearer", expires_in (s), user}` — 422 credenciais inválidas / inativo |
| POST | /refresh | — (Bearer expirado ok) | igual ao login |
| POST | /forgot-password | `{email}` | `{message}` genérica |
| POST | /reset-password | `{token, email, password, password_confirmation}` | `{message}` |

### Autenticadas (`auth:api`)
| Método | Rota | Body | Resposta |
|---|---|---|---|
| GET | /me | — | `{data: User}` (com modules) |
| POST | /logout | — | 204 |
| POST | /broadcasting/auth | `{socket_id, channel_name}` | auth do canal |
| PUT | /me | `{name, position}` | `{data: User}` |
| PUT | /me/password | `{current_password, password, password_confirmation}` | `{message}` |
| POST | /me/avatar | multipart `avatar` (jpg/png/webp, max 5MB) | `{data: User}` |
| DELETE | /me/avatar | — | `{data: User}` |
| GET | /modules | — | `{data: Module[]}` |
| GET | /notifications | `?page` | paginado de Notification |
| GET | /notifications/unread-count | — | `{count}` |
| POST | /notifications/read-all | — | 204 |
| POST | /notifications/{id}/read | — | `{data: Notification}` |
| DELETE | /notifications/{id} | — | 204 |

### Módulo `users`
| Método | Rota | Body |
|---|---|---|
| GET | /users | `?search=&page=` → paginado de User (com modules) |
| POST | /users | `{name, email, password, position?, is_admin?, is_active?, module_slugs?: string[]}` → 201 `{data: User}` |
| GET | /users/{uuid} | `{data: User}` |
| PUT | /users/{uuid} | mesmos campos opcionais (`password` opcional) |
| DELETE | /users/{uuid} | 204 (não pode excluir a si mesmo → 422) |
| PUT | /users/{uuid}/modules | `{module_slugs: string[]}` → `{data: User}` |

### Módulo `settings`
| Método | Rota | Body |
|---|---|---|
| POST | /flags | `{name, color}` (slug gerado) → 201 |
| PUT | /flags/{uuid} | `{name?, color?}` |
| DELETE | /flags/{uuid} | 204 |
| PUT | /priorities/{uuid} | `{name?, color?}` (slugs fixos, sem create/delete) |

### Módulo `tasks`
| Método | Rota | Body / Query | Resposta |
|---|---|---|---|
| GET | /assignable-users | — | `{data: User[]}` (ativos, campos leves) |
| GET | /flags | — | `{data: Flag[]}` |
| GET | /priorities | — | `{data: Priority[]}` |
| GET | /projects | `?archived=0|1&search=` | `{data: Project[]}` (owner, cards_count, active_sprint) |
| POST | /projects | `{name, description?, color?, member_uuids?: string[]}` | 201 `{data: Project}` (cria colunas padrão; criador = owner) |
| GET | /projects/{uuid} | — | `{data: Project}` (owner, members, columns, sprints, cards_count) |
| PUT | /projects/{uuid} | `{name?, description?, color?, is_archived?, member_uuids?}` | `{data: Project}` |
| DELETE | /projects/{uuid} | — | 204 |
| POST | /projects/{uuid}/logo | multipart `logo` | `{data: Project}` |
| DELETE | /projects/{uuid}/logo | — | `{data: Project}` |
| GET | /projects/{uuid}/board | `?sprint=all|backlog|{sprintUuid}` (default `all`) | `{data: Project (com columns[].cards[]), sprint: "all"|"backlog"|uuid, sprints: Sprint[]}` |
| GET | /projects/{uuid}/metrics | `?sprint=all|backlog|{uuid}` | `{data: Metrics}` |
| GET | /projects/{uuid}/columns | — | `{data: BoardColumn[]}` (cards_count) |
| POST | /projects/{uuid}/columns | `{name, color?, wip_limit?, is_done?}` | 201 |
| PATCH | /projects/{uuid}/columns/reorder | `{column_uuids: string[]}` | `{data: BoardColumn[]}` |
| PUT | /columns/{uuid} | `{name?, color?, wip_limit?, is_done?}` | `{data: BoardColumn}` |
| DELETE | /columns/{uuid} | — | 204 (exclui os cards junto; não pode excluir a última coluna → 422) |
| GET | /projects/{uuid}/sprints | — | `{data: Sprint[]}` (cards_count, done_count) |
| POST | /projects/{uuid}/sprints | `{name, goal?, starts_at?, ends_at?}` | 201 |
| PATCH | /projects/{uuid}/sprints/reorder | `{sprint_uuids: string[]}` | `{data: Sprint[]}` |
| PUT | /sprints/{uuid} | `{name?, goal?, starts_at?, ends_at?}` | `{data: Sprint}` |
| DELETE | /sprints/{uuid} | — | 204 (cards voltam ao backlog) |
| POST | /sprints/{uuid}/activate | — | `{data: Sprint}` — só uma ativa por projeto (as outras ativas viram `planned`) |
| POST | /sprints/{uuid}/complete | `{move_open_to?: null|"backlog"|sprintUuid}` | `{data: Sprint}` — status completed, completed_at; cards não concluídos (coluna sem is_done) vão para o destino informado (null = ficam) |
| POST | /sprints/{uuid}/reopen | — | `{data: Sprint}` → planned |
| POST | /sprints/{uuid}/assign-backlog | — | `{data: Sprint, assigned: n}` — todos os cards com sprint null passam para esta sprint |
| GET | /sprints/{uuid}/export | — | CSV `text/csv` (download `sprint-{slug}.csv`): `uuid,titulo,coluna,prioridade,responsavel,prazo,concluido_em` |
| GET | /projects/{uuid}/cards | `?column=uuid&sprint=all|backlog|uuid&assignee=uuid&priority=&flag=uuid&search=` | `{data: Card[]}` |
| POST | /projects/{uuid}/cards | `{title, description?, board_column_uuid, sprint_uuid?, assignee_uuid?, priority?, due_date?, flag_uuids?}` | 201 `{data: Card}` |
| GET | /cards/{uuid} | — | `{data: Card}` (comments, attachments, movements, creator) |
| PUT | /cards/{uuid} | campos opcionais (incl. `board_column_uuid`, `sprint_uuid` null→backlog, `assignee_uuid` null, `flag_uuids`) | `{data: Card}` |
| PATCH | /cards/{uuid}/move | `{board_column_uuid, sprint_uuid?: string|null, position}` | `{data: Card}` — 422 `errors.board_column_uuid` se WIP cheio |
| DELETE | /cards/{uuid} | — | 204 |
| GET | /cards/{uuid}/comments | — | `{data: Comment[]}` (mais recentes primeiro) |
| POST | /cards/{uuid}/comments | `{body}` | 201 `{data: Comment}` |
| PUT | /comments/{uuid} | `{body}` | `{data: Comment}` (só autor ou admin) |
| DELETE | /comments/{uuid} | — | 204 (só autor ou admin) |
| GET | /cards/{uuid}/attachments | — | `{data: Attachment[]}` |
| POST | /cards/{uuid}/attachments | multipart `file` (max 20MB) | 201 `{data: Attachment}` |
| DELETE | /attachments/{uuid} | — | 204 |

### Web
| GET | /docs | Swagger UI | 
| GET | /docs/api.yaml | YAML |
| GET | /up | health |

## 6. Resources (formato JSON)

```ts
User        { id, uuid, name, email, position, avatar_url, is_admin, is_active, modules?: Module[], created_at }
Module      { id, uuid, name, slug, description }
Priority    { id, uuid, slug, name, color, position }
Flag        { id, uuid, name, slug, color }
Project     { id, uuid, name, description, color, logo_url, is_archived, owner?: User, members?: User[],
              columns?: BoardColumn[], sprints?: Sprint[], active_sprint?: Sprint|null, cards_count?, created_at, updated_at }
BoardColumn { id, uuid, project_id, name, color, position, wip_limit, is_done, cards?: Card[], cards_count? }
Sprint      { id, uuid, project_id, name, goal, starts_at (Y-m-d|null), ends_at, status, position,
              completed_at, cards_count?, done_count? }
Card        { id, uuid, project_id, board_column_id, board_column_uuid, sprint_id, sprint_uuid, title, description,
              priority, due_date (Y-m-d|null), started_at, completed_at, position, is_overdue (bool),
              assignee?: User|null, creator?: User|null, flags: Flag[], comments?: Comment[], attachments?: Attachment[],
              movements?: CardMovement[], comments_count?, attachments_count?, created_at, updated_at }
CardMovement{ id, from_column: {uuid,name,color}|null, to_column: {uuid,name,color}, user: User|null, created_at }
Comment     { id, uuid, card_id, body, user?: User, created_at, updated_at }
Attachment  { id, uuid, card_id, comment_id, filename, mime_type, size, url, user?: User, created_at }
Notification{ id, type: 'card_assigned'|'card_commented', data: { card_uuid, card_title, project_uuid, project_name,
              actor_uuid, actor_name, comment_excerpt? }, read (bool), read_at, created_at }
Metrics     { scope: { sprint: 'all'|'backlog'|uuid }, cards_total, cards_done, cards_open, cards_overdue,
              throughput (cards concluídos no escopo), avg_cycle_time_days (completed_at - started_at, 1 casa),
              avg_lead_time_days (completed_at - created_at), by_column: [{uuid,name,color,count,is_done}],
              by_priority: [{slug,name,color,count}], by_assignee: [{user: User|null, count, done}],
              completed_by_day: [{date: 'Y-m-d', count}] (últimos 30 dias, dias sem card = 0) }
```

Regras do card: ao entrar em coluna `is_done` → `completed_at = now()`; ao sair → null. `started_at` é preenchido na primeira vez que o card entra numa coluna que não é a primeira (position 0) do projeto (ou na criação, se criado fora dela). `is_overdue` = due_date < hoje e sem completed_at.

## 7. Tempo real (Reverb) e notificações

Canais (`routes/channels.php`):
- `project.{projectUuid}` — privado e **presence** (o mesmo callback devolve `{id, uuid, name, avatar_url}` para quem tem o módulo `tasks`). O front usa `Echo.private('project.X')` para eventos e `Echo.join('project.X')` para mostrar quem está no board.
- `notifications.{userUuid}` — privado do próprio usuário.

Eventos (`ShouldBroadcastNow`, `broadcast(...)->toOthers()`, header `X-Socket-ID` enviado pelo axios):
| Evento (broadcastAs) | Canal | Payload |
|---|---|---|
| `card.changed` | project.{uuid} | `{card_uuid, action: created|updated|moved|deleted}` |
| `column.changed` | project.{uuid} | `{column_uuid, action: created|updated|deleted|reordered}` |
| `sprint.changed` | project.{uuid} | `{sprint_uuid, action: created|updated|deleted|activated|completed|reopened|reordered|assigned}` |
| `comment.changed` | project.{uuid} | `{card_uuid, comment_uuid, action: created|updated|deleted}` |
| `attachment.changed` | project.{uuid} | `{card_uuid, action: created|deleted}` |
| `notification.created` | notifications.{userUuid} | Notification (mesmo formato do REST) |

Notificações (`database` + reemitidas em tempo real por um listener de `NotificationSent`):
- `CardAssigned` → ao responsável quando alguém o atribui (não notifica o próprio autor).
- `CardCommented` → ao responsável e ao criador do card quando outra pessoa comenta.

## 8. Seed

- Sempre: módulos (`tasks`, `users`, `settings`), prioridades (low Baixa #22c55e, medium Média #0ea5e9, high Alta #f59e0b, urgent Urgente #ef4444), admin do `.env` (`ADMIN_NAME`, `ADMIN_EMAIL=admin@flowtree.test`, `ADMIN_PASSWORD=password`).
- Fora de produção (`DemoSeeder`, determinístico via `mt_srand`): 8 usuários com cargos e módulos variados (`dev@`, `ana@` [tasks+settings], `bruno@`, `carla@`, `diego@`, `elisa@`, `felipe@` [tasks+users+settings], `gabi@` inativa — `@flowtree.test`/`password`), 7 flags (Bug, Melhoria, Urgente, Bloqueado, Documentação, Refatoração, Infra), 5 projetos (FlowTree Demo, App Mobile, Site Institucional, API de Pagamentos, Painel Administrativo) com equipe, colunas padrão, `Sprint 1` concluída, `Sprint 2` ativa e `Sprint 3` planejada, 17–21 cards cada (≈30% na sprint concluída, 40% na ativa, 15% na planejada, resto no backlog) com descrição em markdown, prioridade, prazo (alguns atrasados), flags, `card_movements` percorrendo as colunas até a atual (com `started_at`/`completed_at`), 0–4 comentários entre membros, anexos reais no disco `public` (md/csv/txt/png) em ~25% dos cards e notificações `card_assigned`/`card_commented` (antigas lidas, recentes por ler).

## 9. Frontend — convenções

- Vue 3 `<script setup lang="ts">`, alias `@/`, Pinia, Vue Router (history), Tailwind v4 com tokens semânticos (`bg-canvas`, `bg-surface`, `bg-surface-2`, `border-border`, `text-content`, `text-muted`, `text-subtle`, `brand-*`), dark mode por `[data-theme="dark"]` no `<html>` (persistido em `localStorage`, guarda anti-FOUC no `index.html`).
- Identidade **FlowTree**: fonte Manrope; verde floresta como marca (`brand-500 #3f6650`, `brand-600 #2f4a3a`, `brand-400 #4e735b`), tons `moss #6f8a63`, `bark #7d644a`, `water #4c7570`, `amber #8e744e`; canvas claro `#eef2ea`/superfície `#f6f3ec`; dark: canvas `#141a16`, surface `#1f2822`, surface-2 `#1a221d`, border `#2d382f`. Cards com `rounded-2xl`, colunas com barra de acento no topo.
- `lib/api.ts`: axios `baseURL=/api`, Bearer + `X-Socket-ID`, refresh automático no 401 (fila), redireciona para `/login`. `lib/echo.ts`: Echo/Reverb com `authorizer` via `/broadcasting/auth`; host/porta/esquema = `window.location` (mesma origem, proxy `/app`), sobrescritos por `VITE_REVERB_*` quando definidos. Sem `VITE_REVERB_APP_KEY` → sem realtime (app segue funcionando).
- Rotas: `/login`, `/forgot-password`, `/reset-password`, `/projects`, `/projects/:uuid/board`, `/users` (module users), `/settings` (module settings), `/profile`. Guard: reidrata `/me`, exige auth, checa `meta.module`.
- Layout: sidebar colapsável (drawer no mobile) com navegação por módulo (`config/navigation.ts`), sininho de notificações, avatar/perfil, alternância de tema e sair. Conteúdo com `h1`.
- Board (`BoardView`): cabeçalho com eyebrow "FlowTree" + nome do projeto + avatares de presença; busca; filtros responsável/prioridade/flag; botões "Métricas", "Nova coluna", "Sprints". **Visão em raias (igual ao Gestão IC):** o board carrega sempre `sprint=all`; o cabeçalho das colunas fica fixo no topo (barra de acento, contador `n/wip` somando todas as raias, editar/excluir, reordenação por handle) e abaixo vem uma raia por sprint não concluída (ordem = `position`), depois a raia **Backlog** (cards sem sprint) e, por fim, a seção recolhível **Sprints concluídas**. Cada raia tem cabeçalho (recolher, nome, `concluídos/total`, status, datas, objetivo, barra de progresso, ações rápidas Ativar/Reabrir e atalho para o gerenciador) e uma célula por coluna (`vuedraggable`, grupo `cards`): soltar em outra célula chama `move` com `board_column_uuid` da coluna e `sprint_uuid` da raia (null no Backlog). Raias recolhidas são lembradas por projeto no localStorage; com filtro ativo o drag desliga. Card: badge de prioridade com popover para trocar rápido, título, badge da sprint, prazo (vermelho se atrasado), flags, rodapé com contadores de anexos/comentários e avatar do responsável, ações editar/excluir no hover. Clique abre `CardModal` (abas Detalhes / Comentários / Anexos / Atividade). Realtime: assina `project.{uuid}` e recarrega o board com debounce; presença via `join`.
- `SprintManagerModal`: formulário criar/editar (nome, objetivo, início, término), lista com status, ações Ativar / Concluir (escolha do destino dos cards abertos) / Reabrir / Editar / Excluir / Atribuir backlog / Exportar CSV, reordenação por drag.
- `MetricsModal`: KPIs (throughput, ciclo médio, lead médio, concluídos, abertos, atrasados), barras por coluna, por prioridade, por responsável e "concluídos por dia" (barras CSS, sem lib de gráfico), com seletor de escopo (sprint).
- Demais telas: Projetos (grid + `ProjectModal` + arquivados), Usuários (tabela + `UserModal` + módulos), Configurações (flags + prioridades), Perfil (dados, senha, avatar), Login/Esqueci/Redefinir.
- Toasts para feedback; `ConfirmDialog` para exclusões; estados loading/vazio/erro em toda view. `npm run build` (vue-tsc) precisa passar limpo.
