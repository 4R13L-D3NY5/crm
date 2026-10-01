import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { MaybeRefOrGetter } from 'vue'
import { computed, toValue } from 'vue'

import {
  createContact,
  createTag,
  deleteContact,
  deleteTag,
  getContact,
  getContacts,
  getTags,
  importContacts,
  updateContact,
  updateTag,
} from '../api/contacts.api'
import type { ContactFilters, ContactPayload } from '../types/contact.types'

export function useContacts(filters: MaybeRefOrGetter<ContactFilters>) {
  return useQuery({
    queryKey: computed(() => ['contacts', toValue(filters)]),
    queryFn: () => getContacts(toValue(filters)),
  })
}

export function useContact(contactId: MaybeRefOrGetter<string>) {
  return useQuery({
    queryKey: computed(() => ['contact', toValue(contactId)]),
    queryFn: () => getContact(toValue(contactId)),
    enabled: computed(() => Boolean(toValue(contactId))),
  })
}

export function useContactMutations() {
  const queryClient = useQueryClient()

  const invalidateContacts = async (contactId?: string) => {
    await queryClient.invalidateQueries({ queryKey: ['contacts'] })

    if (contactId) {
      await queryClient.invalidateQueries({ queryKey: ['contact', contactId] })
    }
  }

  const createMutation = useMutation({
    mutationFn: (payload: ContactPayload) => createContact(payload),
    onSuccess: async () => {
      await invalidateContacts()
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: ContactPayload
    }) => updateContact(id, payload),
    onSuccess: async (contact) => {
      await invalidateContacts(contact.id)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteContact(id),
    onSuccess: async (_, id) => {
      await invalidateContacts(id)
    },
  })

  const importMutation = useMutation({
    mutationFn: (payload: { contacts: Array<{ name: string; phone: string; email?: string; tags?: string[] }> }) =>
      importContacts(payload),
    onSuccess: async () => {
      await invalidateContacts()
      await queryClient.invalidateQueries({ queryKey: ['tags'] })
    },
  })

  return {
    createMutation,
    updateMutation,
    deleteMutation,
    importMutation,
  }
}

export function useContactTags() {
  return useQuery({
    queryKey: ['tags'],
    queryFn: () => getTags(),
  })
}

export function useTagMutations() {
  const queryClient = useQueryClient()

  const invalidateTags = async () => {
    await queryClient.invalidateQueries({ queryKey: ['tags'] })
  }

  const createTagMutation = useMutation({
    mutationFn: (payload: { name: string; color_hex?: string }) => createTag(payload),
    onSuccess: async () => {
      await invalidateTags()
    },
  })

  const updateTagMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: { name: string; color_hex?: string } }) =>
      updateTag(id, payload),
    onSuccess: async () => {
      await invalidateTags()
    },
  })

  const deleteTagMutation = useMutation({
    mutationFn: (id: string) => deleteTag(id),
    onSuccess: async () => {
      await invalidateTags()
    },
  })

  return {
    createTagMutation,
    updateTagMutation,
    deleteTagMutation,
  }
}

export function useContactFormOptions() {
  const companiesQuery = useQuery({
    queryKey: ['companies', 'options'],
    queryFn: () => ({ data: [] }),
  })

  const usersQuery = useQuery({
    queryKey: ['users', 'options'],
    queryFn: () => ({ data: [] }),
  })

  return {
    companiesQuery,
    usersQuery,
  }
}
