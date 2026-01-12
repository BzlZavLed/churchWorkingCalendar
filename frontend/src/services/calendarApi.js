import { apiClient } from './authApi'

export const calendarApi = {
  async fetchEvents(params) {
    const { data } = await apiClient.get('/events', { params })
    return data
  },
  async createHold(payload) {
    const { data } = await apiClient.post('/events/hold', payload)
    return data
  },
  async lockEvent(eventId) {
    const { data } = await apiClient.post(`/events/${eventId}/lock`)
    return data
  },
  async updateEvent(eventId, payload) {
    const { data } = await apiClient.patch(`/events/${eventId}`, payload)
    return data
  },
  async reviewEvent(eventId, payload) {
    const { data } = await apiClient.post(`/events/${eventId}/review`, payload)
    return data
  },
  async publishAccepted(payload) {
    const { data } = await apiClient.post('/events/publish-accepted', payload)
    return data
  },
  async addNote(eventId, payload) {
    const { data } = await apiClient.post(`/events/${eventId}/notes`, payload)
    return data
  },
  async replyNote(eventId, payload) {
    const { data } = await apiClient.post(`/events/${eventId}/notes/reply`, payload)
    return data
  },
  async fetchUnseenNotes() {
    const { data } = await apiClient.get('/notes/unseen')
    return data
  },
  async markNoteSeen(noteId) {
    const resolvedId = typeof noteId === 'object' && noteId !== null ? noteId.id : noteId
    const { data } = await apiClient.post(`/notes/${resolvedId}/seen`)
    return data
  },
  async cancelEvent(eventId) {
    const { data } = await apiClient.delete(`/events/${eventId}`)
    return data
  },
  async exportCalendar(view, includeHistory, locale) {
    const response = await apiClient.get('/calendar/export', {
      params: { view, include_history: includeHistory ? 1 : 0, locale },
      responseType: 'blob',
    })
    return response
  },
}
