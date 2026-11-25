import { apiClient } from './authApi'
export const membersApi = {
  async families() { return (await apiClient.get('/members/families')).data.families },
  async moveFamily(payload) { return (await apiClient.post('/members/families/move',payload)).data },
  async exportOptions() { return (await apiClient.get('/members/export-options')).data },
  async saveExportPreset(payload) { return (await apiClient.post('/members/export-presets',payload)).data },
  async deleteExportPreset(id) { await apiClient.delete(`/members/export-presets/${id}`) },
  async exportMembers(columns, sort_by, sort_direction) { return (await apiClient.get('/members/export.csv',{params:{columns,sort_by,sort_direction},responseType:'blob'})).data },
  async deleteDraft(id) { await apiClient.delete(`/members/import-drafts/${id}`) },
  async drafts() { return (await apiClient.get('/members/import-drafts')).data.drafts },
  async draft(id) { return (await apiClient.get(`/members/import-drafts/${id}`)).data },
  async saveDraft(id, payload) { return (await apiClient[id ? 'put' : 'post'](id ? `/members/import-drafts/${id}` : '/members/import-drafts', payload)).data },
  async commitDraft(id, revision) { return (await apiClient.post(`/members/import-drafts/${id}/commit`, {revision})).data },
  async correctBirthDates(file, token) {
    const body = new FormData()
    body.append('file', file)
    if (token) body.append('preview_token', token)
    return (await apiClient.post('/members/birthdate-correction', body)).data
  },
  async list() { return (await apiClient.get('/members')).data },
  async familyMap() { return (await apiClient.get('/members', { params: { view: 'family-map' } })).data },
  async get(id) { return (await apiClient.get(`/members/${id}`)).data },
  async remove(id) { await apiClient.delete(`/members/${id}`) },
  async download() { return (await apiClient.get('/members/template.csv', { responseType: 'blob' })).data },
  async save(id, payload) { return (await apiClient[id ? 'patch' : 'post'](id ? `/members/${id}` : '/members', payload)).data },
  async import(file, preview, editable = false) { const body = new FormData(); body.append('file', file); body.append('preview', preview ? '1' : '0'); body.append('editable', editable ? '1' : '0'); return (await apiClient.post('/members/import', body)).data },
}
