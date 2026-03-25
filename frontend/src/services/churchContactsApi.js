import { apiClient } from './authApi'

export const churchContactsApi = {
  async list() {
    const { data } = await apiClient.get('/church-contacts')
    return data
  },
  async create(payload) {
    const { data } = await apiClient.post('/church-contacts', payload)
    return data
  },
}
