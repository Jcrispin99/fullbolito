import type { User, Tenant, Plan } from './models'

export interface ApiResponse<T = any> {
  success: boolean
  message: string
  data: T
}

export interface ApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export interface LoginResponse {
  user: User
  token: string
}

export interface RegisterTenantResponse {
  central_user: User
  central_token: string
  tenant: Tenant
  tenant_user: User
  tenant_token: string
  checkout_url: string | null
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    from: number
    last_page: number
    per_page: number
    to: number
    total: number
  }
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}
