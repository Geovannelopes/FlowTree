# FlowTree — Kanban com sprints

Monorepo: **API REST (Laravel 12)** + **front SPA (Vue 3 + TypeScript)**, com tempo real
(Laravel Reverb), MySQL e Redis, tudo no Docker. Inspirado no
[FlowTree](https://github.com/Geovannelopes/FlowTree) e estruturado como o Gestão IC.

```
project.geovane/
├── api/    # Backend Laravel 12 (JWT, Reverb, módulos, Kanban) — Controller → Service → Repository (DTO)
├── app/    # Frontend Vue 3 + Vite + TS (Router, Pinia, Tailwind v4, vuedraggable, Echo)
├── PRD.md  # Contrato da API, modelo de dados, eventos e convenções
├── docker-compose.yml
└── deploy.sh
```

## Rodando com Docker (recomendado)

```bash
cp .env.example .env          # preencha REVERB_APP_ID/KEY/SECRET (ver comentários no arquivo)
(cd api && composer install --ignore-platform-req=ext-sodium)   # vendor fica no host, montado no container
docker compose up --build     # use -d para rodar em segundo plano
```

Abra **http://localhost:9050** e entre com `admin@flowtree.test` / `password` (o formulário já vem
preenchido enquanto `DEMO_LOGIN=1` no `.env` da raiz).

O seed de demonstração (`api/database/seeders/DemoSeeder.php`, nunca roda em produção) cria uma equipe
de 8 pessoas (`dev@`, `ana@`, `bruno@`, `carla@`, `diego@`, `elisa@`, `felipe@`, `gabi@` — todas
`@flowtree.test`, senha `password`, com módulos diferentes), 7 flags e 5 projetos (FlowTree Demo, App
Mobile, Site Institucional, API de Pagamentos, Painel Administrativo), cada um com sprint concluída,
ativa e planejada, ~20 cards com descrição, prazo, flags, histórico de movimentações, comentários,
anexos reais e notificações. É determinístico: recriar do zero gera o mesmo cenário.

```bash
docker exec flowtree-api php artisan migrate:fresh --seed --force   # recria e semeia tudo
```

Pela interface, um administrador também pode resetar o banco em **Configurações → Banco de dados**
(`POST /api/maintenance/reset`, com modal de confirmação): **Restaurar demonstração** recria o cenário acima
e **Limpar tudo** deixa apenas a própria conta de admin. Os arquivos enviados são apagados junto.

Tudo sai por uma **URL única** — `http://localhost:9050` — porque o nginx do `app` serve a SPA
e faz proxy do restante para o `api`. É essa a porta para apontar o proxy/domínio externo.

| Caminho na URL única | O que responde       | Container    |
|----------------------|----------------------|--------------|
| `/`                  | SPA Vue              | flowtree-app |
| `/api`               | API Laravel          | flowtree-api |
| `/docs`              | Swagger              | flowtree-api |
| `/storage`           | Uploads (disco público) | flowtree-api |
| `/app/{key}`         | WebSocket do Reverb  | flowtree-api |

### Atrás de um proxy externo (domínio próprio)

O ambiente `develop` usa dois subdomínios, ambos com TLS terminado no proxy:

| Domínio                                | Aponta para | Serve                                |
|----------------------------------------|-------------|--------------------------------------|
| `app.flowtree.develop.ictech.dev`      | porta 9050  | front + `/api` + `/docs` + `/storage` |
| `reverb.flowtree.develop.ictech.dev`   | porta 6100  | WebSocket do Reverb (`/app/{key}`)   |

Configuração no **`.env` da raiz**:

```dotenv
APP_URL=https://app.flowtree.develop.ictech.dev
REVERB_PUBLIC_HOST=reverb.flowtree.develop.ictech.dev
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https
REVERB_BIND=127.0.0.1:   # vazio se o proxy externo não roda nesta máquina
```

`APP_URL` vai para o `api` (links de e-mail e redirects) e `REVERB_PUBLIC_*` é **embutido no
bundle** do front no build — trocar qualquer um exige `docker compose up -d --build app`.
Deixando os `REVERB_PUBLIC_*` vazios, o WebSocket volta a sair pela mesma origem do front
(`/app`), que é o padrão local.

O proxy do Reverb precisa encaminhar o `Upgrade`/`Connection` do WebSocket.

Os valores prontos do `develop` ficam em dois arquivos (fora do git, contêm segredos):

```bash
cp .env.develop     .env         # lido pelo docker compose
cp api/.env.develop api/.env     # lido pelo Laravel (api/ é montado por volume)
./deploy.sh
```

O front **não tem** `.env` próprio no servidor: ele é um bundle estático e toda a
configuração (`VITE_*`) é embutida no build a partir dos `build.args` do compose, que
saem do `.env` da raiz. Por isso qualquer mudança de domínio ou chave exige rebuild.

A API também fica aberta em porta própria, para testar direto (Postman, curl):

| Serviço | URL / porta                        | Container       |
|---------|------------------------------------|-----------------|
| API     | http://localhost:5100/api          | flowtree-api    |
| Swagger | http://localhost:5100/docs         | flowtree-api    |
| MySQL   | sem porta publicada (rede interna) | flowtree-mysql  |
| Redis   | sem porta publicada (rede interna) | flowtree-redis  |

- O **api** roda `migrate` sempre e `seed` **apenas na primeira vez** (banco vazio); gera
  `APP_KEY`/`JWT_SECRET` se estiverem vazios; sobe **cron**, **fila (Redis)** e **Reverb** via
  supervisor. Código montado por volume: editar reflete na hora.
- O **app** é compilado na imagem (`npm run build`) e servido por **nginx**, que faz o proxy de
  `/api`, `/docs`, `/storage` e `/app` (WebSocket) para o container `api` — mesma origem, sem CORS.
  Mudança no front entra com `docker compose up -d --build app`.
- **HMR em dev:** `docker compose --profile dev up app-dev` → http://localhost:5173
  (Vite com proxy para o `api`). Ou rode `cd app && npm run dev` no host (proxy para `localhost:5100`).
- As chaves do Reverb ficam **só no `.env` da raiz**: o compose entrega o mesmo valor ao `api`
  (variável de ambiente, que vence o `api/.env`) e ao build do `app` (`VITE_REVERB_APP_KEY`).

Parar: `docker compose down` (dados persistem nos volumes `mysql-data`/`redis-data`).

## Rodando sem Docker

```bash
cd api && composer install --ignore-platform-req=ext-sodium && php artisan migrate --seed && php artisan serve   # :8000
cd app && npm install && npm run dev   # :5173 (ajuste VITE_API_PROXY_TARGET=http://localhost:8000)
```

## Funcionalidades

- **Projetos** (boards) com cor/logo, membros, arquivamento e colunas padrão do FlowTree
  (Backlog, Pendente, Em front-end, Em back-end, Em teste, Concluído).
- **Colunas** com cor de acento, limite de WIP, marcação "concluído" e reordenação por drag.
- **Cards** com prioridade (troca rápida no card), prazo, responsável, flags, comentários, anexos,
  histórico de movimentações; drag entre colunas com posição persistida.
- **Sprints**: criar/editar, **ativar** (uma por projeto), **concluir** (escolhendo o destino dos
  cards abertos), reabrir, **atribuir todo o backlog**, **exportar CSV**, reordenar.
- **Board em raias** (como no Gestão IC): cabeçalho de colunas fixo no topo e uma raia por sprint
  (ativa/planejadas), depois o Backlog e, recolhida, a seção de sprints concluídas. Cada raia mostra
  progresso (concluídos/total) e ações rápidas; arrastar um card para outra raia muda a sprint dele,
  para outra célula muda a coluna. Raias recolhidas são lembradas por projeto.
- **Métricas**: throughput, tempo de ciclo e lead time médios, atrasados, por coluna, prioridade,
  responsável e concluídos por dia.
- **Tempo real** (Reverb): mudanças de card/coluna/sprint/comentário/anexo atualizam o board de
  todo mundo; presença mostra quem está no board; notificações (atribuição e comentários) chegam
  no sininho na hora.
- **Acesso por módulo**: `tasks`, `users`, `settings` (admin passa em tudo); modo escuro.

## Backend (`api/`)

- Fluxo: `routes/api.php` → `Http/Controllers/{Domínio}` (FormRequest) → `Services/{Domínio}` →
  `Repositories/{Domínio}` com `DTOs/{Domínio}`. Ver `PRD.md` e `.claude/skills/backend`.
- Testes: `cd api && php artisan test` (sqlite em memória) ou `docker exec flowtree-api php artisan test`.
- Swagger: `api/docs/swagger/api.yaml` (YAML único). Requisições de exemplo: `api/requests/*.http`.

## Frontend (`app/`)

| Caminho                | Papel                                                         |
|------------------------|---------------------------------------------------------------|
| `lib/api.ts`           | axios + Bearer JWT + refresh automático + `X-Socket-ID`       |
| `lib/echo.ts`          | Echo/Reverb (mesma origem, `/app`) com auth via `/broadcasting/auth` |
| `stores/`              | Pinia: auth, meta (flags/prioridades), notifications, toast   |
| `router/index.ts`      | Rotas + guard de auth e de módulo                             |
| `config/navigation.ts` | Menu lateral por módulo (fonte única)                         |
| `views/`               | Login, Projetos, Board, Usuários, Configurações, Perfil       |
| `components/`          | Layout, CardCard, CardModal, SprintManagerModal, MetricsModal…|

Build de produção: `cd app && npm run build` (roda `vue-tsc` + `vite build`).

## Deploy

`deploy.sh` (usado pelo workflow `.github/workflows/production.yml` no push para `production`):
instala vendor no host, `docker compose up -d --build`, migra e otimiza.

O seed roda sozinho na primeira subida (banco sem usuários). Para forçar:

```bash
./deploy.sh --seed    # db:seed de novo (idempotente: não duplica dados)
./deploy.sh --fresh   # APAGA o banco e recria tudo (migrate:fresh --seed)
```
