import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosRequestConfig } from 'axios'
import type { ApiResponse } from '@/types/api'

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  timeout: 30000,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

// Request interceptor - add Bearer token
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = localStorage.getItem('auth_token')
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// 401 handler callback — set from main.ts to avoid circular deps
let onUnauthorized: (() => void) | null = null

export function setUnauthorizedHandler(handler: () => void) {
  onUnauthorized = handler
}

// Response interceptor - handle 401
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      onUnauthorized?.()
    }
    return Promise.reject(error)
  }
)

export default api

// Type-safe API methods
export const apiClient = {
  get: <T>(url: string, config?: AxiosRequestConfig) => api.get<ApiResponse<T>>(url, config),
  post: <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig) => api.post<ApiResponse<T>>(url, data, config),
  put: <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig) => api.put<ApiResponse<T>>(url, data, config),
  patch: <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig) => api.patch<ApiResponse<T>>(url, data, config),
  delete: <T>(url: string, config?: AxiosRequestConfig) => api.delete<ApiResponse<T>>(url, config),
}
