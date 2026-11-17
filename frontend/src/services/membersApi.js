import { apiClient } from './authApi'
export const membersApi = {
  async list() { return (await apiClient.get('/members')).data },
  async template(payload) { return (await apiClient.post('/members/template', payload)).data },
  async download() { return (await apiClient.get('/members/template.csv', { responseType: 'blob' })).data },
  async save(id, payload) { return (await apiClient[id ? 'patch' : 'post'](id ? `/members/${id}` : '/members', payload)).data },
  async import(file, preview) { const body = new FormData(); body.append('file', file); body.append('preview', preview ? '1' : '0'); return (await apiClient.post('/members/import', body)).data },
}
