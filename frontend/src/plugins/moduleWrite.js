import { watchEffect } from 'vue'
import { useAuthStore } from '../stores/authStore'
// UI affordance only. Every mutation is independently authorized by the API.
export const moduleWrite = {
 mounted(element,binding){element.__writeModule=binding.value;const auth=useAuthStore();element.__stopWrite=watchEffect(()=>{element.classList.toggle('module-write-hidden',!(element.__writeModule.includes('.') ? auth.can(element.__writeModule) : auth.canEdit(element.__writeModule)))})},
 updated(element,binding){element.__writeModule=binding.value;element.classList.toggle('module-write-hidden',!(binding.value.includes('.') ? useAuthStore().can(binding.value) : useAuthStore().canEdit(binding.value)))},
 unmounted(element){element.__stopWrite?.()},
}
