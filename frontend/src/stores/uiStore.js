import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

const LOCALE_KEY = 'ui_locale'

export const useUiStore = defineStore('ui', () => {
  const locale = ref(localStorage.getItem(LOCALE_KEY) || 'es')

  watch(locale, (next) => {
    if (next) {
      localStorage.setItem(LOCALE_KEY, next)
    }
  })

  return {
    locale,
  }
})
