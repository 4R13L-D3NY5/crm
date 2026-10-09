import { http } from '@/shared/api/http'
import type {
  AgentReportData,
  CategoryReportData,
  CsatReportData,
  DashboardReport,
} from '../types/report.types'

export async function getDashboardReport(): Promise<DashboardReport> {
  const response = await http.get<{ data: DashboardReport }>('/reports/dashboard')
  return response.data.data
}

export async function getAgentsReport(params?: {
  start_date?: string
  end_date?: string
}): Promise<AgentReportData> {
  const response = await http.get<{ data: AgentReportData }>('/reports/agents', { params })
  return response.data.data
}

export async function getCategoriesReport(params?: {
  start_date?: string
  end_date?: string
  parent_id?: string
}): Promise<CategoryReportData> {
  const response = await http.get<{ data: CategoryReportData }>('/reports/categories', { params })
  return response.data.data
}

export async function getCsatReport(params?: {
  start_date?: string
  end_date?: string
}): Promise<CsatReportData> {
  const response = await http.get<{ data: CsatReportData }>('/reports/csat', { params })
  return response.data.data
}

export async function getAnalyticsReport(params?: {
  start_date?: string
  end_date?: string
}): Promise<any> {
  const response = await http.get('/reports/analytics', { params })
  return response.data.data
}

export function getExportUrl(type: 'agents' | 'categories' | 'analytics', params?: { start_date?: string; end_date?: string }): string {
  const base = '/api/reports/export'
  const searchParams = new URLSearchParams()
  searchParams.set('type', type)
  searchParams.set('download', '1')
  if (params?.start_date) searchParams.set('start_date', params.start_date)
  if (params?.end_date) searchParams.set('end_date', params.end_date)
  return `${base}?${searchParams.toString()}`
}
