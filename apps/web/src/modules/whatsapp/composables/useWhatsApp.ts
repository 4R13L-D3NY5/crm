import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'

import {
  createWhatsAppAccount,
  deleteWhatsAppAccount,
  disconnectWhatsApp,
  getWhatsAppAccount,
  getWhatsAppAccounts,
  getWhatsAppEvents,
  simulateIncomingMessage,
  simulateScan,
  updateWhatsAppAccount,
} from '../api/whatsapp.api'
import type {
  CreateWhatsAppAccountPayload,
  SimulateIncomingPayload,
  WhatsAppAccountPayload,
} from '../types/whatsapp.types'

export function useWhatsAppAccounts() {
  return useQuery({
    queryKey: ['whatsapp-accounts'],
    queryFn: getWhatsAppAccounts,
  })
}

export function useWhatsAppAccount() {
  return useQuery({
    queryKey: ['whatsapp-account'],
    queryFn: getWhatsAppAccount,
  })
}

export function useWhatsAppEvents() {
  return useQuery({
    queryKey: ['whatsapp-events'],
    queryFn: () => getWhatsAppEvents(),
    refetchInterval: 15000,
  })
}

export function useWhatsAppMutations() {
  const queryClient = useQueryClient()

  const createAccountMutation = useMutation({
    mutationFn: (payload: CreateWhatsAppAccountPayload) => createWhatsAppAccount(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    },
  })

  const deleteAccountMutation = useMutation({
    mutationFn: (id: string) => deleteWhatsAppAccount(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    },
  })

  const saveAccountMutation = useMutation({
    mutationFn: (payload: WhatsAppAccountPayload) => updateWhatsAppAccount(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-account'] })
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-events'] })
    },
  })

  const simulateScanMutation = useMutation({
    mutationFn: ({ id, phone }: { id: string; phone?: string }) => simulateScan(id, phone),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-account'] })
    },
  })

  const disconnectMutation = useMutation({
    mutationFn: (id: string) => disconnectWhatsApp(id),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-account'] })
    },
  })

  const simulateIncomingMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: SimulateIncomingPayload }) =>
      simulateIncomingMessage(id, payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['conversations'] })
    },
  })

  return {
    createAccountMutation,
    deleteAccountMutation,
    saveAccountMutation,
    simulateScanMutation,
    disconnectMutation,
    simulateIncomingMutation,
  }
}
