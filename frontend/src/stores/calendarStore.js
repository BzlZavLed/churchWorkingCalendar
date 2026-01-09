import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { calendarApi } from '../services/calendarApi'

export const useCalendarStore = defineStore('calendar', () => {
  const events = ref([])
  const echo = ref(null)

  const holds = computed(() => events.value.filter((item) => item.status === 'hold'))
  const locked = computed(() => events.value.filter((item) => item.status === 'locked'))

  const setEvents = (items) => {
    events.value = items
  }

  const upsertEvent = (item) => {
    const index = events.value.findIndex((existing) => existing.id === item.id)
    if (index === -1) {
      events.value.push(item)
    } else {
      events.value[index] = item
    }
  }

  const removeEvent = (id) => {
    events.value = events.value.filter((item) => item.id !== id)
  }

  const fetchRange = async (start, end) => {
    const response = await calendarApi.fetchEvents({ start, end })
    setEvents(response)
  }

  const createHold = async (payload) => {
    const response = await calendarApi.createHold(payload)
    upsertEvent(response)
    return response
  }

  const lockEvent = async (eventId) => {
    const response = await calendarApi.lockEvent(eventId)
    upsertEvent(response)
    return response
  }

  const reviewEvent = async (eventId, payload) => {
    const response = await calendarApi.reviewEvent(eventId, payload)
    upsertEvent(response)
    return response
  }

  const addNote = async (eventId, payload) => {
    const response = await calendarApi.addNote(eventId, payload)
    const event = events.value.find((item) => item.id === eventId)
    if (event) {
      event.notes = [...(event.notes || []), response]
    }
    return response
  }

  const replyNote = async (eventId, payload) => {
    const response = await calendarApi.replyNote(eventId, payload)
    const event = events.value.find((item) => item.id === eventId)
    if (event) {
      event.notes = [...(event.notes || []), response]
    }
    return response
  }

  const markNoteSeen = async (noteId) => {
    const resolvedId = typeof noteId === 'object' && noteId !== null ? noteId.id : noteId
    if (!resolvedId) {
      return null
    }
    const response = await calendarApi.markNoteSeen(resolvedId)
    const eventId = response.event_id
    const event = events.value.find((item) => item.id === eventId)
    if (event && event.notes) {
      const idx = event.notes.findIndex((note) => note.id === noteId)
      if (idx !== -1) {
        event.notes[idx] = response
      }
    }
    return response
  }

  const isSlotBlocked = (startAt, endAt) => {
    const start = new Date(startAt).getTime()
    const end = new Date(endAt).getTime()
    const now = Date.now()

    return events.value.some((item) => {
      if (item.status === 'cancelled') {
        return false
      }

      if (item.status === 'hold') {
        if (!item.expires_at || new Date(item.expires_at).getTime() <= now) {
          return false
        }
      }

      const itemStart = new Date(item.start_at).getTime()
      const itemEnd = new Date(item.end_at).getTime()

      return start < itemEnd && end > itemStart
    })
  }

  const connectRealtime = () => {
    if (echo.value) {
      return
    }

    const appKey = import.meta.env.VITE_REVERB_APP_KEY
    if (!appKey) {
      return
    }

    window.Pusher = Pusher

    echo.value = new Echo({
      broadcaster: 'reverb',
      key: appKey,
      wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
      wsPort: Number(import.meta.env.VITE_REVERB_PORT || 6001),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT || 6001),
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
    })

    echo.value
      .channel('calendar.global')
      .listen('.event.created', (payload) => upsertEvent(payload.event))
      .listen('.event.updated', (payload) => upsertEvent(payload.event))
      .listen('.event.locked', (payload) => upsertEvent(payload.event))
      .listen('.event.cancelled', (payload) => upsertEvent(payload.event))
      .listen('.hold.created', (payload) => upsertEvent(payload.event))
      .listen('.hold.expired', (payload) => removeEvent(payload.event.id))
  }

  return {
    events,
    holds,
    locked,
    fetchRange,
    createHold,
    lockEvent,
    reviewEvent,
    addNote,
    replyNote,
    markNoteSeen,
    isSlotBlocked,
    connectRealtime,
    upsertEvent,
    removeEvent,
  }
})
