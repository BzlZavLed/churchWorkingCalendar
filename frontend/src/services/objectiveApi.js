import { apiClient } from './authApi'

export const objectiveApi = {
  async events(id) { return (await apiClient.get(`/objectives/${id}/events`)).data },
  async list() {
    const { data } = await apiClient.get('/objectives')
    return data
  },
  async create(payload) {
    const { data } = await apiClient.post('/objectives', payload)
    return data
  },
  async update(id, payload) {
    const { data } = await apiClient.put(`/objectives/${id}`, payload)
    return data
  },
  async remove(id) {
    await apiClient.delete(`/objectives/${id}`)
  },
}
