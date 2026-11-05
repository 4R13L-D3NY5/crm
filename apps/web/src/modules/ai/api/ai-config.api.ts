import { http } from '@/shared/api/http'

export interface AiConfigData {
  provider: 'minimax' | 'gemini' | 'openai' | 'deepseek' | 'custom'
  model: string
  base_url: string
  has_api_key: boolean
  api_key_masked: string
  api_key?: string
  system_prompt: string
  similarity_threshold: number
  budget_enabled?: boolean
  spending_limit_usd?: number
  current_spending_usd?: number
  total_tokens_used?: number
  total_requests_count?: number
  is_budget_exceeded?: boolean
  smart_reply_enabled?: boolean
  smart_reply_delay_seconds?: number
  smart_reply_confidence_threshold?: number
  auto_tag_recommendation_enabled?: boolean
  entity_extraction_enabled?: boolean
  sentiment_radar_enabled?: boolean
}

export interface AiTestPayload {
  provider: string
  model: string
  api_key?: string
  base_url?: string
}

export interface AiTestResult {
  status: 'success' | 'error'
  message: string
  latency_ms: number
  provider?: string
  model?: string
  reply?: string
}

export async function getAiConfig(): Promise<AiConfigData> {
  const res = await http.get<{ data: AiConfigData }>('/ai/config')
  return res.data.data
}

export async function updateAiConfig(payload: Partial<AiConfigData>): Promise<AiConfigData> {
  const res = await http.put<{ data: AiConfigData; message: string }>('/ai/config', payload)
  return res.data.data
}

export async function testAiConnection(payload: AiTestPayload): Promise<AiTestResult> {
  const res = await http.post<AiTestResult>('/ai/test', payload)
  return res.data
}

export async function resetAiBudget(): Promise<{ current_spending_usd: number; total_tokens_used: number; total_requests_count: number }> {
  const res = await http.post<{ data: { current_spending_usd: number; total_tokens_used: number; total_requests_count: number }; message: string }>('/ai/budget/reset')
  return res.data.data
}
