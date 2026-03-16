import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useAuthStore } from './authStore'
import { useUiStore } from './uiStore'

const messages = {
  es: {
    event: {
      hold_created: 'Hay una nueva reserva de evento.',
      locked: 'Un evento fue confirmado.',
      updated: 'Un evento fue actualizado.',
      reviewed: 'La revision de un evento cambio.',
      cancelled: 'Un evento fue cancelado.',
      published: 'Un evento aceptado fue publicado.',
    },
    event_note: {
      created: 'Hay una nueva nota de evento.',
      replied: 'Hay una nueva respuesta de evento.',
    },
    meeting: {
      created: 'Hay una nueva reunion.',
      updated: 'Una reunion fue actualizada.',
      agenda_closed: 'La agenda de una reunion fue cerrada.',
      started: 'Una reunion inicio.',
      adjourned: 'Una reunion fue finalizada.',
      reordered: 'El orden de una reunion cambio.',
    },
    meeting_point: {
      created: 'Hay un nuevo punto de reunion.',
      updated: 'Un punto de reunion fue actualizado.',
      reviewed: 'Un punto de reunion fue revisado.',
      activated: 'Un punto de reunion fue activado.',
      finalized: 'Un punto de reunion fue finalizado.',
    },
    meeting_point_note: {
      created: 'Hay una nueva nota en punto de reunion.',
    },
    meeting_note: {
      created: 'Hay una nueva nota general de reunion.',
    },
  },
  en: {
    event: {
      hold_created: 'A new event hold was created.',
      locked: 'An event was confirmed.',
      updated: 'An event was updated.',
      reviewed: 'An event review changed.',
      cancelled: 'An event was cancelled.',
      published: 'An accepted event was published.',
    },
    event_note: {
      created: 'There is a new event note.',
      replied: 'There is a new event reply.',
    },
    meeting: {
      created: 'There is a new meeting.',
      updated: 'A meeting was updated.',
      agenda_closed: 'A meeting agenda was closed.',
      started: 'A meeting started.',
      adjourned: 'A meeting was finished.',
      reordered: 'A meeting order changed.',
    },
    meeting_point: {
      created: 'There is a new meeting point.',
      updated: 'A meeting point was updated.',
      reviewed: 'A meeting point was reviewed.',
      activated: 'A meeting point was activated.',
      finalized: 'A meeting point was finalized.',
    },
    meeting_point_note: {
      created: 'There is a new meeting point note.',
    },
    meeting_note: {
      created: 'There is a new meeting note.',
    },
  },
}

export const useLiveUpdateStore = defineStore('liveUpdates', () => {
  const echo = ref(null)
  const version = ref(0)
  const lastUpdate = ref(null)

  const authStore = useAuthStore()
  const uiStore = useUiStore()

  const effectiveChurchId = computed(() =>
    authStore.user?.church_id || authStore.user?.department?.church_id || null
  )

  const isRelevant = (update) => {
    const user = authStore.user
    if (!user) {
      return false
    }
    if (user.role === 'superadmin') {
      return true
    }
    if (!effectiveChurchId.value) {
      return false
    }
    if (update.church_id && update.church_id !== effectiveChurchId.value) {
      return false
    }
    if (
      user.role === 'admin' &&
      update.department_id &&
      update.department_id !== user.department_id &&
      ['event_note', 'meeting_point', 'meeting_point_note'].includes(update.entity)
    ) {
      return false
    }
    return true
  }

  const formatMessage = (update) => {
    const locale = uiStore.locale in messages ? uiStore.locale : 'en'
    return messages[locale]?.[update.entity]?.[update.action] || ''
  }

  const handleUpdate = (payload) => {
    if (!isRelevant(payload)) {
      return
    }

    lastUpdate.value = payload
    version.value += 1

    if (payload.actor_id && payload.actor_id === authStore.user?.id) {
      return
    }

    if (payload.entity === 'event_note' && payload.action === 'seen') {
      return
    }

    const message = formatMessage(payload)
    if (message) {
      uiStore.showToast(message, 'success', 3500)
    }
  }

  const connect = () => {
    if (echo.value || !authStore.isAuthenticated) {
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

    echo.value.channel('app.updates').listen('.domain.updated', handleUpdate)
  }

  const disconnect = () => {
    if (!echo.value) {
      return
    }
    echo.value.disconnect()
    echo.value = null
  }

  return {
    version,
    lastUpdate,
    connect,
    disconnect,
  }
})
