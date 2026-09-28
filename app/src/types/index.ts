export type Priority = 'low' | 'medium' | 'high' | 'urgent'
export type SprintStatus = 'planned' | 'active' | 'completed'
export type ModuleSlug = 'tasks' | 'users' | 'settings'

export interface Module {
  id: number
  uuid: string
  name: string
  slug: ModuleSlug
  description: string | null
}

export interface User {
  id: number
  uuid: string
  name: string
  email: string
  position: string | null
  avatar_url: string | null
  is_admin: boolean
  is_active: boolean
  modules?: Module[]
  created_at?: string
}

export interface PriorityOption {
  id: number
  uuid: string
  slug: Priority
  name: string
  color: string
  position: number
}

export interface Flag {
  id: number
  uuid: string
  name: string
  slug: string
  color: string
}

/** POST /maintenance/reset — `demo` recria os dados fictícios; `clean` deixa só o admin. */
export type MaintenanceResetMode = 'demo' | 'clean'

export interface MaintenanceResetResult {
  mode: MaintenanceResetMode
  summary: { users: number; projects: number; cards: number }
}

export interface Sprint {
  id: number
  uuid: string
  project_id: number
  name: string
  goal: string | null
  starts_at: string | null
  ends_at: string | null
  status: SprintStatus
  position: number
  completed_at: string | null
  cards_count?: number
  done_count?: number
}

export interface ColumnRef {
  uuid: string
  name: string
  color: string
}

export interface CardMovement {
  id: number
  from_column: ColumnRef | null
  to_column: ColumnRef
  user: User | null
  created_at: string
}

export interface Comment {
  id: number
  uuid: string
  card_id: number
  body: string
  user?: User | null
  created_at: string
  updated_at?: string
}

export interface Attachment {
  id: number
  uuid: string
  card_id: number
  comment_id: number | null
  filename: string
  mime_type: string
  size: number
  url: string | null
  user?: User | null
  created_at: string
}

export interface Card {
  id: number
  uuid: string
  project_id: number
  board_column_id: number
  board_column_uuid: string
  sprint_id: number | null
  sprint_uuid: string | null
  title: string
  description: string | null
  priority: Priority
  due_date: string | null
  started_at: string | null
  completed_at: string | null
  position: number
  is_overdue: boolean
  assignee?: User | null
  creator?: User | null
  flags: Flag[]
  comments?: Comment[]
  attachments?: Attachment[]
  movements?: CardMovement[]
  comments_count?: number
  attachments_count?: number
  created_at?: string
  updated_at?: string
}

export interface BoardColumn {
  id: number
  uuid: string
  project_id: number
  name: string
  color: string
  position: number
  wip_limit: number | null
  is_done: boolean
  cards?: Card[]
  cards_count?: number
}

export interface Project {
  id: number
  uuid: string
  name: string
  description: string | null
  color: string
  logo_url: string | null
  is_archived: boolean
  owner?: User | null
  members?: User[]
  columns?: BoardColumn[]
  sprints?: Sprint[]
  active_sprint?: Sprint | null
  cards_count?: number
  created_at?: string
  updated_at?: string
}

export type SprintScope = 'all' | 'backlog' | string

export interface BoardResponse {
  data: Project
  sprint: SprintScope
  sprints: Sprint[]
}

export interface MetricsByColumn {
  uuid: string
  name: string
  color: string
  count: number
  is_done: boolean
}

export interface MetricsByPriority {
  slug: Priority
  name: string
  color: string
  count: number
}

export interface MetricsByAssignee {
  user: User | null
  count: number
  done: number
}

export interface MetricsByDay {
  date: string
  count: number
}

export interface Metrics {
  scope: { sprint: SprintScope }
  cards_total: number
  cards_done: number
  cards_open: number
  cards_overdue: number
  throughput: number
  avg_cycle_time_days: number | null
  avg_lead_time_days: number | null
  by_column: MetricsByColumn[]
  by_priority: MetricsByPriority[]
  by_assignee: MetricsByAssignee[]
  completed_by_day: MetricsByDay[]
}

export type NotificationType = 'card_assigned' | 'card_commented'

export interface NotificationData {
  card_uuid: string
  card_title: string
  project_uuid: string
  project_name: string
  actor_uuid: string
  actor_name: string
  comment_excerpt?: string
}

export interface AppNotification {
  id: string
  type: NotificationType
  data: NotificationData
  read: boolean
  read_at: string | null
  created_at: string
}

/** Envelope padrão dos JsonResources do Laravel. */
export interface Wrapped<T> {
  data: T
}

/** Envelope das listas paginadas. */
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links: {
    next: string | null
    prev: string | null
  }
}

/** Presença no board (canal presence `project.{uuid}`). */
export interface PresenceMember {
  id: number
  uuid: string
  name: string
  avatar_url: string | null
}
