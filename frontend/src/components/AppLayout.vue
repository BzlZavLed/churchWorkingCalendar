<template>
  <div class="app-shell">
    <aside class="app-sidebar">
      <button class="app-burger" type="button" @click="toggleSidebar">
        ☰
      </button>
      <div class="app-sidebar-inner">
        <img :src="logoUrl" alt="Logo" class="login-logo" />

        <h2 v-if="authStore.isAuthenticated" class="app-sidebar-title">{{ t.navigation }}</h2>

        <nav v-if="authStore.isAuthenticated && authStore.user?.role === 'superadmin'">
          <ul class="app-nav">
            <li><router-link to="/calendar">{{ t.calendar }}</router-link></li>
            <li><router-link to="/objectives">{{ t.objectives }}</router-link></li>
            <li><router-link to="/superadmin/churches">{{ t.churches }}</router-link></li>
            <li><router-link to="/superadmin/departments">{{ t.departments }}</router-link></li>
            <li><router-link to="/superadmin/users">{{ t.users }}</router-link></li>
          </ul>
        </nav>

        <nav v-else-if="authStore.isAuthenticated">
          <ul class="app-nav">
            <li><router-link to="/calendar">{{ t.calendar }}</router-link></li>
            <li>
              <router-link to="/reports">{{ t.reports }}</router-link>
            </li>
            <li v-if="authStore.user?.role !== 'secretary'">
              <router-link to="/objectives">{{ t.objectives }}</router-link>
            </li>
          </ul>
        </nav>

        <div v-if="authStore.isAuthenticated" class="app-sidebar-footer">
          <label class="d-flex align-items-center gap-2 mb-2">
            <span class="form-label small mb-0 text-white">{{ t.language }}</span>
            <select v-model="locale" class="form-select form-select-sm w-auto">
              <option value="es">Espanol</option>
              <option value="en">English</option>
            </select>
          </label>
          <button class="btn btn-outline-dark w-100" type="button" @click="logout">
            {{ t.logout }}
          </button>
        </div>
      </div>
    </aside>

    <main class="app-main">
      <slot />
    </main>
  </div>
</template>

<script setup>
import logoUrl from '../assets/logo.png'

import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { computed, ref, watch } from 'vue'

const authStore = useAuthStore()
const router = useRouter()
const isSidebarOpen = ref(false)
const LOCALE_KEY = 'ui_locale'
const locale = ref(localStorage.getItem(LOCALE_KEY) || 'es')

const translations = {
  es: {
    navigation: 'Navegacion',
    language: 'Idioma',
    calendar: 'Calendario',
    objectives: 'Objetivos',
    churches: 'Iglesias',
    departments: 'Departamentos',
    users: 'Usuarios',
    reports: 'Reportes',
    logout: 'Salir',
  },
  en: {
    navigation: 'Navigation',
    language: 'Language',
    calendar: 'Calendar',
    objectives: 'Objectives',
    churches: 'Churches',
    departments: 'Departments',
    users: 'Users',
    reports: 'Reports',
    logout: 'Logout',
  },
}

const t = computed(() => translations[locale.value] || translations.es)

watch(locale, (next) => {
  if (next) {
    localStorage.setItem(LOCALE_KEY, next)
  }
})

const logout = async () => {
  await authStore.logout()
  await router.push('/login')
}

const toggleSidebar = () => {
  isSidebarOpen.value = !isSidebarOpen.value
  document.body.classList.toggle('sidebar-open', isSidebarOpen.value)
}
</script>
