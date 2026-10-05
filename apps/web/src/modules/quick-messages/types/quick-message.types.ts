export interface QuickMessage {
  id: string
  shortcut: string
  message: string
  media_url?: string | null
  media_type?: string | null
  is_general: boolean
  user_id?: string | null
  created_at?: string
  updated_at?: string
}

export interface QuickMessagePayload {
  shortcut: string
  message: string
  media_url?: string | null
  media_type?: string | null
  is_general?: boolean
}
