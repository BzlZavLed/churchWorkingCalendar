<template>
  <router-view v-if="route.meta.noLayout" />
  <AppLayout v-else>
    <p v-if="currentModule && authStore.canAccess(currentModule) && !authStore.canEdit(currentModule)" class="module-readonly-banner">{{ ui.locale==='es'?'Solo lectura · Puedes consultar y exportar, pero no modificar este módulo.':'Read only · You can view and export, but cannot modify this module.' }}</p>
    <router-view :key="authStore.contextKey + route.path" />
  </AppLayout>
</template>

<script setup>
import { watch,computed } from 'vue'
import { useCalendarStore } from './stores/calendarStore'
import { useAuthStore } from './stores/authStore'
import { useRoute } from 'vue-router'
import { moduleForPath } from './services/accessApi'
import { useUiStore } from './stores/uiStore'
import AppLayout from './components/AppLayout.vue'

const route = useRoute()
const ui=useUiStore()
const currentModule=computed(()=>moduleForPath(route.path))
const authStore = useAuthStore()
const calendarStore = useCalendarStore()
watch(() => authStore.contextKey, () => { calendarStore.events = [] }, { flush: 'sync' })
</script>

<style>.module-readonly-banner{margin:16px 24px 0;padding:12px 16px;background:#edf5f0;border:1px solid #b9d2c2;color:#30513f;border-radius:8px;font-size:13px}</style>
