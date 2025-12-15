import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Lazy-loaded route components
const LandingPage = () => import('@/pages/LandingPage.vue')
const Login = () => import('@/pages/Login.vue')
const Register = () => import('@/pages/Register.vue')
const Dashboard = () => import('@/pages/Dashboard.vue')
const Accounts = () => import('@/pages/Accounts.vue')
const AccountDetail = () => import('@/pages/AccountDetail.vue')
const Trades = () => import('@/pages/Trades.vue')
const ManualTrade = () => import('@/pages/ManualTrade.vue')
const Calendar = () => import('@/pages/Calendar.vue')
const Reports = () => import('@/pages/Reports.vue')
const Settings = () => import('@/pages/Settings.vue')
const Journal = () => import('@/pages/Journal.vue')
const Portfolio = () => import('@/pages/Portfolio.vue')
const News = () => import('@/pages/News.vue')
const Chart = () => import('@/pages/Chart.vue')
const Insights = () => import('@/pages/Insights.vue')

const routes = [
  {
    path: '/',
    name: 'landing',
    component: LandingPage,
    meta: { guest: true },
  },
  {
    path: '/login',
    name: 'login',
    component: Login,
    meta: { guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: Register,
    meta: { guest: true },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: Dashboard,
    meta: { requiresAuth: true },
  },
  {
    path: '/accounts',
    name: 'accounts',
    component: Accounts,
    meta: { requiresAuth: true },
  },
  {
    path: '/accounts/:id',
    name: 'account-detail',
    component: AccountDetail,
    meta: { requiresAuth: true },
  },
  {
    path: '/trades',
    name: 'trades',
    component: Trades,
    meta: { requiresAuth: true },
  },
  {
    path: '/trades/new',
    name: 'manual-trade',
    component: ManualTrade,
    meta: { requiresAuth: true },
  },
  {
    path: '/portfolio',
    name: 'portfolio',
    component: Portfolio,
    meta: { requiresAuth: true },
  },
  {
    path: '/calendar',
    name: 'calendar',
    component: Calendar,
    meta: { requiresAuth: true },
  },
  {
    path: '/reports',
    name: 'reports',
    component: Reports,
    meta: { requiresAuth: true },
  },
  {
    path: '/journal',
    name: 'journal',
    component: Journal,
    meta: { requiresAuth: true },
  },
  {
    path: '/news',
    name: 'news',
    component: News,
    meta: { requiresAuth: true },
  },
  {
    path: '/chart',
    name: 'chart',
    component: Chart,
    meta: { requiresAuth: true },
  },
  {
    path: '/insights',
    name: 'insights',
    component: Insights,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings',
    name: 'settings',
    component: Settings,
    meta: { requiresAuth: true },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  
  // Initialize auth state if needed
  if (!authStore.initialized) {
    await authStore.initAuth()
  }

  const isAuthenticated = authStore.isAuthenticated

  if (to.meta.requiresAuth && !isAuthenticated) {
    next({ name: 'login', query: { redirect: to.fullPath } })
  } else if (to.meta.guest && isAuthenticated && to.name !== 'landing') {
    // Allow authenticated users to view landing page, but redirect from login/register
    next({ name: 'dashboard' })
  } else if (to.name === 'landing' && isAuthenticated) {
    // If user is logged in and goes to landing, redirect to dashboard
    next({ name: 'dashboard' })
  } else {
    next()
  }
})

export default router

