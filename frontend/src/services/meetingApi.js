import { apiClient } from './authApi'

export const meetingApi = {
  async listMeetings(params = {}) {
    const { data } = await apiClient.get('/meetings', { params })
    return data
  },
  async getMeeting(id) {
    const { data } = await apiClient.get(`/meetings/${id}`)
    return data
  },
  async createMeeting(payload) {
    const { data } = await apiClient.post('/meetings', payload)
    return data
  },
  async updateMeeting(id, payload) {
    const { data } = await apiClient.patch(`/meetings/${id}`, payload)
    return data
  },
  async closeAgenda(id) {
    const { data } = await apiClient.post(`/meetings/${id}/close-agenda`)
    return data
  },
  async startMeeting(id) {
    const { data } = await apiClient.post(`/meetings/${id}/start`)
    return data
  },
  async adjournMeeting(id) {
    const { data } = await apiClient.post(`/meetings/${id}/adjourn`)
    return data
  },
  async listMeetingPoints(meetingId, params = {}) {
    const { data } = await apiClient.get(`/meetings/${meetingId}/points`, { params })
    return data
  },
  async createMeetingPoint(meetingId, payload) {
    const { data } = await apiClient.post(`/meetings/${meetingId}/points`, payload)
    return data
  },
  async reorderMeetingPoints(meetingId, pointIds) {
    const { data } = await apiClient.post(`/meetings/${meetingId}/points/reorder`, { point_ids: pointIds })
    return data
  },
  async updateMeetingPoint(pointId, payload) {
    const { data } = await apiClient.patch(`/meeting-points/${pointId}`, payload)
    return data
  },
  async reviewMeetingPoint(pointId, payload) {
    const { data } = await apiClient.post(`/meeting-points/${pointId}/review`, payload)
    return data
  },
  async activateMeetingPoint(pointId) {
    const { data } = await apiClient.post(`/meeting-points/${pointId}/activate`)
    return data
  },
  async finalizeMeetingPoint(pointId, payload) {
    const { data } = await apiClient.post(`/meeting-points/${pointId}/finalize`, payload)
    return data
  },
  async addMeetingPointNote(pointId, payload) {
    const { data } = await apiClient.post(`/meeting-points/${pointId}/notes`, payload)
    return data
  },
  async addMeetingNote(meetingId, payload) {
    const { data } = await apiClient.post(`/meetings/${meetingId}/notes`, payload)
    return data
  },
  async downloadMeetingSummary(meetingId, locale = 'es') {
    const response = await apiClient.get(`/meetings/${meetingId}/summary.pdf`, {
      params: { locale },
      responseType: 'blob',
    })
    return response.data
  },
}
