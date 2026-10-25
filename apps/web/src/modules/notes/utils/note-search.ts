import type { Note } from '../types/note.types'

/**
 * Normaliza una cadena de texto para búsquedas permisivas:
 * - Convierte a minúsculas
 * - Remueve acentos y diacríticos (ej: medicina/medicina, cochabamba/cochabamba, prótesis/protesis)
 * - Remueve espacios iniciales y finales
 */
export function normalizeSearchTerm(str: string | null | undefined): string {
  if (!str) return ''
  return str
    .toString()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim()
}

/**
 * Divide la consulta en tokens o palabras clave individuales.
 * Admite espacios, comas, puntos y símbolos comunes como separadores.
 * Remueve prefijos '#' de atajos para permitir buscar con o sin numeral.
 */
export function extractSearchTokens(query: string | null | undefined): string[] {
  if (!query || !query.trim()) return []

  const clean = query.replace(/^#+/, '').trim()
  if (!clean) return []

  return clean
    .split(/[\s,;+]+/)
    .map((token) => normalizeSearchTerm(token.replace(/^#+/, '')))
    .filter((token) => token.length > 0)
}

/**
 * Evalúa si una nota coincide con la búsqueda permisiva por palabras parciales.
 *
 * Cada token ingresado (ej: "medi", "cocha") debe encontrarse como subcadena o prefijo
 * en al menos uno de los campos de la nota (título, contenido, atajo, categorías, etiquetas o estados).
 *
 * Si se ingresan varios tokens (ej: "medi cocha"), la nota solo coincidirá si TODOS
 * los tokens están presentes en la nota.
 */
export function matchesNoteSearch(note: Note, query: string | null | undefined): boolean {
  if (!query || !query.trim()) return true

  const tokens = extractSearchTokens(query)
  if (tokens.length === 0) return true

  // Campos principales de la nota
  const titleNorm = normalizeSearchTerm(note.title)
  const contentNorm = normalizeSearchTerm(note.content)
  const shortcutNorm = normalizeSearchTerm(note.shortcut?.replace(/^#+/, ''))

  // Categorías y subcategorías vinculadas
  const categoriesNorm = (note.categories || [])
    .map((c: any) => `${normalizeSearchTerm(c.name)} ${normalizeSearchTerm(c.display_path)} ${normalizeSearchTerm(c.full_path)} ${normalizeSearchTerm(c.code)}`)
    .join(' ')

  // Etiquetas vinculadas
  const tagsNorm = (note.tags || [])
    .map((t: any) => `${normalizeSearchTerm(t.name)} ${normalizeSearchTerm(t.slug)}`)
    .join(' ')

  // Estados personalizados vinculados
  const statusesNorm = (note.custom_statuses || [])
    .map((s: any) => `${normalizeSearchTerm(s.name)} ${normalizeSearchTerm(s.slug)}`)
    .join(' ')

  // Bolsa global de búsqueda de la nota
  const searchableHaystack = `${titleNorm} ${contentNorm} ${shortcutNorm} ${categoriesNorm} ${tagsNorm} ${statusesNorm}`

  // Verificar que TODOS los tokens estén presentes en la bolsa de búsqueda
  return tokens.every((token) => searchableHaystack.includes(token))
}

/**
 * Calcula un puntaje de relevancia para ordenar las notas encontradas:
 * - Coincidencia exacta de atajo (#shortcut): +100 puntos
 * - Atajo comienza con la búsqueda: +50 puntos
 * - Coincidencia en título: +35 puntos
 * - Cada token que coincide en el título o atajo incrementa el orden de prioridad
 */
export function computeNoteSearchScore(note: Note, query: string | null | undefined): number {
  if (!query || !query.trim()) return 0

  let score = 0
  const normQuery = normalizeSearchTerm(query.replace(/^#+/, ''))
  const shortcutNorm = normalizeSearchTerm(note.shortcut?.replace(/^#+/, ''))
  const titleNorm = normalizeSearchTerm(note.title)
  const tokens = extractSearchTokens(query)

  // 1. Coincidencia directa de atajo (#)
  if (shortcutNorm && shortcutNorm === normQuery) {
    score += 100
  } else if (shortcutNorm && shortcutNorm.startsWith(normQuery)) {
    score += 50
  } else if (shortcutNorm && shortcutNorm.includes(normQuery)) {
    score += 25
  }

  // 2. Coincidencia directa de frase en título
  if (titleNorm && titleNorm.includes(normQuery)) {
    score += 35
  }

  // 3. Puntuación por token
  for (const token of tokens) {
    if (shortcutNorm && shortcutNorm.includes(token)) {
      score += 15
    }
    if (titleNorm && titleNorm.includes(token)) {
      score += 12
    }
  }

  return score
}
