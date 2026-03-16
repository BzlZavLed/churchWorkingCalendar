import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { useAuthStore } from './authStore'
import { useUiStore } from './uiStore'

const roleLabels = {
  es: {
    secretary: 'Secretaria',
    superadmin: 'Secretaria',
    admin: 'El departamento',
  },
  en: {
    secretary: 'The secretary',
    superadmin: 'The secretary',
    admin: 'The department',
  },
}

const isSecretarySide = (role) => ['secretary', 'superadmin'].includes(role)

const formatActorLabel = (locale, role) => roleLabels[locale]?.[role] || roleLabels.en[role] || ''

const messages = {
  es: {
    event(update, userRole) {
      const actor = formatActorLabel('es', update.actor_role)
      if (userRole === 'admin') {
        if (update.action === 'reviewed') return `${actor} cambio el estado de tu evento.`
        if (update.action === 'published') return `${actor} publico tu evento aceptado.`
        if (update.action === 'cancelled') return `${actor} cancelo tu evento.`
      }
      if (isSecretarySide(userRole)) {
        if (update.action === 'hold_created') return 'Un departamento creo una reserva de evento.'
        if (update.action === 'locked') return 'Un departamento confirmo un evento.'
        if (update.action === 'updated') return 'Un departamento actualizo un evento.'
        if (update.action === 'cancelled') return 'Un departamento cancelo un evento.'
      }
      return ''
    },
    event_note(update, userRole) {
      if (userRole === 'admin' && update.action === 'created') return 'Secretaria dejo una nota en tu evento.'
      if (isSecretarySide(userRole) && update.action === 'replied') return 'El departamento respondio a una nota del evento.'
      if (isSecretarySide(userRole) && update.action === 'seen') return 'El departamento marco una nota del evento como leida.'
      return ''
    },
    meeting(update, userRole) {
      if (userRole === 'admin' && isSecretarySide(update.actor_role)) {
        if (update.action === 'created') return 'Secretaria creo una nueva reunion.'
        if (update.action === 'updated') return 'Secretaria actualizo una reunion.'
        if (update.action === 'agenda_closed') return 'Secretaria cerro la agenda de una reunion.'
        if (update.action === 'started') return 'Secretaria inicio una reunion.'
        if (update.action === 'adjourned') return 'Secretaria finalizo una reunion.'
      }
      if (isSecretarySide(userRole) && update.actor_role === 'admin') {
        if (update.action === 'reordered') return 'Un departamento cambio el orden de una reunion.'
      }
      return ''
    },
    meeting_point(update, userRole) {
      if (userRole === 'admin' && isSecretarySide(update.actor_role)) {
        if (update.action === 'reviewed') return 'Secretaria reviso un punto de reunion.'
        if (update.action === 'activated') return 'Secretaria activo tu punto de reunion.'
        if (update.action === 'finalized') return 'Secretaria finalizo tu punto de reunion.'
      }
      if (isSecretarySide(userRole) && update.actor_role === 'admin') {
        if (update.action === 'created') return 'Un departamento envio un nuevo punto de reunion.'
        if (update.action === 'updated') return 'Un departamento actualizo un punto de reunion.'
      }
      return ''
    },
    meeting_point_note(update, userRole) {
      if (userRole === 'admin' && isSecretarySide(update.actor_role)) {
        return 'Secretaria dejo una nota en un punto de reunion.'
      }
      if (isSecretarySide(userRole) && update.actor_role === 'admin') {
        return 'El departamento dejo una nota en un punto de reunion.'
      }
      return ''
    },
    meeting_note(update, userRole) {
      if (userRole === 'admin' && isSecretarySide(update.actor_role)) {
        return 'Secretaria agrego una nota general de reunion.'
      }
      if (isSecretarySide(userRole) && update.actor_role === 'admin') {
        return 'El departamento agrego una nota general de reunion.'
      }
      return ''
    },
  },
  en: {
    event(update, userRole) {
      const actor = formatActorLabel('en', update.actor_role)
      if (userRole === 'admin') {
        if (update.action === 'reviewed') return `${actor} changed your event status.`
        if (update.action === 'published') return `${actor} published your approved event.`
        if (update.action === 'cancelled') return `${actor} cancelled your event.`
      }
      if (isSecretarySide(userRole)) {
        if (update.action === 'hold_created') return 'A department created an event hold.'
        if (update.action === 'locked') return 'A department confirmed an event.'
        if (update.action === 'updated') return 'A department updated an event.'
        if (update.action === 'cancelled') return 'A department cancelled an event.'
      }
      return ''
    },
    event_note(update, userRole) {
      if (userRole === 'admin' && update.action === 'created') return 'The secretary left a note on your event.'
      if (isSecretarySide(userRole) && update.action === 'replied') return 'The department replied to an event note.'
      if (isSecretarySide(userRole) && update.action === 'seen') return 'The department marked an event note as read.'
      return ''
    },
    meeting(update, userRole) {
      if (userRole === 'admin' && isSecretarySide(update.actor_role)) {
        if (update.action === 'created') return 'The secretary created a new meeting.'
        if (update.action === 'updated') return 'The secretary updated a meeting.'
        if (update.action === 'agenda_closed') return 'The secretary closed a meeting agenda.'
        if (update.action === 'started') return 'The secretary started a meeting.'
        if (update.action === 'adjourned') return 'The secretary finished a meeting.'
      }
      if (isSecretarySide(userRole) && update.actor_role === 'admin') {
        if (update.action === 'reordered') return 'A department changed a meeting order.'
      }
      return ''
    },
    meeting_point(update, userRole) {
      if (userRole === 'admin' && isSecretarySide(update.actor_role)) {
        if (update.action === 'reviewed') return 'The secretary reviewed a meeting point.'
        if (update.action === 'activated') return 'The secretary activated your meeting point.'
        if (update.action === 'finalized') return 'The secretary finalized your meeting point.'
      }
      if (isSecretarySide(userRole) && update.actor_role === 'admin') {
        if (update.action === 'created') return 'A department submitted a new meeting point.'
        if (update.action === 'updated') return 'A department updated a meeting point.'
      }
      return ''
    },
    meeting_point_note(update, userRole) {
      if (userRole === 'admin' && isSecretarySide(update.actor_role)) {
        return 'The secretary left a note on a meeting point.'
      }
      if (isSecretarySide(userRole) && update.actor_role === 'admin') {
        return 'The department left a note on a meeting point.'
      }
      return ''
    },
    meeting_note(update, userRole) {
      if (userRole === 'admin' && isSecretarySide(update.actor_role)) {
        return 'The secretary added a general meeting note.'
      }
      if (isSecretarySide(userRole) && update.actor_role === 'admin') {
        return 'The department added a general meeting note.'
      }
      return ''
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

  const userRole = computed(() => authStore.user?.role || null)

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
    if (Array.isArray(update.audience_roles) && update.audience_roles.length > 0 && !update.audience_roles.includes(user.role)) {
      return false
    }

    if (
      user.role === 'admin' &&
      update.department_id &&
      update.department_id !== user.department_id &&
      ['event', 'event_note', 'meeting_point', 'meeting_point_note'].includes(update.entity)
    ) {
      return false
    }

    return true
  }

  const formatMessage = (update) => {
    const locale = uiStore.locale in messages ? uiStore.locale : 'en'
    const formatter = messages[locale]?.[update.entity]
    if (typeof formatter !== 'function') {
      return ''
    }

    return formatter(update, userRole.value)
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
