<template>
  <div
    class="app-shell"
    :class="{
      'app-shell--sidebar-collapsed': shouldShowIconsOnly,
      'app-shell--greeting-focus': isGreetingFocusMode,
      'app-shell--greeting-sidebar-visible': isGreetingFocusMode && isSidebarOpen,
    }"
  >
    <button
      v-if="isGreetingFocusMode"
      class="greeting-menu-button"
      type="button"
      :aria-label="toggleSidebarLabel"
      :title="toggleSidebarLabel"
      @click="toggleSidebar"
    >
      <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true">
        <path :d="iconPaths.menu" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" />
      </svg>
    </button>

    <aside
      class="app-sidebar"
      :class="{
        'app-sidebar--compact': shouldShowIconsOnly,
        'app-sidebar--open': isSidebarOpen,
        'app-sidebar--greeting-hidden': isGreetingFocusMode && !isSidebarOpen,
      }"
    >
      <button
        v-if="!isGreetingFocusMode"
        class="app-burger"
        type="button"
        :aria-label="toggleSidebarLabel"
        :title="toggleSidebarLabel"
        @click="toggleSidebar"
      >
        <svg viewBox="0 0 24 24" class="nav-icon" aria-hidden="true">
          <path :d="toggleIconPath" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.9" />
        </svg>
      </button>
      <div
        v-show="!isCompactSidebarState || isSidebarOpen"
        ref="sidebarInnerRef"
        class="app-sidebar-inner"
      >
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
          <label class="sidebar-language">
            <span v-if="!shouldShowIconsOnly" class="form-label sidebar-language-label">{{ t.language }}</span>
            <span v-else class="sidebar-language-icon" aria-hidden="true">A</span>
            <select v-model="locale" class="form-select form-select-sm sidebar-language-select" :title="t.language">
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
const desktopSidebarMode = ref(null)
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
  greeting: 'M12 21c4.97-4.1 8-7.75 8-11.5A4.5 4.5 0 0 0 15.5 5c-1.54 0-2.84.72-3.5 1.84A4.15 4.15 0 0 0 8.5 5 4.5 4.5 0 0 0 4 9.5C4 13.25 7.03 16.9 12 21Z',
  greetingList: 'M6 7h12M6 12h12M6 17h12M4 7h.01M4 12h.01M4 17h.01',
}

const navItems = computed(() => {
  if (!authStore.isAuthenticated) {
    return []
  }

  const role = authStore.user?.role
  const isGreetingUser = Boolean(authStore.user?.department?.is_greeting)

  if (isGreetingUser) {
    return [
      { to: '/greeting', label: t.value.greeting, icon: iconPaths.greeting },
      { to: '/greeting/contacts', label: t.value.greetingContacts, icon: iconPaths.greetingList },
    ]
  }

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
    items.push({ to: '/greeting/contacts', label: t.value.greetingContacts, icon: iconPaths.greetingList })
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

const shouldShowIconsOnly = computed(() => {
  if (isCompactSidebarState.value) {
    return false
  }
  if (desktopSidebarMode.value === 'expanded') {
    return false
  }
  if (desktopSidebarMode.value === 'collapsed') {
    return true
  }
  return autoIconOnly.value
})
const toggleSidebarLabel = computed(() => {
  if (isCompactSidebarState.value) {
    return isSidebarOpen.value ? 'Close navigation' : 'Open navigation'
  }
  return shouldShowIconsOnly.value ? 'Expand navigation' : 'Collapse navigation'
})
const toggleIconPath = computed(() => {
  if (isCompactSidebarState.value && isSidebarOpen.value) {
    return 'M6 6l12 12M18 6 6 18'
  }
  if (!isCompactSidebarState.value && shouldShowIconsOnly.value) {
    return 'M7 5v14M11 7l6 5-6 5'
  }
  if (!isCompactSidebarState.value) {
    return 'M17 5v14M13 7l-6 5 6 5'
  }
  return iconPaths.menu
})

const roleLabel = computed(() => {
  const role = authStore.user?.role
  if (!role) {
    return ''
  }
  return t.value.roleLabels?.[role] || role
})

const isGreetingFocusMode = computed(() => Boolean(authStore.user?.department?.is_greeting))

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

const toggleGreetingSidebar = () => {
  if (!isGreetingFocusMode.value) {
    return
  }

  isSidebarOpen.value = !isSidebarOpen.value
  document.body.classList.toggle('sidebar-open', isSidebarOpen.value)
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
  if (isCompactSidebarState.value || desktopSidebarMode.value === 'expanded' || desktopSidebarMode.value === 'collapsed') {
    autoIconOnly.value = false
    return
  }

  autoIconOnly.value = false
  await nextTick()

  const hasOverflow = Object.values(navLinkRefs.value).some(
    (el) => el && el.scrollWidth > el.clientWidth - 10
  )
  autoIconOnly.value = hasOverflow
}

const toggleSidebar = () => {
  if (isGreetingFocusMode.value) {
    toggleGreetingSidebar()
    return
  }

  if (isCompactSidebarState.value) {
    isSidebarOpen.value = !isSidebarOpen.value
    document.body.classList.toggle('sidebar-open', isSidebarOpen.value)
    return
  }

  desktopSidebarMode.value = shouldShowIconsOnly.value ? 'expanded' : 'collapsed'
  closeSidebar()
  updateAutoIconOnly()
}

const handleNavClick = () => {
  if (isGreetingFocusMode.value || isCompactSidebar()) {
    closeSidebar()
  }
}

const handleResize = () => {
  if (isGreetingFocusMode.value) {
    closeSidebar()
    autoIconOnly.value = false
    return
  }

  isCompactSidebarState.value = isCompactSidebar()
  if (isCompactSidebarState.value) {
    closeSidebar()
    autoIconOnly.value = false
    return
  }
  closeSidebar()
  updateAutoIconOnly()
}

const handleGlobalKeydown = (event) => {
  const key = String(event.key || '').toLowerCase()

  if (isGreetingFocusMode.value && (event.metaKey || event.ctrlKey) && key === 'k') {
    event.preventDefault()
    toggleGreetingSidebar()
    return
  }

  if (isGreetingFocusMode.value && key === 'escape' && isSidebarOpen.value) {
    event.preventDefault()
    closeSidebar()
  }
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
    if (isGreetingFocusMode.value || isCompactSidebar()) {
      closeSidebar()
    }
  }
)

watch(
  () => isGreetingFocusMode.value,
  (isGreeting) => {
    if (isGreeting) {
      closeSidebar()
      autoIconOnly.value = false
      return
    }
    handleResize()
  },
  { immediate: true }
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
  window.addEventListener('keydown', handleGlobalKeydown)
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
  window.removeEventListener('keydown', handleGlobalKeydown)
  sidebarResizeObserver?.disconnect()
  closeSidebar()
})
</script>

<style scoped>
.app-shell--greeting-focus {
  grid-template-columns: minmax(0, 1fr);
}

.app-shell--greeting-focus .app-sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  z-index: 1100;
  width: min(86vw, 320px);
  max-width: 320px;
  transform: translateX(-105%);
  opacity: 0;
  pointer-events: none;
  transition: transform 180ms ease, opacity 180ms ease;
  box-shadow: 0 24px 60px rgba(15, 23, 42, 0.16);
}

.app-shell--greeting-sidebar-visible .app-sidebar {
  transform: translateX(0);
  opacity: 1;
  pointer-events: auto;
}

.app-shell--greeting-focus .app-sidebar--greeting-hidden {
  visibility: hidden;
}

.app-shell--greeting-focus .app-main {
  max-width: 1280px;
  width: min(100%, 1280px);
  margin: 0 auto;
  padding-left: 24px;
  padding-right: 24px;
}

.greeting-menu-button {
  position: fixed;
  top: 16px;
  left: 16px;
  z-index: 1200;
  width: 48px;
  height: 48px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(31, 111, 92, 0.16);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.92);
  color: #1f6f5c;
  box-shadow: 0 12px 30px rgba(31, 111, 92, 0.14);
  backdrop-filter: blur(10px);
}

.greeting-menu-button:hover {
  background: #ffffff;
}
</style>
