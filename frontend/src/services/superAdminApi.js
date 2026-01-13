import { apiClient } from './authApi'

export const superAdminApi = {
  async listChurches() {
    const { data } = await apiClient.get('/superadmin/churches')
    return data
  },
  async createChurch(payload) {
    const { data } = await apiClient.post('/superadmin/churches', payload)
    return data
  },
  async updateChurch(churchId, payload) {
    const { data } = await apiClient.put(`/superadmin/churches/${churchId}`, payload)
    return data
  },
  async deleteChurch(churchId) {
    await apiClient.delete(`/superadmin/churches/${churchId}`)
  },
  async generateInvite(churchId, payload) {
    const { data } = await apiClient.post(`/superadmin/churches/${churchId}/invitations`, payload)
    return data
  },
  async listDepartments(churchId) {
    const { data } = await apiClient.get(`/superadmin/churches/${churchId}/departments`)
    return data
  },
  async createDepartment(churchId, payload) {
    const { data } = await apiClient.post(`/superadmin/churches/${churchId}/departments`, payload)
    return data
  },
  async updateDepartment(churchId, departmentId, payload) {
    const { data } = await apiClient.put(`/superadmin/churches/${churchId}/departments/${departmentId}`, payload)
    return data
  },
  async deleteDepartment(churchId, departmentId) {
    await apiClient.delete(`/superadmin/churches/${churchId}/departments/${departmentId}`)
  },
  async deleteDepartmentEvents(churchId, departmentId) {
    const { data } = await apiClient.delete(`/superadmin/churches/${churchId}/departments/${departmentId}/events`)
    return data
  },
  async listChurchEvents(churchId) {
    const { data } = await apiClient.get(`/superadmin/churches/${churchId}/events`)
    return data
  },
  async deleteChurchEvents(churchId) {
    const { data } = await apiClient.delete(`/superadmin/churches/${churchId}/events`)
    return data
  },
  async listUsers(churchId) {
    const { data } = await apiClient.get(`/superadmin/churches/${churchId}/users`)
    return data
  },
  async createUser(churchId, payload) {
    const { data } = await apiClient.post(`/superadmin/churches/${churchId}/users`, payload)
    return data
  },
  async updateUser(churchId, userId, payload) {
    const { data } = await apiClient.put(`/superadmin/churches/${churchId}/users/${userId}`, payload)
    return data
  },
  async updateUserPassword(churchId, userId, payload) {
    const { data } = await apiClient.put(`/superadmin/churches/${churchId}/users/${userId}`, payload)
    return data
  },
  async deleteUser(churchId, userId) {
    await apiClient.delete(`/superadmin/churches/${churchId}/users/${userId}`)
  },
}
