import * as XLSX from 'xlsx'
import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'
import type { Contact } from '../types/contact.types'

/**
 * Formatea el nombre completo del contacto
 */
export function formatContactName(contact: Contact): string {
  if (contact.name && contact.name.trim().length > 0) {
    return contact.name.trim()
  }
  const full = `${contact.first_name || ''} ${contact.last_name || ''}`.trim()
  return full || 'Sin nombre'
}

/**
 * Formatea el teléfono o número de WhatsApp
 */
export function formatContactPhone(contact: Contact): string {
  return contact.phone || 'Sin teléfono'
}

/**
 * Formatea el correo electrónico
 */
export function formatContactEmail(contact: Contact): string {
  return contact.email || '-'
}

/**
 * Formatea el canal de procedencia
 */
export function formatContactChannel(contact: Contact): string {
  if (contact.origin_channel) {
    return contact.origin_channel.toUpperCase()
  }
  if (contact.phone) {
    return 'WHATSAPP'
  }
  return 'MANUAL'
}

/**
 * Formatea la empresa o institución
 */
export function formatContactCompany(contact: Contact): string {
  if (contact.company?.name) return contact.company.name
  if (contact.companies && contact.companies[0]?.name) return contact.companies[0].name
  return 'Particular'
}

/**
 * Formatea las categorías asignadas (Sedes, Carreras, etc.)
 */
export function formatContactCategories(contact: Contact): string {
  if (!contact.categories || contact.categories.length === 0) {
    return 'Sin categoría'
  }
  return contact.categories
    .map((cat) => (cat.code ? `[${cat.code}] ${cat.name}` : cat.name))
    .join(', ')
}

/**
 * Formatea las etiquetas / tags
 */
export function formatContactTags(contact: Contact): string {
  if (!contact.tags || contact.tags.length === 0) {
    return 'Sin tags'
  }
  return contact.tags
    .map((tag) => (typeof tag === 'string' ? tag : tag.name))
    .join(', ')
}

/**
 * Formatea el estado del lead
 */
export function formatContactStatus(contact: Contact): string {
  if (contact.custom_status?.name) {
    return contact.custom_status.name
  }
  switch (contact.status) {
    case 'active':
      return 'Activo'
    case 'lead':
      return 'Lead'
    case 'inactive':
      return 'Inactivo'
    default:
      return contact.status || 'Lead'
  }
}

/**
 * Formatea la fecha de creación / registro
 */
export function formatContactDate(contact: Contact): string {
  if (!contact.created_at) return '-'
  const d = new Date(contact.created_at)
  if (isNaN(d.getTime())) return contact.created_at
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

/**
 * Genera una estampa temporal segura para nombres de archivo
 */
function getTimestampString(): string {
  const now = new Date()
  const yyyy = now.getFullYear()
  const mm = String(now.getMonth() + 1).padStart(2, '0')
  const dd = String(now.getDate()).padStart(2, '0')
  const hh = String(now.getHours()).padStart(2, '0')
  const min = String(now.getMinutes()).padStart(2, '0')
  return `${yyyy}${mm}${dd}_${hh}${min}`
}

/**
 * Descarga un Blob directamente en el navegador del usuario
 */
function triggerBrowserDownload(blob: Blob, filename: string): boolean {
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.style.display = 'none'
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  setTimeout(() => {
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)
  }, 200)
  return true
}

/**
 * Exporta una lista de contactos a un archivo Excel (.xlsx) estructurado
 */
export function exportContactsToExcel(contacts: Contact[], filename?: string): boolean {
  if (!contacts || contacts.length === 0) {
    return false
  }

  const rows = contacts.map((c, index) => ({
    '#': index + 1,
    'Nombre Completo': formatContactName(c),
    'Teléfono / WhatsApp': formatContactPhone(c),
    'Correo Electrónico': formatContactEmail(c),
    'Canal de Origen': formatContactChannel(c),
    'Empresa / Institución': formatContactCompany(c),
    'Categorías / Carreras': formatContactCategories(c),
    'Etiquetas': formatContactTags(c),
    'Estado del Lead': formatContactStatus(c),
    'Fecha de Registro': formatContactDate(c),
  }))

  const ws = XLSX.utils.json_to_sheet(rows)

  // Configuración de anchos de columna óptimos
  ws['!cols'] = [
    { wch: 6 },  // #
    { wch: 30 }, // Nombre Completo
    { wch: 22 }, // Teléfono / WhatsApp
    { wch: 28 }, // Correo Electrónico
    { wch: 16 }, // Canal de Origen
    { wch: 24 }, // Empresa / Institución
    { wch: 34 }, // Categorías / Carreras
    { wch: 28 }, // Etiquetas
    { wch: 18 }, // Estado del Lead
    { wch: 18 }, // Fecha de Registro
  ]

  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Contactos')

  const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' })
  const blob = new Blob([wbout], {
    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=UTF-8',
  })

  const targetFilename = filename || `contactos_unitepc_${getTimestampString()}.xlsx`
  return triggerBrowserDownload(blob, targetFilename)
}

/**
 * Opciones para la generación del Reporte PDF
 */
export interface ExportPdfOptions {
  title?: string
  subtitle?: string
  filename?: string
  organizationName?: string
}

/**
 * Exporta una lista de contactos a un Reporte formal en PDF (.pdf) en orientación horizontal
 */
export function exportContactsToPdf(contacts: Contact[], options?: ExportPdfOptions): boolean {
  if (!contacts || contacts.length === 0) {
    return false
  }

  // Creación del documento en formato A4 horizontal (landscape)
  const doc = new jsPDF({
    orientation: 'landscape',
    unit: 'pt',
    format: 'a4',
  })

  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()

  // Cabecera institucional elegante
  doc.setFillColor(15, 23, 42) // slate-900 (#0f172a)
  doc.rect(0, 0, pageWidth, 62, 'F')

  // Línea de acento teal UNITEPC
  doc.setFillColor(0, 168, 132) // #00a884
  doc.rect(0, 58, pageWidth, 4, 'F')

  // Título y subtítulo
  doc.setTextColor(255, 255, 255)
  doc.setFontSize(15)
  doc.setFont('helvetica', 'bold')
  doc.text(options?.title || 'UNITEPC — REPORTE DE CONTACTOS & AUDIENCIA', 28, 30)

  doc.setFontSize(8.5)
  doc.setFont('helvetica', 'normal')
  doc.setTextColor(203, 213, 225) // slate-300
  doc.text(
    options?.subtitle || 'Directorio centralizado de prospectos, segmentación de leads y procedencia omnicanal',
    28,
    46,
  )

  // Metadatos en la esquina superior derecha
  const now = new Date()
  const dateFormatted = `${now.toLocaleDateString('es-ES')} ${now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })}`
  doc.setFontSize(8)
  doc.setTextColor(241, 245, 249) // slate-100
  doc.text(`Fecha de emisión: ${dateFormatted}`, pageWidth - 28, 28, { align: 'right' })
  doc.text(`Contactos exportados: ${contacts.length}`, pageWidth - 28, 44, { align: 'right' })

  // Preparación de datos para AutoTable
  const head = [
    [
      '#',
      'Nombre Completo',
      'Teléfono / WhatsApp',
      'Correo',
      'Origen',
      'Categorías / Carrera',
      'Etiquetas',
      'Estado',
      'Registro',
    ],
  ]

  const body = contacts.map((c, index) => [
    (index + 1).toString(),
    formatContactName(c),
    formatContactPhone(c),
    formatContactEmail(c),
    formatContactChannel(c),
    formatContactCategories(c),
    formatContactTags(c),
    formatContactStatus(c),
    formatContactDate(c),
  ])

  autoTable(doc, {
    head,
    body,
    startY: 74,
    margin: { left: 24, right: 24, top: 74, bottom: 36 },
    theme: 'grid',
    headStyles: {
      fillColor: [30, 41, 59], // slate-800
      textColor: [255, 255, 255],
      fontStyle: 'bold',
      fontSize: 8,
      halign: 'left',
      cellPadding: 5,
    },
    styles: {
      fontSize: 7.5,
      cellPadding: 4.5,
      overflow: 'linebreak',
      textColor: [30, 41, 59],
      lineColor: [226, 232, 240],
      lineWidth: 0.5,
    },
    alternateRowStyles: {
      fillColor: [248, 250, 252], // slate-50
    },
    columnStyles: {
      0: { cellWidth: 22, halign: 'center' },
      1: { cellWidth: 110, fontStyle: 'bold' },
      2: { cellWidth: 84 },
      3: { cellWidth: 106 },
      4: { cellWidth: 54, halign: 'center' },
      5: { cellWidth: 150 },
      6: { cellWidth: 115 },
      7: { cellWidth: 80, halign: 'center' },
      8: { cellWidth: 60, halign: 'center' },
    },
    didDrawPage: (data) => {
      // Pie de página en cada página
      const pageCount = (doc.internal as unknown as { getNumberOfPages: () => number }).getNumberOfPages()
      const currentPage = data.pageNumber

      doc.setFillColor(241, 245, 249)
      doc.rect(24, pageHeight - 28, pageWidth - 48, 1, 'F')

      doc.setFontSize(7.5)
      doc.setTextColor(100, 116, 139) // slate-500
      doc.setFont('helvetica', 'normal')
      doc.text(
        'XpertiFlow CRM — Sistema Omnicanal UNITEPC (Reporte Oficial de Audiencia)',
        28,
        pageHeight - 16,
      )
      doc.text(
        `Página ${currentPage} de ${pageCount}`,
        pageWidth - 28,
        pageHeight - 16,
        { align: 'right' },
      )
    },
  })

  const targetFilename = options?.filename || `reporte_contactos_unitepc_${getTimestampString()}.pdf`
  doc.save(targetFilename)
  return true
}
