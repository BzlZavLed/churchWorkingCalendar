import { apiClient } from './authApi'

export const publicApi = {
  async listChurches() {
    const { data } = await apiClient.get('/public/churches')
    return data
  },
  async listDepartments(churchId) {
    const { data } = await apiClient.get(`/public/churches/${churchId}/departments`)
    return data
  },
  async lookupInvitation(code) {
    const { data } = await apiClient.get(`/public/invitations/${code}`)
    return data
  },
}
