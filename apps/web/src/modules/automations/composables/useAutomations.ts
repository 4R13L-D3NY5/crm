import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { MaybeRefOrGetter } from 'vue'
import { computed, toValue } from 'vue'

import {
  createAutomationRule,
  deleteAutomationRule,
  getAutomationRules,
  updateAutomationRule,
} from '../api/automations.api'
import type { AutomationFilters, AutomationRulePayload } from '../types/automation.types'

export function useAutomationRules(filters: MaybeRefOrGetter<AutomationFilters>) {
  return useQuery({
    queryKey: computed(() => ['automation-rules', toValue(filters)]),
    queryFn: () => getAutomationRules(toValue(filters)),
  })
}

export function useAutomationMutations() {
  const queryClient = useQueryClient()

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: ['automation-rules'] })
  }

  const createMutation = useMutation({
    mutationFn: (payload: AutomationRulePayload) => createAutomationRule(payload),
    onSuccess: invalidate,
  })

  const updateMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: AutomationRulePayload
    }) => updateAutomationRule(id, payload),
    onSuccess: invalidate,
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteAutomationRule(id),
    onSuccess: invalidate,
  })

  return {
    createMutation,
    updateMutation,
    deleteMutation,
  }
}
