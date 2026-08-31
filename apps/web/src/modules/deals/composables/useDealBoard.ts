import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { MaybeRefOrGetter } from 'vue'
import { computed, toValue } from 'vue'

import {
  createDeal,
  deleteDeal,
  getDealCompanies,
  getDealContacts,
  getPipelineBoard,
  getPipelines,
  moveDealStage,
  updateDeal,
} from '../api/deals.api'
import type { DealPayload } from '../types/deal.types'

export function usePipelines() {
  return useQuery({
    queryKey: ['pipelines'],
    queryFn: getPipelines,
  })
}

export function useDealBoard(pipelineId: MaybeRefOrGetter<string>) {
  return useQuery({
    queryKey: computed(() => ['pipeline-board', toValue(pipelineId)]),
    queryFn: () => getPipelineBoard(toValue(pipelineId)),
    enabled: computed(() => Boolean(toValue(pipelineId))),
  })
}

export function useDealFormOptions() {
  const contactsQuery = useQuery({
    queryKey: ['deal-form', 'contacts'],
    queryFn: getDealContacts,
  })

  const companiesQuery = useQuery({
    queryKey: ['deal-form', 'companies'],
    queryFn: getDealCompanies,
  })

  return {
    contactsQuery,
    companiesQuery,
  }
}

export function useDealMutations(pipelineId: MaybeRefOrGetter<string>) {
  const queryClient = useQueryClient()

  const invalidateBoard = async () => {
    await queryClient.invalidateQueries({ queryKey: ['pipelines'] })
    await queryClient.invalidateQueries({
      queryKey: ['pipeline-board', toValue(pipelineId)],
    })
  }

  const createMutation = useMutation({
    mutationFn: (payload: DealPayload) => createDeal(payload),
    onSuccess: invalidateBoard,
  })

  const updateMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: DealPayload
    }) => updateDeal(id, payload),
    onSuccess: invalidateBoard,
  })

  const moveStageMutation = useMutation({
    mutationFn: ({
      dealId,
      pipelineStageId,
    }: {
      dealId: string
      pipelineStageId: string
    }) => moveDealStage(dealId, pipelineStageId),
    onSuccess: invalidateBoard,
  })

  const deleteMutation = useMutation({
    mutationFn: (dealId: string) => deleteDeal(dealId),
    onSuccess: invalidateBoard,
  })

  return {
    createMutation,
    updateMutation,
    moveStageMutation,
    deleteMutation,
  }
}
