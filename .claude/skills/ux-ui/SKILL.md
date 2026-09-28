---
name: ux-ui
description: Diretrizes de UX/UI e design system do FlowTree. Use ao criar ou alterar telas, componentes visuais, layout, cores, espaçamento ou estados de interface no front (app/).
---

# UX/UI — FlowTree

Kanban de uso diário: clareza, densidade confortável e consistência. Identidade do FlowTree original:
fonte **Manrope**, verde floresta, superfícies claras levemente esverdeadas, cards arredondados.

## Tokens (Tailwind v4, `app/src/style.css`)

Tema dirigido por tokens semânticos que trocam com `[data-theme="dark"]` no `<html>` — **não use**
`slate-*`/`gray-*` literais em superfícies/texto.

- **Marca:** `brand-500` (#3f6650, botões), hover `brand-600` (#2f4a3a), suave `brand-50/100`.
  Acentos: `moss` (#6f8a63), `bark` (#7d644a), `water` (#4c7570), `amber` (#8e744e).
- **Neutros:** `bg-canvas` (página), `bg-surface` (cards/modais), `bg-surface-2` (raias, hovers,
  badges neutras, cabeçalho de tabela), `border-border`, `text-content`, `text-muted`, `text-subtle`.
- **Semânticos:** sucesso `green`, atenção `amber`, perigo `red`, info `sky` (chips `bg-{cor}-100 text-{cor}-700`).
- **Cores de dados** (coluna, prioridade, flag, projeto) vêm da API e são **vivas** (Tailwind 500) para se destacarem do layout verde — aplique via `style` (fundo `${cor}22` + texto na cor). Sugestões em `config/palette.ts`.
- **Raio:** `rounded-lg` (controles), `rounded-2xl` (cards), `rounded-xl` (painéis/modais).
- **Sombra:** `shadow-sm` no repouso, `shadow` no hover de clicáveis. Espaçamento em múltiplos de 4.
- **Texto:** `text-sm` corpo; rótulos `text-xs` uppercase com tracking.

## Padrões

- **Botão primário:** `rounded-full bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600 disabled:opacity-60`.
- **Botão secundário:** `rounded-full border border-border bg-surface px-3 py-2 text-sm text-muted hover:bg-surface-2`.
- **Input:** `rounded-lg border border-border bg-surface px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20`.
- **Coluna do board:** painel `bg-surface rounded-xl` com barra de acento de 5px no topo na cor da coluna;
  cabeçalho com nome, contador `n/wip` e ações.
- **Card:** `rounded-2xl bg-surface-2/60 p-3 shadow-sm`; badge de prioridade no topo; título; badge da
  sprint; rodapé com prazo, contadores e avatar.
- **Modal:** overlay `bg-black/50`, painel `rounded-xl bg-surface shadow-xl`, cabeçalho com título e fechar,
  rodapé com Cancelar + primário. Fecha com Esc e clique fora.
- **Estados:** loading (texto/skeleton), vazio (ícone + frase + ação), erro (faixa `bg-red-50 text-red-700`), toasts para ações.

## Acessibilidade e responsivo

Contraste AA, `:focus-visible`, alvos ≥ 36px, `aria-label` em botões só-ícone, funciona de 360px a
desktop (sidebar vira drawer; board rola horizontalmente).
