import { ref, reactive } from 'vue'
import {
  getNotes,
  createNote,
  updateNote,
  deleteNote,
  togglePinNote,
  toggleArchiveNote,
  analyzeNoteEntities,
  refineNoteAi,
  type NoteFilterParams,
} from '../api/notes.api'
import type { AnalyzeResult, Note, NotePayload, RefineAiResult } from '../types/note.types'

export function useNotes() {
  const notes = ref<Note[]>([])
  const isLoading = ref(false)
  const isSaving = ref(false)
  const isAnalyzing = ref(false)
  const isRefining = ref(false)
  const errorMessage = ref<string | null>(null)

  const filters = reactive<NoteFilterParams>({
    search: '',
    category_id: undefined,
    custom_status_id: undefined,
    tag_id: undefined,
    is_pinned: undefined,
    is_archived: false,
  })

  async function fetchNotes(customFilters?: Partial<NoteFilterParams>) {
    isLoading.value = true
    errorMessage.value = null
    try {
      const activeParams = { ...filters, ...(customFilters || {}) }
      // Clean undefined and empty strings
      const cleanedParams: NoteFilterParams = {}
      if (activeParams.search?.trim()) cleanedParams.search = activeParams.search.trim()
      if (typeof activeParams.permissive === 'boolean') cleanedParams.permissive = activeParams.permissive
      if (activeParams.mode) cleanedParams.mode = activeParams.mode
      if (activeParams.category_id) cleanedParams.category_id = activeParams.category_id
      if (activeParams.custom_status_id) cleanedParams.custom_status_id = activeParams.custom_status_id
      if (activeParams.tag_id) cleanedParams.tag_id = activeParams.tag_id
      if (typeof activeParams.is_pinned === 'boolean') cleanedParams.is_pinned = activeParams.is_pinned
      if (typeof activeParams.is_archived === 'boolean') cleanedParams.is_archived = activeParams.is_archived

      notes.value = await getNotes(cleanedParams)
    } catch (err: any) {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Error al cargar notas'
    } finally {
      isLoading.value = false
    }
  }

  async function saveNote(payload: NotePayload, id?: string): Promise<Note | null> {
    isSaving.value = true
    errorMessage.value = null
    try {
      let saved: Note
      if (id) {
        saved = await updateNote(id, payload)
        const idx = notes.value.findIndex((n) => n.id === id)
        if (idx !== -1) {
          notes.value[idx] = saved
        }
      } else {
        saved = await createNote(payload)
        notes.value.unshift(saved)
      }
      return saved
    } catch (err: any) {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Error al guardar nota'
      throw err
    } finally {
      isSaving.value = false
    }
  }

  async function removeNote(id: string): Promise<boolean> {
    try {
      await deleteNote(id)
      notes.value = notes.value.filter((n) => n.id !== id)
      return true
    } catch (err: any) {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Error al eliminar nota'
      throw err
    }
  }

  async function togglePin(id: string): Promise<boolean> {
    try {
      const res = await togglePinNote(id)
      const note = notes.value.find((n) => n.id === id)
      if (note) {
        note.is_pinned = res.is_pinned
      }
      // Re-sort notes: pinned first, then by updated_at
      notes.value.sort((a, b) => {
        if (a.is_pinned === b.is_pinned) {
          return new Date(b.updated_at || '').getTime() - new Date(a.updated_at || '').getTime()
        }
        return a.is_pinned ? -1 : 1
      })
      return res.is_pinned
    } catch (err: any) {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Error al fijar nota'
      throw err
    }
  }

  async function toggleArchive(id: string): Promise<boolean> {
    try {
      const res = await toggleArchiveNote(id)
      const note = notes.value.find((n) => n.id === id)
      if (note) {
        note.is_archived = res.is_archived
      }
      // If we are currently viewing non-archived notes, remove it from list
      if (!filters.is_archived && res.is_archived) {
        notes.value = notes.value.filter((n) => n.id !== id)
      } else if (filters.is_archived && !res.is_archived) {
        notes.value = notes.value.filter((n) => n.id !== id)
      }
      return res.is_archived
    } catch (err: any) {
      errorMessage.value = err?.response?.data?.message || err?.message || 'Error al archivar nota'
      throw err
    }
  }

  async function analyzeContent(content: string): Promise<AnalyzeResult> {
    isAnalyzing.value = true
    try {
      return await analyzeNoteEntities(content)
    } finally {
      isAnalyzing.value = false
    }
  }

  async function refineWithAi(payload: {
    content: string
    action: 'spellcheck' | 'summarize' | 'tone_formal' | 'tone_persuasive' | 'custom'
    instruction?: string
  }): Promise<RefineAiResult> {
    isRefining.value = true
    try {
      return await refineNoteAi(payload)
    } finally {
      isRefining.value = false
    }
  }

  return {
    notes,
    isLoading,
    isSaving,
    isAnalyzing,
    isRefining,
    errorMessage,
    filters,
    fetchNotes,
    saveNote,
    removeNote,
    togglePin,
    toggleArchive,
    analyzeContent,
    refineWithAi,
  }
}
