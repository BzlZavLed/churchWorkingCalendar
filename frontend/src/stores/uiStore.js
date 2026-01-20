import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

const LOCALE_KEY = 'ui_locale'

export const useUiStore = defineStore('ui', () => {
  const locale = ref(localStorage.getItem(LOCALE_KEY) || 'es')
  const toastMessage = ref('')
  const toastType = ref('success')
  const toastVisible = ref(false)
  const toastTimer = ref(null)

  watch(locale, (next) => {
    if (next) {
      localStorage.setItem(LOCALE_KEY, next)
    }
  })

  const showToast = (message, type = 'success', duration = 3000) => {
    toastMessage.value = message
    toastType.value = type
    toastVisible.value = true
    if (toastTimer.value) {
      clearTimeout(toastTimer.value)
    }
    toastTimer.value = setTimeout(() => {
      toastVisible.value = false
      toastTimer.value = null
    }, duration)
  }

  const hideToast = () => {
    toastVisible.value = false
    if (toastTimer.value) {
      clearTimeout(toastTimer.value)
      toastTimer.value = null
    }
  }

  return {
    locale,
    toastMessage,
    toastType,
    toastVisible,
    showToast,
    hideToast,
  }
})
