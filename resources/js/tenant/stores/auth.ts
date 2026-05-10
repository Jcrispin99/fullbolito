import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiClient } from '@tenant/lib/api'
import type { User } from '@/types/models'
import type { LoginResponse } from '@/types/api'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))
  const authChecked = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!token.value)

  // Actions
  async function login(email: string, password: string): Promise<void> {
    const { data } = await apiClient.post<LoginResponse>('/login', {
      email,
      password,
    })

    token.value = data.data.token
    user.value = data.data.user
    localStorage.setItem('auth_token', data.data.token)
  }

  async function logout(): Promise<void> {
    try {
      await apiClient.post('/logout')
    } finally {
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
    }
  }

  async function fetchUser(): Promise<User | null> {
    if (!token.value) return null

    try {
      const { data } = await apiClient.get<User>('/me')
      user.value = data.data
      return user.value
    } catch {
      token.value = null
      user.value = null
      localStorage.removeItem('auth_token')
      return null
    } finally {
      authChecked.value = true
    }
  }

  async function initAuth(): Promise<void> {
    if (!authChecked.value) {
      await fetchUser()
    }
  }

  return {
    // State
    user,
    token,
    authChecked,
    // Getters
    isAuthenticated,
    // Actions
    login,
    logout,
    fetchUser,
    initAuth,
  }
})
