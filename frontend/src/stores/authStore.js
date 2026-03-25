import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, setAuthToken } from '../services/authApi'

export const getDefaultRouteForUser = (user) => {
  if (!user) {
    return '/login'
  }
  if (user.role === 'superadmin') {
    return '/superadmin/churches'
  }
  if (user.department?.is_greeting) {
    return '/greeting'
  }
  return '/calendar'
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const storageKey = 'auth_token'
  const getStoredToken = () => sessionStorage.getItem(storageKey) || localStorage.getItem(storageKey)
  const token = ref(getStoredToken())
  const isInitializing = ref(true)

  const isAuthenticated = computed(() => Boolean(token.value))
  const role = computed(() => user.value?.role || null)
  const isSuperAdmin = computed(() => role.value === 'superadmin')
  const isSecretary = computed(() => role.value === 'secretary')
  const isGreetingDepartmentUser = computed(() => Boolean(user.value?.department?.is_greeting))

  const applyToken = (nextToken, persist = true) => {
    token.value = nextToken
    if (nextToken) {
      if (persist) {
        localStorage.setItem(storageKey, nextToken)
        sessionStorage.removeItem(storageKey)
      } else {
        sessionStorage.setItem(storageKey, nextToken)
        localStorage.removeItem(storageKey)
      }
    } else {
      localStorage.removeItem(storageKey)
      sessionStorage.removeItem(storageKey)
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

  const login = async (payload, remember = true) => {
    const response = await authApi.login(payload)
    applyToken(response.token, remember)
    user.value = response.user
  }

  const register = async (payload, remember = true) => {
    const response = await authApi.register(payload)
    applyToken(response.token, remember)
    user.value = response.user
  }

  const setSession = (session, remember = true) => {
    applyToken(session.token, remember)
    user.value = session.user
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
    isGreetingDepartmentUser,
    initialize,
    login,
    register,
    setSession,
    logout,
  }
})
