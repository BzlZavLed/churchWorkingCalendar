import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import CalendarView from '../components/CalendarView.vue'
import LoginForm from '../components/LoginForm.vue'
import RegisterWithInvite from '../components/RegisterWithInvite.vue'
import ObjectivesView from '../components/ObjectivesView.vue'
import ReportsView from '../components/ReportsView.vue'
import AdminUsers from '../components/admin/AdminUsers.vue'
import SuperAdminLogin from '../components/superadmin/SuperAdminLogin.vue'
import SuperAdminChurches from '../components/superadmin/SuperAdminChurches.vue'
import SuperAdminDepartments from '../components/superadmin/SuperAdminDepartments.vue'
import SuperAdminUsers from '../components/superadmin/SuperAdminUsers.vue'
import SuperAdminCalendarManager from '../components/superadmin/SuperAdminCalendarManager.vue'
import InventoryView from '../components/InventoryView.vue'

const routes = [
  { path: '/', redirect: '/calendar' },
  { path: '/login', component: LoginForm, meta: { guestOnly: true, noLayout: true } },
  { path: '/register', component: RegisterWithInvite, meta: { guestOnly: true, noLayout: true } },
  { path: '/calendar', component: CalendarView, meta: { requiresAuth: true } },
  { path: '/objectives', component: ObjectivesView, meta: { requiresAuth: true } },
  { path: '/reports', component: ReportsView, meta: { requiresAuth: true } },
  { path: '/admin/users', component: AdminUsers, meta: { requiresAuth: true, adminOnly: true } },
  { path: '/inventory', component: InventoryView, meta: { requiresAuth: true, inventoryOnly: true } },
  { path: '/superadmin', redirect: '/superadmin/churches' },
  { path: '/superadmin/login', component: SuperAdminLogin, meta: { guestOnly: true, noLayout: true } },
  { path: '/superadmin/churches', component: SuperAdminChurches, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/superadmin/departments', component: SuperAdminDepartments, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/superadmin/calendars', component: SuperAdminCalendarManager, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/superadmin/users', component: SuperAdminUsers, meta: { requiresAuth: true, superadminOnly: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (authStore.isInitializing) {
    await authStore.initialize()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return '/login'
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    if (authStore.user?.role === 'superadmin') {
      return '/superadmin/churches'
    }
    return '/calendar'
  }

  if (to.meta.superadminOnly && authStore.user?.role !== 'superadmin') {
    return '/calendar'
  }

  if (to.meta.adminOnly && !['admin', 'superadmin'].includes(authStore.user?.role || '')) {
    return '/calendar'
  }

  if (to.meta.inventoryOnly && !['admin', 'secretary', 'superadmin'].includes(authStore.user?.role || '')) {
    return '/calendar'
  }

  return true
})

export default router
