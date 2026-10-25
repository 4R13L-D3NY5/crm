export interface Note {
  id: string
  organization_id: string
  user_id: string
  user?: {
    id: string
    name: string
    email: string
  }
  author_name?: string
  title: string | null
  content: string
  image_url?: string | null
  media_url?: string | null
  media_type?: 'image' | 'video' | null
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
    display_path?: string
    full_path?: string
    parent_id?: string
    code?: string
  }>
  custom_statuses?: Array<{
    id: string
    name: string
    color?: string
    icon?: string
    slug?: string
    display_path?: string
    full_path?: string
    parent_id?: string
    code?: string
  }>
  tags?: Array<{
    id: string
    name: string
    color_hex?: string
    slug?: string
    display_path?: string
    full_path?: string
    parent_id?: string
    code?: string
  }>
  created_at?: string
  updated_at?: string
}

export interface NotePayload {
  title?: string | null
  content: string
  image_url?: string | null
  media_url?: string | null
  media_type?: 'image' | 'video' | null
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
  display_path?: string
  full_path?: string
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

export interface SimilarNoteItem {
  note: Note
  similarity_percent: number
  similarity_reason: string
  source: 'ia' | 'semantic_analysis'
}

export interface SimilarNotesResponse {
  data: SimilarNoteItem[]
  target_note?: Note
  total: number
}

export interface KeepParsedNote {
  temp_id: string
  title: string
  content: string
  image_url?: string | null
  media_url?: string | null
  media_type?: 'image' | 'video' | null
  color: string
  keep_color: string
  is_pinned: boolean
  is_archived: boolean
  shortcut: string
  labels: string[]
  source_file: string
  selected: boolean
}

export interface KeepPreviewResponse {
  message?: string
  data: {
    total_detected: number
    notes: KeepParsedNote[]
  }
}

export interface KeepImportResult {
  imported_count: number
  ai_chunks_created: number
}

export interface SimilarPairItem {
  id: string
  note_a: Note
  note_b: Note
  similarity_percent: number
  similarity_reason: string
  source: 'ia' | 'algorithmic'
  matched_keywords?: string[]
}

export interface SimilarMatrixResponse {
  data: SimilarPairItem[]
  total: number
  total_scanned: number
  mode: string
}

export interface SuggestEntitiesPayload {
  title?: string
  content: string
  image_url?: string | null
  mode?: 'heuristic' | 'ai'
}

export interface SuggestEntitiesResponse {
  categories: DetectedEntity[]
  custom_statuses: DetectedEntity[]
  tags: DetectedEntity[]
  total: number
  reason?: string
  source: 'ia' | 'heuristic' | 'heuristic_fallback'
  message?: string
}
