import { apiClient } from './authApi'

export const secretaryApi = {
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
