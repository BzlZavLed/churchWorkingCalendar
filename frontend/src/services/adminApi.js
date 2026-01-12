import { apiClient } from './authApi'

export const adminApi = {
  async listDepartments() {
    const { data } = await apiClient.get('/admin/departments')
    return data
  },
  async listUsers() {
    const { data } = await apiClient.get('/admin/users')
    return data
  },
  async createUser(payload) {
    const { data } = await apiClient.post('/admin/users', payload)
    return data
  },
  async updateUser(userId, payload) {
    const { data } = await apiClient.put(`/admin/users/${userId}`, payload)
    return data
  },
  async deleteUser(userId) {
    await apiClient.delete(`/admin/users/${userId}`)
  },
}
