export interface DealRelationOption {
  id: string
  name: string
}

export interface Deal {
  id: string
  pipeline_id: string
  pipeline_stage_id: string
  name: string
  status: 'open' | 'won' | 'lost'
  amount: number
  probability: number
  expected_close_date: string | null
  notes: string | null
  contact: DealRelationOption | null
  company: DealRelationOption | null
}

export interface PipelineStage {
  id: string
  pipeline_id: string
  name: string
  position: number
  probability: number
  color: string
  deals: Deal[]
}

export interface Pipeline {
  id: string
  organization_id: string
  name: string
  is_default: boolean
  stages: PipelineStage[]
}

export interface PipelineListResponse {
  data: Pipeline[]
}

export interface DealPayload {
  pipeline_id: string
  pipeline_stage_id: string
  name: string
  status: 'open' | 'won' | 'lost'
  amount: number
  probability: number
  expected_close_date: string | null
  notes: string
  contact_id: string | null
  company_id: string | null
}
