import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { MaybeRefOrGetter } from 'vue'
import { computed, toValue } from 'vue'

import {
  createCompany,
  deleteCompany,
  getCompanies,
  getCompany,
  updateCompany,
} from '../api/companies.api'
import type { CompanyFilters, CompanyPayload } from '../types/company.types'

export function useCompanies(filters: MaybeRefOrGetter<CompanyFilters>) {
  return useQuery({
    queryKey: computed(() => ['companies', toValue(filters)]),
    queryFn: () => getCompanies(toValue(filters)),
  })
}

export function useCompany(companyId: MaybeRefOrGetter<string>) {
  return useQuery({
    queryKey: computed(() => ['company', toValue(companyId)]),
    queryFn: () => getCompany(toValue(companyId)),
    enabled: computed(() => Boolean(toValue(companyId))),
  })
}

export function useCompanyMutations() {
  const queryClient = useQueryClient()

  const invalidateCompanies = async (companyId?: string) => {
    await queryClient.invalidateQueries({ queryKey: ['companies'] })

    if (companyId) {
      await queryClient.invalidateQueries({ queryKey: ['company', companyId] })
    }
  }

  const createMutation = useMutation({
    mutationFn: (payload: CompanyPayload) => createCompany(payload),
    onSuccess: async () => {
      await invalidateCompanies()
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: CompanyPayload
    }) => updateCompany(id, payload),
    onSuccess: async (company) => {
      await invalidateCompanies(company.id)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteCompany(id),
    onSuccess: async (_, id) => {
      await invalidateCompanies(id)
    },
  })

  return {
    createMutation,
    updateMutation,
    deleteMutation,
  }
}
