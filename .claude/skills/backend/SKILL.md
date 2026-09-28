---
name: backend
description: Convenções do backend Laravel do FlowTree (api/). Use ao criar ou alterar rotas, controllers, services, repositories, DTOs, requests, resources, eventos, migrations ou testes em api/.
---

# Backend — FlowTree (api/)

Laravel 12 + JWT (`php-open-source-saver/jwt-auth`) + Reverb + MySQL + Redis. Contrato completo em `PRD.md`.

## Arquitetura (obrigatória)

`routes/api.php` → **Controller** (recebe o FormRequest e `string $uuid`) → **Service** (regra de
negócio, retorna `JsonResponse`) → **Repository** (Eloquent) usando **DTO**.

- Pastas por domínio e classes com o prefixo do domínio:
  `Http/Controllers/Card/CardController`, `Http/Requests/Card/CardCreateRequest`,
  `DTOs/Card/CardCreateDTO` (`fromRequest(array)` + `toArray()`), `Services/Card/CardService`,
  `Repositories/Card/CardRepository`, `Http/Resources/CardResource`.
- Validação com sintaxe de **array** e `bail` primeiro; mensagens em português em `messages()`.
  Sem `is_string()` defensivo.
- Models usam `HasUuid`; o front só conhece `uuid`. 404/regras → `HttpException(404|422, 'Mensagem!')`
  no Service; erro de campo (ex.: WIP) → `ValidationException::withMessages`.
- Acesso por módulo: `->middleware('module:tasks')` (`EnsureModuleAccess`; admin passa).

## Regras do domínio

- Posição de card é **por célula (coluna × sprint)**; mover fecha o vão na origem e abre no destino;
  WIP conta a coluna inteira e só quando a coluna muda.
- Entrar em coluna `is_done` → `completed_at`; sair → null. Toda troca de coluna grava `card_movements`.
- Só uma sprint `active` por projeto. `complete` recebe `move_open_to`.
- Eventos `ShouldBroadcastNow` no canal privado/presence `project.{uuid}` com `->toOthers()`;
  notificações `database` são reemitidas em tempo real pelo listener de `NotificationSent`.

## Ao alterar um endpoint ou parâmetro, atualize SEMPRE

1. `docs/swagger/api.yaml` (validar chaves duplicadas com `npx js-yaml`),
2. `requests/*.http`,
3. testes em `tests/Feature` (`php artisan test` — sqlite em memória; no Docker:
   `docker exec flowtree-api php artisan test`),
4. `types/index.ts` do front, se o Resource mudou.

Migrations em dev: altere a migration original em vez de criar uma nova (o banco é recriado).
Índices em linhas separadas. Composer no host: `--ignore-platform-req=ext-sodium`.
