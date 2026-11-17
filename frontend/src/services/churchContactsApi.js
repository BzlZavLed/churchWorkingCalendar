import { apiClient } from './authApi'

export const churchContactsApi = {
  async alignLogo(alignment) { return (await apiClient.patch('/visitor-form/logo', { alignment })).data },
  async uploadLogo(file) { const payload = new FormData(); payload.append('logo', file); return (await apiClient.post('/visitor-form/logo', payload)).data },
  async removeLogo() { return (await apiClient.delete('/visitor-form/logo')).data },
  async update(id, payload) { return (await apiClient.patch(`/church-contacts/${id}`, payload)).data },
  async form() { return (await apiClient.get('/visitor-form')).data },
  async publishForm(payload) { return (await apiClient.post('/visitor-form', payload)).data },
  async publicForm(churchId) {
    const { data } = await apiClient.get(`/public/churches/${encodeURIComponent(churchId)}/visit`)
    return data
  },
  async publicCreate(churchId, payload) {
    const { data } = await apiClient.post(`/public/churches/${encodeURIComponent(churchId)}/visit`, payload)
    return data
  },
  async list() {
    const { data } = await apiClient.get('/church-contacts')
    return data
  },
  async restoreConsent(id, channel) {
    const { data } = await apiClient.patch(`/church-contacts/${id}/consent`, { channel, consent_confirmed: true })
    return data
  },
  async create(payload) {
    const { data } = await apiClient.post('/church-contacts', payload)
    return data
  },
}
