import { http } from '@/shared/api/http'
import type {
  AnalyzeResult,
  KeepImportResult,
  KeepParsedNote,
  KeepPreviewResponse,
  Note,
  NotePayload,
  RefineAiResult,
  SimilarMatrixResponse,
  SimilarNotesResponse,
  SuggestEntitiesPayload,
  SuggestEntitiesResponse,
} from '../types/note.types'

export interface NoteFilterParams {
  search?: string
  permissive?: boolean
  mode?: string
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

export async function uploadNoteImage(file: File): Promise<{ image_url: string; message: string }> {
  const formData = new FormData()
  formData.append('image', file)
  const response = await http.post<{ image_url: string; message: string }>('/notes/upload-image', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return response.data
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

export async function getSimilarNotes(
  noteId: string,
  limit = 5,
  mode: 'auto' | 'ai' | 'algorithmic' = 'auto'
): Promise<SimilarNotesResponse> {
  const response = await http.get<SimilarNotesResponse>(`/notes/${noteId}/similar`, {
    params: { limit, mode },
  })
  return response.data
}

export async function searchSimilarDraft(payload: {
  content: string
  title?: string
  exclude_id?: string
  limit?: number
  mode?: 'auto' | 'ai' | 'algorithmic'
}): Promise<SimilarNotesResponse> {
  const response = await http.post<SimilarNotesResponse>('/notes/similar', payload)
  return response.data
}

/**
 * Previsualizar notas contenidas en el ZIP de Google Takeout
 */
export async function previewKeepZip(file: File): Promise<KeepPreviewResponse> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await http.post<KeepPreviewResponse>('/notes/preview-keep', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
  return response.data
}

/**
 * Importar lote de notas seleccionadas y editadas
 */
export async function importKeepBatch(
  notes: KeepParsedNote[],
  indexAi = true
): Promise<KeepImportResult> {
  const response = await http.post<{ message: string; data: KeepImportResult }>('/notes/import-batch', {
    notes,
    index_ai: indexAi,
  })
  return response.data.data
}

/**
 * Cargar e ingerir documento institucional (.pdf, .docx, .xlsx, .txt)
 */
export async function uploadKnowledgeDocument(formData: FormData): Promise<Note> {
  const response = await http.post<{ message: string; data: Note }>('/notes/upload-document', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  })
  return response.data.data
}

/**
 * Consultar matriz de pares de tarjetas similares entre sí en el tablero
 */
export async function getSimilarMatrix(params?: {
  mode?: 'auto' | 'ai' | 'algorithmic'
  search?: string
  category_id?: string
  limit?: number
}): Promise<SimilarMatrixResponse> {
  const response = await http.post<SimilarMatrixResponse>('/notes/similar-matrix', params || {})
  return response.data
}

/**
 * Sugerir categorías, estados y etiquetas (con o sin IA) para una nota
 */
export async function suggestEntities(
  payload: SuggestEntitiesPayload
): Promise<SuggestEntitiesResponse> {
  const response = await http.post<SuggestEntitiesResponse>('/notes/ai/suggest-entities', payload)
  return response.data
}
