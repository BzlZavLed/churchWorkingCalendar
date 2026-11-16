<template>
  <router-view v-if="route.meta.noLayout" />
  <AppLayout v-else>
    <router-view :key="authStore.contextKey + route.path" />
  </AppLayout>
</template>

<script setup>
import { watch } from 'vue'
import { useCalendarStore } from './stores/calendarStore'
import { useAuthStore } from './stores/authStore'
import { useRoute } from 'vue-router'
import AppLayout from './components/AppLayout.vue'

const route = useRoute()
const authStore = useAuthStore()
const calendarStore = useCalendarStore()
watch(() => authStore.contextKey, () => { calendarStore.events = [] }, { flush: 'sync' })
</script>
