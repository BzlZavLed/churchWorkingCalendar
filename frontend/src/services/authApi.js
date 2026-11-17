import axios from 'axios'
import { adminContext } from '../stores/adminContext'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
})

apiClient.interceptors.request.use((config) => {
  // Administration and authentication always use the original account.
  const context = adminContext.value
  if (!/^\/?(superadmin|auth|public)(\/|$)/.test(config.url || '')) {
    config.viewContext = context ? JSON.stringify(context) : null
    config.scopedView = true
    if (!context) return config
    config.headers['X-Church-Context'] = context.church.id
    if (context.churchRole) config.headers['X-Church-Role-Context'] = context.churchRole.id
    if (context.role === 'pastor') config.headers['X-Role-Context'] = 'pastor'
    if (context.department) config.headers['X-Department-Context'] = context.department.id
  }
  return config
})

apiClient.interceptors.response.use((response) => {
  const current = adminContext.value ? JSON.stringify(adminContext.value) : null
  if (response.config.scopedView && response.config.viewContext !== current) {
    throw new axios.CanceledError('Viewing context changed')
  }
  return response
})

export const setAuthToken = (token) => {
  if (token) {
    apiClient.defaults.headers.common.Authorization = `Bearer ${token}`
  } else {
    delete apiClient.defaults.headers.common.Authorization
  }
}

export const authApi = {
  async login(payload) {
    const { data } = await apiClient.post('/auth/login', payload)
    return data
  },
  async register(payload) {
    const { data } = await apiClient.post('/auth/register', payload)
    return data
  },
  async recoverPassword(payload) {
    await apiClient.post('/auth/recover', payload)
  },
  async logout() {
    await apiClient.post('/auth/logout')
  },
  async me() {
    const { data } = await apiClient.get('/auth/me')
    return data
  },
}
