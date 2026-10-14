export interface Note {
  id: string
  organization_id: string
  user_id: string
  user?: {
    id: string
    name: string
    email: string
  }
  title: string | null
  content: string
  color: string
  is_pinned: boolean
  is_archived: boolean
  shortcut?: string | null
  visibility: 'all' | 'categories' | 'private'
  metadata?: Record<string, any> | null
  categories?: Array<{
    id: string
    name: string
    color?: string
    icon?: string
    slug?: string
  }>
  custom_statuses?: Array<{
    id: string
    name: string
    color?: string
    icon?: string
    slug?: string
  }>
  tags?: Array<{
    id: string
    name: string
    color_hex?: string
    slug?: string
  }>
  created_at?: string
  updated_at?: string
}

export interface NotePayload {
  title?: string | null
  content: string
  color?: string
  is_pinned?: boolean
  is_archived?: boolean
  shortcut?: string | null
  visibility?: 'all' | 'categories' | 'private'
  category_ids?: string[]
  custom_status_ids?: string[]
  tag_ids?: string[]
  metadata?: Record<string, any> | null
}

export interface DetectedEntity {
  id: string
  name: string
  color?: string
  color_hex?: string
  icon?: string
  slug?: string
  matched_text?: string
}

export interface AnalyzeResult {
  detected_categories: DetectedEntity[]
  detected_custom_statuses: DetectedEntity[]
  detected_tags: DetectedEntity[]
  total_detected: number
}

export interface RefineAiResult {
  original_content: string
  refined_content: string
  action: 'spellcheck' | 'summarize' | 'tone_formal' | 'tone_persuasive' | 'custom'
  provider: string
}
