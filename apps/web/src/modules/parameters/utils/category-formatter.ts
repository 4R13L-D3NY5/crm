/**
 * Utilidades para formateo y búsqueda de rutas jerárquicas de categorías
 * Formato requerido: Ancestros con su código/abreviatura -> Categoría hoja con su nombre completo
 * Ejemplo: "SEDE-CBB -> CBB-SALUD -> Medicina Veterinaria y Zootecnia"
 */

export interface FormattableCategory {
  id?: string
  name: string
  code?: string | null
  parent_id?: string | null
  parent_name?: string | null
  display_path?: string | null
  full_path?: string | null
  color?: string | null
  icon?: string | null
  [key: string]: any
}

/**
 * Obtiene la ruta optimizada para desplegar en selectores y filtros.
 */
export function formatCategoryDisplayPath(
  category: FormattableCategory,
  allCategories?: FormattableCategory[],
): string {
  if (category.display_path) {
    return category.display_path
  }

  // Si tenemos la lista completa de categorías, podemos reconstruir la jerarquía
  if (allCategories && allCategories.length > 0 && category.parent_id) {
    const byId = new Map<string, FormattableCategory>(allCategories.map((c) => [c.id || '', c]))
    const ancestors: FormattableCategory[] = []
    let currentId: string | null | undefined = category.parent_id

    while (currentId && byId.has(currentId)) {
      const parentNode: FormattableCategory = byId.get(currentId)!
      ancestors.unshift(parentNode)
      currentId = parentNode.parent_id
    }

    // Omitir raíz genérica contenedora si es "SEDES" o "Nuestras Sedes"
    if (ancestors.length > 0) {
      const root = ancestors[0]
      const rootCode = (root.code || '').toUpperCase()
      const rootName = (root.name || '').toLowerCase()
      if (rootCode === 'SEDES' || rootName.includes('nuestras sedes')) {
        ancestors.shift()
      }
    }

    const parts: string[] = []
    for (const anc of ancestors) {
      parts.push(anc.code ? anc.code.trim() : anc.name.trim())
    }
    parts.push(category.name.trim())

    return parts.join(' -> ')
  }

  // Si tiene parent_name disponible
  if (category.parent_name) {
    return `${category.parent_name} -> ${category.name}`
  }

  if (category.full_path) {
    return category.full_path
  }

  return category.code ? `[${category.code}] ${category.name}` : category.name
}

/**
 * Verifica si una categoría coincide con un término de búsqueda (nombre, código o ruta jerárquica)
 */
export function matchesCategorySearch(
  category: FormattableCategory,
  searchTerm: string,
  displayPath?: string,
): boolean {
  const term = searchTerm.toLowerCase().trim()
  if (!term) return true

  const name = (category.name || '').toLowerCase()
  const code = (category.code || '').toLowerCase()
  const path = (displayPath || category.display_path || category.full_path || '').toLowerCase()

  return name.includes(term) || code.includes(term) || path.includes(term)
}
