import axios from 'axios'

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
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
