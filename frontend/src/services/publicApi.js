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
  async searchConsent(payload) {
    const { data } = await apiClient.get('/public/consent/search', { params: payload })
    return data
  },
  async getConsent(token) {
    const { data } = await apiClient.get(`/public/consent/${token}`)
    return data
  },
  async revokeConsent(token, payload) {
    const { data } = await apiClient.post(`/public/consent/${token}/revoke`, payload)
    return data
  },
}
