<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Sidebar from './Sidebar.vue'
import Header from './Header.vue'

const route = useRoute()
const authStore = useAuthStore()

const sidebarOpen = ref(false)

const pageTitle = computed(() => {
  const titles = {
    dashboard: 'Dashboard',
    accounts: 'Trading Accounts',
    'account-detail': 'Account Details',
    trades: 'Trade History',
    'manual-trade': 'Add Trade',
    portfolio: 'Portfolio',
    calendar: 'Calendar & News',
    news: 'EA Monitoring',
    chart: 'Chart Analysis',
    insights: 'Trading Insights',
    reports: 'Reports',
    journal: 'Trading Journal',
    settings: 'Settings',
  }
  return titles[route.name] || 'Trading Journal'
})
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-dark-950">
    <!-- Sidebar -->
    <Sidebar 
      :open="sidebarOpen" 
      @close="sidebarOpen = false" 
    />

    <!-- Main content -->
    <div class="flex flex-1 flex-col overflow-hidden">
      <!-- Header -->
      <Header 
        :title="pageTitle"
        @toggle-sidebar="sidebarOpen = !sidebarOpen" 
      />

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
        <div class="mx-auto max-w-7xl">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

