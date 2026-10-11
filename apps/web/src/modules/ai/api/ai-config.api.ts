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
