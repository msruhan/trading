<script setup>
import { onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/components/layout/AppLayout.vue'

const route = useRoute()
const authStore = useAuthStore()

onMounted(() => {
  authStore.initTheme()
})

// Check if current route requires auth
const isAuthPage = computed(() => {
  return ['login', 'register'].includes(route.name)
})
</script>

<script>
import { computed } from 'vue'
</script>

<template>
  <div class="min-h-screen">
    <!-- Auth pages without layout -->
    <router-view v-if="isAuthPage" />
    
    <!-- Main app with layout -->
    <AppLayout v-else>
      <router-view />
    </AppLayout>
  </div>
</template>

