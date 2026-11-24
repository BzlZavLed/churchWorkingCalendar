import { apiClient } from './authApi'

export const secretaryApi = {
  async sendPasswordLink(userId) { return (await apiClient.post(`/secretary/users/${userId}/password-link`)).data },
  async createDepartment(payload) {
    const { data } = await apiClient.post('/secretary/departments', payload)
    return data
  },
  async createUser(payload) {
    const { data } = await apiClient.post('/secretary/users', payload)
    return data
  },
  async listDepartments() {
    const { data } = await apiClient.get('/secretary/departments')
    return data
  },
  async updateDepartment(departmentId, payload) {
    const { data } = await apiClient.put(`/secretary/departments/${departmentId}`, payload)
    return data
  },
  async listUsers() {
    const { data } = await apiClient.get('/secretary/users')
    return data
  },
  async updateUser(userId, payload) {
    const { data } = await apiClient.put(`/secretary/users/${userId}`, payload)
    return data
  },
}
