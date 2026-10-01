export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page?: number
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
  links: PaginationLinks
}
