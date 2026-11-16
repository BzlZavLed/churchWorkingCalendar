import { adminContext, setAdminContext } from './adminContext'
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
  const account = ref(null)
  const isPlatformAdmin = computed(() => account.value?.role === 'superadmin')
  const user = computed(() => {
    if (!isPlatformAdmin.value || !adminContext.value) return account.value
    const { church, department } = adminContext.value
    return { ...account.value, role: department ? 'admin' : 'secretary',
      church_id: church.id, church, department_id: department?.id || null, department: department || null }
  })
  const contextKey = computed(() => isPlatformAdmin.value && adminContext.value
    ? `${adminContext.value.church.id}:${adminContext.value.department?.id || 'clerk'}` : 'account')
  const enterChurch = (church) => setAdminContext({ church, department: null })
  const enterDepartment = (department) => setAdminContext({ church: adminContext.value.church, department })
  const exitContext = () => setAdminContext(null)
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
        account.value = response.user
      } catch {
        setAdminContext(null)
        applyToken(null)
        account.value = null
      }
    }

    isInitializing.value = false
  }

  const login = async (payload, remember = true) => {
    setAdminContext(null)
    const response = await authApi.login(payload)
    applyToken(response.token, remember)
    account.value = response.user
  }

  const register = async (payload, remember = true) => {
    setAdminContext(null)
    const response = await authApi.register(payload)
    applyToken(response.token, remember)
    account.value = response.user
  }

  const setSession = (session, remember = true) => {
    setAdminContext(null)
    applyToken(session.token, remember)
    account.value = session.user
  }

  const logout = async () => {
    if (token.value) {
      await authApi.logout()
    }
    setAdminContext(null)
    applyToken(null)
    account.value = null
  }

  return {
    user,
    account,
    isPlatformAdmin,
    contextKey,
    adminContext,
    enterChurch,
    enterDepartment,
    exitContext,
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
