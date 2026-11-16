import { apiClient } from './authApi'

export const budgetApi = {
  async settings(payload) { return (await apiClient.put('/budget/settings', payload)).data },
  async plan(payload) { return (await apiClient.put('/budget/plan', payload)).data },
  async overview(params = {}) { return (await apiClient.get('/budget', { params })).data },
  async start(payload) { return (await apiClient.post('/budget/start', payload)).data },
  async addEntry(payload) { return (await apiClient.post('/budget/entries', payload)).data },
  async publish(payload) { return (await apiClient.post('/budget/allocations', payload)).data },
  async preview(file, params) {
    const data = new FormData()
    data.append('file', file)
    Object.entries(params).forEach(([key, value]) => data.append(key, value))
    return (await apiClient.post('/budget/allocations/preview', data)).data
  },
}
