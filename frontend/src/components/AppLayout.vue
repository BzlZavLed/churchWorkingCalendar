<template>
  <div class="app-shell" :class="{ 'app-shell--sidebar-collapsed': shouldShowIconsOnly }">
    <aside class="app-sidebar" :class="{ 'app-sidebar--compact': shouldShowIconsOnly }">
      <button
        class="app-burger"
        type="button"
        :aria-label="toggleSidebarLabel"
        :title="toggleSidebarLabel"
        @click="toggleSidebar"
      >
        <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true">
          <path :d="iconPaths.menu" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" />
        </svg>
      </button>
      <div ref="sidebarInnerRef" class="app-sidebar-inner">
        <img :src="logoUrl" alt="Logo" class="login-logo" />

        <div v-if="authStore.isAuthenticated && authStore.user" class="sidebar-user">
          <div class="sidebar-user-initials" aria-hidden="true">
            {{ userInitials }}
          </div>
          <div class="sidebar-user-name">{{ authStore.user.name }}</div>
          <div class="sidebar-user-role">{{ roleLabel }}</div>
          <div v-if="departmentLabel" class="sidebar-user-dept">{{ departmentLabel }}</div>
        </div>

        <h2 v-if="authStore.isAuthenticated" class="app-sidebar-title">{{ t.navigation }}</h2>

        <nav v-if="authStore.isAuthenticated" @click="handleNavClick">
          <ul class="app-nav">
            <li v-for="item in navItems" :key="item.to">
              <router-link
                :to="item.to"
                :ref="(el) => setNavLinkRef(el, item.to)"
                :title="shouldShowIconsOnly ? item.label : ''"
              >
                <span class="nav-link-content">
                  <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true">
                    <path :d="item.icon" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" />
                  </svg>
                  <span v-if="!shouldShowIconsOnly" class="nav-label">{{ item.label }}</span>
                </span>
              </router-link>
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
          <button
            class="btn btn-outline-dark w-100 sidebar-logout-button"
            type="button"
            :title="shouldShowIconsOnly ? t.logout : ''"
            @click="logout"
          >
            <span class="nav-link-content">
              <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true">
                <path :d="iconPaths.logout" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" />
              </svg>
              <span v-if="!shouldShowIconsOnly" class="nav-label">{{ t.logout }}</span>
            </span>
          </button>
        </div>
      </div>
    </aside>

    <main class="app-main">
      <slot />
    </main>
    <ToastMessage />
  </div>
</template>

<script setup>
import logoUrl from '../assets/logo.png'

import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import { useUiStore } from '../stores/uiStore'
import { translations } from '../i18n/translations'
import { storeToRefs } from 'pinia'
import ToastMessage from './ToastMessage.vue'
import { useLiveUpdateStore } from '../stores/liveUpdateStore'

const authStore = useAuthStore()
const uiStore = useUiStore()
const liveUpdateStore = useLiveUpdateStore()
const { locale } = storeToRefs(uiStore)
const router = useRouter()
const route = useRoute()
const isSidebarOpen = ref(false)
const isSidebarCollapsed = ref(false)
const autoIconOnly = ref(false)
const isCompactSidebarState = ref(false)
const sidebarInnerRef = ref(null)
const navLinkRefs = ref({})
const compactSidebarBreakpoint = 1120
let sidebarResizeObserver = null

const t = computed(() => translations[locale.value].appLayout)
const iconPaths = {
  menu: 'M4 7h16M4 12h16M4 17h16',
  calendar: 'M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a1 1 0 0 1 1-1Z',
  objectives: 'M12 3 21 8l-9 5-9-5 9-5Zm-7 8 7 4 7-4M5 15l7 4 7-4',
  churches: 'M12 3l7 4v11H5V7l7-4Zm0 4v11M9 11h6',
  departments: 'M4 6h16M4 12h16M4 18h10',
  calendarManager: 'M5 5h14v14H5zM9 3v4M15 3v4M8 11h3M13 11h3M8 15h3',
  meetings: 'M5 7h14M7 11h10M9 15h6M4 4h16v16H4z',
  inventory: 'M6 7 12 4l6 3-6 3-6-3Zm0 5 6 3 6-3M6 17l6 3 6-3',
  users: 'M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm-7 8a7 7 0 0 1 14 0',
  reports: 'M5 19V9M12 19V5M19 19v-8',
  meetingPoints: 'M5 5h14v4H5zM5 11h8v8H5zM15 13h4M15 17h4',
  logout: 'M15 16l4-4-4-4M19 12H9M12 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6',
}

const navItems = computed(() => {
  if (!authStore.isAuthenticated) {
    return []
  }

  const role = authStore.user?.role
  if (role === 'superadmin') {
    return [
      { to: '/calendar', label: t.value.calendar, icon: iconPaths.calendar },
      { to: '/objectives', label: t.value.objectives, icon: iconPaths.objectives },
      { to: '/superadmin/churches', label: t.value.churches, icon: iconPaths.churches },
      { to: '/superadmin/departments', label: t.value.departments, icon: iconPaths.departments },
      { to: '/superadmin/calendars', label: t.value.calendarManager, icon: iconPaths.calendarManager },
      { to: '/superadmin/meetings', label: t.value.meetings, icon: iconPaths.meetings },
      { to: '/inventory', label: t.value.inventory, icon: iconPaths.inventory },
      { to: '/superadmin/users', label: t.value.users, icon: iconPaths.users },
    ]
  }

  const items = [
    { to: '/calendar', label: t.value.calendar, icon: iconPaths.calendar },
    { to: '/reports', label: t.value.reports, icon: iconPaths.reports },
  ]

  if (role === 'admin') {
    items.push({ to: '/meeting-points', label: t.value.meetingPoints, icon: iconPaths.meetingPoints })
  }

  if (role !== 'secretary') {
    items.push({ to: '/objectives', label: t.value.objectives, icon: iconPaths.objectives })
  }

  if (['admin', 'secretary'].includes(role || '')) {
    items.push({ to: '/inventory', label: t.value.inventory, icon: iconPaths.inventory })
  }

  if (role === 'secretary') {
    items.push(
      { to: '/secretary/meetings', label: t.value.meetings, icon: iconPaths.meetings },
      { to: '/secretary/departments', label: t.value.departments, icon: iconPaths.departments },
      { to: '/secretary/users', label: t.value.users, icon: iconPaths.users },
    )
  }

  return items
})

const shouldShowIconsOnly = computed(
  () => !isCompactSidebarState.value && (isSidebarCollapsed.value || autoIconOnly.value)
)
const toggleSidebarLabel = computed(() => {
  if (isCompactSidebarState.value) {
    return isSidebarOpen.value ? 'Close navigation' : 'Open navigation'
  }
  return shouldShowIconsOnly.value ? 'Expand navigation' : 'Collapse navigation'
})

const roleLabel = computed(() => {
  const role = authStore.user?.role
  if (!role) {
    return ''
  }
  return t.value.roleLabels?.[role] || role
})

const userInitials = computed(() => {
  const name = authStore.user?.name || ''
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) {
    return ''
  }
  const first = parts[0][0] || ''
  const last = parts.length > 1 ? parts[parts.length - 1][0] || '' : ''
  return `${first}${last}`.toUpperCase()
})

const departmentLabel = computed(() => {
  const dept = authStore.user?.department?.name
  return dept ? `${t.value.departmentLabel}: ${dept}` : ''
})

const logout = async () => {
  closeSidebar()
  await authStore.logout()
  await router.push('/login')
}

const closeSidebar = () => {
  isSidebarOpen.value = false
  document.body.classList.remove('sidebar-open')
}

const isCompactSidebar = () => window.innerWidth <= compactSidebarBreakpoint

const setNavLinkRef = (el, key) => {
  const resolved = el?.$el || el
  if (resolved && resolved.nodeType === 1) {
    navLinkRefs.value[key] = resolved
    return
  }
  delete navLinkRefs.value[key]
}

const updateAutoIconOnly = async () => {
  if (isCompactSidebarState.value || isSidebarCollapsed.value) {
    autoIconOnly.value = false
    return
  }

  autoIconOnly.value = false
  await nextTick()

  const hasOverflow = Object.values(navLinkRefs.value).some(
    (el) => el && el.scrollWidth > el.clientWidth + 1
  )
  autoIconOnly.value = hasOverflow
}

const toggleSidebar = () => {
  if (isCompactSidebarState.value) {
    isSidebarOpen.value = !isSidebarOpen.value
    document.body.classList.toggle('sidebar-open', isSidebarOpen.value)
    return
  }

  isSidebarCollapsed.value = !isSidebarCollapsed.value
  closeSidebar()
  updateAutoIconOnly()
}

const handleNavClick = () => {
  if (isCompactSidebar()) {
    closeSidebar()
  }
}

const handleResize = () => {
  isCompactSidebarState.value = isCompactSidebar()
  if (isCompactSidebarState.value) {
    closeSidebar()
    autoIconOnly.value = false
    return
  }
  closeSidebar()
  updateAutoIconOnly()
}

watch(
  () => authStore.isAuthenticated,
  (isAuthenticated) => {
    if (isAuthenticated) {
      liveUpdateStore.connect()
      return
    }
    liveUpdateStore.disconnect()
  },
  { immediate: true }
)

watch(
  () => route.fullPath,
  () => {
    if (isCompactSidebar()) {
      closeSidebar()
    }
  }
)

watch(
  () => locale.value,
  () => {
    updateAutoIconOnly()
  }
)

watch(
  () => navItems.value.map((item) => item.label).join('|'),
  () => {
    navLinkRefs.value = {}
    updateAutoIconOnly()
  }
)

onMounted(() => {
  window.addEventListener('resize', handleResize)
  handleResize()
  if (typeof ResizeObserver !== 'undefined') {
    sidebarResizeObserver = new ResizeObserver(() => {
      updateAutoIconOnly()
    })
    if (sidebarInnerRef.value) {
      sidebarResizeObserver.observe(sidebarInnerRef.value)
    }
  }
})

onUnmounted(() => {
  window.removeEventListener('resize', handleResize)
  sidebarResizeObserver?.disconnect()
  closeSidebar()
})
</script>
