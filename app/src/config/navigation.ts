import type { RouteLocationRaw } from 'vue-router'
import type { IconName } from '@/components/AppIcon.vue'

export interface NavItem {
  label: string
  to: RouteLocationRaw
  icon: IconName
  /** Marca ativo quando a rota atual começa por este path (ex.: board). */
  matchPrefix?: string
}

export interface NavGroup {
  key: string
  label: string
  /** Slug do módulo que libera o grupo; ausente = sempre visível. */
  module?: string
  items: NavItem[]
}

/** Menu lateral dirigido por módulo — fonte única da navegação. */
export const navigation: NavGroup[] = [
  {
    key: 'tasks',
    label: 'Kanban',
    module: 'tasks',
    items: [{ label: 'Projetos', to: { name: 'projects' }, icon: 'projects', matchPrefix: '/projects' }],
  },
  {
    key: 'admin',
    label: 'Administração',
    module: 'users',
    items: [{ label: 'Usuários', to: { name: 'users' }, icon: 'users', matchPrefix: '/users' }],
  },
  {
    key: 'settings',
    label: 'Sistema',
    module: 'settings',
    items: [{ label: 'Configurações', to: { name: 'settings' }, icon: 'settings', matchPrefix: '/settings' }],
  },
]
