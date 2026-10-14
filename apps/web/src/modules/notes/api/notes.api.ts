import { http } from '@/shared/api/http'
import type { AnalyzeResult, Note, NotePayload, RefineAiResult } from '../types/note.types'

export interface NoteFilterParams {
  search?: string
  category_id?: string
  custom_status_id?: string
  tag_id?: string
  is_pinned?: boolean
  is_archived?: boolean
}

export async function getNotes(params?: NoteFilterParams): Promise<Note[]> {
  const response = await http.get<{ data: Note[] }>('/notes', { params })
  return response.data.data
}

export async function getNote(id: string): Promise<Note> {
  const response = await http.get<{ data: Note }>(`/notes/${id}`)
  return response.data.data
}

export async function createNote(payload: NotePayload): Promise<Note> {
  const response = await http.post<{ data: Note }>('/notes', payload)
  return response.data.data
}

export async function updateNote(id: string, payload: NotePayload): Promise<Note> {
  const response = await http.put<{ data: Note }>(`/notes/${id}`, payload)
  return response.data.data
}

export async function deleteNote(id: string): Promise<void> {
  await http.delete(`/notes/${id}`)
}

export async function togglePinNote(id: string): Promise<{ id: string; is_pinned: boolean }> {
  const response = await http.patch<{ data: { id: string; is_pinned: boolean } }>(`/notes/${id}/pin`)
  return response.data.data
}

export async function toggleArchiveNote(id: string): Promise<{ id: string; is_archived: boolean }> {
  const response = await http.patch<{ data: { id: string; is_archived: boolean } }>(`/notes/${id}/archive`)
  return response.data.data
}

export async function analyzeNoteEntities(content: string): Promise<AnalyzeResult> {
  const response = await http.post<{ data: AnalyzeResult }>('/notes/analyze', { content })
  return response.data.data
}

export async function refineNoteAi(payload: {
  content: string
  action: 'spellcheck' | 'summarize' | 'tone_formal' | 'tone_persuasive' | 'custom'
  instruction?: string
}): Promise<RefineAiResult> {
  const response = await http.post<{ data: RefineAiResult }>('/notes/ai/refine', payload)
  return response.data.data
}
