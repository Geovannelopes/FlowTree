/**
 * Paletas de cor de DADOS (colunas, projetos, flags). O layout do FlowTree é
 * verde e sóbrio; as informações usam cores vivas para se destacarem nele.
 */
export const COLUMN_PALETTE = [
  '#64748b', // slate — backlog/neutro
  '#f59e0b', // amber
  '#3b82f6', // blue
  '#8b5cf6', // violet
  '#f97316', // orange
  '#22c55e', // green — concluído
  '#0ea5e9', // sky
  '#ec4899', // pink
]

export function nextColumnColor(index: number): string {
  return COLUMN_PALETTE[index % COLUMN_PALETTE.length] ?? COLUMN_PALETTE[0]!
}

/** Cores sugeridas para projetos e flags. */
export const ACCENT_PALETTE = [
  '#ef4444', // red
  '#f97316', // orange
  '#f59e0b', // amber
  '#eab308', // yellow
  '#22c55e', // green
  '#14b8a6', // teal
  '#0ea5e9', // sky
  '#3b82f6', // blue
  '#8b5cf6', // violet
  '#a855f7', // purple
  '#ec4899', // pink
  '#64748b', // slate
]
