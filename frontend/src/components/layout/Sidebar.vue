<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  HomeIcon,
  ChartBarIcon,
  CurrencyDollarIcon,
  CalendarIcon,
  DocumentTextIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  PlusCircleIcon,
  ClipboardDocumentListIcon,
  BriefcaseIcon,
  BookOpenIcon,
  NewspaperIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  open: Boolean,
})

const emit = defineEmits(['close'])

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const navigation = [
  { name: 'Dashboard', href: '/dashboard', icon: HomeIcon },
  { name: 'Accounts', href: '/accounts', icon: BriefcaseIcon },
  { name: 'Trades', href: '/trades', icon: ChartBarIcon },
  { name: 'Add Trade', href: '/trades/new', icon: PlusCircleIcon },
  { name: 'Portfolio', href: '/portfolio', icon: CurrencyDollarIcon },
  { name: 'Calendar', href: '/calendar', icon: CalendarIcon },
  { name: 'News', href: '/news', icon: NewspaperIcon },
  { name: 'Journal', href: '/journal', icon: BookOpenIcon },
  { name: 'Reports', href: '/reports', icon: DocumentTextIcon },
  { name: 'Settings', href: '/settings', icon: Cog6ToothIcon },
]

const isActive = (href) => {
  if (href === '/dashboard') {
    return route.path === '/dashboard'
  }
  return route.path.startsWith(href)
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <!-- Mobile sidebar backdrop -->
  <div
    v-if="open"
    class="fixed inset-0 z-40 bg-dark-950/80 backdrop-blur-sm lg:hidden"
    @click="emit('close')"
  />

  <!-- Sidebar -->
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-dark-900 border-r border-dark-800 transition-transform duration-300 lg:static lg:translate-x-0',
      open ? 'translate-x-0' : '-translate-x-full',
    ]"
  >
    <!-- Logo -->
    <div class="flex h-16 items-center justify-between px-4 border-b border-dark-800">
      <router-link to="/dashboard" class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center">
          <ChartBarIcon class="w-5 h-5 text-white" />
        </div>
        <span class="text-lg font-semibold gradient-text">TradingJournal</span>
      </router-link>
      
      <!-- Close button (mobile) -->
      <button
        class="lg:hidden p-1 rounded-lg hover:bg-dark-800 text-dark-400"
        @click="emit('close')"
      >
        <XMarkIcon class="w-5 h-5" />
      </button>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto p-4 space-y-1">
      <router-link
        v-for="item in navigation"
        :key="item.name"
        :to="item.href"
        :class="[
          'sidebar-link',
          isActive(item.href) && 'active',
        ]"
        @click="emit('close')"
      >
        <component :is="item.icon" class="w-5 h-5" />
        <span>{{ item.name }}</span>
      </router-link>
    </nav>

    <!-- User section -->
    <div class="border-t border-dark-800 p-4">
      <div class="flex items-center gap-3 mb-3">
        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center">
          <span class="text-sm font-medium text-white">
            {{ authStore.user?.name?.charAt(0)?.toUpperCase() || 'U' }}
          </span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-dark-100 truncate">
            {{ authStore.user?.name || 'User' }}
          </p>
          <p class="text-xs text-dark-400 truncate">
            {{ authStore.user?.email || '' }}
          </p>
        </div>
      </div>
      
      <button
        class="sidebar-link w-full text-red-400 hover:text-red-300 hover:bg-red-500/10"
        @click="handleLogout"
      >
        <ArrowRightOnRectangleIcon class="w-5 h-5" />
        <span>Logout</span>
      </button>
    </div>
  </aside>
</template>

