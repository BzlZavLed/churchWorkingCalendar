import { apiClient } from './authApi'

export const inventoryApi = {
  async list() {
    const { data } = await apiClient.get('/inventory')
    return data
  },
  async create(payload) {
    const { data } = await apiClient.post('/inventory', payload)
    return data
  },
  async update(id, payload) {
    const { data } = await apiClient.put(`/inventory/${id}`, payload)
    return data
  },
  async remove(id) {
    await apiClient.delete(`/inventory/${id}`)
  },
}
