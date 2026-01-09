import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, setAuthToken } from '../services/authApi'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token'))
  const isInitializing = ref(true)

  const isAuthenticated = computed(() => Boolean(token.value))
  const role = computed(() => user.value?.role || null)
  const isSuperAdmin = computed(() => role.value === 'superadmin')
  const isSecretary = computed(() => role.value === 'secretary')

  const applyToken = (nextToken) => {
    token.value = nextToken
    if (nextToken) {
      localStorage.setItem('auth_token', nextToken)
    } else {
      localStorage.removeItem('auth_token')
    }
    setAuthToken(nextToken)
  }

  const initialize = async () => {
    if (!isInitializing.value) {
      return
    }

    if (token.value) {
      setAuthToken(token.value)
      try {
        const response = await authApi.me()
        user.value = response.user
      } catch {
        applyToken(null)
        user.value = null
      }
    }

    isInitializing.value = false
  }

  const login = async (payload) => {
    const response = await authApi.login(payload)
    applyToken(response.token)
    user.value = response.user
  }

  const register = async (payload) => {
    const response = await authApi.register(payload)
    applyToken(response.token)
    user.value = response.user
  }

  const logout = async () => {
    if (token.value) {
      await authApi.logout()
    }
    applyToken(null)
    user.value = null
  }

  return {
    user,
    token,
    isInitializing,
    isAuthenticated,
    role,
    isSuperAdmin,
    isSecretary,
    initialize,
    login,
    register,
    logout,
  }
})
