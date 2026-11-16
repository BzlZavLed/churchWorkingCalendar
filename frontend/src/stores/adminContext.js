import { ref } from 'vue'

const storageKey = 'superadmin_view_context'
const restore = () => {
  try { return JSON.parse(sessionStorage.getItem(storageKey) || 'null') } catch { return null }
}
export const adminContext = ref(restore())
export const setAdminContext = (context) => {
  adminContext.value = context
  if (context) sessionStorage.setItem(storageKey, JSON.stringify(context))
  else sessionStorage.removeItem(storageKey)
}
