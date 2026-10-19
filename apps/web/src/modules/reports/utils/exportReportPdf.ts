import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import type {
  AgentReportItem,
  AgentReportSummary,
  CategoryCampusReportItem,
  CategoryReportSummary,
  CsatReportData,
  TopCareerReportItem,
} from '../types/report.types'

export interface ReportPdfPayload {
  activeTab: 'general' | 'agents' | 'categories' | 'csat'
  organizationName: string
  dateRangeText: string
  generalKpis?: {
    chats_created: number
    chats_resolved: number
    resolved_percentage: number
    in_attention: number
    with_replies: number
    unanswered: number
  }
  timeMetrics?: {
    first_response: string
    resolution: string
    in_chatbot: string
    human_attention: string
  }
  agentsSummary?: AgentReportSummary
  agentsList?: AgentReportItem[]
  categoriesSummary?: CategoryReportSummary
  campusList?: CategoryCampusReportItem[]
  topCareers?: TopCareerReportItem[]
  csatData?: CsatReportData
}

export function exportReportToPdf(payload: ReportPdfPayload): void {
  const doc = new jsPDF({
    orientation: 'portrait',
    unit: 'mm',
    format: 'a4',
  })

  const pageWidth = doc.internal.pageSize.getWidth()
  const pageHeight = doc.internal.pageSize.getHeight()
  const primaryColor: [number, number, number] = [13, 148, 136] // Teal
  const darkColor: [number, number, number] = [15, 23, 42]     // Slate dark
  const greyColor: [number, number, number] = [100, 116, 139]   // Slate grey

  const now = new Date()
  const generatedAt = now.toLocaleString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })

  // ==========================================
  // 1. ENCABEZADO INSTITUCIONAL ELEGANTE
  // ==========================================
  // Barra superior decorativa
  doc.setFillColor(...primaryColor)
  doc.rect(0, 0, pageWidth, 6, 'F')

  // Fondo del bloque de membrete
  doc.setFillColor(248, 250, 252)
  doc.rect(0, 6, pageWidth, 32, 'F')
  doc.setDrawColor(226, 232, 240)
  doc.line(0, 38, pageWidth, 38)

  // Nombre de la Organización y Título
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(15)
  doc.setTextColor(...darkColor)
  doc.text(payload.organizationName || 'Universidad UNITEPC', 14, 18)

  doc.setFontSize(8.5)
  doc.setFont('helvetica', 'normal')
  doc.setTextColor(...greyColor)
  doc.text('SISTEMA DE GESTIÓN MULTICANAL & ANALÍTICA DE ATENCIÓN AL CLIENTE', 14, 24)

  // Metadatos a la derecha
  doc.setFontSize(8)
  doc.text(`Período: ${payload.dateRangeText}`, pageWidth - 14, 18, { align: 'right' })
  doc.text(`Generado: ${generatedAt}`, pageWidth - 14, 24, { align: 'right' })
  doc.text(`Entorno: Producción Oficial`, pageWidth - 14, 30, { align: 'right' })

  // Título del Reporte según Pestaña
  let reportTitle = 'INFORME DE RENDIMIENTO & MÉTRICAS'
  if (payload.activeTab === 'general') {
    reportTitle = 'INFORME EJECUTIVO DE RENDIMIENTO GENERAL & SLA'
  } else if (payload.activeTab === 'agents') {
    reportTitle = 'EVALUACIÓN DE DESEMPEÑO Y CUMPLIMIENTO SLA DE ASESORES'
  } else if (payload.activeTab === 'categories') {
    reportTitle = 'DISTRIBUCIÓN DE DEMANDA POR CATEGORÍAS & SUBCATEGORÍAS'
  } else if (payload.activeTab === 'csat') {
    reportTitle = 'REPORTE DE CALIDAD & CALIFICACIÓN DEL CLIENTE (SATISFACCIÓN)'
  }

  doc.setFont('helvetica', 'bold')
  doc.setFontSize(12.5)
  doc.setTextColor(...primaryColor)
  doc.text(reportTitle, 14, 48)

  let currentY = 54

  // ==========================================
  // 2. CONTENIDO SEGÚN LA PESTAÑA ACTIVA
  // ==========================================
  if (payload.activeTab === 'general' && payload.generalKpis) {
    const kpis = payload.generalKpis
    const times = payload.timeMetrics

    // Cuadro de Resumen Ejecutivo de Métricas
    autoTable(doc, {
      startY: currentY,
      head: [['Métrica de Volumen', 'Valor Registrado', 'Métrica de Tiempo', 'Promedio']],
      body: [
        ['Conversaciones Creadas', kpis.chats_created.toString(), 'Tiempo Primera Respuesta', times?.first_response || '0s'],
        ['Atenciones Resueltas / Cerradas', `${kpis.chats_resolved} (${kpis.resolved_percentage}%)`, 'Tiempo Medio de Resolución', times?.resolution || '0s'],
        ['En Atención Activa', kpis.in_attention.toString(), 'Tiempo en Menú / Chatbot', times?.in_chatbot || '0s'],
        ['Con Interacción Humana', kpis.with_replies.toString(), 'Tiempo Asesor Humano', times?.human_attention || '0s'],
        ['Sin Respuesta (En Espera SLA)', kpis.unanswered.toString(), 'Estado Global SLA', kpis.unanswered > 0 ? 'Con pendientes' : 'Al día'],
      ],
      theme: 'grid',
      headStyles: { fillColor: primaryColor, textColor: 255, fontStyle: 'bold', fontSize: 9 },
      styles: { fontSize: 8.5, cellPadding: 3.5, textColor: darkColor },
      columnStyles: {
        0: { fontStyle: 'bold', cellWidth: 50 },
        1: { cellWidth: 40 },
        2: { fontStyle: 'bold', cellWidth: 50 },
        3: { cellWidth: 45 },
      },
    })

    const finalY = (doc as any).lastAutoTable?.finalY || currentY + 50
    currentY = finalY + 12

    // Tabla de distribución horaria si existe
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    doc.setTextColor(...darkColor)
    doc.text('Distribución de Carga Horaria (Horas Pico de Atención)', 14, currentY)
    currentY += 4

    autoTable(doc, {
      startY: currentY,
      head: [['Franja Horaria', 'Nivel de Tráfico Estimado', 'Atención Recomendada']],
      body: [
        ['08:00 - 12:00', 'Alto (Turno Mañana)', 'Mayor dotación de operadores para mantener SLA < 10 min'],
        ['12:00 - 14:00', 'Medio (Horario Continuo)', 'Atención rotativa y soporte por chatbot automatizado'],
        ['14:00 - 18:00', 'Pico Máximo (Turno Tarde)', 'Máxima demanda de postulantes e inscripciones'],
        ['18:00 - 22:00', 'Moderado (Turno Vespertino)', 'Consultas de carreras nocturnas y postgrados'],
      ],
      theme: 'striped',
      headStyles: { fillColor: darkColor, textColor: 255, fontStyle: 'bold', fontSize: 8.5 },
      styles: { fontSize: 8, cellPadding: 3 },
    })

  } else if (payload.activeTab === 'agents' && payload.agentsList) {
    const summary = payload.agentsSummary

    // Resumen Superior en cajas
    if (summary) {
      autoTable(doc, {
        startY: currentY,
        head: [['Chats Asignados', 'Chats Resueltos', 'A Tiempo (SLA)', 'Con Retraso', 'Sin Respuesta', '% Cumplimiento SLA']],
        body: [[
          summary.total_assigned.toString(),
          summary.total_resolved.toString(),
          summary.total_on_time.toString(),
          summary.total_delayed.toString(),
          summary.total_unanswered.toString(),
          `${summary.sla_compliance_rate}%`,
        ]],
        theme: 'grid',
        headStyles: { fillColor: darkColor, textColor: 255, fontStyle: 'bold', halign: 'center', fontSize: 8.5 },
        bodyStyles: { halign: 'center', fontStyle: 'bold', fontSize: 9.5, textColor: primaryColor },
      })
      currentY = (doc as any).lastAutoTable?.finalY + 8
    }

    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    doc.setTextColor(...darkColor)
    doc.text('Detalle Individual por Asesor / Operador de Atención', 14, currentY)
    currentY += 4

    const tableRows = payload.agentsList.map(a => [
      a.name,
      a.assigned_count.toString(),
      a.resolved_count.toString(),
      a.sla_on_time.toString(),
      a.sla_delayed.toString(),
      a.unanswered.toString(),
      `${a.sla_compliance_rate}%`,
      a.avg_first_response_time,
      `${a.avg_rating} ⭐`,
    ])

    autoTable(doc, {
      startY: currentY,
      head: [['Asesor / Operador', 'Asignados', 'Resueltos', 'A Tiempo', 'Retraso', 'Sin Resp.', '% SLA', '1ra Resp.', 'Calificación']],
      body: tableRows,
      theme: 'striped',
      headStyles: { fillColor: primaryColor, textColor: 255, fontStyle: 'bold', fontSize: 8, halign: 'center' },
      styles: { fontSize: 7.5, cellPadding: 2.5, textColor: darkColor },
      columnStyles: {
        0: { halign: 'left', fontStyle: 'bold', cellWidth: 42 },
        1: { halign: 'center' },
        2: { halign: 'center' },
        3: { halign: 'center', textColor: [16, 185, 129] },
        4: { halign: 'center', textColor: [245, 158, 11] },
        5: { halign: 'center', textColor: [239, 68, 68] },
        6: { halign: 'center', fontStyle: 'bold' },
        7: { halign: 'center' },
        8: { halign: 'center', fontStyle: 'bold', textColor: [217, 119, 6] },
      },
    })

  } else if (payload.activeTab === 'categories') {
    const summary = payload.categoriesSummary

    if (summary) {
      autoTable(doc, {
        startY: currentY,
        head: [['Prospectos Categorizados', 'Chats Categorizados', 'Categoría Principal Líder', 'Subcategoría / Carrera Líder']],
        body: [[
          summary.total_categorized_contacts.toString(),
          summary.total_categorized_conversations.toString(),
          summary.top_campus || summary.top_category || 'N/A',
          summary.top_career || summary.top_subcategory || 'N/A',
        ]],
        theme: 'grid',
        headStyles: { fillColor: darkColor, textColor: 255, fontStyle: 'bold', halign: 'center', fontSize: 8.5 },
        bodyStyles: { halign: 'center', fontStyle: 'bold', fontSize: 9.5, textColor: primaryColor },
      })
      currentY = (doc as any).lastAutoTable?.finalY + 8
    }

    if (payload.topCareers && payload.topCareers.length > 0) {
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(10)
      doc.setTextColor(...darkColor)
      doc.text('Ranking de Carreras / Subcategorías con Mayor Demanda', 14, currentY)
      currentY += 4

      const careerRows = payload.topCareers.map((c, idx) => [
        `#${idx + 1}`,
        c.name,
        c.code || '-',
        c.contacts_count.toString(),
        c.conversations_count.toString(),
        `${c.percentage}%`,
      ])

      autoTable(doc, {
        startY: currentY,
        head: [['#', 'Carrera / Subcategoría', 'Código', 'Prospectos', 'Conversaciones', '% Demanda']],
        body: careerRows,
        theme: 'striped',
        headStyles: { fillColor: primaryColor, textColor: 255, fontStyle: 'bold', fontSize: 8, halign: 'center' },
        styles: { fontSize: 8, cellPadding: 2.5, textColor: darkColor },
        columnStyles: {
          0: { halign: 'center', cellWidth: 12 },
          1: { fontStyle: 'bold' },
          2: { halign: 'center' },
          3: { halign: 'center' },
          4: { halign: 'center' },
          5: { halign: 'center', fontStyle: 'bold', textColor: primaryColor },
        },
      })
      currentY = (doc as any).lastAutoTable?.finalY + 8
    }

    if (payload.campusList && payload.campusList.length > 0) {
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(10)
      doc.setTextColor(...darkColor)
      doc.text('Desglose por Sedes / Categorías Principales', 14, currentY)
      currentY += 4

      const campusRows = payload.campusList.map(c => [
        c.name,
        c.code || '-',
        c.contacts_count.toString(),
        c.conversations_count.toString(),
        c.won_leads.toString(),
        `${c.conversion_rate}%`,
        `${c.percentage}%`,
      ])

      autoTable(doc, {
        startY: currentY,
        head: [['Categoría / Sede', 'Código', 'Prospectos', 'Chats', 'Ganados', '% Conversión', '% Total']],
        body: campusRows,
        theme: 'striped',
        headStyles: { fillColor: darkColor, textColor: 255, fontStyle: 'bold', fontSize: 8, halign: 'center' },
        styles: { fontSize: 8, cellPadding: 2.5, textColor: darkColor },
        columnStyles: {
          0: { fontStyle: 'bold' },
          1: { halign: 'center' },
          2: { halign: 'center' },
          3: { halign: 'center' },
          4: { halign: 'center', textColor: [16, 185, 129], fontStyle: 'bold' },
          5: { halign: 'center', fontStyle: 'bold', textColor: primaryColor },
          6: { halign: 'center' },
        },
      })
    }

  } else if (payload.activeTab === 'csat' && payload.csatData) {
    const csat = payload.csatData

    // Tarjeta gigante de calificación
    autoTable(doc, {
      startY: currentY,
      head: [['Puntaje Promedio de Atención', 'Porcentaje de Satisfacción', 'Total de Clientes Encuestados']],
      body: [[
        `${csat.average_score} / 5.0 ⭐`,
        `${csat.satisfaction_percentage}%`,
        `${csat.total_responses} respuestas`,
      ]],
      theme: 'grid',
      headStyles: { fillColor: darkColor, textColor: 255, fontStyle: 'bold', halign: 'center', fontSize: 9 },
      bodyStyles: { halign: 'center', fontStyle: 'bold', fontSize: 11, textColor: [217, 119, 6] },
    })

    currentY = (doc as any).lastAutoTable?.finalY + 10

    doc.setFont('helvetica', 'bold')
    doc.setFontSize(10)
    doc.setTextColor(...darkColor)
    doc.text('Desglose de Calificaciones Recibidas de los Clientes', 14, currentY)
    currentY += 4

    const s5 = csat.distribution?.[5] ?? csat.stars_breakdown?.['5_stars'] ?? 0
    const s4 = csat.distribution?.[4] ?? csat.stars_breakdown?.['4_stars'] ?? 0
    const s3 = csat.distribution?.[3] ?? csat.stars_breakdown?.['3_stars'] ?? 0
    const s2 = csat.distribution?.[2] ?? csat.stars_breakdown?.['2_stars'] ?? 0
    const s1 = csat.distribution?.[1] ?? csat.stars_breakdown?.['1_star'] ?? 0

    const distRows = [
      ['5 Estrellas (Excelente)', s5.toString(), '⭐⭐⭐⭐⭐', csat.total_responses > 0 ? `${Math.round((s5 / csat.total_responses) * 100)}%` : '0%'],
      ['4 Estrellas (Muy Bueno)', s4.toString(), '⭐⭐⭐⭐', csat.total_responses > 0 ? `${Math.round((s4 / csat.total_responses) * 100)}%` : '0%'],
      ['3 Estrellas (Regular)', s3.toString(), '⭐⭐⭐', csat.total_responses > 0 ? `${Math.round((s3 / csat.total_responses) * 100)}%` : '0%'],
      ['2 Estrellas (Malo)', s2.toString(), '⭐⭐', csat.total_responses > 0 ? `${Math.round((s2 / csat.total_responses) * 100)}%` : '0%'],
      ['1 Estrella (Deficiente)', s1.toString(), '⭐', csat.total_responses > 0 ? `${Math.round((s1 / csat.total_responses) * 100)}%` : '0%'],
    ]

    autoTable(doc, {
      startY: currentY,
      head: [['Escala de Calificación', 'Cantidad de Respuestas', 'Iconografía', 'Porcentaje del Total']],
      body: distRows,
      theme: 'striped',
      headStyles: { fillColor: primaryColor, textColor: 255, fontStyle: 'bold', fontSize: 8.5 },
      styles: { fontSize: 8, cellPadding: 3, textColor: darkColor },
      columnStyles: {
        0: { fontStyle: 'bold' },
        1: { halign: 'center' },
        2: { halign: 'center' },
        3: { halign: 'center', fontStyle: 'bold' },
      },
    })
  }

  // ==========================================
  // 3. PIE DE PÁGINA PROFESIONAL EN TODAS LAS PÁGINAS
  // ==========================================
  const pageCount = doc.getNumberOfPages()
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i)

    // Línea divisoria de pie de página
    doc.setDrawColor(226, 232, 240)
    doc.line(14, pageHeight - 12, pageWidth - 14, pageHeight - 12)

    doc.setFont('helvetica', 'normal')
    doc.setFontSize(7.5)
    doc.setTextColor(...greyColor)
    doc.text(
      'Documento de carácter institucional y confidencial — Generado automáticamente por XpertiFlow CRM V2 Suite Pro',
      14,
      pageHeight - 7
    )
    doc.text(
      `Página ${i} de ${pageCount}`,
      pageWidth - 14,
      pageHeight - 7,
      { align: 'right' }
    )
  }

  // Descarga del archivo PDF
  const cleanFileName = `Reporte_${payload.activeTab}_${now.toISOString().split('T')[0]}.pdf`
  doc.save(cleanFileName)
}
