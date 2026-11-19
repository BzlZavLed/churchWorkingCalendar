import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/authStore'
import CalendarView from '../components/CalendarView.vue'
import LoginForm from '../components/LoginForm.vue'
import RegisterWithInvite from '../components/RegisterWithInvite.vue'
import ObjectivesView from '../components/ObjectivesView.vue'
import ReportsView from '../components/ReportsView.vue'
import AdminUsers from '../components/admin/AdminUsers.vue'
import SecretaryUsers from '../components/secretary/SecretaryUsers.vue'
import SecretaryMeetings from '../components/secretary/SecretaryMeetings.vue'
import SuperAdminLogin from '../components/superadmin/SuperAdminLogin.vue'
import SuperAdminChurches from '../components/superadmin/SuperAdminChurches.vue'
import SuperAdminDepartments from '../components/superadmin/SuperAdminDepartments.vue'
import SuperAdminUsers from '../components/superadmin/SuperAdminUsers.vue'
import SuperAdminCalendarManager from '../components/superadmin/SuperAdminCalendarManager.vue'
import BudgetView from '../components/BudgetView.vue'
import InventoryView from '../components/InventoryView.vue'
import MeetingPointsView from '../components/MeetingPointsView.vue'
import GreetingIntakeView from '../components/GreetingIntakeView.vue'
import GreetingContactsView from '../components/GreetingContactsView.vue'
import NoAccessView from '../components/NoAccessView.vue'
import SuperAdminSettings from '../components/superadmin/SuperAdminSettings.vue'
import SuperAdminRoles from '../components/superadmin/SuperAdminRoles.vue'
import { moduleForPath } from '../services/accessApi'
import SuperAdminModuleContext from '../components/superadmin/SuperAdminModuleContext.vue'
import CommitteesView from '../components/CommitteesView.vue'
import MembersView from '../components/MembersView.vue'
import VisitorFormBuilder from '../components/VisitorFormBuilder.vue'
import PublicConsentView from '../components/PublicConsentView.vue'
import { getDefaultRouteForUser } from '../stores/authStore'

const routes = [
  { path: '/superadmin/whatsapp-tools', component: () => import('../components/superadmin/WhatsAppTools.vue'), meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/secretary/settings', component: () => import('../components/secretary/SecretaryIntegrationSettings.vue'), meta: { requiresAuth: true, integrationManager: true } },
  { path: '/whatsapp-tools', component: () => import('../components/superadmin/WhatsAppTools.vue'), meta: { requiresAuth: true, integrationManager: true } },
  { path: '/whatsapp-settings', component: () => import('../components/WhatsAppSettings.vue'), meta: { requiresAuth: true } },
  { path: '/superadmin/module-context', component: SuperAdminModuleContext, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/committees', component: CommitteesView, meta: { requiresAuth: true } },
  { path: '/superadmin/roles', component: SuperAdminRoles, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/no-access', component: NoAccessView, meta: { requiresAuth: true } },
  { path: '/superadmin/settings', component: SuperAdminSettings, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/members', component: MembersView, meta: { requiresAuth: true } },
  { path: '/greeting/form-builder', component: VisitorFormBuilder, meta: { requiresAuth: true, pastorOnly: true } },
  { path: '/visit/:churchId', component: GreetingIntakeView, meta: { noLayout: true } },
  { path: '/', redirect: '/calendar' },
  { path: '/login', component: LoginForm, meta: { guestOnly: true, noLayout: true } },
  { path: '/register', component: RegisterWithInvite, meta: { guestOnly: true, noLayout: true } },
  { path: '/calendar', component: CalendarView, meta: { requiresAuth: true } },
  { path: '/objectives', component: ObjectivesView, meta: { requiresAuth: true } },
  { path: '/reports', component: ReportsView, meta: { requiresAuth: true } },
  { path: '/meeting-points', component: MeetingPointsView, meta: { requiresAuth: true } },
  { path: '/greeting/attendance', component: GreetingIntakeView, meta: { requiresAuth: true, greetingOnly: true } },
  { path: '/greeting', component: GreetingIntakeView, meta: { requiresAuth: true, greetingOnly: true } },
  { path: '/greeting/contacts', component: GreetingContactsView, meta: { requiresAuth: true, contactsAllowed: true } },
  { path: '/consent/:token?', component: PublicConsentView, meta: { guestOnly: false, noLayout: true } },
  { path: '/admin/users', component: AdminUsers, meta: { requiresAuth: true, adminOnly: true } },
  { path: '/secretary/departments', component: SuperAdminDepartments, meta: { requiresAuth: true, secretaryOnly: true } },
  { path: '/secretary/meetings', component: SecretaryMeetings, meta: { requiresAuth: true, secretaryOnly: true } },
  { path: '/secretary/users', component: SecretaryUsers, meta: { requiresAuth: true, secretaryOnly: true } },
  { path: '/budget', component: BudgetView, meta: { requiresAuth: true } },
  { path: '/inventory', component: InventoryView, meta: { requiresAuth: true, inventoryOnly: true } },
  { path: '/superadmin', redirect: '/superadmin/churches' },
  { path: '/superadmin/login', component: SuperAdminLogin, meta: { guestOnly: true, noLayout: true } },
  { path: '/superadmin/churches', component: SuperAdminChurches, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/superadmin/departments', component: SuperAdminDepartments, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/superadmin/calendars', component: SuperAdminCalendarManager, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/superadmin/meetings', component: SecretaryMeetings, meta: { requiresAuth: true, superadminOnly: true } },
  { path: '/superadmin/users', component: SuperAdminUsers, meta: { requiresAuth: true, superadminOnly: true } },
]

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior() { return { left: 0, top: 0 } },
  routes,
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()

  if (authStore.isInitializing) {
    await authStore.initialize()
  }

  if (to.path.startsWith('/superadmin/') && !to.meta.noLayout && authStore.isPlatformAdmin) authStore.exitContext()


  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { path: '/login', query: to.path === '/greeting/contacts' ? { redirect: to.fullPath } : {} }
  }

  if (to.meta.guestOnly && authStore.isAuthenticated) {
    return getDefaultRouteForUser(authStore.user)
  }

  if (authStore.isAuthenticated && to.meta.requiresAuth) {
    try { await authStore.refreshAccess() } catch { return to.path === '/no-access' ? true : '/no-access' }
    if (to.meta.integrationManager && !authStore.isSecretary && !authStore.isPlatformAdmin) return authStore.firstAccessibleRoute()
    if (to.meta.superadminOnly && !authStore.isPlatformAdmin) return authStore.firstAccessibleRoute()
    const module = moduleForPath(to.path)
    if (module && authStore.isPlatformAdmin && (!authStore.adminContext || !authStore.canAccess(module))) return {path:'/superadmin/module-context',query:{module}}
    if (module && !authStore.canAccess(module)) return authStore.firstAccessibleRoute()
    if (to.path === '/greeting/form-builder' && !authStore.can('visitors.configure')) return '/greeting/contacts'
  }

  return true
})

export default router
