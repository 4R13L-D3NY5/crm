import type { Note } from '../types/note.types'

/**
 * Detecta si una URL corresponde a un archivo de video
 */
export function isVideoMedia(url: string | null | undefined): boolean {
  if (!url) return false
  const clean = url.split('?')[0].toLowerCase()
  return ['.mp4', '.webm', '.mov', '.ogg', '.m4v', '.quicktime'].some((ext) => clean.endsWith(ext))
}

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
 * Evalúa si una nota coincide con la búsqueda permisiva por palabras parciales o atajos (#).
 *
 * 1. Si el usuario ingresa solo '#' o '##', devuelve únicamente notas que tengan un atajo configurado.
 * 2. Si el usuario escribe '#' seguido de un texto (ej: #precio), se prioriza la búsqueda por atajo.
 * 3. En búsquedas por palabras normales (ej: "medi cocha"), cada token ingresado debe encontrarse
 *    en algún campo de la nota (título, contenido, atajo, categorías, etiquetas o estados).
 */
export function matchesNoteSearch(
  note: Note,
  query: string | null | undefined,
  options?: { isPermissive?: boolean }
): boolean {
  if (!query || !query.trim()) return true

  const trimmed = query.trim()
  const isShortcutSearch = trimmed.startsWith('#')
  const cleanQuery = trimmed.replace(/^#+/, '').trim()

  // 1. Si el usuario ingresó solo '#' o '##', mostrar exclusivamente notas con atajo
  if (isShortcutSearch && cleanQuery.length === 0) {
    return Boolean(note.shortcut && note.shortcut.trim().length > 0)
  }

  const noteShortcutNorm = normalizeSearchTerm(note.shortcut?.replace(/^#+/, ''))
  const cleanQueryNorm = normalizeSearchTerm(cleanQuery)

  // 2. Si el usuario comenzó explícitamente con '#', dar match directo si el atajo lo incluye
  if (isShortcutSearch && cleanQueryNorm.length > 0) {
    if (noteShortcutNorm.includes(cleanQueryNorm)) {
      return true
    }
  }

  // 3. Extracción de tokens
  const tokens = extractSearchTokens(query)
  if (tokens.length === 0) return true

  // Campos principales de la nota
  const titleNorm = normalizeSearchTerm(note.title)
  const contentNorm = normalizeSearchTerm(note.content)

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
  const searchableHaystack = `${titleNorm} ${contentNorm} ${noteShortcutNorm} ${categoriesNorm} ${tagsNorm} ${statusesNorm}`

  // Si se fuerza modo no permisivo (frase continua)
  if (options?.isPermissive === false) {
    return searchableHaystack.includes(cleanQueryNorm)
  }

  // Búsqueda permisiva inteligente: todos los tokens deben existir en la nota
  return tokens.every((token) => searchableHaystack.includes(token))
}

/**
 * Calcula un puntaje de relevancia para ordenar las notas encontradas:
 * - Búsqueda por atajo (#): notas con atajo coincidente obtienen hasta +200 puntos
 * - Coincidencia exacta de atajo (#shortcut): +100 a +200 puntos
 * - Atajo comienza con la búsqueda: +50 a +150 puntos
 * - Coincidencia en título: +35 puntos
 * - Cada token que coincide en el título o atajo incrementa el orden de prioridad
 */
export function computeNoteSearchScore(note: Note, query: string | null | undefined): number {
  if (!query || !query.trim()) return 0

  let score = 0
  const trimmed = query.trim()
  const isShortcutSearch = trimmed.startsWith('#')
  const cleanQuery = trimmed.replace(/^#+/, '').trim()
  const normQuery = normalizeSearchTerm(cleanQuery)
  const shortcutNorm = normalizeSearchTerm(note.shortcut?.replace(/^#+/, ''))
  const titleNorm = normalizeSearchTerm(note.title)
  const tokens = extractSearchTokens(query)

  // 1. Si el usuario buscó solo '#', priorizar notas con atajo
  if (isShortcutSearch && cleanQuery.length === 0) {
    return shortcutNorm ? 100 : 0
  }

  // 2. Coincidencia directa de atajo (#)
  if (shortcutNorm && normQuery) {
    if (shortcutNorm === normQuery) {
      score += isShortcutSearch ? 200 : 100
    } else if (shortcutNorm.startsWith(normQuery)) {
      score += isShortcutSearch ? 150 : 50
    } else if (shortcutNorm.includes(normQuery)) {
      score += isShortcutSearch ? 80 : 25
    }
  } else if (isShortcutSearch && !shortcutNorm) {
    // Si buscó explícitamente con '#' pero la nota carece de atajo, restar prioridad
    score -= 30
  }

  // 3. Coincidencia directa de frase en título
  if (titleNorm && normQuery && titleNorm.includes(normQuery)) {
    score += 35
  }

  // 4. Puntuación por cada token individual
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
